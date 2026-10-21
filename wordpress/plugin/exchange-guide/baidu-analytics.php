<?php
/** Optional Baidu Tongji integration. No tracking is enabled without the owner's real site ID. */
if (!defined('ABSPATH')) { exit; }

function cg_baidu_site_id($input) {
    if (!is_string($input)) { return ''; }
    $input = trim($input);
    if (preg_match('/^[a-f0-9]{32}$/i', $input)) { return strtolower($input); }
    // Only retain the official hm.js ID; pasted HTML/JavaScript is never stored or executed.
    preg_match_all('~(?:https?:)?//hm\.baidu\.com/hm\.js\?([a-f0-9]{32})(?=["\'\s<]|$)~i', $input, $matches);
    $ids = array_unique(array_map('strtolower', $matches[1]));
    return count($ids) === 1 ? reset($ids) : '';
}

function cg_baidu_analytics_settings() {
    $option = get_option('cg_baidu_analytics', array());
    if (!is_array($option)) { $option = array(); }
    // A stored ID must itself be an ID, not a script from legacy/untrusted option values.
    $id = isset($option['id']) && is_string($option['id']) && preg_match('/^[a-f0-9]{32}$/i', $option['id']) ? strtolower($option['id']) : '';
    return array('id' => $id, 'enabled' => $id !== '' && !empty($option['enabled']));
}

function cg_baidu_analytics_enabled() {
    return cg_baidu_analytics_settings()['enabled'];
}

function cg_baidu_save_settings($code, $enabled, $nonce) {
    if (!current_user_can('manage_options') || !is_string($nonce) || !wp_verify_nonce($nonce, 'cg_baidu_analytics_save')) {
        return new WP_Error('not_allowed', '无法保存：请使用管理员账户并刷新页面后重试。');
    }
    if (!is_string($code)) { return new WP_Error('invalid_code', '请粘贴本站百度统计代码或 32 位站点 ID。'); }
    $id = cg_baidu_site_id($code);
    if (trim($code) !== '' && $id === '') {
        return new WP_Error('invalid_code', '未找到唯一有效的百度统计站点 ID。请从本站百度统计账户重新复制，原设置已保留。');
    }
    if ($enabled && $id === '') { return new WP_Error('missing_code', '启用前必须填写本站真实百度统计代码。'); }
    update_option('cg_baidu_analytics', array('id' => $id, 'enabled' => (bool) $enabled && $id !== ''), false);
    do_action('litespeed_purge_all');
    return true;
}

function cg_baidu_webmaster_status() {
    // Rank Math's settings group is "general", stored in this actual WP option.
    $settings = get_option('rank-math-options-general', array());
    if (!is_array($settings)) { $settings = array(); }
    $providers = array('bing' => array('Bing 站长工具', 'bing_verify'), 'baidu' => array('百度搜索资源平台', 'baidu_verify'), 'google' => array('Google Search Console（补充）', 'google_verify'));
    $status = array();
    foreach ($providers as $provider => $data) {
        $status[$provider] = array('name' => $data[0], 'configured' => isset($settings[$data[1]]) && is_string($settings[$data[1]]) && trim($settings[$data[1]]) !== '');
    }
    return $status;
}

add_action('admin_menu', function () {
    add_submenu_page('cg-setup', '统计与站长验证', '统计与站长验证', 'manage_options', 'cg-baidu-analytics', 'cg_baidu_analytics_admin');
}, 20);

function cg_baidu_analytics_admin() {
    if (!current_user_can('manage_options')) { return; }
    $settings = cg_baidu_analytics_settings();
    echo '<div class="wrap"><h1>统计与站长验证</h1>';
    $notice = isset($_GET['saved']) && is_string($_GET['saved']) ? sanitize_key($_GET['saved']) : '';
    if ($notice === '1') { echo '<div class="notice notice-success"><p>设置已保存，页面缓存已请求清除。启用真实统计前，请核对并更新隐私政策中的统计服务与数据说明。</p></div>'; }
    elseif ($notice === 'invalid_code' || $notice === 'missing_code') { echo '<div class="notice notice-error"><p>未保存：请填写本站唯一的真实百度统计代码或 32 位站点 ID；启用时不能为空。原设置已保留。</p></div>'; }
    echo '<h2>百度统计站长版</h2><p>本站只接入这一套站内访问统计。当前状态：<strong>'.($settings['enabled'] ? '已启用' : '未启用').'</strong>。</p><p>未取得真实代码前请保持关闭。仅提取 hm.baidu.com/hm.js 中的站点 ID，不会执行粘贴代码中的其他内容。</p>';
    echo '<form action="'.esc_url(admin_url('admin-post.php')).'" method="post"><input type="hidden" name="action" value="cg_baidu_analytics_save">';
    wp_nonce_field('cg_baidu_analytics_save', 'cg_baidu_analytics_nonce');
    echo '<table class="form-table"><tr><th scope="row"><label for="cg-baidu-code">本站统计代码或站点 ID</label></th><td><textarea id="cg-baidu-code" name="cg_baidu_code" rows="5" class="large-text code" spellcheck="false">'.esc_textarea($settings['id']).'</textarea><p class="description">保存后这里只显示提取的站点 ID。清空并取消启用可移除本站接入。</p></td></tr><tr><th scope="row">启用统计</th><td><label><input name="cg_baidu_enabled" type="checkbox" value="1" '.checked($settings['enabled'], true, false).'> 取得本站真实代码后启用百度统计</label></td></tr></table>';
    submit_button('保存统计设置');
    $privacy = get_post((int) get_option('wp_page_for_privacy_policy'));
    if (!$privacy || $privacy->post_type !== 'page') { $privacy = get_page_by_path('privacy-policy'); }
    $privacy_url = $privacy ? get_edit_post_link($privacy->ID, 'raw') : admin_url('options-privacy.php');
    echo '</form><p><strong>启用真实统计前，请审阅并更新隐私政策。</strong>早期草稿可能仍写“未启用统计”，启用后须如实说明百度统计服务、访问及外链点击数据。本站不会自动覆盖已有隐私内容。<a href="'.esc_url($privacy_url).'">编辑隐私政策</a></p><p>脚本异步加载，具有网站管理权限的已登录管理员和预览页面不加载本站统计。推广入口仅记录外链点击，包含交易所、文章标识与按钮位置；不代表注册或交易成功。</p><p>检测到页面已有百度统计脚本时不重复加载；如站点 ID 冲突会停止本站脚本接入。请避免在 Rank Math、自定义 HTML 或其他插件中再次插入统计代码。</p>';
    echo '<h2>站长平台验证</h2><p>以下读取 Rank Math 已有验证字段，本站不会另行输出重复验证标签。字段有值仅表示已填写，是否验证成功须到对应平台确认。</p><table class="widefat striped"><thead><tr><th>平台</th><th>Rank Math 字段状态</th></tr></thead><tbody>';
    foreach (cg_baidu_webmaster_status() as $row) { echo '<tr><td>'.esc_html($row['name']).'</td><td>'.($row['configured'] ? '已填写（平台验证结果待确认）' : '未填写').'</td></tr>'; }
    echo '</tbody></table><p><a href="'.esc_url(admin_url('admin.php?page=rank-math-options-general&view=webmaster')).'">打开 Rank Math → 常规设置 → 站长工具</a></p><p>百度统计、百度搜索资源平台、Bing 与 Google 的验证是不同服务。收到各平台真实验证值后，在现有 Rank Math 字段填写即可。</p><p>正式验收需使用真实百度统计账户和大陆网络确认脚本加载、访问与点击入账；本地测试不能替代这些验收。</p></div>';
}

add_action('admin_post_cg_baidu_analytics_save', function () {
    if (!current_user_can('manage_options')) { wp_die('无权修改统计设置。', '', array('response' => 403)); }
    $nonce = isset($_POST['cg_baidu_analytics_nonce']) && is_string($_POST['cg_baidu_analytics_nonce']) ? wp_unslash($_POST['cg_baidu_analytics_nonce']) : '';
    if (!wp_verify_nonce($nonce, 'cg_baidu_analytics_save')) { wp_die('保存校验已失效，请返回并刷新页面。', '', array('response' => 403)); }
    $code = isset($_POST['cg_baidu_code']) && is_string($_POST['cg_baidu_code']) ? wp_unslash($_POST['cg_baidu_code']) : null;
    $enabled = isset($_POST['cg_baidu_enabled']) && $_POST['cg_baidu_enabled'] === '1';
    $result = cg_baidu_save_settings($code, $enabled, $nonce);
    $status = is_wp_error($result) ? $result->get_error_code() : '1';
    wp_safe_redirect(add_query_arg(array('page' => 'cg-baidu-analytics', 'saved' => $status), admin_url('admin.php')));
    exit;
});

function cg_baidu_enqueue() {
    $settings = cg_baidu_analytics_settings();
    if (!$settings['enabled'] || is_admin() || is_preview() || current_user_can('manage_options')) { return; }
    $article = ''; $article_id = 0; $exchange = '';
    $id = get_queried_object_id();
    if (is_singular() && function_exists('cg_uses_article_layout') && cg_uses_article_layout($id) && get_post_status($id) === 'publish' && !get_post_field('post_password', $id)) {
        $article = get_post_field('post_name', $id); $article_id = $id;
        $platform = function_exists('cg_article_exchange') ? cg_article_exchange($id) : null;
        if ($platform && in_array($platform->post_name, array('binance', 'okx', 'bitget', 'bybit'), true)) { $exchange = $platform->post_name; }
    }
    wp_enqueue_script('cg-baidu-analytics', plugins_url('assets/baidu-analytics.js', __FILE__), array(), '1.14.0', true);
    $data = array('siteId' => $settings['id'], 'article' => $article, 'articleId' => $article_id, 'reviewExchange' => $exchange, 'sitePath' => wp_parse_url(home_url('/'), PHP_URL_PATH));
    wp_add_inline_script('cg-baidu-analytics', 'window.cgBaiduAnalytics = '.wp_json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT).';', 'before');
}
add_action('wp_enqueue_scripts', 'cg_baidu_enqueue');
