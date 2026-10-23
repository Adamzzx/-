// Disposable local WordPress only. Uses native editor saves; never follows an exchange URL.
// COMPOSE_PROJECT_NAME=<isolated project> node wordpress/tests/referral-destinations-browser.cjs
const {chromium}=require('playwright');
const {execFileSync}=require('node:child_process');
const assert=require('node:assert/strict');
const project=process.env.COMPOSE_PROJECT_NAME;
assert(project,'Set COMPOSE_PROJECT_NAME to the disposable local test project explicitly');
const base='http://localhost:8088/';
const ownerUrl='https://okx-invite.example.com/join/owner-test?ref=keep';
const replacementUrl='https://okx-invite.example.com/join/owner-test?ref=keep&lang=zh-CN';
const wp=(...args)=>execFileSync('docker',['compose','-f','wordpress/tests/compose.packages.yaml','run','--rm','-T','cli','wp',...args],{encoding:'utf8',env:process.env,stdio:['pipe','pipe','pipe']}).trim();
const phpData=value=>"json_decode(base64_decode('"+Buffer.from(JSON.stringify(value)).toString('base64')+"'),true)";
const readConfig=id=>JSON.parse(wp('eval',`$id=${id};echo wp_json_encode(array('url'=>get_post_meta($id,'_cg_destination',true),'host'=>get_post_meta($id,'_cg_referral_host',true),'exchange'=>get_post_meta($id,'_cg_referral_exchange',true),'enabled'=>get_post_meta($id,'_cg_enabled',true),'expiry'=>get_post_meta($id,'_cg_expiry',true)));`));
const clickCount=id=>Number(wp('post','meta','get',String(id),'_cg_clicks')||0);
const denied=async response=>{
 const text=await response.text();let body;try{body=JSON.parse(text);}catch{}
 assert(response.status()>=400||body?.success===false||text.trim()==='-1'||text.trim()==='0','Status endpoint rejects unauthorized request');
 assert(!text.includes(ownerUrl)&&!text.includes(replacementUrl),'Unauthorized status does not disclose destination');
};
(async()=>{
 assert.equal(wp('option','get','home'),base.slice(0,-1),'Only the disposable local WordPress may be modified');
 assert.equal(wp('eval',"echo function_exists('cg_save_referral_settings')?'ready':'missing';"),'ready','Install the new plugin before running this browser test');
 let browser,state,legitimateStatusForm;
 try{
  state=JSON.parse(wp('eval',`
   global $wpdb;$post=cg_exchange('okx');if(!$post){throw new RuntimeException('Initialize the public local OKX platform first');}
   $admin=get_user_by('login','smoke-admin');if(!$admin||!user_can($admin,'manage_options')){throw new RuntimeException('Local smoke-admin administrator is required');}
   $snapshot=function($id)use($wpdb){return array('post'=>$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE ID=%d",$id),ARRAY_A),'meta'=>$wpdb->get_results($wpdb->prepare("SELECT meta_key,meta_value FROM {$wpdb->postmeta} WHERE post_id=%d ORDER BY meta_id",$id),ARRAY_A));};
   $saved=$snapshot($post->ID);$revisions=array();foreach(get_posts(array('post_type'=>'revision','post_status'=>'inherit','post_parent'=>$post->ID,'numberposts'=>-1)) as $revision){$revisions[$revision->ID]=$snapshot($revision->ID);}
   $transients=array();foreach(array('_transient_cg_error_'.$admin->ID,'_transient_timeout_cg_error_'.$admin->ID) as $key){$transients[$key]=array('exists'=>get_option($key,null)!==null,'value'=>get_option($key,null));}
   $root=get_term_by('slug','crypto','cg_topic');if(!$root){throw new RuntimeException('Initialize local crypto categories before the browser test');}$sidebar=get_term_link($root);if(is_wp_error($sidebar)){throw new RuntimeException($sidebar->get_error_message());}
   wp_set_current_user($admin->ID);$id=wp_insert_post(array('post_type'=>'post','post_status'=>'publish','post_author'=>$admin->ID,'post_title'=>'本地推荐入口浏览器验证','post_excerpt'=>'仅验证本地编辑和推荐入口，不含真实平台测试结论。','post_content'=>'<!-- wp:heading --><h2 class="wp-block-heading">本地流程说明</h2><!-- /wp:heading --><!-- wp:paragraph --><p>仅测试中央链接和地区确认流程，不提供交易建议。</p><!-- /wp:paragraph --><!-- wp:shortcode -->[cg_registration_cards]<!-- /wp:shortcode -->'),true);if(is_wp_error($id)){throw new RuntimeException($id->get_error_message());}
   update_post_meta($id,'_cg_review_exchange','okx');update_post_meta($id,'_cg_related_exchanges',array($post->ID));update_post_meta($id,'_cg_review_policy',array('enabled'=>true,'product'=>'Local routing-only policy fixture','countries'=>array('SG'),'terms'=>'https://www.okx.com/terms-of-service','verified'=>wp_date('Y-m-d'),'expiry'=>gmdate('Y-m-d',time()+30*DAY_IN_SECONDS)));
   // A blank expiry and image avoid unrelated previous local fixture state.
   update_post_meta($post->ID,'_cg_expiry','');delete_post_meta($post->ID,'_cg_banner_attachment');
   if(!metadata_exists('post',$post->ID,'_cg_clicks')){add_post_meta($post->ID,'_cg_clicks','0',true);}
   echo wp_json_encode(array('id'=>$post->ID,'admin'=>$admin->ID,'saved'=>$saved,'revisions'=>$revisions,'transients'=>$transients,'article'=>$id,'articleUrl'=>get_permalink($id),'sidebarUrl'=>$sidebar,'goUrl'=>cg_go_url('okx')));
  `));
  browser=await chromium.launch({headless:true,executablePath:process.env.CG_CHROMIUM_PATH||'/usr/bin/chromium',args:['--no-sandbox']});
  const admin=await browser.newContext({viewport:{width:1440,height:1100}}),anon=await browser.newContext({viewport:{width:1440,height:1100}});
  // Even a mistaken click cannot contact a real exchange or resolve the synthetic referral host.
  for(const context of [admin,anon]){await context.route('**/*',route=>{const url=new URL(route.request().url());return url.origin===base.slice(0,-1)?route.continue():route.abort('blockedbyclient');});}
  const page=await admin.newPage(),publicPage=await anon.newPage(),errors=[];
  page.on('pageerror',e=>errors.push(e.message));publicPage.on('pageerror',e=>errors.push(e.message));
  await page.goto(base+'wp-login.php',{waitUntil:'load'});
  const login=await admin.request.post(base+'wp-login.php',{form:{log:'smoke-admin',pwd:'local-test-only',testcookie:'1',redirect_to:base+'wp-admin/'}});assert.equal(login.status(),200);
  const editUrl=base+'wp-admin/post.php?post='+state.id+'&action=edit';
  const metaScope=async()=>{
   const end=Date.now()+30000;while(Date.now()<end){for(const frame of page.frames()){if(await frame.locator('#cg_destination').count()){return frame;}}await page.waitForTimeout(100);}throw new Error('Native exchange editor did not render #cg_destination');
  };
  const loadEditor=async()=>{
   assert.equal((await page.goto(editUrl,{waitUntil:'load'})).status(),200);
   await page.waitForFunction(()=>window.wp?.data?.select('core/editor')?.getCurrentPostId());
   // Close the optional Gutenberg welcome guide with its real UI control.
   const guide=page.locator('.components-modal__frame');if(await guide.count()){
    const close=guide.getByRole('button',{name:/关闭|Close|Skip|跳过/}).first();if(await close.count())await close.click();
   }
   const scope=await metaScope(),field=scope.locator('#cg_destination');
   if(!await field.isVisible()){
    const area=page.getByRole('button',{name:/^(Meta Boxes|元框)$/i});if(await area.count()){await area.focus();await area.press('Enter');}
    const toggle=scope.locator('#cg-data .handlediv');if(await toggle.isVisible()&&await toggle.getAttribute('aria-expanded')==='false')await toggle.click();
   }
   await field.waitFor({state:'visible'});return scope;
  };
  const statusResponse=response=>{
   if(!response.url().includes('/wp-admin/admin-ajax.php'))return false;
   const request=response.request(),body=request.postData()||'';
   return request.method()==='POST'&&(new URLSearchParams(body).get('action')==='cg_referral_status'||body.includes('"action":"cg_referral_status"'));
  };
  const save=async(scope,url,wantsError=false)=>{
   await scope.locator('#cg_destination').fill(url);await scope.locator('#cg_enabled').check();
   if(await scope.locator('#cg_expiry').count())await scope.locator('#cg_expiry').fill('');
   const button=page.getByRole('button',{name:/^(保存|更新|Save|Update)$/}).first();await button.waitFor({state:'visible'});
   assert.equal(await button.isEnabled(),true,'Native editor save button recognizes the changed meta-box fields');
   const started=Date.now();
   const statusWait=page.waitForResponse(response=>statusResponse(response)&&response.request().timing().startTime>=started,{timeout:30000});
   await button.click();const response=await statusWait;assert.equal(response.status(),200,'Authorized status check succeeds after native save');
   const requestBody=response.request().postData()||'';
   legitimateStatusForm=Object.fromEntries(new URLSearchParams(requestBody));
   if(!legitimateStatusForm.nonce){try{legitimateStatusForm=JSON.parse(requestBody);}catch{}}
   assert.equal(String(legitimateStatusForm.post_id),String(state.id));assert(legitimateStatusForm.nonce,'Editor status request includes the post-specific nonce');
   const result=await response.json();assert.equal(result.success,true);
   assert.equal(result.data.attempt,await scope.locator('#cg-referral-attempt').inputValue(),'Status confirms this actual native save attempt');
   if(wantsError){assert(result.data.error.includes('未保存'));}else{assert.equal(result.data.state,'enabled');assert.equal(result.data.error,'');}
   const status=scope.locator('#cg-referral-save-status');await status.waitFor({state:'visible'});
   assert(['polite','assertive'].includes(await status.getAttribute('aria-live')),'Save feedback is announced accessibly');
   if(wantsError){await status.filter({hasText:'未保存'}).waitFor();assert((await status.innerText()).includes('未保存'),'Rejected native save is clearly reported');}
   else{await page.waitForFunction(()=>!wp.data.select('core/editor').isSavingPost());assert(!(await status.innerText()).includes('未保存'),'Valid save clears previous persistent error');}
   return result.data;
  };
  let scope=await loadEditor();await save(scope,ownerUrl);
  assert.deepEqual(readConfig(state.id),{url:ownerUrl,host:'okx-invite.example.com',exchange:'okx',enabled:'1',expiry:''},'Native Gutenberg save stores the exact URL, platform-specific host and exchange binding');
  const initialClicks=clickCount(state.id);
  const redirect=await anon.request.get(state.goUrl,{maxRedirects:0});assert.equal(redirect.status(),302);assert.equal(redirect.headers().location,ownerUrl);assert((redirect.headers()['x-robots-tag']||'').includes('noindex'));assert(/no-cache|no-store/.test(redirect.headers()['cache-control']||''));
  assert.equal(clickCount(state.id),initialClicks+1,'Anonymous central redirect increments aggregate count once');
  const administratorRedirect=await admin.request.get(state.goUrl,{maxRedirects:0});assert.equal(administratorRedirect.status(),302);assert.equal(administratorRedirect.headers().location,ownerUrl);assert.equal(clickCount(state.id),initialClicks+1,'Administrator preview is excluded from aggregate click count');
  console.log('PASS native Gutenberg URL+enabled save, exact host/platform trust, 302 without external navigation, anonymous count and administrator exclusion');

  for(const [url,selector] of [[base,'.cg-feed-sidebar'],[state.sidebarUrl,'.cg-registration-sidebar']]){
   assert.equal((await publicPage.goto(url,{waitUntil:'load'})).status(),200);
   const sidebar=publicPage.locator(selector),card=sidebar.locator('a.cg-registration-card').filter({has:publicPage.getByText('OKX',{exact:true})});
   assert.equal(await card.count(),1,'Public sidebar contains one OKX banner');assert.equal(await card.getAttribute('href'),state.goUrl);assert.equal(await card.getAttribute('target'),'_blank');
   const rel=(await card.getAttribute('rel')||'').split(/\s+/);assert(rel.includes('sponsored')&&rel.includes('noopener'));
   assert((await sidebar.innerText()).includes('佣金'),'Central referral disclosure is visible alongside banners');assert.equal(await publicPage.locator('a[href^="https://okx-invite.example.com"]').count(),0,'No hardcoded destination bypasses the central entry');
  }
  await publicPage.goto(state.articleUrl,{waitUntil:'load'});assert.equal(await publicPage.locator('.cg-registration-card').count(),0,'Native article does not automatically expose unconditional registration banners');
  const eligibility=publicPage.locator('.cg-review-eligibility-form');assert.equal(await eligibility.locator('a.cg-button').count(),0);assert((await publicPage.locator('.cg-review-eligibility').innerText()).includes('佣金'));
  await eligibility.locator('input[name="country"]').fill('US');await eligibility.locator('input[name="confirmed"]').check();await eligibility.locator('button[type="submit"]').click();
  await eligibility.getByText('此地区或产品暂无可展示的推荐入口。',{exact:true}).waitFor();assert.equal(await eligibility.locator('a.cg-button').count(),0);
  await eligibility.locator('input[name="country"]').fill('SG');await eligibility.locator('button[type="submit"]').click();const articleEntry=eligibility.locator('a.cg-button');await articleEntry.waitFor();assert.equal(await articleEntry.getAttribute('target'),'_blank');
  const signed=await articleEntry.getAttribute('href');assert.equal(new URL(signed).origin,base.slice(0,-1));const articleRedirect=await anon.request.get(signed,{maxRedirects:0});assert.equal(articleRedirect.status(),302);assert.equal(articleRedirect.headers().location,ownerUrl,'The eligible article uses the same centrally configured destination');
  console.log('PASS homepage/topic sidebars share sponsored central entry and disclosure; native article retains regional confirmation and uses the same destination');

  for(const bad of ['http://okx-invite.example.com/join/owner-test?ref=keep','https://user:password@okx-invite.example.com/join/owner-test?ref=keep']){
   scope=await loadEditor();const previous=readConfig(state.id);await save(scope,bad,true);assert.deepEqual(readConfig(state.id),previous,'Rejected native update preserves the complete working configuration');
   scope=await loadEditor();const persistent=scope.locator('#cg-referral-save-status');await persistent.filter({hasText:'未保存'}).waitFor();assert((await persistent.innerText()).includes('未保存'),'Error remains visible after reloading the native editor');
   const stillWorks=await admin.request.get(state.goUrl,{maxRedirects:0});assert.equal(stillWorks.status(),302);assert.equal(stillWorks.headers().location,ownerUrl);
  }
  await save(scope,replacementUrl);assert.deepEqual(readConfig(state.id),{url:replacementUrl,host:'okx-invite.example.com',exchange:'okx',enabled:'1',expiry:''});scope=await loadEditor();
  assert(!(await scope.locator('#cg-referral-save-status').innerText()).includes('未保存'),'Successful native save clears persistent error after reload');
  const replacementRedirect=await admin.request.get(state.goUrl,{maxRedirects:0});assert.equal(replacementRedirect.status(),302);assert.equal(replacementRedirect.headers().location,replacementUrl,'Query parameters remain intact after the replacement save');
  console.log('PASS invalid HTTP/userinfo editor saves show persistent 未保存 feedback, preserve the previous working URL, and a later valid save clears the error');

  await denied(await admin.request.post(base+'wp-admin/admin-ajax.php',{form:{action:'cg_referral_status',post_id:String(state.id),nonce:'invalid-local-nonce'}}));
  await denied(await admin.request.post(base+'wp-admin/admin-ajax.php',{form:{action:'cg_referral_status',post_id:String(state.id)}}));
  await denied(await anon.request.post(base+'wp-admin/admin-ajax.php',{form:{action:'cg_referral_status',post_id:String(state.id),nonce:legitimateStatusForm.nonce}}));
  assert.deepEqual(readConfig(state.id),{url:replacementUrl,host:'okx-invite.example.com',exchange:'okx',enabled:'1',expiry:''},'Unauthorized status probes never modify referral settings');
  assert.deepEqual(errors,[],'No browser JavaScript errors');
  console.log('PASS invalid/missing nonce and anonymous status AJAX are denied without leaking destination or mutating configuration');
 }finally{
  if(browser)await browser.close();
  if(state){wp('eval',`
   global $wpdb;$state=${phpData(state)};
   wp_delete_post($state['article'],true);
   foreach(get_posts(array('post_type'=>'revision','post_status'=>'inherit','post_parent'=>$state['id'],'numberposts'=>-1)) as $revision){if(!isset($state['revisions'][$revision->ID])){wp_delete_post($revision->ID,true);}}
   $restore=function($snapshot)use($wpdb){$id=(int)$snapshot['post']['ID'];$wpdb->update($wpdb->posts,$snapshot['post'],array('ID'=>$id));$wpdb->delete($wpdb->postmeta,array('post_id'=>$id));foreach($snapshot['meta'] as $row){$wpdb->insert($wpdb->postmeta,array('post_id'=>$id,'meta_key'=>$row['meta_key'],'meta_value'=>$row['meta_value']));}clean_post_cache($id);};
   $restore($state['saved']);foreach($state['revisions'] as $snapshot){$restore($snapshot);}foreach($state['transients'] as $key=>$value){if($value['exists']){update_option($key,$value['value']);}else{delete_option($key);}}
   do_action('litespeed_purge_post',$state['id']);
   $actual=$wpdb->get_results($wpdb->prepare("SELECT meta_key,meta_value FROM {$wpdb->postmeta} WHERE post_id=%d ORDER BY meta_id",$state['id']),ARRAY_A);if($actual!==$state['saved']['meta']){throw new RuntimeException('OKX private metadata restoration mismatch');}
   echo 'RESTORED original OKX post and every private metadata value; removed temporary article and new revisions';
  `);console.log('PASS restored all original OKX post/meta, removed local article/new revisions and restored previous administrator error transient');}
 }
})().catch(error=>{console.error(error);process.exit(1)});
