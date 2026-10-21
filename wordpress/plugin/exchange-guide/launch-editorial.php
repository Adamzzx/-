<?php
/** Exact, reviewable repairs for existing editorial content. No market data is generated. */
if (!defined('ABSPATH')) { exit; }

function cg_launch_legacy_article_paths() {
    return array(
        '/币安-binance-使用评测（2026）：手续费、出入金、理财与/' => 'binance-review-2026',
        '/币安-binance-使用评测（2026）：手续费、出入金、理财与-2/' => 'binance-review-2026',
        '/币安-binance-vs-欧易-okx（2026）：安全性、手续费、美股与理财/' => 'binance-vs-okx-2026',
    );
}

function cg_launch_article_target($slug) {
    $post = get_page_by_path($slug, OBJECT, array('post','cg_review','cg_compare','cg_guide'));
    if (!$post || $post->post_status !== 'publish' || $post->post_password !== '') { return null; }
    if (stripos($post->post_title, 'binance') === false && mb_strpos($post->post_title, '币安') === false) { return null; }
    $comparison = stripos($post->post_title, 'okx') !== false || mb_strpos($post->post_title, '欧易') !== false;
    if (($slug === 'binance-vs-okx-2026') !== $comparison) { return null; }
    return $post;
}

function cg_launch_internal_legacy_target($href) {
    $href = html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $parts = wp_parse_url($href);
    if (!is_array($parts) || !empty($parts['query']) || isset($parts['user']) || isset($parts['pass'])) { return ''; }
    if (!empty($parts['host']) && strtolower($parts['host']) !== strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST))) { return ''; }
    if (isset($parts['scheme']) && !in_array(strtolower($parts['scheme']), array('http','https'), true)) { return ''; }
    $path = rawurldecode($parts['path'] ?? '');
    $home_path = untrailingslashit((string) wp_parse_url(home_url('/'), PHP_URL_PATH));
    if ($home_path && str_starts_with($path, $home_path . '/')) { $path = substr($path, strlen($home_path)); }
    $path = trailingslashit($path);
    $paths = cg_launch_legacy_article_paths();
    $target = isset($paths[$path]) ? cg_launch_article_target($paths[$path]) : null;
    if (!$target) { return ''; }
    return get_permalink($target) . (!empty($parts['fragment']) ? '#' . $parts['fragment'] : '');
}

// Exact aliases only. Unknown Chinese paths and deleted demos retain a real 404.
add_action('template_redirect', function () {
    if (is_admin() || is_preview() || is_feed()) { return; }
    $request = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
    $target = cg_launch_internal_legacy_target($request);
    if ($target) { wp_safe_redirect($target, 301, 'DegenDeer'); exit; }
}, 0);

function cg_launch_wallet_alt_definitions() {
    // These six existing images were individually viewed during the public audit.
    return array(
        'wallet-security-01-custody.webp' => array('交易所托管、手机自托管与硬件钱包的密钥管理方式对比', '3d740a85e8f3e8f6c12e082f7d42f56369471c1ea1bd24ee7312d064edd1102a'),
        'wallet-security-02-shared-backup.webp' => array('一组助记词关联三个派生账户的示意图', 'ce22622e94cb3facc69ac7851bb7c6ae3b36b2ae52e290f87a668b94622490ce'),
        'wallet-security-03-okx-backup.webp' => array('OKX Wallet 从九宫格进入钱包备份并选择钱包的三屏界面', 'aa71de775611009da14fd986eb50889dbc8a09d6242388624bf4a9ec7016860d'),
        'wallet-security-04-okx-receive-network.webp' => array('OKX Wallet 从首页打开不同网络的钱包地址列表', 'ced953ec4e98f8e0786a7e368e925dafc980b612040664d33363d399d38172e6'),
        'wallet-security-05-okx-send-address.webp' => array('OKX Wallet 发送入口与接收地址确认页面', '9e336503e73ce3e32c9f6d58d4212f33c1a82a97a3e0e8e7c2a84c0576ab99d8'),
        'wallet-security-06-approval-limits.webp' => array('普通代币支出授权100与1,000 USDT的额度对比示意', 'f8d09ad78d9723c292c91d87af8959109bf4db47748e8883d12410f282dd0dcf'),
    );
}

function cg_launch_wallet_image($processor) {
    if ($processor->get_tag() !== 'IMG') { return null; }
    $src = (string) $processor->get_attribute('src');
    $name = wp_basename((string) wp_parse_url($src, PHP_URL_PATH));
    $definitions = cg_launch_wallet_alt_definitions();
    if (!isset($definitions[$name])) { return null; }
    $id = attachment_url_to_postid($src);
    if (!$id && preg_match('/(?:^|\s)wp-image-(\d+)(?:\s|$)/', (string) $processor->get_attribute('class'), $m)) { $id = (int) $m[1]; }
    $file = $id ? get_attached_file($id) : '';
    // IDs alone are never evidence. Only the audited media bytes can receive these descriptions.
    if (!$id || !wp_attachment_is_image($id) || !$file || wp_basename($file) !== $name || !is_readable($file) || hash_file('sha256', $file) !== $definitions[$name][1]) { return array('name'=>$name, 'skip'=>'原图与审计文件不能匹配，请人工看图后补充 alt。'); }
    return array('name'=>$name, 'id'=>$id, 'alt'=>$definitions[$name][0]);
}

function cg_launch_editorial_changes($post) {
    $processor = new WP_HTML_Tag_Processor($post->post_content);
    $links = 0; $image_alts = 0; $media = array(); $block_alts = array(); $skipped = array();
    while ($processor->next_tag()) {
        if ($processor->get_tag() === 'A') {
            $href = $processor->get_attribute('href');
            $target = is_string($href) ? cg_launch_internal_legacy_target($href) : '';
            if ($target && $target !== $href) { $processor->set_attribute('href', $target); $links++; }
        }
        if ($post->post_name !== 'crypto-wallet-security-guide-2026') { continue; }
        $image = cg_launch_wallet_image($processor);
        if (!$image) { continue; }
        if (isset($image['skip'])) { $skipped[$image['name']] = $image['skip']; continue; }
        if (trim((string) $processor->get_attribute('alt')) === '') { $processor->set_attribute('alt', $image['alt']); $image_alts++; }
        $block_alts[$image['id']] = (string) $processor->get_attribute('alt');
        if (get_post_meta($image['id'], '_wp_attachment_image_alt', true) === '') { $media[$image['id']] = $image['alt']; }
    }
    $content = $processor->get_updated_html();
    // Keep native image block attributes in sync without parsing/reserializing the whole article.
    if ($post->post_name === 'crypto-wallet-security-guide-2026' && $block_alts) {
        $content = preg_replace_callback('/<!-- wp:image (\{[^\r\n]*\}) -->/', function ($m) use ($block_alts) {
            $attrs = json_decode($m[1], true);
            $id = is_array($attrs) ? absint($attrs['id'] ?? 0) : 0;
            if (!$id || !isset($block_alts[$id]) || !empty($attrs['alt'])) { return $m[0]; }
            // Core image blocks store alt on the image HTML; only amend an existing optional alt attribute.
            if (!array_key_exists('alt', $attrs)) { return $m[0]; }
            $attrs['alt'] = $block_alts[$id];
            return '<!-- wp:image ' . wp_json_encode($attrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ' -->';
        }, $content);
    }
    return array('content'=>$content, 'links'=>$links, 'image_alts'=>$image_alts, 'media'=>$media, 'skipped'=>$skipped);
}

function cg_launch_editorial_plan() {
    $posts = get_posts(array('post_type'=>array('post','cg_review','cg_compare','cg_guide','cg_ranking'), 'post_status'=>'publish', 'has_password'=>false, 'posts_per_page'=>-1, 'post_name__in'=>array('binance-review-2026','okx-review-2026','bitget-review-2026','bybit-review-2026','binance-vs-okx-2026','crypto-beginners-guide-2026','crypto-wallet-security-guide-2026')));
    $plan = array();
    foreach ($posts as $post) {
        $change = cg_launch_editorial_changes($post);
        if ($change['links'] || $change['image_alts'] || $change['media'] || $change['skipped']) { $plan[$post->ID] = array('title'=>$post->post_title, 'slug'=>$post->post_name, 'links'=>$change['links'], 'image_alts'=>$change['image_alts'], 'media_alts'=>count($change['media']), 'skipped'=>$change['skipped']); }
    }
    return $plan;
}

function cg_launch_repair_editorial() {
    if (!current_user_can('manage_options')) { return new WP_Error('forbidden', '没有权限。'); }
    $result = array('changed_posts'=>array(), 'links'=>0, 'image_alts'=>0, 'media_alts'=>0, 'skipped'=>array());
    foreach (cg_launch_editorial_plan() as $id => $row) {
        if (!current_user_can('edit_post', $id)) { $result['skipped'][$id] = '无文章编辑权限。'; continue; }
        $post = get_post($id); $change = cg_launch_editorial_changes($post);
        if ($change['content'] !== $post->post_content) {
            $saved = wp_update_post(wp_slash(array('ID'=>$id, 'post_content'=>$change['content'])), true);
            if (is_wp_error($saved)) { $result['skipped'][$id] = $saved->get_error_message(); continue; }
            $result['changed_posts'][] = $id;
            $result['links'] += $change['links']; $result['image_alts'] += $change['image_alts'];
        }
        foreach ($change['media'] as $media_id => $alt) { if (get_post_meta($media_id, '_wp_attachment_image_alt', true) === '') { update_post_meta($media_id, '_wp_attachment_image_alt', $alt); $result['media_alts']++; } }
        if ($change['skipped']) { $result['skipped'][$id] = $change['skipped']; }
    }
    update_option('cg_launch_editorial_log', array('time'=>current_time('mysql', true), 'result'=>$result), false);
    return $result;
}
