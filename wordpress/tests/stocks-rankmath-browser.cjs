// Uses the checksum-verified official free Rank Math already installed locally.
const {chromium}=require('playwright'),{execFileSync}=require('node:child_process'),assert=require('node:assert/strict');
const wp=(...args)=>execFileSync('docker',['compose','-f','wordpress/tests/compose.packages.yaml','run','--rm','-T','cli','wp',...args],{encoding:'utf8',stdio:['pipe','pipe','pipe']}).trim();
const origin='http://localhost:8088';
(async()=>{
 assert.equal(wp('option','get','home'),origin);let state,browser;
 try{
  state=JSON.parse(wp('eval',`$root=cg_stocks_root();$page=cg_stocks_page();$ids=array();for($i=0;$i<11;$i++){$id=wp_insert_post(array('post_type'=>'post','post_status'=>'publish','post_title'=>'Local stocks SEO compatibility '.$i));wp_set_object_terms($id,array($root->term_id),'cg_topic');$ids[]=$id;}echo wp_json_encode(array('ids'=>$ids,'page'=>$page->ID,'titleHad'=>metadata_exists('post',$page->ID,'rank_math_title'),'title'=>get_post_meta($page->ID,'rank_math_title',true),'public'=>get_option('blog_public'),'active'=>get_option('active_plugins'),'skip'=>get_option('rank_math_registration_skip',null)));delete_post_meta($page->ID,'rank_math_title');`));
  wp('plugin','activate','seo-by-rank-math');wp('option','update','blog_public','1');
  assert.equal(wp('eval',`echo RankMath\\Helper::is_site_connected()?'1':'0';`),'0','Use unconnected local SEO only');
  browser=await chromium.launch({headless:true,executablePath:'/usr/bin/chromium',args:['--no-sandbox']});const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
  for(const configured of [false,true]){
   wp('eval',configured?`update_option('rank_math_registration_skip','1');`:`delete_option('rank_math_registration_skip');`);
   for(const number of [1,2]){
    const url=origin+'/tokenized-stocks/'+(number>1?'?cg_stocks_page=2':'');const response=await page.goto(url,{waitUntil:'load'});assert.equal(response.status(),200);
    assert.equal(await page.locator('.cg-stocks-archive').count(),1);assert.equal(await page.locator('h1').textContent(),'美股');
    assert.deepEqual(await page.locator('link[rel="canonical"]').evaluateAll(nodes=>nodes.map(n=>n.href)),[url]);assert.equal(await page.locator('meta[name="description"]').count(),1);assert((await page.title()).includes('美股'));if(number>1)assert((await page.title()).includes('第 2 页'));
    const graphs=(await page.locator('script[type="application/ld+json"]').allTextContents()).flatMap(text=>{const d=JSON.parse(text);return d['@graph']||[d];});const collections=graphs.filter(n=>[].concat(n['@type']).includes('CollectionPage'));assert.equal(collections.length,1);assert.equal(collections[0].url,url);assert(!graphs.some(n=>[].concat(n['@type']).some(t=>['Article','BlogPosting','NewsArticle'].includes(t))));assert.deepEqual(collections[0].mainEntity.itemListElement.map(n=>n.url),await page.locator('.cg-stocks-archive .cg-story-link').evaluateAll(nodes=>nodes.map(n=>n.href)));
   }
   if(configured){wp('post','meta','update',String(state.page),'rank_math_title','Owner supplied stocks title');await page.goto(origin+'/tokenized-stocks/');assert.equal(await page.title(),'Owner supplied stocks title');wp('post','meta','delete',String(state.page),'rank_math_title');}
   wp('option','update','blog_public','0');await page.goto(origin+'/tokenized-stocks/');assert((await page.locator('meta[name="robots"]').getAttribute('content')).includes('noindex'));wp('option','update','blog_public','1');
   console.log('PASS stocks Rank Math '+(configured?'frontend enabled':'wizard pending')+': single canonical/description/CollectionPage, paginated URLs, no Article graph and owner SEO/site privacy');
  }
  assert.deepEqual(errors,[]);
 }finally{
  if(browser)await browser.close();if(state){if(!state.active.includes('seo-by-rank-math/rank-math.php'))wp('plugin','deactivate','seo-by-rank-math');wp('option','update','blog_public',state.public);const encoded=Buffer.from(JSON.stringify(state)).toString('base64');wp('eval',`$s=json_decode(base64_decode('${encoded}'),true);if($s['skip']===null){delete_option('rank_math_registration_skip');}else{update_option('rank_math_registration_skip',$s['skip']);}if($s['titleHad']){update_post_meta($s['page'],'rank_math_title',$s['title']);}else{delete_post_meta($s['page'],'rank_math_title');}foreach($s['ids'] as $id){wp_delete_post($id,true);}`);}
 }
})().catch(e=>{console.error(e);process.exit(1)});
