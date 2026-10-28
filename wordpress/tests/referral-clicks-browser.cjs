// Disposable WordPress only. Genuine browser activation is required; no exchange is contacted.
// COMPOSE_PROJECT_NAME=exchange-guide-click-test NODE_PATH=/tmp/cg-click-tools/node_modules node wordpress/tests/referral-clicks-browser.cjs
const {chromium}=require('playwright');
const {execFileSync}=require('node:child_process');
const {createHash,randomUUID}=require('node:crypto');
const assert=require('node:assert/strict');
assert.equal(process.env.COMPOSE_PROJECT_NAME,'exchange-guide-click-test','Use the isolated click-test Compose project');
const base='http://localhost:8088/',origin=base.slice(0,-1);
const ownerUrl='https://okx-clicks.example.com/join/local-browser-test?ref=synthetic-only';
const tokenPath='/wp-json/cg/v1/referral-click-token',eventPath='/wp-json/cg/v1/referral-click';
// A controlled ordinary-Chrome UA lets native Playwright activations exercise the human path.
// This simulation is not evidence that a production UA identifies a real human.
const humanUA='Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
const wp=(...args)=>execFileSync('docker',['compose','-f','wordpress/tests/compose.packages.yaml','run','--rm','-T','cli','wp',...args],{encoding:'utf8',env:process.env,stdio:['pipe','pipe','pipe']}).trim();
const phpData=value=>"json_decode(base64_decode('"+Buffer.from(JSON.stringify(value)).toString('base64')+"'),true)";
const counter=id=>JSON.parse(wp('eval',`echo wp_json_encode(array('legacy'=>(int)get_post_meta(${id},'_cg_clicks',true),'confirmed'=>(int)get_post_meta(${id},'_cg_confirmed_clicks',true)));`));
const pathOf=url=>new URL(url).pathname;
(async()=>{
 assert.equal(wp('option','get','home'),origin,'Never run against production');
 assert.equal(wp('eval',"echo rest_get_server()->get_routes()['/cg/v1/referral-click']?'ready':'missing';"),'ready','Install the updated plugin first');
 let state,browser;const eventIds=new Set(),contexts=[],allRequests=[],navigationRecords=[],externalMocks=[],scriptErrors=[];let expected=0;
 try{
  state=JSON.parse(wp('eval',`
   global $wpdb;$exchange=cg_exchange('okx');$admin=get_user_by('login','smoke-admin');if(!$exchange||!$admin||!user_can($admin,'manage_options')){throw new RuntimeException('Initialize local OKX and smoke-admin first');}
   $meta=$wpdb->get_results($wpdb->prepare("SELECT meta_key,meta_value FROM {$wpdb->postmeta} WHERE post_id=%d ORDER BY meta_id",$exchange->ID),ARRAY_A);
   $editor=wp_insert_user(array('user_login'=>'local-click-editor-'.wp_generate_password(8,false),'user_pass'=>'local-click-editor-only','role'=>'editor'));if(is_wp_error($editor)){throw new RuntimeException($editor->get_error_message());}
   wp_set_current_user($admin->ID);$saved=cg_save_referral_settings($exchange,'${ownerUrl}',true,'');if(is_wp_error($saved)){throw new RuntimeException($saved->get_error_message());}
   update_post_meta($exchange->ID,'_cg_clicks','45');update_post_meta($exchange->ID,'_cg_confirmed_clicks','0');delete_post_meta($exchange->ID,'_cg_banner_attachment');
   $article=wp_insert_post(array('post_type'=>'post','post_status'=>'publish','post_author'=>$editor,'post_title'=>'Local signed click acceptance fixture','post_excerpt'=>'Local routing checks only; no financial facts.','post_content'=>'<!-- wp:heading --><h2 class="wp-block-heading">Local routing checks</h2><!-- /wp:heading --><!-- wp:paragraph --><p>This temporary article verifies explicit eligibility and outbound clicks.</p><!-- /wp:paragraph -->'),true);if(is_wp_error($article)){throw new RuntimeException($article->get_error_message());}
   update_post_meta($article,'_cg_review_exchange','okx');update_post_meta($article,'_cg_related_exchanges',array($exchange->ID));update_post_meta($article,'_cg_review_policy',array('enabled'=>true,'product'=>'Local routing-only product fixture','countries'=>array('SG'),'terms'=>'https://www.okx.com/terms-of-service','verified'=>wp_date('Y-m-d'),'expiry'=>gmdate('Y-m-d',time()+30*DAY_IN_SECONDS)));
   $page=wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_author'=>$editor,'post_title'=>'Local preview click acceptance fixture','post_content'=>'<!-- wp:shortcode -->[cg_registration_cards]<!-- /wp:shortcode --><!-- wp:shortcode -->[cg_affiliate exchange="okx"]<!-- /wp:shortcode -->'),true);if(is_wp_error($page)){throw new RuntimeException($page->get_error_message());}
   echo wp_json_encode(array('id'=>$exchange->ID,'meta'=>$meta,'admin'=>$admin->ID,'editor'=>$editor,'editorName'=>get_userdata($editor)->user_login,'article'=>$article,'articleUrl'=>get_permalink($article),'page'=>$page,'pageUrl'=>get_permalink($page),'previewUrl'=>get_preview_post_link($page),'hub'=>get_permalink($exchange),'go'=>cg_go_url('okx'),'official'=>cg_official_registration_urls()['okx']));
  `));
  browser=await chromium.launch({headless:true,executablePath:process.env.CG_CHROMIUM_PATH||'/usr/bin/chromium',args:['--no-sandbox']});
  const newContext=async(name,options={})=>{
   const context=await browser.newContext({viewport:{width:1440,height:1000},userAgent:humanUA,...options});contexts.push(context);
   // Keep local proof/event requests entirely native, including unload keepalive requests.
   await context.route(url=>url.origin!==origin||/^\/go\//.test(url.pathname)||url.searchParams.has('cg_go')||url.searchParams.has('cg_review_go'),async route=>{
    const request=route.request(),url=new URL(request.url());
    if(url.origin===origin){
     const jump=/^\/go\//.test(url.pathname)||url.searchParams.has('cg_go')||url.searchParams.has('cg_review_go');
     if(jump&&request.isNavigationRequest()){
      // Routing a continued 302 only intercepts the first URL. Read it without following,
      // then render a local mock at the unchanged go URL so no external request can escape.
      const response=await route.fetch({maxRedirects:0});assert.equal(response.status(),302);
      const location=response.headers().location;assert([ownerUrl,state.official].includes(location),'Only the configured synthetic/official destination is permitted');
      navigationRecords.push({context:name,url:request.url(),location});
      return route.fulfill({status:200,contentType:'text/html',body:'<!doctype html><title>Local outbound mock</title><h1>Locally mocked redirect destination</h1>'});
     }
     return route.continue();
    }
    // Fulfill synthetic and explicitly known official document destinations locally. No external network.
    if(request.isNavigationRequest()&&(url.origin===new URL(ownerUrl).origin||url.href===state.official)){externalMocks.push({context:name,url:request.url()});return route.fulfill({status:200,contentType:'text/html',body:'<!doctype html><title>Local outbound mock</title><h1>Local outbound navigation mock</h1>'});}
    return route.abort('blockedbyclient');
   });
   // Replay the same trusted Event through the actual registered callbacks, without a product test hook.
   await context.addInitScript(()=>{
    window.__cgRepeatTrustedCallbacks=false;window.__cgRepeatedCallbackCount=0;
    const add=document.addEventListener;
    document.addEventListener=function(type,listener,options){
     if((type==='click'||type==='auxclick')&&typeof listener==='function'){
      const wrapped=function(event){listener.call(this,event);const target=event.target instanceof Element?event.target:event.target?.parentElement;if(window.__cgRepeatTrustedCallbacks&&event.isTrusted&&target?.closest('a[href*="/go/okx/"]')){window.__cgRepeatedCallbackCount++;listener.call(this,event);}};
      return add.call(this,type,wrapped,options);
     }
     return add.call(this,type,listener,options);
    };
   });
   context.on('request',request=>{
    if(pathOf(request.url())===eventPath&&request.method()==='POST'){
     let payload;try{payload=request.postDataJSON();}catch{}
     if(payload?.event)eventIds.add(payload.event);
     allRequests.push({context:name,url:request.url(),method:request.method(),headers:request.headers(),body:request.postData(),payload});
    }else if(pathOf(request.url())===tokenPath){allRequests.push({context:name,url:request.url(),method:request.method(),token:true});}
   });
   context.on('page',page=>page.on('pageerror',error=>scriptErrors.push(error.message)));
   return context;
  };
  const assertCounters=label=>{assert.deepEqual(counter(state.id),{legacy:45,confirmed:expected},label+'; legacy 45 remains frozen');};
  const pageAt=async(context,url=base)=>{const page=await context.newPage();const response=await page.goto(url,{waitUntil:'load'});assert.equal(response.status(),200);await page.mouse.move(1438,998);return page;};
  const card=page=>page.locator('a.cg-registration-card[href="'+state.go+'"]');
  const isEventResponse=response=>pathOf(response.url())===eventPath&&response.request().method()==='POST';
  const activated=async(context,page,link,options={})=>{
   await page.waitForFunction(()=>window.cgReferralClickTokenReady===true);
   const linkUrl=new URL(await link.getAttribute('href'),page.url()).href;
   const reply=context.waitForEvent('response',{predicate:isEventResponse,timeout:20000});
   const popupPromise=context.waitForEvent('page',{timeout:20000});
   await link.click({button:options.middle?'middle':'left'});
   const popup=await popupPromise;if(options.middle)await popup.bringToFront();
   let navigationLoaded=true;
   try{await popup.waitForURL(url=>url.href===linkUrl||url.href===(options.destination||ownerUrl),{waitUntil:'domcontentloaded',timeout:options.middle?5000:30000});}
   catch(error){if(!options.middle||popup.url()!=='about:blank')throw error;navigationLoaded=false;console.log('NOTE native middle activation opened a background about:blank tab in headless Chromium; its trusted auxclick POST and committed count are verified, while middle-tab document navigation is not verified in this environment');}
   if(navigationLoaded){if(new URL(popup.url()).origin===origin){const navigation=navigationRecords.filter(record=>record.url===popup.url()).at(-1);assert(navigation,'The real redirect was checked without following');assert.equal(navigation.location,options.destination||ownerUrl);}else{assert.equal(popup.url(),options.destination||ownerUrl);assert(externalMocks.some(record=>record.url===popup.url()),'External document is fulfilled locally, never fetched from the exchange');}}
   const response=await reply;assert.equal(response.status(),200);const result=await response.json();
   if(options.excluded){assert.equal(result.counted,false);assert.equal(result.duplicate,false);}else{assert.equal(result.counted,true);assert.equal(result.duplicate,false);expected++;}
   await page.waitForFunction(()=>['recorded','duplicate','excluded'].includes(window.cgReferralClickStatus));
   await popup.close();return allRequests.filter(request=>request.context===options.contextName&&!request.token).at(-1)||allRequests.filter(request=>!request.token).at(-1);
  };
  const anonymous=await newContext('anonymous'),page=await pageAt(anonymous);
  await page.waitForFunction(()=>window.cgReferralClicksReady===true);
  const speculation=await page.locator('script[type="speculationrules"]').allTextContents();assert(speculation.length>0,'Core speculative loading rules are available for exclusion checks');
  const rules=speculation.map(text=>JSON.stringify(JSON.parse(text))).join(' ');assert(rules.includes('/go/*')&&rules.includes('/register/*'),'WordPress speculative loading excludes redirect and registration routes');
  assert.equal(await card(page).count(),1);assert.equal(await card(page).getAttribute('target'),'_blank');
  for(let i=0;i<5;i++){await page.reload({waitUntil:'load'});}await page.goto(state.hub,{waitUntil:'load'});await page.goto(base,{waitUntil:'load'});
  assert.equal(allRequests.filter(request=>!request.token).length,0,'Views, five refreshes and platform hub send no click events; noncounting proof warmup is allowed');assertCounters('Reading and refreshing do not count');
  await card(page).evaluate(link=>{link.addEventListener('click',event=>event.preventDefault(),{once:true});link.dispatchEvent(new MouseEvent('click',{bubbles:true,button:0}));});
  await page.waitForTimeout(200);assert.equal(allRequests.filter(request=>!request.token).length,0,'Untrusted script-generated click is ignored');assertCounters('Synthetic DOM click does not count');
  console.log('PASS initial homepage, five reloads, platform hub and script-generated clicks leave both counters unchanged');

  await page.evaluate(()=>{window.__cgRepeatTrustedCallbacks=true;});
  const first=await activated(anonymous,page,card(page),{contextName:'anonymous'});await page.evaluate(()=>{window.__cgRepeatTrustedCallbacks=false;});
  assert((await page.evaluate(()=>window.__cgRepeatedCallbackCount))>0,'Real trusted Event was replayed through registered callbacks');
  assert.equal(allRequests.filter(request=>!request.token).length,1,'The same Event object generates only one report');assertCounters('One genuine activation is counted once despite repeated callbacks');
  assert.match(first.payload.event,/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i);
  assert.equal(first.payload.target,state.go);assert.equal(first.payload.source,base);
  const replay=await anonymous.request.fetch(first.url,{method:first.method,headers:{'Content-Type':'application/json','X-CG-Click':'1',Origin:origin,Referer:base},data:first.body,maxRedirects:0});
  assert.equal(replay.status(),200);assert.deepEqual(await replay.json(),{counted:false,duplicate:true});assertCounters('Replaying the exact original event request is idempotent');
  console.log('PASS real native click counts once, repeated same-Event callbacks do not resend, original POST replay is a duplicate and legacy total stays 45');

  // The existing affiliate shortcode is a real same-tab link; preserve its native target.
  const sameTab=await pageAt(anonymous,state.pageUrl);
  const sameTabLink=()=>sameTab.locator('.cg-affiliate a.cg-button[href="'+state.go+'"]');
  assert.equal(await sameTabLink().count(),1);assert.equal(await sameTabLink().getAttribute('target'),null,'Existing shortcode remains a native same-tab link');
  for(const activation of ['initial','after-back']){
   await sameTab.waitForFunction(()=>window.cgReferralClickTokenReady===true);
   const sent=anonymous.waitForEvent('request',{predicate:request=>pathOf(request.url())===eventPath&&request.method()==='POST',timeout:20000});
   await Promise.all([sameTab.waitForURL(state.go,{waitUntil:'domcontentloaded'}),sameTabLink().click()]);
   const request=await sent,payload=request.postDataJSON();assert.equal(payload.source,state.pageUrl);assert.equal(payload.target,state.go);eventIds.add(payload.event);expected++;
   // Chromium may omit a response event for a successful keepalive POST after document unload.
   // Verify this real request's transaction receipt and aggregate instead of inventing another POST.
   const receiptKey='cg_click_event_'+createHash('sha256').update(payload.event.toLowerCase()).digest('hex');
   const finish=Date.now()+15000;let receipt;
   do{receipt=JSON.parse(wp('eval',`echo wp_json_encode(get_option('${receiptKey}',false));`));if(receipt)break;await new Promise(resolve=>setTimeout(resolve,100));}while(Date.now()<finish);
   assert(receipt,'Native same-tab event has a committed transaction receipt');assert.equal(receipt.post_id,state.id);assert.equal(receipt.source_path,new URL(state.pageUrl).pathname);assert.equal(receipt.entry_type,'direct');
   assertCounters('Native same-tab '+activation+' activation reports exactly once');
   if(activation==='initial'){await sameTab.goBack({waitUntil:'load'});assert.equal(sameTab.url(),state.pageUrl);}
  }
  await sameTab.close();
  const firstOther=await newContext('visitor-one'),secondOther=await newContext('visitor-two');const p1=await pageAt(firstOther),p2=await pageAt(secondOther);
  await activated(firstOther,p1,card(p1),{contextName:'visitor-one'});await activated(secondOther,p2,card(p2),{contextName:'visitor-two'});assertCounters('Two separate visitor contexts contribute two independent genuine activations');
  await activated(anonymous,page,card(page),{middle:true,contextName:'anonymous'});assertCounters('Native middle-button activation counts once');
  console.log('PASS outbound/back/new activation, two independent contexts and native middle-click each add the intended count');

  for(const options of [{},{headers:{Purpose:'prefetch'}},{headers:{'Sec-Purpose':'prefetch'}},{headers:{'User-Agent':'Googlebot/2.1 (+http://www.google.com/bot.html)'}}]){
   const response=await anonymous.request.get(state.go,{...options,maxRedirects:0});assert.equal(response.status(),302);assert.equal(response.headers().location,ownerUrl);
  }
  const head=await anonymous.request.head(state.go,{maxRedirects:0});assert.equal(head.status(),302);assertCounters('Raw GET, prefetch, HEAD and crawler GET do not count');

  const article=await pageAt(anonymous,state.articleUrl),form=article.locator('.cg-review-eligibility-form');assert.equal(await article.locator('.cg-registration-card').count(),0);
  await form.locator('input[name="country"]').fill('SG');await form.locator('input[name="confirmed"]').check();await form.locator('button[type="submit"]').click();
  const signedLink=form.locator('a.cg-button');await signedLink.waitFor();const signedTarget=await signedLink.getAttribute('href');assert.equal(new URL(signedTarget).origin,origin);assertCounters('Eligibility request and dynamic CTA rendering alone do not count');
  const signedRaw=await anonymous.request.get(signedTarget,{maxRedirects:0});assert.equal(signedRaw.status(),302);assertCounters('Signed review redirect raw GET also does not count');
  await activated(anonymous,article,signedLink,{contextName:'anonymous'});assertCounters('Real eligible signed-review CTA counts using the same confirmed metric');
  console.log('PASS raw GET/prefetch/HEAD/crawler and signed GET remain zero, while genuine signed-review CTA activation contributes one confirmed click');

  const login=async(context,name,password)=>{await context.request.get(base+'wp-login.php');const response=await context.request.post(base+'wp-login.php',{form:{log:name,pwd:password,testcookie:'1',redirect_to:base+'wp-admin/'}});assert.equal(response.status(),200);};
  const administrator=await newContext('administrator');await login(administrator,'smoke-admin','local-test-only');const adminPage=await pageAt(administrator);
  assert.equal(await adminPage.evaluate(()=>typeof window.cgReferralClicks),'undefined','Administrators receive no click reporter');
  const adminPopupPromise=administrator.waitForEvent('page');await card(adminPage).click();const adminPopup=await adminPopupPromise;await adminPopup.waitForURL(state.go,{waitUntil:'domcontentloaded'});assert.equal(adminPopup.url(),state.go);assert.equal(navigationRecords.filter(record=>record.url===adminPopup.url()).at(-1).location,ownerUrl);await adminPopup.close();
  assert.equal(allRequests.filter(request=>request.context==='administrator').length,0,'Admin UI clicks do not issue click tokens/events');assertCounters('Administrator live preview click is excluded');
  const editor=await newContext('editor-preview');await login(editor,state.editorName,'local-click-editor-only');const preview=await pageAt(editor,state.previewUrl);
  assert.equal(await preview.evaluate(()=>typeof window.cgReferralClicks),'undefined','Non-admin content preview receives no click reporter');
  const previewPopupPromise=editor.waitForEvent('page');await card(preview).click();const previewPopup=await previewPopupPromise;await previewPopup.waitForURL(state.go,{waitUntil:'domcontentloaded'});assert.equal(previewPopup.url(),state.go);assert.equal(navigationRecords.filter(record=>record.url===previewPopup.url()).at(-1).location,ownerUrl);await previewPopup.close();
  assert.equal(allRequests.filter(request=>request.context==='editor-preview').length,0,'Non-admin preview clicks do not issue tokens/events');assertCounters('Non-admin preview activation is excluded');
  const testTraffic=await newContext('headless-test',{userAgent:undefined}),testPage=await pageAt(testTraffic);
  assert((await testPage.evaluate(()=>navigator.userAgent)).includes('HeadlessChrome'),'Default browser context retains its explicit test UA');
  const testPopupPromise=testTraffic.waitForEvent('page');await card(testPage).click();const testPopup=await testPopupPromise;await testPopup.waitForURL(state.go,{waitUntil:'domcontentloaded'});assert.equal(testPopup.url(),state.go);assert.equal(navigationRecords.filter(record=>record.url===testPopup.url()).at(-1).location,ownerUrl);await testPopup.close();
  await testPage.waitForFunction(()=>typeof window.cgReferralClicks==='undefined'||['error','excluded'].includes(window.cgReferralClickStatus));
  assert.equal(allRequests.filter(request=>request.context==='headless-test'&&!request.token).length,0,'Headless test traffic cannot issue a confirmed event after token rejection');assertCounters('Explicit headless test traffic activation is excluded');
  console.log('PASS logged-in administrator, separate editor preview and explicit HeadlessChrome test activations navigate without reporting or counting');

  wp('eval',`wp_set_current_user(${state.admin});$result=cg_save_referral_settings(cg_exchange('okx'),'${ownerUrl}',false,'');if(is_wp_error($result)){throw new RuntimeException($result->get_error_message());}`);
  const fallback=await pageAt(anonymous);assert.equal(await card(fallback).getAttribute('href'),state.go);assert(!(await card(fallback).getAttribute('rel')).includes('sponsored'));
  await activated(anonymous,fallback,card(fallback),{excluded:true,destination:state.official,contextName:'anonymous'});assertCounters('Official fallback retains the previous recommendation-only metric and does not count');
  wp('eval',`wp_set_current_user(${state.admin});$result=cg_save_referral_settings(cg_exchange('okx'),'${ownerUrl}',true,'');if(is_wp_error($result)){throw new RuntimeException($result->get_error_message());}`);

  const reject=async(payload,headers={'Content-Type':'application/json','X-CG-Click':'1',Origin:origin,Referer:base})=>{
   if(payload.event)eventIds.add(payload.event);const response=await anonymous.request.post(origin+eventPath,{headers,data:payload,maxRedirects:0});let result;try{result=await response.json();}catch{}
   assert(response.status()>=400||result?.counted===false,'Forged/invalid request is rejected or explicitly excluded');assertCounters('Invalid event cannot increase the counter');
  };
  await reject({...first.payload,event:randomUUID(),proof:'invalid-proof'});
  await reject({...first.payload,event:randomUUID(),target:'https://okx-clicks.example.com/join/bypass'});
  await reject({...first.payload,event:randomUUID(),source:base+'?preview=true'});
  await reject({...first.payload,event:randomUUID()},{'Content-Type':'application/json','X-CG-Click':'1',Origin:'https://attacker.example.net',Referer:'https://attacker.example.net/'});
  await reject({...first.payload,event:randomUUID()},{'Content-Type':'application/json',Origin:origin,Referer:base});
  const markup=JSON.parse(wp('eval',`wp_set_current_user(${state.admin});ob_start();cg_clicks_markup(cg_exchange('okx'));$admin=ob_get_clean();wp_set_current_user(${state.editor});ob_start();cg_clicks_markup(cg_exchange('okx'));$editor=ob_get_clean();wp_set_current_user(0);ob_start();cg_clicks_markup(cg_exchange('okx'));$anonymous=ob_get_clean();echo wp_json_encode(array('admin'=>$admin,'editor'=>$editor,'anonymous'=>$anonymous));`));
  assert(markup.admin.includes('旧版入口请求累计：<strong>45</strong>'));assert(markup.admin.includes('新版推广点击累计：<strong>'+expected+'</strong>'));
  assert(markup.admin.includes('最近点击记录（仅管理员可见）'));assert(markup.admin.includes('文章确认入口')&&markup.admin.includes('普通推荐入口'));
  assert(markup.admin.includes(new URL(state.pageUrl).pathname)&&markup.admin.includes(new URL(state.articleUrl).pathname));
  const table=/<tbody>([\s\S]*?)<\/tbody>/.exec(markup.admin);assert(table);assert((table[1].match(/<tr>/g)||[]).length<=20);
  for(const privateValue of ['synthetic-only','okx-clicks.example.com','signature=','proof'])assert(!markup.admin.includes(privateValue),'Admin recent log omits destination/referral/signature/proof');
  assert.equal(markup.editor,'');assert.equal(markup.anonymous,'','Recent click markup is restricted to administrators');
  for(const width of [1440,390]){
   await page.setViewportSize({width,height:1000});await page.goto(base,{waitUntil:'load'});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,'No horizontal overflow '+width);
   const banners=page.locator('.cg-feed-sidebar a.cg-registration-card');assert.equal(await banners.count(),4,'All four banners remain at '+width);
   for(const [index,slug] of ['binance','okx','bitget','bybit'].entries())assert((await banners.nth(index).getAttribute('href')).includes('/go/'+slug+'/'));
  }
  assertCounters('Responsive page rendering and reading administrator recent logs do not count');
  console.log('PASS administrator-only recent log shows separate legacy/new totals, source paths and both entry types without private URL details; 1440/390px layouts retain four banners without overflow');
  assert.deepEqual(scriptErrors,[],'No browser JavaScript errors');
  console.log('PASS official fallback, invalid proof, external destination, preview source, cross-origin and missing-header events cannot inflate the new counter');
 }finally{
  if(browser)await browser.close();
  if(state){
   const keys=[...eventIds].map(id=>'cg_click_event_'+createHash('sha256').update(id.toLowerCase()).digest('hex'));
   wp('eval',`
    global $wpdb;$state=${phpData(state)};$keys=${phpData(keys)};
    wp_delete_post($state['article'],true);wp_delete_post($state['page'],true);require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($state['editor']);
    $wpdb->delete($wpdb->postmeta,array('post_id'=>$state['id']));foreach($state['meta'] as $row){$wpdb->insert($wpdb->postmeta,array('post_id'=>$state['id'],'meta_key'=>$row['meta_key'],'meta_value'=>$row['meta_value']));}clean_post_cache($state['id']);
    foreach($keys as $key){delete_option($key);}do_action('litespeed_purge_post',$state['id']);
    $actual=$wpdb->get_results($wpdb->prepare("SELECT meta_key,meta_value FROM {$wpdb->postmeta} WHERE post_id=%d ORDER BY meta_id",$state['id']),ARRAY_A);if($actual!==$state['meta']){throw new RuntimeException('Original exchange metadata restoration mismatch');}
    echo 'RESTORED original OKX metadata and deleted only this run event keys, local articles and editor';
   `);console.log('PASS restored every original exchange metadata value and removed only the temporary articles, editor and this run event records; Bybit was never changed');
  }
 }
})().catch(error=>{console.error(error);process.exit(1)});
