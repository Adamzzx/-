(function () {
  'use strict';
  var config = window.cgBaiduAnalytics;
  if (!config || !/^[a-f0-9]{32}$/i.test(config.siteId || '') || window.cgBaiduStatus) return;
  var siteId = config.siteId.toLowerCase();
  var present = false;
  var conflict = false;
  document.querySelectorAll('script[src]').forEach(function (script) {
    try {
      var url = new URL(script.src, location.href);
      if (url.hostname.toLowerCase() !== 'hm.baidu.com' || url.pathname !== '/hm.js') return;
      if (url.search.slice(1).toLowerCase() === siteId) present = true;
      else conflict = true;
    } catch (error) { /* An unrelated invalid script URL must not stop the page. */ }
  });
  if (conflict) { window.cgBaiduStatus = 'conflicting-site-id'; return; }
  window._hmt = window._hmt || [];
  if (typeof window._hmt.push !== 'function') { window.cgBaiduStatus = 'invalid-event-queue'; return; }
  window.cgBaiduStatus = present ? 'existing-script' : 'loading';
  if (!present) {
    var script = document.createElement('script');
    script.async = true;
    script.src = 'https://hm.baidu.com/hm.js?' + siteId;
    script.dataset.cgBaidu = '1';
    script.onload = function () { window.cgBaiduStatus = 'loaded'; };
    script.onerror = function () { window.cgBaiduStatus = 'load-error'; };
    (document.head || document.documentElement).appendChild(script);
  }
  var platforms = ['binance', 'okx', 'bitget', 'bybit'];
  var root = String(config.sitePath || '/').replace(/\/+$/, '') + '/';
  function platformFor(link) {
    var url;
    try { url = new URL(link.href, location.href); } catch (error) { return ''; }
    if (url.origin !== location.origin || !url.pathname.startsWith(root)) return '';
    var path = url.pathname.slice(root.length);
    var direct = /^go\/(binance|okx|bitget|bybit)\/?$/.exec(path);
    if (direct) return direct[1];
    if (path === '' && platforms.indexOf(url.searchParams.get('cg_go')) !== -1) return url.searchParams.get('cg_go');
    var review = /^go\/review\/(\d+)\/?$/.exec(path);
    var reviewId = review ? Number(review[1]) : (path === '' ? Number(url.searchParams.get('cg_review_go')) : 0);
    return reviewId > 0 && reviewId === Number(config.articleId) && platforms.indexOf(config.reviewExchange) !== -1 ? config.reviewExchange : '';
  }
  function positionFor(link) {
    if (link.closest('.cg-review-eligibility')) return 'article-eligibility';
    if (link.closest('.cg-registration-sidebar, .cg-feed-sidebar')) return 'sidebar';
    if (link.closest('.cg-home-registration, .cg-feed-registration')) return 'home-registration';
    if (link.closest('.cg-registration-page')) return 'registration-page';
    if (link.closest('.cg-affiliate, .cg-article-content, .entry-content')) return 'article-cta';
    return 'content';
  }
  function clean(value, limit) {
    // No query strings, affiliate destination, country, signature or visitor identifiers.
    value = String(value || '').split('?')[0].split('#')[0];
    try { value = decodeURIComponent(value); } catch (error) { /* Keep malformed public slugs as text. */ }
    return value.replace(/[\u0000-\u001f;=]/g, '-').slice(0, limit);
  }
  function record(event) {
    if ((event.type === 'click' && event.button !== 0) || (event.type === 'auxclick' && event.button !== 1)) return;
    var target = event.target instanceof Element ? event.target : event.target && event.target.parentElement;
    var link = target && target.closest('a[href]');
    if (!link) return;
    var platform = platformFor(link);
    if (!platform) return;
    var article = config.article || location.pathname;
    var label = 'exchange=' + platform + ';article=' + clean(article, 160) + ';position=' + positionFor(link);
    window._hmt.push(['_trackEvent', 'exchange_outbound', 'click', label]);
    // Navigation is intentionally untouched. A click is never a registration-success event.
  }
  document.addEventListener('click', record, true);
  document.addEventListener('auxclick', record, true);
}());
