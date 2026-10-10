// Runs only against the disposable local WordPress smoke-test installation.
const {chromium}=require('playwright');
const {execFileSync}=require('node:child_process');
const assert=require('node:assert/strict');
const base=['compose','-f','wordpress/tests/compose.packages.yaml','run','--rm','-T','cli','wp'];
function wp(...args){return execFileSync('docker',[...base,...args],{encoding:'utf8',stdio:['pipe','pipe','pipe']}).trim();}
(async()=>{
 assert.equal(wp('option','get','home'),'http://localhost:8088');
 const ids=JSON.parse(wp('eval',"echo wp_json_encode(get_posts(array('post_type'=>'cg_exchange','post_status'=>'publish','numberposts'=>-1,'fields'=>'ids')));"));
 const locations=wp('eval',"echo wp_json_encode(get_theme_mod('nav_menu_locations',array()));");
 let b;
 try{
 b=await chromium.launch({headless:true,executablePath:process.env.CG_CHROMIUM_PATH||'/usr/bin/chromium',args:['--no-sandbox']});
 const p=await b.newPage();
 const errors=[];p.on('pageerror',e=>errors.push(e.message));
 const expected=['Binance','OKX','Bitget','Bybit'];
 async function verify(mode){
  await p.setViewportSize({width:1440,height:1000});await p.goto('http://localhost:8088/');
  assert.equal(await p.locator('#cg-primary-nav > .cg-menu > li').count(),3,mode+' three primary headings');
  assert.equal(await p.locator('#cg-primary-nav > .cg-menu > li > button svg').count(),3,mode+' visible dropdown arrows');
  const submenuLabels=[['Binance','OKX','Bitget','Bybit','交易所对比'],['交易指南','出入金指南'],['新手入门','钱包与资产安全','出入金','加密货币理财']];
  for(let i=0;i<3;i++){
   const actual=await p.locator('#cg-primary-nav > .cg-menu > li > .sub-menu').nth(i).locator(':scope > li > a, :scope > li > .cg-nav-label').allTextContents();
   assert.deepEqual(actual.slice(0,submenuLabels[i].length),submenuLabels[i],mode+' confirmed submenu labels/order');
  }

  assert.deepEqual(await p.locator('.cg-home-registration .cg-registration-label').allTextContents(),expected,mode+' all four banners');
  const bannerBox=await p.locator('.cg-home-registration').boundingBox();assert(bannerBox.y<300,mode+' banners near top');
  for(let i=0;i<3;i++){
   const button=p.locator('#cg-primary-nav > .cg-menu > li > button').nth(i);
   await button.click();assert.equal(await button.getAttribute('aria-expanded'),'true');
   assert(await p.locator('#cg-primary-nav > .cg-menu > li > .sub-menu').nth(i).isVisible(),mode+' desktop submenu '+i);
   await button.click();assert.equal(await button.getAttribute('aria-expanded'),'false');
   assert.equal(await p.locator('#cg-primary-nav > .cg-menu > li > .sub-menu').nth(i).isVisible(),false,mode+' click arrow again closes submenu');
   await p.keyboard.press('Escape');await p.locator('h1').click();
  }
  assert.equal(await p.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
  await p.screenshot({path:'/tmp/cg-'+mode+'-desktop.png',fullPage:false});
  await p.setViewportSize({width:390,height:844});await p.reload();
  await p.locator('.cg-menu-toggle').click();assert(await p.locator('#cg-primary-nav').isVisible());
  const first=p.locator('.cg-submenu-toggle').first();await first.click();assert(await p.locator('.sub-menu').first().isVisible());
  await first.click();assert.equal(await p.locator('.sub-menu').first().isVisible(),false);
  await p.locator('.cg-menu-toggle').click();assert.equal(await p.locator('#cg-primary-nav').isVisible(),false);
  assert.equal(await p.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
  await p.screenshot({path:'/tmp/cg-'+mode+'-mobile.png',fullPage:false});
  console.log('PASS '+mode+': three arrows, dropdowns, four near-top banners, mobile navigation and overflow');
 }
 await verify('initialized');
 wp('eval',"set_theme_mod('nav_menu_locations',array());");
 wp('eval',"foreach("+JSON.stringify(ids).replace('[','array(').replace(']',')')+" as $id){wp_update_post(array('ID'=>$id,'post_status'=>'draft'));}");
 await verify('uninitialized');
 for(const slug of ['binance','okx','bitget','bybit']){
  const response=await p.goto('http://localhost:8088/register/'+slug+'/');assert.equal(response.status(),200);
  assert(await p.getByText('该平台的推荐入口尚未配置', {exact:false}).isVisible());
  assert.equal(await p.locator('a[href*="/go/"]').count(),0);
 }
 console.log('PASS missing records: all registration pages work without invented referral offers');
 wp('plugin','deactivate','exchange-guide');
 await verify('theme-only');
 await p.locator('.cg-home-registration a').first().click();assert(p.url().endsWith('#cg-registration-conditions'));
 console.log('PASS theme-only banner points to available registration conditions');
 assert.deepEqual(errors,[],'browser has no JavaScript errors');
 }finally{
  wp('plugin','activate','exchange-guide');
  wp('eval',"foreach("+JSON.stringify(ids).replace('[','array(').replace(']',')')+" as $id){wp_update_post(array('ID'=>$id,'post_status'=>'publish'));}");
  wp('eval',"set_theme_mod('nav_menu_locations',json_decode('"+locations+"',true));");
  if(b)await b.close();
 }
})().catch(e=>{console.error(e);process.exit(1)});
