// Actual admin actions and anonymous HTTP on a disposable WordPress installation.
// The caller must restore its private full SQL/file backup after this fixture.
const {chromium}=require('playwright');
const {execFileSync}=require('node:child_process');
const {randomBytes}=require('node:crypto');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const base='http://localhost:8088';
const compose=['compose','-f','wordpress/tests/compose.packages.yaml'];
const wp=(...args)=>execFileSync('docker',[...compose,'run','--rm','-T','cli','wp',...args],{encoding:'utf8',stdio:['pipe','pipe','pipe']}).trim();
const php=code=>JSON.parse(wp('eval',code));
const docker=(...args)=>execFileSync('docker',[...compose,'exec','-T','wordpress',...args],{encoding:'utf8'});
const paths=['/exchanges/mexc/','/exchanges/gate/','/best/','/guides/','/home/'];
(async()=>{
 assert.equal(process.env.CG_CLEANUP_DISPOSABLE,'1');assert.equal(wp('option','get','home'),base);
 assert(fs.existsSync('/workspace/private/cleanup-20261021/before.sql'),'Private local SQL backup required');
 const fixture=php(`
  $admin=get_user_by('login','smoke-admin');wp_set_current_user($admin->ID);
  update_option('blog_public',1);update_option('rank_math_registration_skip','1');
  $modules=(array)get_option('rank_math_modules',array());$modules=array_diff($modules,array('instant-indexing'));$modules[]='sitemap';update_option('rank_math_modules',array_values(array_unique($modules)));
  $settings=(array)get_option('rank-math-options-sitemap',array());foreach(array('post','page','cg_exchange') as $t){$settings['pt_'.$t.'_sitemap']=true;}update_option('rank-math-options-sitemap',$settings);
  update_option('show_on_front','posts');update_option('page_on_front',0);update_option('page_for_posts',0);delete_option('cg_launch_home_alias_id');
  $ids=array();foreach(cg_launch_cleanup_specs() as $s){$p=get_page_by_path($s['slug'],OBJECT,$s['type']);$id=wp_insert_post(array('ID'=>$p?$p->ID:0,'post_type'=>$s['type'],'post_name'=>$s['slug'],'post_title'=>$s['titles'][0],'post_content'=>$s['placeholder'],'post_excerpt'=>'','post_status'=>'publish'));update_post_meta($id,'_cg_created','1');$ids[]=$id;}
  require_once ABSPATH.'wp-admin/includes/class-wp-privacy-policy-content.php';
  $p=get_page_by_path('privacy-policy',OBJECT,'page');$privacy=wp_insert_post(array('ID'=>$p?$p->ID:0,'post_type'=>'page','post_name'=>'privacy-policy','post_title'=>'Privacy Policy','post_status'=>'publish','post_content'=>WP_Privacy_Policy_Content::get_default_content()));update_option('wp_page_for_privacy_policy',$privacy);update_post_meta($privacy,'rank_math_title','Preserved privacy SEO title');
  $articles=array();foreach(array('binance-review-2026','okx-review-2026','bitget-review-2026','bybit-review-2026','binance-vs-okx-2026','crypto-beginners-guide-2026','crypto-wallet-security-guide-2026','binance-us-stocks-guide-2026','binance-earn-rwusd-guide-2026') as $slug){$p=get_page_by_path($slug,OBJECT,'post');$id=wp_insert_post(array('ID'=>$p?$p->ID:0,'post_type'=>'post','post_name'=>$slug,'post_title'=>'Local preservation fixture '.$slug,'post_content'=>'<p>本地验证文章。MEXC 与 Gate 是正常文字引用，必须保留。</p>','post_status'=>'publish'));update_post_meta($id,'rank_math_title','Existing SEO '.$slug);update_post_meta($id,'rank_math_description','Existing description '.$slug);$articles[]=$id;}
  $protected=get_posts(array('post_type'=>array('post','page','cg_exchange','cg_review','cg_compare','cg_guide'),'post_status'=>'publish','posts_per_page'=>-1,'exclude'=>array_merge($ids,array($privacy))));$state=array();foreach($protected as $p){$state[$p->ID]=array('post'=>(array)$p,'seo'=>array(get_post_meta($p->ID,'rank_math_title',true),get_post_meta($p->ID,'rank_math_description',true)),'url'=>get_permalink($p));}update_option('cg_test_protected_state',$state,false);
  echo wp_json_encode(array('ids'=>$ids,'privacy'=>$privacy,'articles'=>$articles,'admin'=>$admin->ID,'original_privacy'=>hash('sha256',get_post_field('post_content',$privacy))));
 `);
 wp('rewrite','flush');
 let browser;
 try{
  browser=await chromium.launch({headless:true,executablePath:'/usr/bin/chromium',args:['--no-sandbox']});
  const ctx=await browser.newContext(), page=await ctx.newPage(), anonymous=await browser.newContext(), anon=await anonymous.newPage();
  const login='cleanup-'+randomBytes(5).toString('hex'),password=randomBytes(24).toString('hex');
  wp('user','create',login,login+'@example.invalid','--role=administrator','--user_pass='+password,'--porcelain');
  await page.goto(base+'/wp-login.php');await ctx.request.post(base+'/wp-login.php',{form:{log:login,pwd:password,testcookie:'1',redirect_to:base+'/wp-admin/'}});
  const admin=async()=>{await page.goto(base+'/wp-admin/admin.php?page=cg-launch-cleanup');assert.equal(await page.locator('h1').innerText(),'上线整改');return page.locator('input[name="cg_launch_cleanup_nonce"]').inputValue();};
  const submit=async nonce=>ctx.request.post(base+'/wp-admin/admin-post.php',{form:{action:'cg_launch_cleanup',cg_launch_cleanup_nonce:nonce,cg_launch_backup_confirmed:'1'}});
  const maps=async()=>{
   const parse=async(res,node)=>{assert.equal(res.status(),200,res.url());return page.evaluate(({xml,node})=>{const doc=new DOMParser().parseFromString(xml,'application/xml');if(doc.querySelector('parsererror'))throw Error('Invalid XML');return [...doc.getElementsByTagNameNS('*',node)].map(n=>[...n.children].find(c=>c.localName==='loc')?.textContent).filter(Boolean);},{xml:await res.text(),node});};
   const children=await parse(await anon.request.get(base+'/sitemap_index.xml'),'sitemap'),urls=[];
   for(const child of children)urls.push(...await parse(await anon.request.get(child),'url'));
   return {children,urls};
  };
  const before=await maps();for(const path of paths)assert(before.urls.includes(base+path),path+' exists before real cleanup');
  const nonce=await admin();
  const unauthorized=await anon.request.post(base+'/wp-admin/admin-post.php',{form:{action:'cg_launch_cleanup'}});assert([400,403].includes(unauthorized.status()));
  const noBackup=await ctx.request.post(base+'/wp-admin/admin-post.php',{form:{action:'cg_launch_cleanup',cg_launch_cleanup_nonce:nonce}});assert.equal(noBackup.status(),400);
  const badNonce=await ctx.request.post(base+'/wp-admin/admin-post.php',{form:{action:'cg_launch_cleanup',cg_launch_cleanup_nonce:'invalid',cg_launch_backup_confirmed:'1'}});assert.equal(badNonce.status(),403);
  // An unwritable private backup directory must stop before database cleanup.
  docker('chown','root:root','/var/www/private-cleanup');docker('chmod','0500','/var/www/private-cleanup');
  const failed=await submit(nonce);assert.equal(failed.status(),400);assert.equal(wp('post','get',String(fixture.ids[0]),'--field=post_status'),'publish');
  docker('chmod','0700','/var/www/private-cleanup');docker('chown','www-data:www-data','/var/www/private-cleanup');
  const applied=await submit(await admin());assert.equal(applied.status(),200);
  const result=php(`$r=(array)get_option('cg_cleanup_runs');echo wp_json_encode(end($r));`);
  assert.deepEqual(result.errors,[]);assert(result.rows.every(r=>r.result==='trashed'));assert.equal(result.privacy.result,'updated_original_page');
  const snap=php(`$p='${result.backup.directory}/database.snapshot';echo wp_json_encode(array('exists'=>is_readable($p),'sha'=>hash_file('sha256',$p),'manifest'=>is_readable(dirname($p).'/manifest.json')));`);assert(snap.exists&&snap.manifest);assert.equal(snap.sha,result.backup.sha256);
  const statuses=[];
  for(const path of paths){const res=await anon.request.get(base+path,{maxRedirects:0});assert.equal(res.status(),404,path);assert(!res.headers().location);statuses.push({path,status:res.status(),location:res.headers().location||null});}
  const after=await maps();for(const path of paths)assert(!after.urls.includes(base+path));assert(!after.urls.some(u=>u.includes('/go/')));
  for(const url of after.urls){const res=await anon.goto(url);assert.equal(res.status(),200,url);assert.equal(await anon.locator('link[rel="canonical"]').getAttribute('href'),url);assert(!(await anon.locator('meta[name="robots"]').getAttribute('content')).includes('noindex'));}
  const exchanges=await (await anon.request.get(base+'/wp-json/wp/v2/cg_exchange?per_page=100')).json();assert.deepEqual(exchanges.map(x=>x.slug).sort(),['binance','bitget','bybit','okx']);
  await anon.goto(base+'/privacy-policy/');assert(!(await anon.locator('body').innerText()).includes('Suggested text:'));assert((await anon.locator('body').innerText()).includes('hello@degendeer.com'));assert.equal(wp('post','meta','get',String(fixture.privacy),'rank_math_title'),'Preserved privacy SEO title');
  const preserved=()=>php(`$expected=get_option('cg_test_protected_state');$out=array();foreach($expected as $id=>$s){$out[$id]=array('post'=>(array)get_post($id),'seo'=>array(get_post_meta($id,'rank_math_title',true),get_post_meta($id,'rank_math_description',true)),'url'=>get_permalink($id));}echo wp_json_encode(array('equal'=>serialize($expected)===serialize($out),'count'=>count($out)));`);
  assert(preserved().equal,'All formal bodies/dates/permalinks/SEO unchanged, including >7 articles');
  wp('eval','cg_setup_pages(true);');assert.equal(wp('post','get',String(fixture.ids[4]),'--field=post_status'),'trash','explicit initialization cannot revive a retired idle home');assert.equal(wp('option','get','show_on_front'),'posts');
  const go=[];for(const slug of ['binance','okx','bitget','bybit']){const r=await anon.request.get(base+'/go/'+slug+'/',{maxRedirects:0});assert.equal(r.status(),302);assert(r.headers()['x-robots-tag'].includes('noindex'));go.push({slug,status:r.status(),location:r.headers().location});}
  for(const width of [1440,1024,390,320]){await anon.setViewportSize({width,height:1000});await anon.goto(base+'/');assert(await anon.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));if(width<=850)await anon.locator('.cg-menu-toggle').click();const roots=anon.locator('#cg-primary-nav .cg-menu > li');assert.deepEqual(await roots.locator(':scope > a').allTextContents(),['交易所','加密货币','美股']);for(const i of [0,1]){await roots.nth(i).locator(':scope > button').click();assert(await roots.nth(i).locator(':scope > ul').isVisible());await roots.nth(i).locator(':scope > button').click();}}
  // A later edit blocks rollback, rather than silently overwriting newer work.
  wp('option','update','cg_launch_home_alias_id','999999');await admin();let restoreNonce=await page.locator('[name="cg_cleanup_restore_nonce"]').inputValue();
  const restore=()=>ctx.request.post(base+'/wp-admin/admin-post.php',{form:{action:'cg_cleanup_restore',cg_cleanup_restore_nonce:restoreNonce,run:result.backup.run}});
  const conflict=await restore();assert.equal(conflict.status(),400);assert((await conflict.text()).includes('后续编辑'));wp('option','delete','cg_launch_home_alias_id');
  const restored=await restore();assert.equal(restored.status(),200);for(const path of paths)assert.equal((await anon.request.get(base+path,{maxRedirects:0})).status(),200,path+' restored with same ID');
  assert.equal(php(`echo wp_json_encode(hash('sha256',get_post_field('post_content',${fixture.privacy})));`),fixture.original_privacy);assert(preserved().equal);
  // True static homepage: retain rich data and internal ID; only its equivalent alias301.
  wp('eval',`wp_update_post(array('ID'=>${fixture.ids[4]},'post_content'=>'<p>Preserve static-front content.</p>'));update_option('show_on_front','page');update_option('page_on_front',${fixture.ids[4]});`);
  assert.equal((await submit(await admin())).status(),200);
  const staticResult=php(`$r=(array)get_option('cg_cleanup_runs');echo wp_json_encode(end($r));`);assert.deepEqual(staticResult.errors,[]);assert.equal(staticResult.rows.find(r=>r.slug==='home').result,'kept_static_front_alias_301');
  assert.equal(wp('post','get',String(fixture.ids[4]),'--field=post_status'),'publish');assert.equal(wp('post','get',String(fixture.ids[4]),'--field=post_content'),'<p>Preserve static-front content.</p>');
  const alias=await anon.request.get(base+'/home/',{maxRedirects:0});assert.equal(alias.status(),301);assert.equal(alias.headers().location,base+'/');assert.equal((await anon.request.get(base+'/')).status(),200);assert(!(await maps()).urls.includes(base+'/home/'));
  const future=php(`$root=cg_find_topic('crypto');$term=wp_insert_term('Local future permanent desk','cg_topic',array('slug'=>'cleanup-future-permanent','parent'=>$root->term_id,'description'=>'Local future desk introduction.'));if(is_wp_error($term)){throw new RuntimeException($term->get_error_message());}update_term_meta($term['term_id'],'_cg_formal_directory','1');$retired=cg_find_topic('deposits-withdrawals',$root->term_id);if(!$retired){$old=wp_insert_term('出入金','cg_topic',array('slug'=>'deposits-withdrawals','parent'=>$root->term_id));$retired=get_term($old['term_id'],'cg_topic');}update_term_meta($retired->term_id,'_cg_formal_directory','1');echo wp_json_encode(array('url'=>get_term_link($term['term_id'],'cg_topic'),'retired'=>get_term_link($retired)));`);
  const futureMap=await maps();assert(futureMap.urls.includes(future.url),'explicit future permanent empty topic enters dynamic sitemap');assert(!futureMap.urls.includes(future.retired),'retired topic cannot return through empty/formal flags');await anon.goto(future.url);assert(!(await anon.locator('meta[name="robots"]').getAttribute('content')).includes('noindex'));assert.equal(await anon.locator('link[rel="canonical"]').getAttribute('href'),future.url);
  // The initializer is a known explicit path; it must not recreate retired demos.
  wp('eval','cg_setup_pages(false);');for(const slug of ['mexc','gate'])assert.equal(wp('eval',`echo cg_exchange('${slug}')?'published':'absent';`),'absent');for(const path of ['/best/','/guides/'])assert.equal((await anon.request.get(base+path,{maxRedirects:0})).status(),404);
  fs.writeFileSync('wordpress/audits/cleanup-local-20261021.json',JSON.stringify({environment:'disposable local WordPress; NOT production',backupVerified:true,adminNonceAndPermissionGuards:true,backupFailureStopsCleanup:true,realTrash:true,statuses,privacyReplacedInDatabase:true,seoAndAllFormalArticlesPreserved:preserved(),rollbackVerified:true,rollbackConflictStopsOverwrite:true,staticFrontAlias:{status:alias.status(),location:alias.headers().location},futurePermanentEmptyTopic:true,retiredTopicExcluded:true,go,widths:[1440,1024,390,320],beforeSitemapUrls:before.urls,afterSitemapUrls:after.urls},null,2)+'\n');
  console.log('PASS actual admin backup/failure gates, five real trash/404/REST/sitemap removal, Chinese privacy body with SEO preserved, all >7 formal articles unchanged, rollback and conflict guard, static homepage retained/alias301, four widths and no recreation');
 }finally{docker('chmod','0700','/var/www/private-cleanup');docker('chown','www-data:www-data','/var/www/private-cleanup');if(browser)await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
