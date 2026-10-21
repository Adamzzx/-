<?php
/** Run only against the disposable local WP: wp eval-file .../baidu-analytics.php */
if (home_url() !== 'http://localhost:8088') { throw new RuntimeException('Disposable local WordPress only'); }
function baidu_check($condition, $label) { if (!$condition) { throw new RuntimeException($label); } echo "PASS: $label\n"; }
$admin = get_user_by('login', 'smoke-admin');
if (!$admin) { throw new RuntimeException('Initialize local smoke-admin first'); }
$before = get_option('cg_baidu_analytics', null); $rankmath = get_option('rank-math-options-general', null);
$user_before = get_current_user_id();
global $wp_scripts, $wp_query;
$scripts_before = $wp_scripts; $query_before = $wp_query;
$id = str_repeat('a', 32); $other = str_repeat('b', 32); $users = array();
try {
    wp_set_current_user($admin->ID); delete_option('cg_baidu_analytics');
    baidu_check(cg_baidu_analytics_settings() === array('id' => '', 'enabled' => false), 'no invented site ID or default tracking');
    baidu_check(cg_baidu_site_id(strtoupper($id)) === $id, 'site ID is normalized');
    $snippet = '<script>var hm=document.createElement("script");hm.src="https://hm.baidu.com/hm.js?'.$id.'";</script><script>never_execute()</script>';
    baidu_check(cg_baidu_site_id($snippet) === $id, 'official snippet only yields the ID, never executable pasted code');
    foreach (array(array($id), 'https://hm.baidu.com.evil.invalid/hm.js?'.$id, 'https://evil.invalid/hm.js?'.$id, 'https://hm.baidu.com/hm.js?'.$id.'extra', 'https://hm.baidu.com/hm.js?'.$id.' https://hm.baidu.com/hm.js?'.$other) as $invalid) {
        baidu_check(cg_baidu_site_id($invalid) === '', 'malformed/foreign/ambiguous tracking values rejected');
    }
    $nonce = wp_create_nonce('cg_baidu_analytics_save');
    baidu_check(cg_baidu_save_settings($snippet, true, $nonce) === true && cg_baidu_analytics_enabled(), 'real-code-shaped fixture saves only after administrator nonce check');
    baidu_check(get_option('cg_baidu_analytics') === array('id' => $id, 'enabled' => true), 'pasted HTML is not stored');
    $saved = get_option('cg_baidu_analytics');
    foreach (array(array('bad', true, $nonce), array('', true, $nonce), array($other, true, ''), array(array($other), true, $nonce)) as $input) {
        baidu_check(is_wp_error(cg_baidu_save_settings(...$input)) && get_option('cg_baidu_analytics') === $saved, 'invalid or unauthenticated save preserves settings');
    }
    foreach (array('editor', 'subscriber') as $role) {
        $user = wp_insert_user(array('user_login' => 'local-baidu-'.$role.'-'.wp_generate_password(6, false), 'user_pass' => wp_generate_password(), 'role' => $role));
        if (is_wp_error($user)) { throw new RuntimeException($user->get_error_message()); }
        $users[] = $user; wp_set_current_user($user);
        baidu_check(is_wp_error(cg_baidu_save_settings($other, true, wp_create_nonce('cg_baidu_analytics_save'))) && get_option('cg_baidu_analytics') === $saved, $role.' cannot edit global analytics settings');
    }
    wp_set_current_user($admin->ID); $wp_query = new WP_Query(); $wp_scripts = new WP_Scripts();
    cg_baidu_enqueue(); baidu_check(!wp_script_is('cg-baidu-analytics', 'enqueued'), 'administrators do not load onsite tracking');
    wp_set_current_user(0); cg_baidu_enqueue();
    baidu_check(wp_script_is('cg-baidu-analytics', 'enqueued'), 'enabled anonymous frontend enqueues the local bootstrap');
    $inline = implode('', $wp_scripts->registered['cg-baidu-analytics']->extra['before']);
    baidu_check(str_contains($inline, $id) && !str_contains($inline, 'user_login') && !str_contains($inline, 'nonce'), 'frontend config contains no visitor identity or tracking nonce');
    $wp_scripts = new WP_Scripts(); $wp_query->is_preview = true; cg_baidu_enqueue();
    baidu_check(!wp_script_is('cg-baidu-analytics', 'enqueued'), 'preview pages are excluded');
    $wp_query->is_preview = false; wp_set_current_user($admin->ID);
    cg_baidu_save_settings($id, false, wp_create_nonce('cg_baidu_analytics_save')); wp_set_current_user(0); cg_baidu_enqueue();
    baidu_check(!wp_script_is('cg-baidu-analytics', 'enqueued') && !cg_baidu_analytics_enabled(), 'disabled setting results in no tracking script');
    wp_set_current_user($admin->ID);
    update_option('rank-math-options-general', array('bing_verify' => '', 'baidu_verify' => '', 'google_verify' => ''));
    foreach (cg_baidu_webmaster_status() as $row) { baidu_check(!$row['configured'], 'empty official Rank Math field reports unconfigured: '.$row['name']); }
    update_option('rank-math-options-general', array('bing_verify' => 'local-bing-verification', 'baidu_verify' => 'local-baidu-verification', 'google_verify' => 'local-google-verification', 'unrelated_setting' => 'preserve'));
    foreach (cg_baidu_webmaster_status() as $row) { baidu_check($row['configured'], 'existing official Rank Math verification key recognized: '.$row['name']); }
    ob_start(); cg_baidu_analytics_admin(); $html = ob_get_clean();
    baidu_check(str_contains($html, 'cg_baidu_analytics_nonce') && str_contains($html, '启用真实统计前，请审阅并更新隐私政策') && str_contains($html, '编辑隐私政策'), 'admin form has dedicated nonce and actionable privacy update reminder');
    baidu_check(!str_contains($html, 'local-bing-verification') && !str_contains($html, '<meta name="baidu-site-verification"'), 'verification UI reports status without duplicating meta or exposing values');
    baidu_check(str_contains(html_entity_decode($html), 'admin.php?page=rank-math-options-general&view=webmaster') && !str_contains($html, 'setting-panel-webmaster'), 'webmaster link uses the official Rank Math 1.0.280 view query parameter');
    baidu_check(get_option('rank-math-options-general')['unrelated_setting'] === 'preserve', 'Baidu module never changes Rank Math options');
    cg_baidu_save_settings('', false, wp_create_nonce('cg_baidu_analytics_save'));
    baidu_check(cg_baidu_analytics_settings() === array('id' => '', 'enabled' => false), 'owner can clear ID and disable analytics');
} finally {
    wp_set_current_user($admin->ID);
    if ($before === null) { delete_option('cg_baidu_analytics'); } else { update_option('cg_baidu_analytics', $before); }
    if ($rankmath === null) { delete_option('rank-math-options-general'); } else { update_option('rank-math-options-general', $rankmath); }
    require_once ABSPATH.'wp-admin/includes/user.php'; foreach ($users as $user) { wp_delete_user($user); }
    wp_set_current_user($user_before); $wp_scripts = $scripts_before; $wp_query = $query_before;
}
