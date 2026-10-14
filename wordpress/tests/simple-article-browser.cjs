const {chromium}=require('playwright'),{execFileSync}=require('node:child_process'),assert=require('node:assert/strict'),fs=require('node:fs');
const wp=(...a)=>execFileSync('docker',['compose','-f','wordpress/tests/compose.packages.yaml','run','--rm','-T','cli','wp',...a],{encoding:'utf8',stdio:['pipe','pipe','pipe']}).trim();
(async()=>{
 assert.equal(wp('option','get','home'),'http://localhost:8088');
 const state=JSON.parse(wp('eval',`wp_set_current_user(get_user_by('login','smoke-admin')->ID);$id=wp_insert_post(array('post_type'=>'post','post_status'=>'publish','post_author'=>get_current_user_id(),'post_title'=>'币安 Binance 使用评测（2026）：手续费、出入金、理财与美股交易','post_excerpt'=>'简洁版排版预览：正文沿用原生编辑器，数据来源与核验日期可写在对应段落和表格旁。','post_content'=>'<!-- wp:heading --><h2>核心结论</h2><!-- /wp:heading --><!-- wp:paragraph --><p>本页用于展示新版文章排版，不代表交易所数据核验或实测结论。标题、正文、图片和表格均由作者在后台编辑。</p><!-- /wp:paragraph --><!-- wp:heading --><h2>账户安全与使用流程</h2><!-- /wp:heading --><!-- wp:paragraph --><p>教程可按操作顺序组织内容，清楚说明账户安全、出入金步骤、费用条件和使用风险。实际测试材料由作者提供。</p><!-- /wp:paragraph --><!-- wp:heading --><h2>手续费与资料来源</h2><!-- /wp:heading --><!-- wp:table --><figure class="wp-block-table"><table><thead><tr><th>项目</th><th>适用条件</th><th>来源及核验日期</th></tr></thead><tbody><tr><td>现货与合约费用</td><td>填写账户等级、产品与折扣条件</td><td>填写官方来源和核验日期</td></tr><tr><td>理财产品</td><td>说明分档规则与赎回条件</td><td>未核验信息不填写具体收益率</td></tr></tbody></table></figure><!-- /wp:table --><!-- wp:heading --><h2>操作截图与说明</h2><!-- /wp:heading --><!-- wp:image --><figure class="wp-block-image"><img src="https://darkred-panther-124490.hostingersite.com/wp-content/uploads/2026/10/IMG_0825-1-538x1024.png" alt="作者上传的操作截图排版预览"/><figcaption>图片排版预览；测试日期、环境和条件由作者填写。</figcaption></figure><!-- /wp:image --><!-- wp:heading --><h2>风险提示</h2><!-- /wp:heading --><!-- wp:paragraph --><p>费用、产品状态及地区可用性可能变化。文章更新时间不等于所有数据已重新核验。</p><!-- /wp:paragraph -->'));
update_post_meta($id,'_cg_related_exchanges',array(cg_exchange('binance')->ID));$other=wp_insert_post(array('post_type'=>'cg_guide','post_status'=>'publish','post_title'=>'Binance 账户安全指南 · 示例链接'));update_post_meta($other,'_cg_related_exchanges',array(cg_exchange('binance')->ID));echo wp_json_encode(array('id'=>$id,'other'=>$other,'url'=>get_permalink($id)));`));
 let browser;
 try{
 browser=await chromium.launch({headless:true,executablePath:'/usr/bin/chromium',args:['--no-sandbox']});const context=await browser.newContext();
 await context.route('https://darkred-panther-124490.hostingersite.com/wp-content/uploads/**',route=>route.fulfill({status:200,contentType:'image/png',body:fs.readFileSync('/tmp/simple-article-photo.png')}));
 const page=await context.newPage({viewport:{width:1440,height:1000}});const errors=[];page.on('pageerror',e=>errors.push(e.message));await page.goto(state.url);
 assert.equal(await page.locator('.cg-simple-article').count(),1);assert.equal(await page.locator('h1').count(),1);assert.equal(await page.locator('.cg-toc a').count(),5);assert.equal(await page.locator('.cg-breadcrumb a[href*="/exchanges/binance/"]').count(),1);
 assert.equal(await page.locator('a[href*="/go/"]').count(),0);assert.equal(await page.locator('.cg-registration-card').count(),0);assert.equal(await page.locator('.cg-review-eligibility-form').count(),0);
 const graph=JSON.parse(await page.locator('script[type="application/ld+json"]').textContent());assert.equal(graph['@graph'][0]['@type'],'Article');assert.equal(graph['@graph'][1].itemListElement[2].name,'Binance');
 assert(await page.getByText('Binance 账户安全指南 · 示例链接',{exact:true}).isVisible());
 const image=await page.locator('.cg-content img').boundingBox();assert(image.height<=680);
 assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
 await page.screenshot({path:'/tmp/simple-article-desktop.png',fullPage:true});
 await page.setViewportSize({width:390,height:844});await page.reload();assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
 const table=await page.locator('figure.wp-block-table').evaluate(el=>[el.scrollWidth,el.clientWidth]);assert(table[0]>table[1]);await page.locator('.cg-toc summary').click();assert.equal(await page.locator('.cg-toc ol').isVisible(),false);
 await page.screenshot({path:'/tmp/simple-article-mobile.png',fullPage:true});assert.deepEqual(errors,[]);
 console.log('PASS ordinary post without template/primary-platform fields: automatic layout/TOC/Schema, existing association, related links, portrait sizing, mobile tables and no unsolicited CTA');
 }finally{if(browser)await browser.close();wp('eval',`wp_delete_post(${state.id},true);wp_delete_post(${state.other},true);`);}
})().catch(e=>{console.error(e);process.exit(1)});
