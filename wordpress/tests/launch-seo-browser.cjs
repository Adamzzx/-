// Official free Rank Math must already be installed in the disposable local WordPress.
// No credentials, remote account, public deployment, or cache-busting query is used.
const {chromium}=require('playwright');
const {execFileSync}=require('node:child_process');
const assert=require('node:assert/strict');
const origin='http://localhost:8088';
const wp=(...args)=>execFileSync('docker',['compose','-f','wordpress/tests/compose.packages.yaml','run','--rm','-T','cli','wp',...args],{encoding:'utf8',stdio:['pipe','pipe','pipe']}).trim();
const encoded=value=>Buffer.from(JSON.stringify(value)).toString('base64');
(async()=>{
 assert.equal(wp('option','get','home'),origin,'Disposable local WordPress only');
 let state,browser,fixtures;
 try{
  state=JSON.parse(wp('eval',`$saved=array();foreach(array('blog_public','active_plugins','rank_math_registration_skip','rank_math_modules','rank-math-options-sitemap','rank-math-options-titles','rewrite_rules') as $key){$saved[$key]=array('exists'=>get_option($key,null)!==null,'value'=>get_option($key));}$statuses=array();$stocks=cg_stocks_page();$root=cg_find_topic('crypto');$yield=cg_find_topic('yield-products',$root->term_id);$stockroot=cg_stocks_root();$posts=get_posts(array('post_type'=>array('post','cg_review','cg_compare','cg_ranking','cg_guide'),'post_status'=>'publish','has_password'=>false,'numberposts'=>-1,'tax_query'=>array(array('taxonomy'=>'cg_topic','field'=>'term_id','terms'=>array($stockroot->term_id,$yield->term_id),'include_children'=>true))));foreach($posts as $post){$statuses[$post->ID]=$post->post_status;wp_update_post(array('ID'=>$post->ID,'post_status'=>'draft'));}echo wp_json_encode(array('options'=>$saved,'statuses'=>$statuses,'stocks'=>get_permalink($stocks),'stocksAlias'=>get_term_link($stockroot),'yield'=>get_term_link($yield),'yieldID'=>$yield->term_id,'stockID'=>$stockroot->term_id,'cryptoID'=>$root->term_id,'topics'=>array_map('get_term_link',array_map(function($id){return get_term($id,'cg_topic');},cg_launch_formal_topic_ids()))));`));
  wp('plugin','activate','seo-by-rank-math');
  assert.equal(wp('eval',`echo RankMath\\Helper::is_site_connected()?'1':'0';`),'0','Use an unconnected local SEO installation');
  wp('eval',`update_option('blog_public',1);$modules=(array)get_option('rank_math_modules',array());$modules[]='sitemap';update_option('rank_math_modules',array_values(array_unique($modules)));$settings=(array)get_option('rank-math-options-sitemap',array());foreach(array('post','page','cg_exchange') as $type){$settings['pt_'.$type.'_sitemap']=true;}update_option('rank-math-options-sitemap',$settings);$titles=(array)get_option('rank-math-options-titles',array());$titles['noindex_empty_taxonomies']=true;update_option('rank-math-options-titles',$titles);cg_launch_invalidate_sitemaps();`);
  browser=await chromium.launch({headless:true,executablePath:'/usr/bin/chromium',args:['--no-sandbox']});
  const page=await browser.newPage({viewport:{width:1440,height:1000}}),errors=[];page.on('pageerror',error=>errors.push(error.message));
  const inspect=async(url)=>{
   const response=await page.goto(url,{waitUntil:'load'});assert.equal(response.status(),200,url);
   assert.deepEqual(await page.locator('link[rel="canonical"]').evaluateAll(nodes=>nodes.map(node=>node.href)),[url],'exactly one self-canonical on '+url);
   assert.equal(await page.locator('meta[name="description"]').count(),1,'one description on '+url);
   const robots=await page.locator('meta[name="robots"]').allTextContents();assert.equal(robots.length,1);
   const directives=await page.locator('meta[name="robots"]').getAttribute('content');assert(/\bindex\b/.test(directives)&&/\bfollow\b/.test(directives)&&!directives.includes('noindex'),directives);
   assert.equal(await page.locator('.cg-story').count(),0,'empty desk has no invented cards');assert.equal(await page.locator('.cg-empty').count(),1,'one useful empty state');
   const graphs=(await page.locator('script[type="application/ld+json"]').allTextContents()).flatMap(text=>{const data=JSON.parse(text);return data['@graph']||[data];});
   assert.equal(graphs.filter(node=>[].concat(node['@type']).includes('CollectionPage')).length,1,'one CollectionPage metadata owner');
  };
  for(const configured of [false,true]){
   wp('eval',configured?`update_option('rank_math_registration_skip','1');`:`delete_option('rank_math_registration_skip');`);
   for(const url of [state.stocks,state.yield]){await inspect(url);}
   const alias=await page.request.get(state.stocksAlias,{maxRedirects:0});assert.equal(alias.status(),301);assert.equal(alias.headers().location,state.stocks);
   const invalid=await page.goto(state.stocks+'?cg_stocks_page=9999');assert.equal(invalid.status(),404);assert((await page.locator('meta[name="robots"]').getAttribute('content')).includes('noindex'));
   wp('option','update','blog_public','0');const privacyResponse=await page.goto(state.yield);const privacyRobots=await page.locator('meta[name="robots"]').getAttribute('content');assert(privacyRobots.includes('noindex'),JSON.stringify({configured,privacyRobots,blogPublic:wp('option','get','blog_public'),status:privacyResponse.status(),freshMeta:(await (await page.request.get(state.yield)).text()).match(/<meta[^>]+name=[\"']robots[\"'][^>]*>/g)}));wp('option','update','blog_public','1');
   console.log('PASS '+(configured?'Rank Math enabled':'Rank Math wizard pending')+': empty formal desks 200/index/follow/self-canonical, one CollectionPage, no invented cards, old alias301, true404/site privacy retained');
  }
  // The supported skip-account setup enables Rank Math's router only after a new request.
  wp('rewrite','flush');
  const xmlLocs=async(response,node)=>{
   assert.equal(response.status(),200,response.url());return page.evaluate(({xml,node})=>{const document=new DOMParser().parseFromString(xml,'application/xml');if(document.querySelector('parsererror'))throw Error('Invalid sitemap XML');return [...document.getElementsByTagNameNS('*',node)].map(item=>[...item.children].find(child=>child.localName==='loc')?.textContent).filter(Boolean);},{xml:await response.text(),node});
  };
  const sitemap=async()=>{
   const root=await page.request.get(origin+'/sitemap_index.xml');assert.equal(root.status(),200,'Rank Math sitemap route must be active');assert((root.headers()['x-robots-tag']||'').includes('noindex'),'XML keeps its own noindex header');const maps=await xmlLocs(root,'sitemap');const entries=[];
   for(const map of maps){entries.push(...await xmlLocs(await page.request.get(map),'url'));}
   return {maps,entries};
  };
  const before=await sitemap();for(const url of [origin+'/',origin+'/exchanges/',state.stocks,...state.topics])assert(before.entries.includes(url),'formal directory is in sitemap: '+url);
  assert.equal(before.entries.filter(url=>url===origin+'/').length,1,'one homepage across child sitemaps');
  assert(!before.entries.includes(state.stocksAlias));assert(!before.entries.some(url=>url.includes('/category/uncategorized/')||url.includes('/go/')));
  assert(before.maps.some(url=>url.includes('/cg_topic-sitemap'))&&before.maps.some(url=>url.includes('/cg_sections-sitemap')),'Rank Math dynamic providers exposed through its root index');
  fixtures=JSON.parse(wp('eval',`$ids=array();$term=wp_insert_term('Local future editorial topic','cg_topic',array('slug'=>'launch-browser-future-topic','parent'=>${state.cryptoID}));if(is_wp_error($term)){throw new RuntimeException($term->get_error_message());}$tid=$term['term_id'];$urls=array();foreach(array('post','page','cg_exchange') as $type){$id=wp_insert_post(array('post_type'=>$type,'post_status'=>'publish','post_name'=>'launch-browser-future-'.$type,'post_title'=>'Local future publication '.$type,'post_content'=>'Original local test body.'));$ids[]=$id;$urls[]=get_permalink($id);if($type==='post'){wp_set_object_terms($id,array($tid,${state.stockID},${state.yieldID}),'cg_topic');}}$hidden=array();foreach(array('draft','private','password','noindex') as $kind){$id=wp_insert_post(array('post_type'=>'post','post_status'=>in_array($kind,array('password','noindex'),true)?'publish':$kind,'post_password'=>$kind==='password'?'local-secret':'','post_name'=>'launch-browser-hidden-'.$kind,'post_title'=>'Local hidden publication '.$kind));if($kind==='noindex'){update_post_meta($id,'rank_math_robots',array('noindex'));}$ids[]=$id;$hidden[]=get_permalink($id);}cg_launch_invalidate_sitemaps();echo wp_json_encode(array('ids'=>$ids,'urls'=>$urls,'hidden'=>$hidden,'topicID'=>$tid,'topicURL'=>get_term_link(get_term($tid,'cg_topic'))));`));
  const after=await sitemap();for(const url of [...fixtures.urls,fixtures.topicURL])assert(after.entries.includes(url),'future publication automatically enters sitemap: '+url);
  assert(!fixtures.hidden.some(url=>after.entries.includes(url)),'draft, private, password and noindex articles stay out of XML');
  assert(after.entries.length>before.entries.length,'map is dynamic rather than a fixed launch checklist');
  for(const url of after.entries){const parsed=new URL(url);assert.equal(parsed.origin,origin);assert.equal(parsed.search,'');assert.equal(parsed.hash,'');}
  for(const url of [state.stocks,state.yield]){await page.goto(url);assert.equal(await page.locator('.cg-story-link').filter({hasText:'Local future publication post'}).count(),1,'real assignment adds article automatically to '+url);}
  wp('post','update',String(fixtures.ids[0]),'--post_status=draft');const unpublished=await sitemap();assert(!unpublished.entries.includes(fixtures.topicURL),'nonformal future topic leaves map when its only public article is withdrawn');
  assert(unpublished.entries.includes(state.yield)&&unpublished.entries.includes(state.stocks),'formal empty sections stay mapped after article withdrawal');
  console.log('PASS official Rank Math root and child XML: permanent empty desks, no old Stocks alias/Uncategorized/go, one homepage, future post/page/exchange/topic auto-inclusion and withdrawal/privacy exclusions');
  assert.deepEqual(errors,[]);
 }finally{
  if(browser)await browser.close();
  if(fixtures)wp('eval',`$f=json_decode(base64_decode('${encoded(fixtures)}'),true);foreach($f['ids'] as $id){wp_delete_post($id,true);}wp_delete_term($f['topicID'],'cg_topic');`);
  if(state)wp('eval',`$s=json_decode(base64_decode('${encoded(state)}'),true);foreach($s['statuses'] as $id=>$status){wp_update_post(array('ID'=>$id,'post_status'=>$status));}foreach($s['options'] as $key=>$saved){if($saved['exists']){update_option($key,$saved['value']);}else{delete_option($key);}}cg_launch_invalidate_sitemaps();`);
 }
})().catch(error=>{console.error(error);process.exit(1);});
