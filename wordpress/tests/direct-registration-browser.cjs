const {chromium}=require('playwright');const assert=require('node:assert/strict');
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.CG_CHROMIUM_PATH||'/usr/bin/chromium',args:['--no-sandbox']});
 try{
 const context=await browser.newContext();
 // Verify navigation without contacting exchanges or interacting with actual registration forms.
 await context.route('https://**/*',route=>route.fulfill({status:200,contentType:'text/html',body:'<h1>Local navigation verification</h1>'}));
 const page=await context.newPage();await page.goto('http://localhost:8088/');
 const urls=['https://www.binance.com/en/register?ref=smoke','https://www.okx.com/join','https://www.bitget.com/register','https://www.bybit.com/en/register'];
 for(let i=0;i<4;i++){
  const card=page.locator('.cg-home-registration .cg-registration-card').nth(i);
  assert.equal(await card.getAttribute('target'),'_blank');assert((await card.getAttribute('rel')).includes('noopener'));
  const opened=context.waitForEvent('page');await card.click();const popup=await opened;
  await popup.waitForURL(urls[i],{waitUntil:'domcontentloaded'});assert.equal(popup.url(),urls[i]);
  assert.equal(page.url(),'http://localhost:8088/');await popup.close();
 }
 console.log('PASS four homepage banners open centralized redirects in new tabs; original page remains unchanged');
 await page.goto('http://localhost:8088/reviews/smoke-published-review/');
 assert.equal(await page.locator('.cg-registration-sidebar a[target="_blank"][href*="/go/"]').count(),4);
 const popupEvent=context.waitForEvent('page');await page.locator('.cg-registration-sidebar .cg-registration-card').nth(1).click();const popup=await popupEvent;
 await popup.waitForURL(urls[1],{waitUntil:'domcontentloaded'});assert(page.url().includes('/reviews/'));await popup.close();
 console.log('PASS article sidebar also opens official registration in a new tab');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
