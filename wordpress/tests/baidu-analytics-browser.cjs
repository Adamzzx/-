// Isolated mock only: no WordPress database, real Baidu service or mainland network is used.
const { chromium } = require('playwright');
const { readFileSync } = require('node:fs');
const assert = require('node:assert/strict');
const source = readFileSync('wordpress/plugin/exchange-guide/assets/baidu-analytics.js', 'utf8');
const id = 'a'.repeat(32), other = 'b'.repeat(32), origin = 'https://local-baidu.example';
(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: '/usr/bin/chromium', args: ['--no-sandbox'] });
  try {
    const context = await browser.newContext(); let loads = 0;
    await context.route('https://hm.baidu.com/**', route => { loads++; return route.fulfill({ contentType: 'text/javascript', body: 'window.mockBaiduLoaded = true;' }); });
    await context.route(origin + '/**', route => route.fulfill({ contentType: 'text/html', body: '<html><head></head><body></body></html>' }));
    const page = await context.newPage(); const errors = []; page.on('pageerror', error => errors.push(error.message));
    async function reset(config, existing = '') {
      await page.goto(origin + '/article-path/?should-not-enter-event=private');
      if (existing) { await page.addScriptTag({ url: 'https://hm.baidu.com/hm.js?' + existing }); }
      await page.evaluate(value => { window.cgBaiduAnalytics = value; }, config);
      await page.addScriptTag({ content: source });
    }
    const config = { siteId: id, article: '币安-vs-okx-2026', articleId: 57, reviewExchange: 'binance', sitePath: '/' };
    await reset(null); assert.equal(await page.locator('script[src*="hm.baidu.com"]').count(), 0); assert.equal(loads, 0);
    await reset({ ...config, siteId: 'invalid' }); assert.equal(loads, 0); console.log('PASS blank/invalid configuration does not load a tracking script');
    await reset(config); await page.waitForFunction(() => window.mockBaiduLoaded);
    assert.equal(loads, 1); assert.equal(await page.locator('script[data-cg-baidu]').evaluate(script => script.async), true);
    await page.addScriptTag({ content: source }); assert.equal(await page.locator('script[src*="hm.baidu.com"]').count(), 1);
    console.log('PASS asynchronous mock hm.js loads exactly once');
    await page.evaluate(() => {
      document.body.innerHTML = '<aside class="cg-registration-sidebar"><a id="sidebar" target="_blank" href="/go/binance/?ref=secret">Binance</a></aside><div class="cg-home-registration"><a id="home" href="/?cg_go=okx&token=secret">OKX</a></div><main class="cg-article-content"><a id="article" href="/go/bitget/">Bitget</a><a id="external" href="https://www.binance.com/en/fee">Official source</a><a id="retired" href="/go/gate/">Gate</a><a id="foreign" href="https://external.example/go/bybit/">External</a></main><div class="cg-review-eligibility"></div>';
      // Test-only navigation suppression runs after the analytics capture listener.
      document.addEventListener('click', event => event.preventDefault());
      document.addEventListener('auxclick', event => event.preventDefault());
    });
    await page.locator('#sidebar').click(); await page.locator('#home').click(); await page.locator('#article').click();
    await page.locator('#external').click(); await page.locator('#retired').click(); await page.locator('#foreign').click();
    await page.evaluate(() => {
      const region = document.querySelector('.cg-review-eligibility');
      region.innerHTML = '<a id="dynamic" target="_blank" href="/go/review/57/?country=US&expires=123&signature=secret">Qualified link</a><a id="wrong-review" href="/go/review/58/?signature=secret">Unrelated</a><a id="fallback-review" href="/?cg_review_go=57&country=GB&signature=secret">Fallback</a>';
    });
    await page.locator('#dynamic').click(); await page.locator('#wrong-review').click(); await page.locator('#fallback-review').click();
    await page.locator('#article').dispatchEvent('auxclick', { button: 1 });
    const events = await page.evaluate(() => window._hmt);
    assert.equal(events.length, 6); assert(events.every(event => event[0] === '_trackEvent' && event[1] === 'exchange_outbound' && event[2] === 'click'));
    assert(events[0][3].includes('exchange=binance;article=币安-vs-okx-2026;position=sidebar'));
    assert(events[1][3].includes('exchange=okx;article=币安-vs-okx-2026;position=home-registration'));
    assert(events[2][3].includes('exchange=bitget;article=币安-vs-okx-2026;position=article-cta'));
    assert(events[3][3].includes('position=article-eligibility')); assert(events[4][3].includes('position=article-eligibility'));
    assert(!JSON.stringify(events).match(/secret|signature|country|registration-success|private|should-not-enter-event/));
    console.log('PASS promotion click platform/article/position fields, middle click, dynamic qualified link, no destination/query or fabricated registrations');
    await reset(config, id); const sameBefore = loads; assert.equal(await page.locator('script[src*="hm.baidu.com"]').count(), 1); assert.equal(await page.evaluate(() => window.cgBaiduStatus), 'existing-script'); assert.equal(loads, sameBefore);
    await reset(config, other); assert.equal(await page.locator('script[src*="hm.baidu.com"]').count(), 1); assert.equal(await page.evaluate(() => window.cgBaiduStatus), 'conflicting-site-id'); assert.equal(await page.evaluate(() => typeof window._hmt), 'undefined');
    console.log('PASS same-ID script reused; different-ID script blocks our additional script and event listener');
    await reset({ ...config, article: '', articleId: 0, reviewExchange: '' });
    await page.evaluate(() => { document.body.innerHTML = '<a href="/go/bybit/" id="list">Bybit</a>'; document.addEventListener('click', e => e.preventDefault()); });
    await page.locator('#list').click(); const fallback = await page.evaluate(() => window._hmt[0][3]); assert.equal(fallback, 'exchange=bybit;article=/article-path/;position=content');
    assert.deepEqual(errors, []); console.log('PASS nonarticle page path strips query; no browser runtime errors');
    console.log('MOCK ONLY: real Baidu visits/events receipt and mainland connectivity remain untested.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
