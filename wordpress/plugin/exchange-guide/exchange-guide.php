<?php
/**
 * Plugin Name: 数字金融指南 · 内容与推荐管理
 * Description: 加密资产与数字金融内容、交易所资料和集中推荐入口。启用后请打开「网站管理」完成初始化。
 * Version: 1.6.0
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * Author: Exchange Guide
 * License: GPL-2.0-or-later
 * Text Domain: exchange-guide
 */
if (!defined('ABSPATH')) { exit; }
require_once __DIR__ . '/navigation.php';
require_once __DIR__ . '/registration.php';
require_once __DIR__ . '/admin-content.php';

function cg_definitions() {
    return array(
        'binance' => array('name' => 'Binance', 'hosts' => array('binance.com', 'www.binance.com', 'accounts.binance.com')),
        'bybit' => array('name' => 'Bybit', 'hosts' => array('bybit.com', 'www.bybit.com')),
        'okx' => array('name' => 'OKX', 'hosts' => array('okx.com', 'www.okx.com')),
        'bitget' => array('name' => 'Bitget', 'hosts' => array('bitget.com', 'www.bitget.com')),
        'mexc' => array('name' => 'MEXC', 'hosts' => array('mexc.com', 'www.mexc.com')),
        'gate' => array('name' => 'Gate', 'hosts' => array('gate.com', 'www.gate.com', 'gate.io', 'www.gate.io')),
    );
}
function cg_safe_destination($slug, $url) {
    $definitions = cg_definitions();
    $parts = wp_parse_url($url);
    return isset($definitions[$slug]) && is_array($parts)
        && isset($parts['scheme'], $parts['host']) && strtolower($parts['scheme']) === 'https'
        && !isset($parts['user']) && !isset($parts['pass'])
        && (!isset($parts['port']) || (int) $parts['port'] === 443)
        && in_array(strtolower($parts['host']), $definitions[$slug]['hosts'], true);
}
function cg_register() {
    $types = array(
        'cg_exchange' => array('交易所资料', '交易所', 'exchanges', true),
        'cg_review' => array('交易所评测', '评测', 'reviews', true),
        'cg_compare' => array('交易所对比', '交易所对比', 'compare', false),
        'cg_ranking' => array('推荐榜单', '榜单', 'best', false),
        'cg_guide' => array('专题与指南', '指南', 'guides', false),
    );
    foreach ($types as $type => $values) {
        register_post_type($type, array(
            'labels' => array('menu_name'=>$type==='cg_exchange'?'交易所管理':$values[0],'all_items'=>$values[0],'name' => $values[0], 'singular_name' => $values[1], 'add_new_item' => '添加' . $values[1], 'edit_item' => '编辑' . $values[1]),
            'public' => true, 'show_in_rest' => true,
            'show_in_menu' => in_array($type,array('cg_review','cg_compare','cg_ranking'),true)?'edit.php?post_type=cg_exchange':true,
            'has_archive' => $values[3] ? $values[2] : false,
            'rewrite' => array('slug' => $values[2], 'with_front' => false),
            'supports' => array('title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'author'),
            'menu_icon' => $type === 'cg_exchange' ? 'dashicons-chart-bar' : 'dashicons-media-document',
            'delete_with_user' => false,
        ));
    }
    add_rewrite_rule('^go/([a-z0-9-]+)/?$', 'index.php?cg_go=$matches[1]', 'top');
}
add_action('init', 'cg_register');
add_filter('query_vars', function ($vars) { $vars[] = 'cg_go'; return $vars; });
register_activation_hook(__FILE__, function () { cg_register(); cg_register_registration_routes(); flush_rewrite_rules(); });
register_deactivation_hook(__FILE__, function () { foreach (array('cg_exchange','cg_review','cg_compare','cg_ranking','cg_guide') as $type) { unregister_post_type($type); } flush_rewrite_rules(); });

function cg_exchange($slug) {
    $post = get_page_by_path(sanitize_title($slug), OBJECT, 'cg_exchange');
    return $post && $post->post_status === 'publish' ? $post : null;
}
function cg_expiry_timestamp($date) {
    if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { return false; }
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
    return $parsed && $parsed->format('Y-m-d') === $date ? $parsed->getTimestamp() : false;
}
function cg_active_offer($post) {
    if (!$post || !get_post_meta($post->ID, '_cg_enabled', true)) { return false; }
    if (!cg_safe_destination($post->post_name, get_post_meta($post->ID, '_cg_destination', true))) { return false; }
    $end = get_post_meta($post->ID, '_cg_expiry', true);
    // Expiry is an exclusive boundary at 00:00 UTC on the selected date.
    return !$end || (cg_expiry_timestamp($end) !== false && cg_expiry_timestamp($end) > time());
}
function cg_redirect() {
    $slug = get_query_var('cg_go');
    if (!$slug) { return; }
    if (!defined('DONOTCACHEPAGE')) { define('DONOTCACHEPAGE', true); }
    do_action('litespeed_control_set_nocache', 'Affiliate redirects must not be cached');
    nocache_headers(); header('X-Robots-Tag: noindex, nofollow', true);
    $post = cg_exchange($slug);
    if (!is_string($slug) || !isset(cg_definitions()[$slug])) { status_header(404); wp_die('未知交易所。', '入口不存在', array('response' => 404)); }
    if (!cg_active_offer($post)) {
        $official=cg_registration_destination($slug);
        if($official){wp_redirect($official,302,'Exchange Guide');exit;}
        if($post){wp_safe_redirect(get_permalink($post),302,'Exchange Guide');exit;}
        status_header(404);wp_die('注册入口尚未配置。','入口不存在',array('response'=>404));
    }
    global $wpdb;
    // Aggregate count only; do not record visitor identities or request parameters.
    add_post_meta($post->ID, '_cg_clicks', '0', true);
    $wpdb->query($wpdb->prepare("UPDATE {$wpdb->postmeta} SET meta_value = CAST(meta_value AS UNSIGNED) + 1 WHERE post_id = %d AND meta_key = %s", $post->ID, '_cg_clicks'));
    // The URL has already been checked against an exact exchange-specific host allowlist.
    wp_redirect(get_post_meta($post->ID, '_cg_destination', true), 302, 'Exchange Guide'); exit;
}
add_action('template_redirect', 'cg_redirect', 1);

function cg_meta_boxes() {
    if (!current_user_can('manage_options')) { return; }
    add_meta_box('cg-data', '资料核验与推荐链接（管理员）', 'cg_meta_box', 'cg_exchange', 'normal', 'high');
    foreach (array('post','cg_review','cg_compare','cg_ranking','cg_guide') as $type) {
        add_meta_box('cg-editorial', '编辑信息与商业披露', 'cg_editorial_box', $type, 'normal');
    }
}
add_action('add_meta_boxes', 'cg_meta_boxes');
function cg_input($id, $key, $label, $type = 'text', $help = '') {
    $value = get_post_meta($id, '_cg_' . $key, true);
    echo '<p><label for="cg_' . esc_attr($key) . '"><strong>' . esc_html($label) . '</strong></label><br>';
    if ($type === 'textarea') { echo '<textarea class="widefat" rows="3" id="cg_' . esc_attr($key) . '" name="cg_' . esc_attr($key) . '">' . esc_textarea($value) . '</textarea>'; }
    elseif ($type === 'checkbox') { echo '<input type="checkbox" id="cg_' . esc_attr($key) . '" name="cg_' . esc_attr($key) . '" value="1" ' . checked($value, '1', false) . '> 是'; }
    else { echo '<input class="widefat" type="' . esc_attr($type) . '" id="cg_' . esc_attr($key) . '" name="cg_' . esc_attr($key) . '" value="' . esc_attr($value) . '">'; }
    if ($help) { echo '<span class="description">' . esc_html($help) . '</span>'; } echo '</p>';
}
function cg_meta_box($post) {
    wp_nonce_field('cg_save_meta', 'cg_meta_nonce');
    echo '<p>保留初始网址标识，如 binance。空白资料在前台显示「待核验」，不要填写未确认的优惠。</p>';
    cg_input($post->ID, 'verified', '资料已核验', 'checkbox');
    cg_input($post->ID, 'reviewed', '资料核验日期', 'date');
    cg_input($post->ID, 'source', '官方资料来源', 'url');
    cg_input($post->ID, 'fees', '费用与适用条件', 'textarea');
    cg_input($post->ID, 'regions', '地区与产品资格', 'textarea');
    cg_input($post->ID, 'destination', '注册或推荐链接（选填）', 'url', '留空、未启用或过期时，四家首页平台默认使用官方注册链接。以后填写自己的推荐链接并勾选启用，即可统一替换横幅跳转。仅允许对应平台的 HTTPS 官方域名。');
    cg_input($post->ID, 'terms', '推荐活动条款', 'textarea');
    $banner=(int)get_post_meta($post->ID,'_cg_banner_attachment',true);
    echo '<p><strong>注册横幅图片（选填）</strong><br>只上传自己制作或有权使用的图片，建议宽高比约 4:1。留空使用主题的独立文字卡片。</p><input type="hidden" name="cg_banner_attachment" id="cg_banner_attachment" value="'.absint($banner).'">';
    echo '<img id="cg-banner-preview" alt="横幅预览" style="max-width:100%;width:360px;'.($banner?'':'display:none;').'" src="'.esc_url($banner?wp_get_attachment_image_url($banner,'medium_large'):'').'">';
    echo '<p><button type="button" class="button" id="cg-banner-select">选择横幅图片</button> <button type="button" class="button" id="cg-banner-clear">使用默认文字卡片</button></p>';
    cg_input($post->ID, 'expiry', '入口停用日期（UTC）', 'date', '到所选日期 00:00 UTC 即失效；没有期限可留空。');
    cg_input($post->ID, 'enabled', '推荐链接已核实并启用', 'checkbox');
    $officials=cg_official_registration_urls();if(isset($officials[$post->post_name])){echo '<p>默认官方注册链接：<a href="'.esc_url($officials[$post->post_name]).'" target="_blank" rel="noopener noreferrer">'.esc_html($officials[$post->post_name]).'</a></p>';}
    echo '<p>累计推荐点击：<strong>' . absint(get_post_meta($post->ID, '_cg_clicks', true)) . '</strong>（不代表注册转化）</p>';
}
function cg_editorial_box($post) {
    wp_nonce_field('cg_save_meta', 'cg_meta_nonce');
    cg_input($post->ID, 'reviewed', '核验日期', 'date');
    cg_input($post->ID, 'source', '主要资料来源', 'url');
    cg_input($post->ID, 'sponsored', '这是赞助内容', 'checkbox');
    echo '<p><label for="cg_primary_topic">主要主题（同时加入文章主题）</label>';
    wp_dropdown_categories(array('taxonomy'=>'cg_topic','hide_empty'=>false,'hierarchical'=>true,'name'=>'cg_primary_topic','id'=>'cg_primary_topic','selected'=>(int)get_post_meta($post->ID,'_cg_primary_topic',true),'show_option_none'=>'未选择','option_none_value'=>0)); echo '</p>';
    echo '<p><label for="cg_related_exchanges">关联交易所（可多选）</label><br><select id="cg_related_exchanges" name="cg_related_exchanges[]" multiple>';
    $related=(array)get_post_meta($post->ID,'_cg_related_exchanges',true);
    foreach(get_posts(array('post_type'=>'cg_exchange','post_status'=>'publish','numberposts'=>100)) as $exchange){echo '<option value="'.absint($exchange->ID).'" '.selected(in_array($exchange->ID,$related,true),true,false).'>'.esc_html($exchange->post_title).'</option>';}
    echo '</select></p>';
    echo '<p>更多来源请在正文列出。推荐按钮使用短代码 <code>[cg_affiliate exchange="binance"]</code>，不要把推荐链接直接写入文章。</p>';
}
function cg_save_meta($id) {
    if (wp_is_post_revision($id) || wp_is_post_autosave($id) || !current_user_can('manage_options') || !current_user_can('edit_post', $id)) { return; }
    if (!isset($_POST['cg_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cg_meta_nonce'])), 'cg_save_meta')) { return; }
    $type = get_post_type($id);
    if (!in_array($type, array('post','cg_exchange','cg_review','cg_compare','cg_ranking','cg_guide'), true)) { return; }
    foreach (array('reviewed','source','sponsored') as $key) {
        $value = isset($_POST['cg_' . $key]) ? wp_unslash($_POST['cg_' . $key]) : '';
        if ($key === 'source') { $value = esc_url_raw($value, array('https')); }
        elseif ($key === 'sponsored') { $value = $value ? '1' : ''; }
        else { $value = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : ''; }
        update_post_meta($id, '_cg_' . $key, $value);
    }
    if ($type !== 'cg_exchange') {
        $primary=absint($_POST['cg_primary_topic'] ?? 0);
        if($primary && term_exists($primary,'cg_topic')){wp_set_object_terms($id,array($primary),'cg_topic',true);update_post_meta($id,'_cg_primary_topic',$primary);}else{delete_post_meta($id,'_cg_primary_topic');}
        $related=array();foreach((array)($_POST['cg_related_exchanges'] ?? array()) as $related_id){$related_id=absint($related_id);if(get_post_type($related_id)==='cg_exchange'){$related[]=$related_id;}}
        update_post_meta($id,'_cg_related_exchanges',array_values(array_unique($related))); return;
    }
    foreach (array('fees','regions','terms') as $key) { update_post_meta($id, '_cg_' . $key, sanitize_textarea_field(wp_unslash($_POST['cg_' . $key] ?? ''))); }
    update_post_meta($id, '_cg_verified', isset($_POST['cg_verified']) ? '1' : '');
    if(isset($_POST['cg_banner_attachment'])){$banner=absint($_POST['cg_banner_attachment']);if(!$banner||in_array(get_post_mime_type($banner),array('image/jpeg','image/png','image/webp','image/avif'),true)){update_post_meta($id,'_cg_banner_attachment',$banner);}}
    $url = trim(wp_unslash($_POST['cg_destination'] ?? ''));
    $slug = get_post_field('post_name', $id);
    if ($url && !cg_safe_destination($slug, $url)) {
        update_post_meta($id, '_cg_enabled', '');
        set_transient('cg_error_' . get_current_user_id(), '推荐链接未保存：域名或协议不符合允许名单，已停用入口。请检查链接。', 120);
        return;
    }
    update_post_meta($id, '_cg_destination', esc_url_raw($url, array('https')));
    $expiry = sanitize_text_field(wp_unslash($_POST['cg_expiry'] ?? ''));
    if ($expiry && cg_expiry_timestamp($expiry) === false) { $expiry = '1970-01-01'; }
    update_post_meta($id, '_cg_expiry', $expiry);
    update_post_meta($id, '_cg_enabled', $url && isset($_POST['cg_enabled']) ? '1' : '');
    do_action('litespeed_purge_post', $id);
}
add_action('save_post', 'cg_save_meta');
add_action('admin_notices', function () {
    $key = 'cg_error_' . get_current_user_id(); $error = get_transient($key);
    if ($error) { echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>'; delete_transient($key); }
});

function cg_cards($type = 'cg_exchange', $limit = 6) {
    $posts = get_posts(array('post_type' => $type, 'post_status' => 'publish', 'numberposts' => $limit, 'orderby' => $type === 'cg_exchange' ? 'menu_order title' : 'date', 'order' => $type === 'cg_exchange' ? 'ASC' : 'DESC'));
    if (!$posts) { return '<p class="cg-empty">内容正在准备中，核验完成后发布。</p>'; }
    $html = '<div class="cg-grid">';
    foreach ($posts as $post) {
        $badge = $type === 'cg_exchange' ? (get_post_meta($post->ID, '_cg_verified', true) ? '已核验' : '待核验') : (get_post_meta($post->ID, '_cg_sponsored', true) ? '赞助内容' : '编辑内容');
        $html .= '<a class="cg-card" href="' . esc_url(get_permalink($post)) . '"><span class="cg-badge">' . esc_html($badge) . '</span><h3>' . esc_html($post->post_title) . '</h3><p>' . esc_html($post->post_excerpt ?: ($type === 'cg_exchange' ? '了解费用条件、地区资格与注册入口。' : '查看内容与资料来源。')) . '</p><span class="cg-card-link">查看详情 →</span></a>';
    }
    return $html . '</div>';
}
function cg_affiliate($attributes) {
    $attributes = shortcode_atts(array('exchange' => ''), $attributes);
    $slug = sanitize_title($attributes['exchange']); $post = cg_exchange($slug);
    if (!$post) { return ''; }
    if (!cg_active_offer($post)) { return '<p class="cg-empty">暂无已核实的推荐入口。</p>'; }
    return '<div class="cg-affiliate"><p>推荐披露：通过此入口注册，我们可能获得佣金。优惠和资格以官方条款为准。</p><p>' . esc_html(get_post_meta($post->ID, '_cg_terms', true) ?: '未声明额外优惠，请阅读官方条款。') . '</p><a class="cg-button" rel="sponsored nofollow" href="' . esc_url(home_url('/go/' . $slug . '/')) . '">前往 ' . esc_html($post->post_title) . ' 官方入口 ↗</a></div>';
}
add_shortcode('cg_affiliate', 'cg_affiliate');
add_shortcode('cg_exchanges', function () { return cg_cards('cg_exchange', 20); });
add_shortcode('cg_content', function ($atts) {
    $atts = shortcode_atts(array('type' => 'guides'), $atts);
    $types = array('guides' => 'cg_guide', 'rankings' => 'cg_ranking', 'comparisons' => 'cg_compare');
    return isset($types[$atts['type']]) ? cg_cards($types[$atts['type']], 30) : '';
});
function cg_compare_shortcode() {
    $definitions = cg_definitions();
    $a = isset($_GET['a']) && is_string($_GET['a']) ? sanitize_title(wp_unslash($_GET['a'])) : 'binance'; $b = isset($_GET['b']) && is_string($_GET['b']) ? sanitize_title(wp_unslash($_GET['b'])) : 'bybit';
    if (!isset($definitions[$a])) { $a = 'binance'; } if (!isset($definitions[$b])) { $b = 'bybit'; }
    $posts = array(cg_exchange($a), cg_exchange($b));
    ob_start(); ?>
    <p class="cg-lead">从费用和地区资格出发，并排了解差异。缺少资料表示待核验，不代表不支持。</p>
    <form class="cg-selectors" method="get" action="<?php echo esc_url(home_url('/compare/')); ?>">
    <?php foreach (array('a' => $a, 'b' => $b) as $key => $selected) { ?>
    <label><?php echo $key === 'a' ? '第一家交易所' : '第二家交易所'; ?><select name="<?php echo esc_attr($key); ?>"><?php foreach ($definitions as $slug => $def) { ?><option value="<?php echo esc_attr($slug); ?>" <?php selected($selected, $slug); ?>><?php echo esc_html($def['name']); ?></option><?php } ?></select></label>
    <?php } ?><button class="cg-button" type="submit">开始比较</button></form>
    <div class="cg-table-wrap" role="region" aria-label="交易所比较表" tabindex="0"><table class="cg-table"><caption><?php echo esc_html($definitions[$a]['name'] . ' 与 ' . $definitions[$b]['name']); ?></caption><thead><tr><th scope="col">比较项目</th><th scope="col"><?php echo esc_html($definitions[$a]['name']); ?></th><th scope="col"><?php echo esc_html($definitions[$b]['name']); ?></th></tr></thead><tbody>
    <?php foreach (array('verified' => '资料状态', 'fees' => '费用与条件', 'regions' => '地区与资格') as $key => $title) { ?><tr><th scope="row"><?php echo esc_html($title); ?></th><?php foreach ($posts as $post) { $value = $post ? get_post_meta($post->ID, '_cg_' . $key, true) : ''; if ($key === 'verified') { $value = $value ? '已核验' : '待核验'; } ?><td><?php echo esc_html($value ?: '待核验'); ?></td><?php } ?></tr><?php } ?>
    <tr><th scope="row">进一步了解</th><?php foreach ($posts as $post) { ?><td><?php if ($post) { ?><a href="<?php echo esc_url(get_permalink($post)); ?>">查看完整资料 →</a><?php } else { echo '资料尚未创建'; } ?></td><?php } ?></tr></tbody></table></div>
    <h2>比较分析</h2><?php echo cg_cards('cg_compare', 6); ?>
    <?php return ob_get_clean();
}
add_shortcode('cg_compare', 'cg_compare_shortcode');
add_filter('wp_robots', function ($robots) { if (is_page('compare') && (isset($_GET['a']) || isset($_GET['b']))) { $robots['noindex'] = true; unset($robots['index']); } return $robots; });
add_filter('robots_txt', function ($output) { return $output . "\nDisallow: /go/\n"; });
add_action('wp_enqueue_scripts', function () { wp_enqueue_style('cg-components', plugins_url('assets/components.css', __FILE__), array(), '1.0.0'); });

function cg_setup_pages($set_home = false) {
    $messages = array();
    foreach (cg_definitions() as $slug => $definition) {
        if (!get_page_by_path($slug, OBJECT, 'cg_exchange')) {
            $id = wp_insert_post(array('post_type'=>'cg_exchange','post_status'=>'publish','post_name'=>$slug,'post_title'=>$definition['name'],'post_excerpt'=>'','post_content'=>''), true);
            if (is_wp_error($id)) { $messages[] = $id->get_error_message(); }
            else { update_post_meta($id, '_cg_created', '1'); }
        }
    }
    $pages = array(
        'home' => array('首页', ''),
        'compare' => array('交易所比较', '[cg_compare]'),
        'best' => array('推荐榜单', '[cg_content type="rankings"]'),
        'guides' => array('实用指南', '[cg_content type="guides"]'),
        'tokenized-stocks' => array('代币化股票', '<p>专题内容尚在核验中。后续会分别说明发行主体、持有人权利、托管、赎回机制与地区条件，不把所有代币化产品视为相同的股票权益。</p>'),
        'disclosure' => array('推荐与商业披露', '<p>本站部分入口为推荐链接。通过这些入口注册，我们可能获得佣金。优惠与资格以交易所官方条款为准。赞助内容会明确标识，编辑结论不因推荐佣金自动改变。</p><p>本站不提供投资建议。加密资产可能损失全部本金。请核实所在地法律与拟使用产品的资格条件。</p>'),
        'methodology' => array('比较方法', '<p>我们从费用及其条件、地区资格、产品与使用流程出发比较交易所。未知资料显示待核验，核验信息附来源与日期。</p><p>当前未发布量化评分或排名。后续榜单应注明适用场景、候选范围、指标及方法版本。</p>'),
    );
    foreach ($pages as $slug => $data) {
        $existing = get_page_by_path($slug);
        if ($existing && !get_post_meta($existing->ID, '_cg_created', true)) { $messages[] = '已保留已有页面 /' . $slug . '/，未覆盖。'; continue; }
        $id = $existing ? $existing->ID : wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_name'=>$slug,'post_title'=>$data[0],'post_content'=>$data[1]), true);
        if (is_wp_error($id)) { $messages[] = $id->get_error_message(); continue; }
        update_post_meta($id, '_cg_created', '1');
        if ($slug === 'home') { update_option('cg_home_id', $id); if ($set_home) { update_option('show_on_front', 'page'); update_option('page_on_front', $id); } }
    }
    flush_rewrite_rules(); return $messages;
}
add_action('admin_menu', function () { add_menu_page('网站管理', '网站管理', 'manage_options', 'cg-setup', 'cg_admin_page', 'dashicons-admin-site-alt3', 3); });
function cg_admin_page() {
    if (!current_user_can('manage_options')) { return; }
    ?><div class="wrap"><h1>网站管理 · 初始化与导航</h1><p>管理综合数字金融网站的基础页面、平台资料和导航。初始化会创建六家平台记录，前台导航仅显示已确认的四家；重复运行不覆盖已有资料、文章或推荐链接。</p>
    <?php if (isset($_GET['cg_done'])) { ?><div class="notice notice-success"><p>初始化已完成。打开「交易所资料」填写信息，或访问网站查看结果。</p></div><?php } ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('cg_setup'); ?><input type="hidden" name="action" value="cg_setup">
    <p><label>站点展示名称 <input name="cg_brand" value="<?php echo esc_attr(get_option('cg_brand', '交易所指南')); ?>" maxlength="40"></label></p>
    <p><label><input type="checkbox" name="cg_set_home" value="1"> 将新建的「首页」设为网站首页（仅对你当前这个网站生效）</label></p>
    <?php submit_button('创建基础页面并保存名称'); ?></form>
    <h2>导航与内容分类</h2><p>创建三个一级导航和主题分类。交易所顺序为 Binance / OKX / Bitget / Bybit / 交易所对比。MEXC、Gate 数据与推荐配置保留。没有文章的主题显示为不可点击标签，不发布空分类页。</p><p>应用后会将本插件管理的新菜单指定为主导航，旧菜单保留；自定义品牌名称保留。重复应用只替换本功能创建的菜单项。</p>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('cg_navigation'); ?><input type="hidden" name="action" value="cg_navigation"><?php submit_button('应用已确认的导航与定位'); ?></form>
    <?php if(isset($_GET['cg_navigation_done'])){ ?><p>导航配置已处理，请检查上方错误提示及「外观 → 菜单」。</p><?php } ?>
    <h2>接下来在哪里编辑</h2><ol><li>交易所资料：编辑费用、资格、资料来源和推荐链接。</li><li>交易所管理：维护评测、对比与榜单。美股代币、加密货币：按主题管理文章；专题与指南：维护已有指南。核验后再发布。</li><li>推荐按钮：在文章添加短代码块，输入 <code>[cg_affiliate exchange="binance"]</code>。</li></ol>
    <p>LiteSpeed Cache 或其他缓存插件请排除 <code>/go/</code> 与比较页。测试活动时清除旧缓存。当前插件只记录累计推荐点击，不代表注册量。</p>
    <p>正式上线前，请填写真实资料、设置正式域名，完善隐私与联系页面，再关闭 WordPress 的「建议搜索引擎不索引本站」。不要公开未核实的费用或优惠。</p>
    <p>停用不会删除你的内容；不要删除数据库或进行一键重置。</p></div><?php
}
add_action('admin_post_cg_setup', function () {
    if (!current_user_can('manage_options')) { wp_die('没有权限。', '', array('response'=>403)); }
    check_admin_referer('cg_setup');
    $brand = sanitize_text_field(wp_unslash($_POST['cg_brand'] ?? '交易所指南')); $brand = mb_substr($brand ?: '交易所指南', 0, 40); update_option('cg_brand', $brand); update_option('blogname', $brand);
    $messages = cg_setup_pages(isset($_POST['cg_set_home']));
    if ($messages) { set_transient('cg_error_' . get_current_user_id(), implode(' ', $messages), 120); }
    wp_safe_redirect(admin_url('admin.php?page=cg-setup&cg_done=1')); exit;
});
