(function () {
  'use strict';
  var config = window.cgReferralAdmin;
  if (!config || !window.wp || !wp.domReady) return;
  wp.domReady(function () {
    var noticeId = 'cg-referral-save-result';
    var initialAttempt = document.getElementById('cg-referral-attempt');
    var activeAttempt = initialAttempt ? initialAttempt.value : '';
    var lastBusy = false;
    var timer;
    var generation = 0;
    function renewAttempt() {
      var input = document.getElementById('cg-referral-attempt');
      if (!input) return;
      activeAttempt = window.crypto && crypto.randomUUID ? crypto.randomUUID() : Date.now().toString(36) + '-' + Math.random().toString(36).slice(2);
      input.value = activeAttempt;
    }
    function show(message, error, announce) {
      var box = document.getElementById('cg-referral-save-status');
      if (box) {
        box.className = 'notice inline ' + (error ? 'notice-error' : 'notice-info');
        box.textContent = '';
        var paragraph = document.createElement('p'); paragraph.textContent = message; box.appendChild(paragraph);
      }
      if (!wp.data || !wp.data.dispatch('core/notices')) return;
      var notices = wp.data.dispatch('core/notices');
      notices.removeNotice(noticeId);
      if (error) notices.createErrorNotice(message, { id: noticeId, isDismissible: true });
      else if (announce) notices.createSuccessNotice('推荐设置已保存。' + message, { id: noticeId, isDismissible: true });
    }
    async function readStatus(announce) {
      var current = ++generation;
      try {
        var response = await fetch(config.ajaxUrl, {
          method: 'POST', credentials: 'same-origin', cache: 'no-store',
          body: new URLSearchParams({ action: 'cg_referral_status', post_id: String(config.postId), nonce: config.nonce })
        });
        var result = await response.json();
        if (current !== generation) return;
        if (!response.ok || !result.success) throw new Error('status-unavailable');
        var state = result.data;
        if (announce && (!activeAttempt || state.attempt !== activeAttempt)) {
          show('本次推荐设置的保存结果尚未确认，请刷新页面检查链接及启用状态。', true, false); return;
        }
        show(state.error ? state.error + ' ' + state.message : state.message, !!state.error, announce);
      } catch (error) {
        if (current === generation) show('推荐设置状态查询失败，请刷新页面核对已保存的链接及启用状态。', true, false);
      }
    }
    function afterSave() { clearTimeout(timer); timer = setTimeout(function () { readStatus(true); }, 150); }
    document.addEventListener('input', function (event) {
      if (event.target && ['cg_destination', 'cg_enabled', 'cg_expiry'].indexOf(event.target.id) !== -1) renewAttempt();
    });
    document.addEventListener('change', function (event) {
      if (event.target && ['cg_destination', 'cg_enabled', 'cg_expiry'].indexOf(event.target.id) !== -1) renewAttempt();
    });
    if (wp.hooks && wp.hooks.addAction) {
      // The native legacy-metabox save is awaited at priority 10 in the block editor.
      wp.hooks.addAction('editor.savePost', 'cg/referral-attempt', function (post, options) {
        if (!options || !options.isAutosave) renewAttempt();
      }, 5);
      wp.hooks.addAction('editor.savePost', 'cg/referral-status', function (post, options) {
        if (!options || !options.isAutosave) afterSave();
      }, 20);
    }
    if (wp.hooks && wp.hooks.addFilter) {
      // WordPress 6.6 uses this filter before its legacy-metabox save at priority 10.
      wp.hooks.addFilter('editor.__unstableSavePost', 'cg/referral-attempt-legacy', function (promise, options) {
        if (!options || !options.isAutosave) renewAttempt();
        return promise;
      }, 5);
    }
    // Compatibility with editors using the legacy store rather than the savePost hooks.
    if (wp.data && wp.data.subscribe) wp.data.subscribe(function () {
      var store = wp.data.select('core/edit-post');
      if (!store || typeof store.isSavingMetaBoxes !== 'function') return;
      var busy = store.isSavingMetaBoxes();
      if (lastBusy && !busy) afterSave();
      lastBusy = busy;
    });
    readStatus(false);
  });
}());
