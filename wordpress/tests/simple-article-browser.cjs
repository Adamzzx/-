const {chromium}=require('playwright'),{execFileSync}=require('node:child_process'),assert=require('node:assert/strict'),fs=require('node:fs');
const wp=(...a)=>execFileSync('docker',['compose','-f','wordpress/tests/compose.packages.yaml','run','--rm','-T','cli','wp',...a],{encoding:'utf8',stdio:['pipe','pipe','pipe']}).trim();
const h=(level,text)=>`<!-- wp:heading ${level===3?' {"level":3}':''} --><h${level} class="wp-block-heading">${text}</h${level}><!-- /wp:heading -->`;
const p=text=>`<!-- wp:paragraph --><p>${text}</p><!-- /wp:paragraph -->`;
const body=[
 h(2,'核心结论'),p('本页仅展示文章排版。以下章节可按文章内容增删和调整，未填写交易所数据或实测结论。'),
 h(2,'支持的地区'),p('地区与产品资格在本节说明，并在具体产品有额外限制时就近补充。来源和核验日期写在对应信息旁。'),
 h(2,'注册与 KYC'),h(3,'注册步骤'),p('按实际操作步骤组织说明，在需要的位置插入对应截图。'),h(3,'身份核验与账户安全'),p('本节可分别说明身份核验流程与账户安全设置，不承接手续费、理财等其他主题。'),
 h(2,'出入金'),h(3,'充值与提币'),p('将步骤、网络选择和到账条件放在同一小节，便于读者对照操作。'),h(3,'法币与 C2C'),p('需要涵盖的渠道由作者按实际资料添加，相关限制和费用就近说明。'),
 h(2,'手续费'),h(3,'现货手续费'),p('费用、适用账户、折扣条件、来源与核验日期在这里说明，不重复增加文末手续费章节。'),
 '<!-- wp:table --><figure class="wp-block-table"><table><thead><tr><th>项目</th><th>适用条件</th><th>来源与核验日期</th></tr></thead><tbody><tr><td>现货 Maker / Taker</td><td>由作者填写账户等级、折扣条件</td><td>由作者填写官方来源及实际核验日期</td></tr></tbody></table></figure><!-- /wp:table -->',
 h(3,'合约手续费'),p('合约费用独立说明；未核验时不填写数值，不自动推断产品可用性。'),
 h(2,'理财产品'),h(3,'产品类型与操作'),p('产品说明、申购步骤和截图在这里连续阅读，无需跳去单独的截图章节。'),
 '<!-- wp:image --><figure class="wp-block-image"><img src="https://darkred-panther-124490.hostingersite.com/wp-content/uploads/2026/10/IMG_0825-1-538x1024.png" alt="作者公开文章中的理财操作截图，供排版预览"/><figcaption>截图穿插在对应操作说明中；仅供排版预览，不代表图中产品或数值已核验。</figcaption></figure><!-- /wp:image -->',
 h(3,'赎回条件与风险'),p('说明流动性、收益变化与其他产品风险；来源和核验日期随本段资料填写。'),
 h(2,'美股支持情况'),p('根据实际资料区分具体产品类型、资产权利与地区资格；不由模板预设支持或不支持。'),
 h(2,'风险提示'),p('费用、产品状态及地区可用性可能变化。文章更新时间不等于所有数据已重新核验。')
].join('\n');
(async()=>{
 assert.equal(wp('option','get','home'),'http://localhost:8088');
 const encoded=Buffer.from(body).toString('base64');
 const state=JSON.parse(wp('eval',`wp_set_current_user(get_user_by('login','smoke-admin')->ID);$id=wp_insert_post(array('post_type'=>'post','post_status'=>'publish','post_author'=>get_current_user_id(),'post_title'=>'币安 Binance 使用评测（2026）：手续费、出入金、理财与美股交易','post_excerpt'=>'排版示意：保留原文章的大章节，用 H3 细分目录，来源、表格和截图随正文，不限定写作结构。','post_content'=>base64_decode('${encoded}')));update_post_meta($id,'_cg_related_exchanges',array(cg_exchange('binance')->ID));$other=wp_insert_post(array('post_type'=>'cg_guide','post_status'=>'publish','post_title'=>'Binance 账户安全指南 · 示例链接'));update_post_meta($other,'_cg_related_exchanges',array(cg_exchange('binance')->ID));$edge=wp_insert_post(array('post_type'=>'post','post_status'=>'publish','post_title'=>'自定义章节测试','post_content'=>'<h3>开篇子标题</h3><h2 id="same">自定义 &amp; 标题</h2><h3 id="same">相同锚点</h3><h2>目录</h2><h2>另一个主题</h2>'));echo wp_json_encode(array('id'=>$id,'other'=>$other,'edge'=>$edge,'url'=>get_permalink($id),'edgeUrl'=>get_permalink($edge),'modified'=>get_post($id)->post_modified));`));
 let browser;
 try{
 browser=await chromium.launch({headless:true,executablePath:'/usr/bin/chromium',args:['--no-sandbox']});const context=await browser.newContext({viewport:{width:1440,height:1000}});
 await context.route('https://darkred-panther-124490.hostingersite.com/wp-content/uploads/**',route=>route.fulfill({status:200,contentType:'image/png',body:fs.readFileSync('/tmp/simple-article-photo.png')}));
 const page=await context.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));await page.goto(state.url);
 assert.equal(await page.locator('.cg-simple-article').count(),1);assert.equal(await page.locator('h1').count(),1);
 const topTitles=await page.locator('.cg-toc>ol>li>a>span:last-child').allTextContents();
 assert.deepEqual(topTitles,['核心结论','支持的地区','注册与 KYC','出入金','手续费','理财产品','美股支持情况','风险提示']);
 assert.equal(await page.locator('.cg-toc a').count(),16);
 const fees=page.locator('.cg-toc>ol>li').filter({has:page.locator('a>span:last-child',{hasText:/^手续费$/})});
 assert.deepEqual(await fees.locator('ol>li>a>span:last-child').allTextContents(),['现货手续费','合约手续费']);
 assert.deepEqual(await fees.locator('ol>li>a>.cg-toc-number').allTextContents(),['5.1','5.2']);
 assert.equal(await page.locator('.cg-breadcrumb a[href*="/exchanges/binance/"]').count(),1);
 assert.equal(await page.locator('a[href*="/go/"]').count(),0);assert.equal(await page.locator('.cg-registration-card').count(),0);assert.equal(await page.locator('.cg-review-eligibility-form').count(),0);
 const graph=JSON.parse(await page.locator('script[type="application/ld+json"]').textContent());assert.equal(graph['@graph'][0]['@type'],'Article');assert.equal(graph['@graph'][1].itemListElement[2].name,'Binance');
 assert(await page.getByText('Binance 账户安全指南 · 示例链接',{exact:true}).isVisible());
 assert.equal(await page.locator('.cg-review-sources').count(),0);
 await page.locator('.cg-content img').scrollIntoViewIfNeeded();await page.locator('.cg-content img').evaluate(img=>img.decode());
 const image=await page.locator('.cg-content img').boundingBox();assert(image.height<=680);
 assert.equal(await page.locator('.cg-content img').evaluate(img=>{const preceding=[...document.querySelectorAll('.cg-content h2')].filter(h=>h.compareDocumentPosition(img)&Node.DOCUMENT_POSITION_FOLLOWING);return preceding.at(-1).textContent;}),'理财产品');
 assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
 await page.evaluate(()=>window.scrollTo(0,0));await page.screenshot({path:'/tmp/simple-article-desktop.png',fullPage:true});
 const feeLink=fees.locator('ol a').last();const targetId=(await feeLink.getAttribute('href')).slice(1);await feeLink.click();assert.equal(new URL(page.url()).hash,'#'+targetId);
 assert(Math.abs(await page.locator('#'+targetId).evaluate(el=>el.getBoundingClientRect().top)-30)<5);
 await page.setViewportSize({width:390,height:844});await page.goto(state.url);assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
 const table=await page.locator('figure.wp-block-table').evaluate(el=>[el.scrollWidth,el.clientWidth]);assert(table[0]>table[1]);
 await page.locator('.cg-content img').scrollIntoViewIfNeeded();await page.locator('.cg-content img').evaluate(img=>img.decode());await page.evaluate(()=>window.scrollTo(0,0));
 await page.screenshot({path:'/tmp/simple-article-mobile.png',fullPage:true});
 await page.locator('.cg-toc summary').focus();await page.keyboard.press('Enter');assert.equal(await page.locator('.cg-toc>ol').isVisible(),false);await page.keyboard.press('Enter');assert.equal(await page.locator('.cg-toc>ol').isVisible(),true);
 const mobileLink=page.locator('.cg-toc a').filter({hasText:'合约手续费'});await mobileLink.click();assert.equal(new URL(page.url()).hash,'#'+targetId);
 assert(Math.abs(await page.locator('#'+targetId).evaluate(el=>el.getBoundingClientRect().top)-30)<5);
 // Displaying and navigating must not rewrite the saved body, association or timestamps.
 const unchanged=JSON.parse(wp('eval',`$p=get_post(${state.id});echo wp_json_encode(array('content'=>$p->post_content,'modified'=>$p->post_modified,'relations'=>get_post_meta($p->ID,'_cg_related_exchanges',true),'sources'=>get_post_meta($p->ID,'_cg_review_sources',true),'template'=>get_page_template_slug($p->ID)));`));
 assert.equal(unchanged.content,body);assert.equal(unchanged.modified,state.modified);assert.equal(unchanged.sources,'');assert.equal(unchanged.template,'');assert.equal(unchanged.relations.length,1);
 await page.goto(state.edgeUrl);assert.deepEqual(await page.locator('.cg-toc>ol>li>a>span:last-child').allTextContents(),['开篇子标题','自定义 & 标题','另一个主题']);
 assert.equal(await page.locator('.cg-content h3#same-2').count(),1);assert.equal(await page.locator('.cg-toc>ol>li').nth(1).locator('ol a').getAttribute('href'),'#same-2');assert.equal(await page.locator('.cg-toc a').count(),4);
 wp('post','update',String(state.edge),'--post_content=<p>没有标题的短文，保留原生段落。</p>');await page.reload();assert.equal(await page.locator('.cg-toc').count(),0);assert.equal(await page.locator('.cg-content h2').count(),0);
 assert.deepEqual(errors,[]);
 console.log('PASS native article: independent H2 sections, nested numbered H3, desktop/mobile anchor navigation and keyboard collapse, inline images/sources, no body rewrite, custom headings/duplicate IDs/no-heading fallback, Schema/related links and closed CTA');
 }finally{if(browser)await browser.close();wp('eval',`wp_delete_post(${state.id},true);wp_delete_post(${state.other},true);wp_delete_post(${state.edge},true);`);}
})().catch(e=>{console.error(e);process.exit(1)});
