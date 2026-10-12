const {chromium}=require('playwright');const {execFileSync}=require('node:child_process');const assert=require('node:assert/strict');
const wp=(...args)=>execFileSync('docker',['compose','-f','wordpress/tests/compose.packages.yaml','run','--rm','-T','cli','wp',...args],{encoding:'utf8',stdio:['pipe','pipe','pipe']}).trim();
(async()=>{
 assert.equal(wp('option','get','home'),'http://localhost:8088');
 const ids=JSON.parse(wp('eval',`$b=cg_exchange('binance');$y=cg_exchange('bybit');$ids=array();for($i=0;$i<11;$i++){$id=wp_insert_post(array('post_type'=>$i===10?'cg_compare':'cg_guide','post_status'=>'publish','post_title'=>'Hub UI acceptance '.$i,'post_content'=>'Local-only article fixture','post_name'=>'hub-ui-'.$i));update_post_meta($id,'_cg_related_exchanges',$i===10?array($b->ID,$y->ID):array($b->ID));if($i===10){update_post_meta($id,'_cg_sponsored','1');}$ids[]=$id;}echo wp_json_encode($ids);`));
 let browser;
 try{
 browser=await chromium.launch({headless:true,executablePath:process.env.CG_CHROMIUM_PATH||'/usr/bin/chromium',args:['--no-sandbox']});const page=await browser.newPage({viewport:{width:1440,height:950}});
 await page.goto('http://localhost:8088/exchanges/binance/');assert.equal(await page.locator('h1').innerText(),'Binance');
 assert.equal(await page.locator('#exchange-articles h2').innerText(),'Binance 相关文章');
 assert.equal(await page.locator('#exchange-articles .cg-story').count(),9);assert(await page.locator('#exchange-articles').getByText('赞助内容').isVisible());
 const first=await page.locator('#exchange-articles .cg-story-link').evaluateAll(nodes=>nodes.map(n=>n.href));
 await page.locator('#exchange-articles').screenshot({path:'/tmp/cg-exchange-hub-desktop.png'});
 await page.locator('#exchange-articles a.next').click();assert(page.url().includes('cg_articles_page=2'));
 const second=await page.locator('#exchange-articles .cg-story-link').evaluateAll(nodes=>nodes.map(n=>n.href));assert(!first.some(link=>second.includes(link)));
 await page.locator('#exchange-articles .cg-story-link').first().click();assert(!page.url().includes('/exchanges/binance/'));
 await page.goto('http://localhost:8088/exchanges/bybit/');assert(await page.getByText('Hub UI acceptance 10',{exact:true}).isVisible());
 await page.setViewportSize({width:390,height:844});await page.goto('http://localhost:8088/exchanges/binance/');
 assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
 const box=await page.locator('#exchange-articles .cg-story').first().boundingBox();assert(box.width>300);
 await page.locator('#exchange-articles').screenshot({path:'/tmp/cg-exchange-hub-mobile.png'});
 console.log('PASS rendered hub: heading, nine cards, sponsorship, pagination, article links, shared comparison and mobile layout');
 }finally{
  if(browser)await browser.close();
  wp('eval','foreach(array('+ids.join(',')+') as $id){wp_delete_post($id,true);}');
 }
})().catch(e=>{console.error(e);process.exit(1)});
