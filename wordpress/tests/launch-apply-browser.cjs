// Destructive integration fixture: root must restore the private local SQL snapshot afterwards.
// Never run against production. No real statistics ID, remote account or deployment is used.
const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const { randomBytes } = require('node:crypto');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const origin = 'http://localhost:8088';
const wp = (...args) => execFileSync('docker', ['compose','-f','wordpress/tests/compose.packages.yaml','run','--rm','-T','cli','wp',...args], {encoding:'utf8',stdio:['pipe','pipe','pipe']}).trim();
(async () => {
  assert.equal(process.env.CG_LAUNCH_DISPOSABLE_APPLY, '1', 'Requires explicit disposable apply mode and a private database backup');
  assert.equal(wp('option','get','home'), origin, 'Local installation only');
  const login='launch-browser-'+randomBytes(5).toString('hex'), password=randomBytes(24).toString('hex');
  const id=wp('user','create',login,login+'@example.invalid','--role=administrator','--user_pass='+password,'--porcelain');
  const baseline=JSON.parse(wp('eval',`echo wp_json_encode(array('front'=>get_option('page_on_front'),'mode'=>get_option('show_on_front'),'rows'=>cg_launch_cleanup_plan()));`));
  let browser;
  try {
    browser=await chromium.launch({headless:true,executablePath:'/usr/bin/chromium',args:['--no-sandbox']});
    const context=await browser.newContext({viewport:{width:1440,height:1000}}), page=await context.newPage(), errors=[];
    page.on('pageerror',e=>errors.push(e.message));
    const denied=await context.request.post(origin+'/wp-admin/admin-post.php',{form:{action:'cg_launch_cleanup'}});assert.equal(denied.status(),400);
    await page.goto(origin+'/wp-login.php');await context.request.post(origin+'/wp-login.php',{form:{log:login,pwd:password,testcookie:'1',redirect_to:origin+'/wp-admin/'}});
    await page.goto(origin+'/wp-admin/admin.php?page=cg-launch-cleanup');assert.equal(await page.locator('h1').innerText(),'上线整改');
    const nonce=await page.locator('input[name="cg_launch_cleanup_nonce"]').inputValue();
    const noBackup=await context.request.post(origin+'/wp-admin/admin-post.php',{form:{action:'cg_launch_cleanup',cg_launch_cleanup_nonce:nonce}});assert.equal(noBackup.status(),400);assert((await noBackup.text()).includes('完整文件与数据库备份'));
    const badNonce=await context.request.post(origin+'/wp-admin/admin-post.php',{form:{action:'cg_launch_cleanup',cg_launch_cleanup_nonce:'invalid',cg_launch_backup_confirmed:'1'}});assert.equal(badNonce.status(),403);
    const applied=await context.request.post(origin+'/wp-admin/admin-post.php',{form:{action:'cg_launch_cleanup',cg_launch_cleanup_nonce:nonce,cg_launch_backup_confirmed:'1'}});assert.equal(applied.status(),200);
    const result=JSON.parse(wp('eval',`$logs=get_option('cg_launch_cleanup_log');echo wp_json_encode(end($logs));`));assert.equal(result.backup_confirmed,true);assert.equal(result.errors.length,0);
    for(const row of result.rows){if(row.result==='trashed')assert.equal(wp('post','get',String(row.id),'--field=post_status'),'trash');if(row.result==='deleted_empty_term')assert.equal(wp('eval',`echo term_exists(${row.id},'cg_topic')?'exists':'gone';`),'gone');}
    assert.equal(wp('option','get','page_on_front'),String(baseline.front));assert.equal(wp('option','get','show_on_front'),baseline.mode);
    await page.goto(origin+'/wp-admin/admin.php?page=cg-launch-cleanup');assert((await page.locator('body').innerText()).includes('最近一次执行记录'));assert((await page.locator('body').innerText()).includes('编辑'));
    await page.goto(origin+'/wp-admin/admin.php?page=cg-baidu-analytics');assert((await page.locator('body').innerText()).includes('未启用'));assert.equal(await page.locator('#cg-baidu-code').inputValue(),'');
    const clicksBefore=wp('eval',`$p=cg_exchange('binance');echo $p?(string)get_post_meta($p->ID,'_cg_clicks',true):'';`);
    const adminOutbound=await context.request.get(origin+'/go/binance/',{maxRedirects:0});assert.equal(adminOutbound.status(),302);assert.equal(wp('eval',`$p=cg_exchange('binance');echo $p?(string)get_post_meta($p->ID,'_cg_clicks',true):'';`),clicksBefore,'administrator checks do not inflate the existing aggregate click counter');
    const fixture=JSON.parse(wp('eval',`$id=wp_insert_post(array('post_type'=>'post','post_status'=>'publish','post_author'=>${id},'post_title'=>'本地长标题布局验收：钱包安全、手续费与资产风险的中文阅读测试','post_excerpt'=>'仅用于本地布局验证，不含平台费率、收益或实际测试结果。','post_content'=>'<!-- wp:heading --><h2>使用流程</h2><!-- /wp:heading --><!-- wp:paragraph --><p>本地布局测试。</p><!-- /wp:paragraph --><!-- wp:table --><figure class="wp-block-table"><table><tbody><tr><td>本地表格列一</td><td>本地表格列二</td><td>本地表格列三</td><td>本地表格列四</td><td>本地表格列五</td></tr></tbody></table></figure><!-- /wp:table -->'));update_post_meta($id,'_wp_page_template','templates/simple-article.php');echo wp_json_encode(array('id'=>$id,'url'=>get_permalink($id)));`));
    const anonymous=await browser.newContext(), anon=await anonymous.newPage();anon.on('pageerror',e=>errors.push(e.message));
    for(const width of [1440,1024,390,320]){
      await anon.setViewportSize({width,height:1000});const response=await anon.goto(origin+'/');assert.equal(response.status(),200);
      assert.equal(await anon.locator('script[src*="hm.baidu.com"],script[src*="cdn-reach.hostinger.com"]').count(),0);
      assert.equal(await anon.locator('.cg-brand').getAttribute('href'),origin+'/');
      const menu=anon.locator('#cg-primary-nav');
      if(width<=850){await anon.locator('.cg-menu-toggle').click();assert.equal(await anon.locator('.cg-menu-toggle').getAttribute('aria-expanded'),'true');}
      const roots=menu.locator('.cg-menu > li');assert.deepEqual(await roots.locator(':scope > a').allTextContents(),['交易所','加密货币','美股']);assert.equal(await roots.nth(2).locator('button').count(),0);
      for(const index of [0,1]){const toggle=roots.nth(index).locator(':scope > button');await toggle.click();assert.equal(await toggle.getAttribute('aria-expanded'),'true');assert(await roots.nth(index).locator(':scope > .sub-menu').isVisible());await toggle.click();}
      if(width<=850)await anon.locator('.cg-menu-toggle').click();
      assert(await anon.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'homepage horizontal overflow at '+width);
      if(width===1440||width===390)await anon.screenshot({path:'wordpress/dist/launch-1.14.0-'+width+'.png',fullPage:false});
      await anon.goto(fixture.url);assert.equal(await anon.locator('h1').count(),1);assert(await anon.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'article horizontal overflow at '+width);
      await anon.goto(origin+'/topics/crypto/yield-products/');assert.equal(await anon.locator('.cg-registration-card').count(),4);assert.equal(await anon.locator('link[rel="canonical"]').getAttribute('href'),origin+'/topics/crypto/yield-products/');
    }
    for(const row of result.rows){if(row.result==='trashed'){const response=await anon.goto(origin+row.path);assert.equal(response.status(),404,'trashed object is a true404: '+row.path);}}
    const homeAlias=await anon.request.get(origin+'/home/',{maxRedirects:0});const homeRow=result.rows.find(row=>row.slug==='home');if(homeRow.result==='kept_internal_alias_to_root'){assert.equal(homeAlias.status(),301);assert.equal(homeAlias.headers().location,origin+'/');}
    await anon.goto(origin+'/category/uncategorized/');assert((await anon.locator('meta[name="robots"]').getAttribute('content')).includes('noindex'));
    const helpers=result.helpers;for(const step of Object.values(helpers))assert.equal(step.status,'completed');
    const drafts=helpers.cg_launch_prepare_page_drafts.result;for(const draft of Object.values(drafts))assert.notEqual(draft.status,'publish');
    const second=JSON.parse(wp('eval',`wp_set_current_user(${id});echo wp_json_encode(cg_launch_apply_cleanup(true));`));assert.equal(second.errors.length,0);assert(!second.rows.some(row=>row.result==='trashed'||row.result==='deleted_empty_term'),'controlled apply is idempotent');
    wp('eval',`cg_setup_pages(false);`);for(const slug of ['mexc','gate'])assert.equal(wp('eval',`echo cg_exchange('${slug}')?'exists':'absent';`),'absent','initializer does not recreate obsolete platform');
    assert.deepEqual(errors,[]);fs.writeFileSync('wordpress/audits/launch-local-apply-20261020.json',JSON.stringify({environment:'local disposable actual ZIP installation, never production',widths:[1440,1024,390,320],rows:result.rows.map(({path,action,result})=>({path,action,result})),helpers:Object.fromEntries(Object.entries(helpers).map(([key,value])=>[key,value.status])),analytics:'disabled; no real code provided',mainlandAndBaiduReceipt:'not tested; requires real code and account',errors},null,2)+'\n');
    console.log('PASS actual admin POST nonce/backup gates, safe apply and private report, draft edit entries, four widths/nav/dropdowns/no overflow, public404, default analytics absent, repeated apply and no seed recreation');
  } finally {if(browser)await browser.close();}
})().catch(error=>{console.error(error);process.exit(1);});
