const {chromium}=require('playwright'),{execFileSync}=require('node:child_process'),assert=require('node:assert/strict');
const wp=(...a)=>execFileSync('docker',['compose','-f','wordpress/tests/compose.packages.yaml','run','--rm','-T','cli','wp',...a],{encoding:'utf8',stdio:['pipe','pipe','pipe']}).trim();
(async()=>{
 assert.equal(wp('option','get','home'),'http://localhost:8088');
 const state=JSON.parse(wp('eval',`wp_set_current_user(get_user_by('login','smoke-admin')->ID);$content=cg_review_pattern().'<!-- wp:heading {"anchor":"same"} --><h2 id="same">Repeated heading</h2><!-- /wp:heading --><!-- wp:heading {"anchor":"same"} --><h2 id="same">Repeated heading</h2><!-- /wp:heading --><!-- wp:shortcode -->[cg_registration_cards]<!-- /wp:shortcode -->';$id=wp_insert_post(array('post_type'=>'post','post_status'=>'publish','post_author'=>get_current_user_id(),'post_title'=>'币安 Binance 评测 2026：手续费、安全性、交易体验、理财与美股代币全面分析','post_excerpt'=>'Local layout acceptance excerpt; no actual exchange test claims.','post_content'=>$content));update_post_meta($id,'_wp_page_template','templates/exchange-review.php');update_post_meta($id,'_cg_review_exchange','binance');update_post_meta($id,'_cg_reviewed',wp_date('Y-m-d'));update_post_meta($id,'_cg_review_description','Local SEO acceptance description');$related=wp_insert_post(array('post_type'=>'cg_compare','post_status'=>'publish','post_title'=>'Local Binance vs Bybit link fixture'));update_post_meta($related,'_cg_related_exchanges',array(cg_exchange('binance')->ID,cg_exchange('bybit')->ID));$upload=wp_upload_bits('local-review-image.png',null,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aR1sAAAAASUVORK5CYII='));$image=wp_insert_attachment(array('post_mime_type'=>'image/png','post_title'=>'Local image test','post_excerpt'=>'Local fixture, not an exchange screenshot'),$upload['file']);update_post_meta($image,'_wp_attachment_image_alt','Local review image proof');require_once ABSPATH.'wp-admin/includes/image.php';wp_update_attachment_metadata($image,wp_generate_attachment_metadata($image,$upload['file']));set_post_thumbnail($id,$image);echo wp_json_encode(array('id'=>$id,'related'=>$related,'image'=>$image,'url'=>get_permalink($id)));`));
 let browser;
 try{
 browser=await chromium.launch({headless:true,executablePath:'/usr/bin/chromium',args:['--no-sandbox']});const context=await browser.newContext();const page=await context.newPage({viewport:{width:1440,height:1000}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto(state.url);assert.equal(await page.locator('.cg-review').count(),1);assert.equal(await page.locator('h1').count(),1);
 assert.equal(await page.locator('a[href*="/go/"]').count(),0);assert.equal(await page.locator('.cg-registration-sidebar').count(),0);assert.equal(await page.locator('.cg-review-eligibility-form').count(),0);
 const headings=await page.locator('.cg-review-body .cg-content h2,.cg-review-body .cg-content h3').evaluateAll(n=>n.map(x=>x.id));assert.equal(new Set(headings).size,headings.length);assert(headings.includes('same-2'));
 assert.equal(await page.locator('.cg-toc a').count(),headings.length);assert(await page.getByText('Local Binance vs Bybit link fixture',{exact:true}).isVisible());
 const graph=await page.locator('script[type="application/ld+json"]').textContent();const parsed=JSON.parse(graph);assert.equal(parsed['@graph'][0]['@type'],'Article');assert.equal(parsed['@graph'][1]['@type'],'BreadcrumbList');assert(!graph.includes('aggregateRating'));assert(parsed['@graph'][0].image.length===1);assert.equal(await page.locator('.cg-review-cover img').getAttribute('alt'),'Local review image proof');assert(await page.getByText('Local fixture, not an exchange screenshot',{exact:true}).isVisible());
 assert.equal(await page.locator('meta[name=description]').getAttribute('content'),'Local SEO acceptance description');
 await page.setViewportSize({width:390,height:844});await page.reload();assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
 const scroll=await page.locator('.cg-review .cg-table-wrap').first().evaluate(el=>({wide:el.scrollWidth,client:el.clientWidth}));assert(scroll.wide>scroll.client);
 console.log('PASS native review layout, unique TOC, public related links, Schema/meta and mobile table scrolling; no unconditional CTA');
 wp('eval',`update_post_meta(${state.id},'_cg_review_policy',array('enabled'=>true,'product'=>'Local test product','countries'=>array('SG'),'terms'=>'https://www.binance.com/en/terms','verified'=>wp_date('Y-m-d'),'expiry'=>'2030-01-01'));`);
 await page.reload();const form=page.locator('.cg-review-eligibility-form');await form.locator('input[name=country]').fill('US');await form.locator('input[name=confirmed]').check();await form.locator('button').click();await page.getByText('此地区或产品暂无可展示的推荐入口。',{exact:true}).waitFor();assert.equal(await form.locator('a.cg-button').count(),0);
 await form.locator('input[name=country]').fill('SG');await form.locator('button').click();const link=form.locator('a.cg-button');await link.waitFor();assert.equal(await link.getAttribute('target'),'_blank');
 const signed=await link.getAttribute('href');const request=await context.request.get(signed,{maxRedirects:0});assert.equal(request.status(),302);assert.equal(request.headers().location,'https://www.binance.com/en/register?ref=smoke');
 const tampered=new URL(signed);tampered.searchParams.set('country','US');assert.equal((await context.request.get(tampered.href,{maxRedirects:0})).status(),403);
 const missing=new URL(signed);missing.searchParams.delete('signature');assert.equal((await context.request.get(missing.href,{maxRedirects:0})).status(),403);
 console.log('PASS explicit eligibility, blocked region, signed new-tab CTA and direct-access/tampering rejection');
 await page.goto('http://localhost:8088/wp-login.php');await context.request.post('http://localhost:8088/wp-login.php',{form:{log:'smoke-admin',pwd:'local-test-only',testcookie:'1',redirect_to:'http://localhost:8088/wp-admin/'}});
 await page.goto('http://localhost:8088/wp-admin/post.php?post='+state.id+'&action=edit',{waitUntil:'domcontentloaded'});
 await page.waitForFunction(()=>window.wp?.data?.select('core/block-editor')?.getBlocks().some(b=>b.name==='cg/review-table'));
 assert.equal(await page.evaluate(()=>wp.data.select('core/editor').getEditedPostAttribute('template')),'templates/exchange-review.php');
 const registered=await page.evaluate(()=>wp.blocks.getBlockType('cg/review-table').title);assert.equal(registered,'评测核验数据表');
 const frame=page.frameLocator('iframe[name="editor-canvas"]');const rendered=frame.getByText('数值或产品说明',{exact:true});
 if(await page.locator('iframe[name="editor-canvas"]').count()){await rendered.first().waitFor();}else{await page.getByText('数值或产品说明',{exact:true}).first().waitFor();}
 const canvas=(await page.locator('iframe[name="editor-canvas"]').count())?frame:page;
 await canvas.getByLabel('数值或产品说明',{exact:true}).first().fill('LOCAL EDIT SAVE');
 await canvas.getByLabel('HTTPS 来源链接',{exact:true}).first().fill('https://www.binance.com/en/fee');
 await canvas.getByLabel('来源名称',{exact:true}).first().fill('Local source editing proof');
 const today=wp('eval',"echo wp_date('Y-m-d');");await canvas.getByLabel('实际核验日期',{exact:true}).first().fill(today);
 await canvas.getByLabel('核验状态',{exact:true}).first().selectOption('verified');
 await page.evaluate(async()=>{await wp.data.dispatch('core/editor').savePost();});
 await page.goto(state.url);await page.getByText('LOCAL EDIT SAVE',{exact:true}).waitFor();
 assert.deepEqual(errors,[]);console.log('PASS actual native editor edits and saves verification block values, source/date/state; public renderer reflects saved data');
 }finally{if(browser)await browser.close();wp('eval',`wp_delete_post(${state.id},true);wp_delete_post(${state.related},true);wp_delete_attachment(${state.image},true);`);}
})().catch(e=>{console.error(e);process.exit(1)});
