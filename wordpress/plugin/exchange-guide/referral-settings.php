<?php
/** Owner-verified referral destinations. Never infer trust on activation or public requests. */
if (!defined('ABSPATH')) { exit; }

/** Return an exact public DNS host, without following or resolving an arbitrary URL. */
function cg_referral_url_host($url) {
    if (!is_string($url) || $url === '' || $url !== trim($url) || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) { return ''; }
    $parts = wp_parse_url($url);
    if (!is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && (int)$parts['port'] !== 443)) { return ''; }
    $host = strtolower($parts['host']);
    // A dotted ASCII hostname excludes IP literals, numeric IP aliases and local single-label names.
    if (!preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $host)) { return ''; }
    foreach (array('localhost','local','localdomain','internal','invalid','test','onion','home.arpa') as $suffix) {
        if ($host === $suffix || str_ends_with($host, '.'.$suffix)) { return ''; }
    }
    return $host;
}

function cg_referral_destination_allowed($post, $url) {
    if (!$post instanceof WP_Post || $post->post_type !== 'cg_exchange' || !isset(cg_definitions()[$post->post_name])) { return false; }
    $host = cg_referral_url_host($url);
    if ($host === '') { return false; }
    if (cg_safe_destination($post->post_name, $url)) { return true; }
    return get_post_meta($post->ID, '_cg_referral_exchange', true) === $post->post_name
        && get_post_meta($post->ID, '_cg_referral_host', true) === $host;
}

/** Called by the nonce-protected native metabox save; direct callers still need administrator rights. */
function cg_save_referral_settings($post, $url, $enabled, $expiry) {
    if (!$post instanceof WP_Post || $post->post_type !== 'cg_exchange' || !isset(cg_definitions()[$post->post_name]) || !current_user_can('manage_options') || !current_user_can('edit_post', $post->ID)) {
        return new WP_Error('referral_forbidden', '需要可编辑该交易所的网站管理员权限。');
    }
    if (!is_string($url) || !is_string($expiry) || !is_bool($enabled)) { return new WP_Error('referral_invalid', '推荐链接或日期格式不正确。'); }
    $url = trim($url); $expiry = trim($expiry);
    $host = $url === '' ? '' : cg_referral_url_host($url);
    if ($url !== '' && $host === '') { return new WP_Error('referral_invalid_url', '请填写完整的 HTTPS 推荐链接，使用公开域名，不包含用户名、密码或自定义端口。'); }
    if ($expiry !== '' && cg_expiry_timestamp($expiry) === false) { return new WP_Error('referral_invalid_expiry', '入口停用日期无效，请填写有效日期或留空。'); }
    $clean = esc_url_raw($url, array('https'));
    if ($url !== '' && ($clean === '' || cg_referral_url_host($clean) !== $host)) { return new WP_Error('referral_invalid_url', '推荐链接未通过网址格式校验。'); }
    $trust_custom_host = $url !== '' && $enabled && !cg_safe_destination($post->post_name, $clean);
    $before = array_map(fn($key)=>get_post_meta($post->ID,$key,true), array('_cg_destination','_cg_enabled','_cg_expiry','_cg_referral_host','_cg_referral_exchange'));
    // WordPress strips slashes when storing metadata; preserve the actual path and referral parameters.
    update_post_meta($post->ID, '_cg_destination', wp_slash($clean));
    update_post_meta($post->ID, '_cg_expiry', $expiry);
    update_post_meta($post->ID, '_cg_enabled', $url !== '' && $enabled ? '1' : '');
    if ($trust_custom_host) {
        update_post_meta($post->ID, '_cg_referral_host', $host);
        update_post_meta($post->ID, '_cg_referral_exchange', $post->post_name);
    } else {
        delete_post_meta($post->ID, '_cg_referral_host');
        delete_post_meta($post->ID, '_cg_referral_exchange');
    }
    $after = array_map(fn($key)=>get_post_meta($post->ID,$key,true), array('_cg_destination','_cg_enabled','_cg_expiry','_cg_referral_host','_cg_referral_exchange'));
    // The same destination/disclosure appears on the homepage and sidebars throughout the site.
    if ($before !== $after) { do_action('litespeed_purge_all'); }
    return true;
}

/** Private administrator view of the saved result; never expose this as public REST metadata. */
function cg_referral_settings_state($post) {
    $state = array('state'=>'official', 'message'=>'当前使用官方注册入口；推荐链接尚未填写或未启用。', 'error'=>'', 'attempt'=>'');
    if (!$post instanceof WP_Post) { return $state; }
    if (cg_active_offer($post)) {
        $state['state']='enabled';
        $state['message']='推荐入口已启用，目标域名：'.cg_referral_url_host(get_post_meta($post->ID,'_cg_destination',true)).'。';
    } elseif (get_post_meta($post->ID,'_cg_enabled',true)) {
        $state['message']='当前使用官方注册入口；推荐链接已到期或未通过域名核验，请检查并重新保存。';
    }
    $last = get_post_meta($post->ID,'_cg_referral_last_save',true);
    if (is_array($last) && (int)($last['user'] ?? 0) === get_current_user_id()) {
        $state['error']=is_string($last['error'] ?? null) ? $last['error'] : '';
        $state['attempt']=is_string($last['attempt'] ?? null) ? $last['attempt'] : '';
    }
    return $state;
}

function cg_referral_status_markup($post) {
    $state = cg_referral_settings_state($post);
    echo '<input type="hidden" name="cg_referral_attempt" id="cg-referral-attempt" value="'.esc_attr(wp_generate_uuid4()).'">';
    echo '<div id="cg-referral-save-status" role="status" aria-live="polite" class="notice inline '.($state['error'] !== '' ? 'notice-error' : 'notice-info').'"><p>'.esc_html($state['error'] !== '' ? $state['error'].' '.$state['message'] : $state['message']).'</p></div>';
}

add_action('wp_ajax_cg_referral_status', function () {
    $id = isset($_POST['post_id']) && is_scalar($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    $post = get_post($id);
    if (!current_user_can('manage_options') || !$post || $post->post_type !== 'cg_exchange' || !current_user_can('edit_post',$id)) { wp_send_json_error(array('message'=>'无权查看推荐设置。'),403); }
    check_ajax_referer('cg_referral_status_'.$id,'nonce');
    nocache_headers();
    wp_send_json_success(cg_referral_settings_state($post));
});

add_action('admin_enqueue_scripts', function ($hook) {
    if (!in_array($hook,array('post.php','post-new.php'),true) || !current_user_can('manage_options')) { return; }
    $screen=get_current_screen(); global $post;
    if (!$screen || $screen->post_type !== 'cg_exchange' || !$post instanceof WP_Post || !$screen->is_block_editor()) { return; }
    wp_enqueue_script('cg-referral-admin',plugins_url('assets/referral-admin.js',__FILE__),array('wp-data','wp-notices','wp-hooks','wp-dom-ready'),'1.14.3',true);
    $config=array('postId'=>$post->ID,'ajaxUrl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('cg_referral_status_'.$post->ID));
    wp_add_inline_script('cg-referral-admin','window.cgReferralAdmin = '.wp_json_encode($config,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';','before');
});
