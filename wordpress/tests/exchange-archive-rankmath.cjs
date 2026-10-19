// Official Rank Math must already be installed in the disposable local WordPress.
const {chromium}=require('playwright'),{execFileSync}=require('node:child_process'),assert=require('node:assert/strict');
const wp=(...args)=>execFileSync('docker',['compose','-f','wordpress/tests/compose.packages.yaml','run','--rm','-T','cli','wp',...args],{encoding:'utf8',stdio:['pipe','pipe','pipe']}).trim();
(async()=>{
 assert.equal(wp('option','get','home'),'http://localhost:8088');let state,browser;
 try{
  state=JSON.parse(wp('eval',`$platform=cg_exchange('binance');$ids=array();for($i=0;$i<11;$i++){$id=wp_insert_post(array('post_type'=>'post','post_status'=>'publish','post_title'=>'Local exchange SEO compatibility '.$i));update_post_meta($id,'_cg_related_exchanges',array($platform->ID));$ids[]=$id;}echo wp_json_encode(array('ids'=>$ids,'public'=>get_option('blog_public'),'active'=>get_option('active_plugins'),'skip'=>get_option('rank_math_registration_skip',null)));`));
  wp('plugin','activate','seo-by-rank-math');wp('option','update','blog_public','1');
  assert.equal(wp('eval',`echo RankMath\\Helper::is_site_connected()?'1':'0';`),'0','Use an unconnected disposable SEO installation');
  browser=await chromium.launch({headless:true,executablePath:'/usr/bin/chromium',args:['--no-sandbox']});const page=await browser.newPage(),errors=[];page.on('pageerror',error=>errors.push(error.message));
  for(const configured of [false,true]){
   wp('eval',configured?`update_option('rank_math_registration_skip','1');`:`delete_option('rank_math_registration_skip');`);
   for(const route of ['/exchanges/','/exchanges/page/2/']){
    const response=await page.goto('http://localhost:8088'+route,{waitUntil:'load'});assert.equal(response.status(),200);
    assert.equal(await page.locator('.cg-exchange-archive').count(),1);assert.equal(await page.locator('h1').textContent(),'交易所');
    assert.deepEqual(await page.locator('link[rel="canonical"]').evaluateAll(nodes=>nodes.map(n=>n.href)),['http://localhost:8088'+route]);
    assert.equal(await page.locator('meta[name="description"]').count(),1);assert((await page.title()).includes('交易所'),'Unexpected archive title '+route+': '+await page.title());assert(!(await page.title()).includes('交易所资料'));
    assert.equal(await page.locator('meta[name="robots"]').count(),1);assert(!(await page.locator('meta[name="robots"]').getAttribute('content')).includes('noindex'));
    const graphs=(await page.locator('script[type="application/ld+json"]').allTextContents()).flatMap(text=>{const data=JSON.parse(text);return data['@graph']||[data];});assert.equal(graphs.filter(node=>[].concat(node['@type']).includes('CollectionPage')).length,1);
   }
   wp('option','update','blog_public','0');await page.goto('http://localhost:8088/exchanges/');assert((await page.locator('meta[name="robots"]').getAttribute('content')).includes('noindex'));wp('option','update','blog_public','1');
   console.log('PASS Rank Math '+(configured?'frontend enabled':'wizard pending')+': archive template, title, single canonical/description/CollectionPage, native page 2 and site privacy');
  }
  assert.deepEqual(errors,[]);
 }finally{
  if(browser)await browser.close();if(state){if(!state.active.includes('seo-by-rank-math/rank-math.php'))wp('plugin','deactivate','seo-by-rank-math');wp('option','update','blog_public',state.public);wp('eval',`$state=json_decode(base64_decode('${Buffer.from(JSON.stringify(state)).toString('base64')}'),true);if($state['skip']===null){delete_option('rank_math_registration_skip');}else{update_option('rank_math_registration_skip',$state['skip']);}foreach($state['ids'] as $id){wp_delete_post($id,true);}`);}
 }
})().catch(error=>{console.error(error);process.exit(1)});
