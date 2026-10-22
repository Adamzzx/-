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
  const root='http://localhost:8088/';
  const homePagination=()=>page.getByRole('navigation',{name:'首页文章分页',exact:true});
  const searchPagination=()=>page.getByRole('navigation',{name:'搜索结果分页',exact:true});
  const numberedLink=(nav,number)=>nav.locator('a.page-numbers').filter({hasText:new RegExp('^(?:第 *)?'+number+'(?: *页)?$')});
  const assertCurrent=async(nav,number)=>{const current=nav.locator('span.page-numbers.current[aria-current="page"]');assert.equal(await current.count(),1);assert.equal((await current.textContent()).replace(/[^0-9]/g,''),String(number));};
  const assertCanonical=async expected=>assert.deepEqual(await page.locator('link[rel="canonical"]').evaluateAll(nodes=>nodes.map(node=>node.href)),[expected]);
  const go=async path=>{const response=await page.goto(root+path,{waitUntil:'load'});assert.equal(response.status(),200);};
  const homeUrls=()=>page.locator('#home-articles .cg-feed-title a').evaluateAll(nodes=>nodes.map(node=>node.href));
  const assertFirstHref=async link=>{assert.equal(await link.count(),1,'page 1 must be a clickable link on page 2');const url=new URL(await link.getAttribute('href'),root);assert.equal(url.origin,new URL(root).origin);assert.equal(url.pathname,'/');assert.equal(url.searchParams.get('cg_home_page')||'1','1');assert.equal(url.hash,'#home-articles');};
  // The official wizard permits skipping account connection for the free plugin.
  for(const configured of [false,true]){
   wp('eval',configured?`update_option('rank_math_registration_skip','1');`:`delete_option('rank_math_registration_skip');`);
   for(const mode of ['page','posts']){
    wp('option','update','show_on_front',mode);
    await go('');await assertCanonical(root);await assertCurrent(homePagination(),1);const first=await homeUrls();assert(first.length>0);
    const two=numberedLink(homePagination(),2);assert.equal(await two.count(),1);const twoURL=new URL(await two.getAttribute('href'),root);assert.equal(twoURL.searchParams.get('cg_home_page'),'2');await two.click();await page.waitForLoadState('load');assert.equal(new URL(page.url()).searchParams.get('cg_home_page'),'2');await assertCanonical(root+'?cg_home_page=2');await assertCurrent(homePagination(),2);assert.equal(await numberedLink(homePagination(),2).count(),0);const second=await homeUrls();assert(second.length>0&&!first.some(url=>second.includes(url)),'page-2 results must differ from initial page');
    const one=numberedLink(homePagination(),1);await assertFirstHref(one);await one.click();await page.waitForLoadState('load');await assertCanonical(root);await assertCurrent(homePagination(),1);assert.deepEqual(await homeUrls(),first,'numeric page 1 restores the exact initial list');
    await numberedLink(homePagination(),2).click();await page.waitForLoadState('load');await assertCurrent(homePagination(),2);const previous=homePagination().locator('a.prev');await assertFirstHref(previous);await previous.click();await page.waitForLoadState('load');await assertCanonical(root);await assertCurrent(homePagination(),1);assert.deepEqual(await homeUrls(),first,'previous link restores the exact initial list');
    console.log('PASS Rank Math '+(configured?'frontend enabled':'wizard pending')+': correct canonical, active page 2, clickable page 1 and exact numeric/previous round trips in '+mode+' front-page mode');
   }
   await go('?s=CGSeoCompatibility');assert((await page.locator('meta[name="robots"]').getAttribute('content')).includes('noindex'));assert.equal(await page.locator('#search-articles .cg-feed-article').count(),9);await assertCurrent(searchPagination(),1);const searchUrls=()=>page.locator('#search-articles .cg-feed-title a').evaluateAll(nodes=>nodes.map(node=>node.href));const firstSearch=await searchUrls();
   await searchPagination().locator('a.next').click();await page.waitForLoadState('load');await assertCurrent(searchPagination(),2);const secondSearch=await searchUrls();assert(secondSearch.length>0&&!firstSearch.some(url=>secondSearch.includes(url)));assert((await page.locator('meta[name="robots"]').getAttribute('content')).includes('noindex'));
   const firstSearchLink=numberedLink(searchPagination(),1);assert.equal(await firstSearchLink.count(),1);const firstSearchURL=new URL(await firstSearchLink.getAttribute('href'),root);assert.equal(firstSearchURL.searchParams.get('s'),'CGSeoCompatibility');assert.equal(firstSearchURL.searchParams.get('paged')||'1','1');assert(!firstSearchURL.pathname.endsWith('/page/2/')&&!firstSearchURL.pathname.endsWith('/page/2'));await firstSearchLink.click();await page.waitForLoadState('load');await assertCurrent(searchPagination(),1);assert.deepEqual(await searchUrls(),firstSearch);
   await searchPagination().locator('a.next').click();await page.waitForLoadState('load');await assertCurrent(searchPagination(),2);await searchPagination().locator('a.prev').click();await page.waitForLoadState('load');await assertCurrent(searchPagination(),1);assert.equal(new URL(page.url()).searchParams.get('s'),'CGSeoCompatibility');assert.deepEqual(await searchUrls(),firstSearch,'search previous link restores the exact initial results');
   await page.goto('http://localhost:8088/topics/crypto/yield-products/',{waitUntil:'load'});assert(!(await page.locator('meta[name="robots"]').getAttribute('content')).includes('noindex'));
   console.log('PASS search noindex/current-state/return links and formal empty-category index with Rank Math '+(configured?'frontend enabled':'wizard pending'));
  }
 }finally{
  if(browser)await browser.close();if(state){if(!state.active.includes('seo-by-rank-math/rank-math.php'))wp('plugin','deactivate','seo-by-rank-math');wp('option','update','blog_public',state.public);wp('option','update','show_on_front',state.front);wp('option','update','page_on_front',String(state.home));wp('eval',`$state=json_decode(base64_decode('${Buffer.from(JSON.stringify(state)).toString('base64')}'),true);if($state['skip']===null){delete_option('rank_math_registration_skip');}else{update_option('rank_math_registration_skip',$state['skip']);}foreach($state['ids'] as $id){wp_delete_post($id,true);}`);}
 }
})().catch(error=>{console.error(error);process.exit(1)});
