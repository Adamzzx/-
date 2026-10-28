(function () {
  'use strict';
  var config = window.cgReferralClicks;
  if (!config || window.cgReferralClicksReady) return;
  window.cgReferralClicksReady = true;
  var handled = new WeakSet();
  var tokenPromise;
  var tokenExpires = 0;
  var root = String(config.sitePath || '/').replace(/\/+$/, '') + '/';
  function targetFor(link) {
    var url;
    try { url = new URL(link.href, location.href); } catch (error) { return null; }
    if (url.origin !== location.origin || !url.pathname.startsWith(root)) return null;
    var path = url.pathname.slice(root.length);
    if (/^go\/(binance|okx|bitget|bybit)\/?$/.test(path) || /^go\/review\/[0-9]+\/?$/.test(path)) return url.href;
    if (path === '' && (/^(binance|okx|bitget|bybit)$/.test(url.searchParams.get('cg_go') || '') || /^[1-9][0-9]*$/.test(url.searchParams.get('cg_review_go') || ''))) return url.href;
    return null;
  }
  function token() {
    // Prepare a noncounting proof independently of the cached page. Server time avoids client-clock errors.
    if (!tokenPromise || Date.now() >= tokenExpires) {
      tokenExpires = Date.now() + 5 * 60 * 1000;
      tokenPromise = fetch(config.tokenUrl, { credentials: 'same-origin', cache: 'no-store', keepalive: true, headers: { 'X-CG-Click': '1' } })
        .then(function (response) { if (!response.ok) throw new Error('token-unavailable'); return response.json(); })
        .then(function (proof) { window.cgReferralClickTokenReady = true; return proof; })
        .catch(function (error) { tokenExpires = 0; tokenPromise = null; window.cgReferralClickTokenReady = false; throw error; });
    }
    return tokenPromise;
  }
  function eventId() {
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    if (!window.crypto || !crypto.getRandomValues) return '';
    var bytes = new Uint8Array(16); crypto.getRandomValues(bytes);
    bytes[6] = (bytes[6] & 15) | 64; bytes[8] = (bytes[8] & 63) | 128;
    var hex = Array.from(bytes, function (value) { return value.toString(16).padStart(2, '0'); }).join('');
    return hex.slice(0, 8) + '-' + hex.slice(8, 12) + '-' + hex.slice(12, 16) + '-' + hex.slice(16, 20) + '-' + hex.slice(20);
  }
  function record(event) {
    if (!event.isTrusted || handled.has(event)) return;
    if ((event.type === 'click' && event.button !== 0) || (event.type === 'auxclick' && event.button !== 1)) return;
    var element = event.target instanceof Element ? event.target : event.target && event.target.parentElement;
    var link = element && element.closest('a[href]');
    var target = link && targetFor(link);
    if (!target) return;
    handled.add(event);
    var id = eventId();
    if (!id) { window.cgReferralClickStatus = 'unavailable'; return; }
    window.cgReferralClickStatus = 'pending';
    // Do not change href, block navigation, or copy referral parameters into a public analytics label.
    token().then(function (proof) {
      return fetch(config.eventUrl, {
        method: 'POST', credentials: 'same-origin', cache: 'no-store', keepalive: true,
        headers: { 'Content-Type': 'application/json', 'X-CG-Click': '1' },
        body: JSON.stringify({ event: id, issued: proof.issued, proof: proof.proof, target: target, source: location.href })
      });
    }).then(function (response) {
      if (!response.ok) throw new Error('event-unavailable');
      return response.json();
    }).then(function (result) {
      window.cgReferralClickStatus = result.duplicate ? 'duplicate' : (result.counted ? 'recorded' : 'excluded');
    }).catch(function () { window.cgReferralClickStatus = 'error'; });
  }
  document.addEventListener('click', record, true);
  document.addEventListener('auxclick', record, true);
  // Warm the proof before a same-tab link can unload this document. This never creates a click or a receipt.
  token().catch(function () { /* Navigation continues even when local statistics are unavailable. */ });
  document.addEventListener('pointerover', function (event) {
    var link = event.target instanceof Element && event.target.closest('a[href]');
    if (link && targetFor(link)) token().catch(function () {});
  }, true);
  document.addEventListener('focusin', function (event) {
    var link = event.target instanceof Element && event.target.closest('a[href]');
    if (link && targetFor(link)) token().catch(function () {});
  }, true);
}());
