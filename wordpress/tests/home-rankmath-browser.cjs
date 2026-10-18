// Install the official free Rank Math ZIP in the disposable site before this check.
const {chromium}=require('playwright'),{execFileSync}=require('node:child_process'),assert=require('node:assert/strict');
const wp=(...a)=>execFileSync('docker',['compose','-f','wordpress/tests/compose.packages.yaml','run','--rm','-T','cli','wp',...a],{encoding:'utf8',stdio:['pipe','pipe','pipe']}).trim();
(async()=>{
 assert.equal(wp('option','get','home'),'http://localhost:8088');let state,browser;
 try{
  state=JSON.parse(wp('eval',`$ids=array();for($i=0;$i<11;$i++){$ids[]=wp_insert_post(array('post_type'=>'post','post_status'=>'publish','post_title'=>'CGSeoCompatibility '.$i,'post_content'=>'Local SEO compatibility fixture only.'));}echo wp_json_encode(array('ids'=>$ids,'public'=>get_option('blog_public'),'front'=>get_option('show_on_front'),'home'=>get_option('page_on_front'),'active'=>get_option('active_plugins'),'skip'=>get_option('rank_math_registration_skip',null)));`));
  wp('plugin','activate','seo-by-rank-math');wp('option','update','blog_public','1');
  assert.equal(wp('eval',`echo RankMath\\Helper::is_site_connected()?'1':'0';`),'0','This check uses an unconnected disposable site; no external account is modified');
  browser=await chromium.launch({headless:true,executablePath:'/usr/bin/chromium',args:['--no-sandbox']});const page=await browser.newPage();
  // The official wizard permits skipping account connection for the free plugin.
  for(const configured of [false,true]){
   wp('eval',configured?`update_option('rank_math_registration_skip','1');`:`delete_option('rank_math_registration_skip');`);
   for(const mode of ['page','posts']){
    wp('option','update','show_on_front',mode);
    for(const [path,canonical] of [['/','http://localhost:8088/'],['/?cg_home_page=2','http://localhost:8088/?cg_home_page=2']]){
     const response=await page.goto('http://localhost:8088'+path,{waitUntil:'load'});assert.equal(response.status(),200);assert.deepEqual(await page.locator('link[rel="canonical"]').evaluateAll(nodes=>nodes.map(node=>node.href)),[canonical]);assert((await page.locator('#home-articles .cg-feed-article').count())>0);
    }
    console.log('PASS Rank Math '+(configured?'frontend enabled':'wizard pending')+': exactly one correct homepage/page-2 canonical in '+mode+' front-page mode');
   }
   await page.goto('http://localhost:8088/?s=CGSeoCompatibility',{waitUntil:'load'});assert((await page.locator('meta[name="robots"]').getAttribute('content')).includes('noindex'));assert.equal(await page.locator('#search-articles .cg-feed-article').count(),9);
   await page.goto('http://localhost:8088/topics/crypto/yield-products/',{waitUntil:'load'});assert((await page.locator('meta[name="robots"]').getAttribute('content')).includes('noindex'));
   console.log('PASS search and empty-category noindex with Rank Math '+(configured?'frontend enabled':'wizard pending'));
  }
 }finally{
  if(browser)await browser.close();if(state){if(!state.active.includes('seo-by-rank-math/rank-math.php'))wp('plugin','deactivate','seo-by-rank-math');wp('option','update','blog_public',state.public);wp('option','update','show_on_front',state.front);wp('option','update','page_on_front',String(state.home));wp('eval',`$state=json_decode(base64_decode('${Buffer.from(JSON.stringify(state)).toString('base64')}'),true);if($state['skip']===null){delete_option('rank_math_registration_skip');}else{update_option('rank_math_registration_skip',$state['skip']);}foreach($state['ids'] as $id){wp_delete_post($id,true);}`);}
 }
})().catch(error=>{console.error(error);process.exit(1)});
