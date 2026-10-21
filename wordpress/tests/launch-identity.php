<?php
// Run with wp eval-file only in the disposable local WordPress.
if (home_url() !== 'http://localhost:8088') { throw new RuntimeException('Disposable local test only'); }
function launch_identity_check($ok, $label) {
    if (!$ok) { throw new RuntimeException($label); }
    echo "PASS: $label\n";
}
$old_user = get_current_user_id();
$option_keys = array('blogname', 'blogdescription', 'cg_brand', 'cg_tagline', 'default_comment_status', 'default_ping_status', 'rank-math-options-titles', 'wp_page_for_privacy_policy');
$old_options = array();
foreach ($option_keys as $key) { $old_options[$key] = get_option($key, null); }
$created = array();
$moved = array();
try {
    foreach (array('cg_launch_apply_identity', 'cg_launch_page_drafts', 'cg_launch_prepare_page_drafts', 'cg_launch_footer_links', 'cg_launch_rankmath_graph') as $function) {
        launch_identity_check(function_exists($function), 'identity API exists: ' . $function);
    }
    $admin = get_user_by('login', 'smoke-admin');
    launch_identity_check($admin instanceof WP_User, 'local administrator exists');
    wp_set_current_user(0);
    $before = get_option('blogname');
    launch_identity_check(is_wp_error(cg_launch_apply_identity()) && is_wp_error(cg_launch_prepare_page_drafts()) && get_option('blogname') === $before, 'anonymous requests cannot apply identity or create drafts');
    wp_set_current_user($admin->ID);
    update_option('rank-math-options-titles', array('preserve_manual_setting' => 'untouched', 'knowledgegraph_type' => 'company'));
    $article = wp_insert_post(array('post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'Launch identity article fixture', 'post_content' => 'Original body.'));
    $created[] = $article;
    update_post_meta($article, 'rank_math_title', 'Original manual SEO title');
    update_post_meta($article, 'rank_math_description', 'Original manual SEO description');
    $applied = cg_launch_apply_identity();
    $titles = get_option('rank-math-options-titles');
    launch_identity_check(!is_wp_error($applied) && get_option('blogname') === 'DegenDeer 鹿研社' && get_option('cg_brand') === 'DegenDeer 鹿研社' && get_option('blogdescription') === '加密货币与美股入门、交易所评测与资产安全', 'controlled helper writes only the confirmed brand and tagline');
    launch_identity_check($titles['knowledgegraph_type'] === 'person' && $titles['knowledgegraph_name'] === '鹿研社' && $titles['website_name'] === 'DegenDeer 鹿研社' && $titles['website_alternate_name'] === '鹿研社' && $titles['preserve_manual_setting'] === 'untouched', 'real Rank Math option stores the confirmed Person identity while unrelated settings remain');
    if (class_exists('RankMath\\Helper') && function_exists('rank_math')) {
        launch_identity_check(RankMath\Helper::get_settings('titles.website_name') === 'DegenDeer 鹿研社' && RankMath\Helper::get_settings('titles.knowledgegraph_type') === 'person', 'Rank Math settings API reads the stored identity immediately');
    }
    launch_identity_check(get_post_meta($article, 'rank_math_title', true) === 'Original manual SEO title' && get_post_meta($article, 'rank_math_description', true) === 'Original manual SEO description' && get_post_field('post_content', $article) === 'Original body.', 'manual article metadata and body remain intact');
    launch_identity_check(get_option('default_comment_status') === 'closed' && get_option('default_ping_status') === 'closed' && !comments_open($article) && !pings_open($article), 'new discussion defaults and public discussion are closed');

    $graph = array(
        'publisher' => array('@type' => array('Person', 'Organization'), '@id' => home_url('/#organization'), 'name' => 'Old site name'),
        'WebSite' => array('@type' => 'WebSite', '@id' => home_url('/#website'), 'name' => 'Old site name', 'publisher' => array('@id' => home_url('/#organization'))),
        'Article' => array('@type' => 'Article', 'headline' => 'Manual article headline', 'description' => 'Manual article description', 'author' => array('@id' => home_url('/#author')), 'publisher' => array('@id' => home_url('/#organization'))),
        'author' => array('@type' => 'Person', '@id' => home_url('/#author'), 'name' => 'Actual article author'),
        'third_party' => array('@type' => 'Organization', '@id' => 'https://example.org/#organization', 'name' => 'Official source'),
    );
    $fixed = cg_launch_rankmath_graph($graph);
    launch_identity_check($fixed['publisher']['@type'] === 'Person' && $fixed['publisher']['name'] === '鹿研社' && $fixed['publisher']['@id'] === $graph['publisher']['@id'] && $fixed['WebSite']['name'] === 'DegenDeer 鹿研社', 'schema updates the site name and personal publisher while preserving entity IDs');
    launch_identity_check($fixed['Article'] === $graph['Article'] && $fixed['author'] === $graph['author'] && $fixed['third_party'] === $graph['third_party'], 'schema leaves article metadata, actual authors and external organizations unchanged');
    $wrapped = cg_launch_rankmath_graph(array('@context' => 'https://schema.org', '@graph' => array_values($graph)));
    launch_identity_check($wrapped['@graph'][0]['@type'] === 'Person' && $wrapped['@graph'][1]['name'] === 'DegenDeer 鹿研社', 'schema also supports numeric @graph output');
    launch_identity_check(apply_filters('rank_math/opengraph/facebook/og_site_name', 'Old name') === 'DegenDeer 鹿研社', 'Rank Math OG site name uses confirmed branding');

    // Isolate draft paths without overwriting any existing local page content.
    foreach (array('about', 'contact', 'privacy-policy') as $slug) {
        $existing = get_page_by_path($slug, OBJECT, 'page');
        if ($existing) {
            $moved[$existing->ID] = $existing->post_name;
            wp_update_post(array('ID' => $existing->ID, 'post_name' => 'launch-identity-held-' . $existing->ID));
        }
    }
    update_option('wp_page_for_privacy_policy', 0);
    $drafts = cg_launch_prepare_page_drafts();
    foreach ($drafts as $slug => $result) {
        $created[] = $result['id'];
        $page = get_post($result['id']);
        launch_identity_check($result['result'] === 'created' && $page->post_type === 'page' && $page->post_status === 'draft' && $page->post_name === $slug && strpos($page->post_content, 'hello@degendeer.com') !== false, 'native editable draft created: ' . $slug);
    }
    $again = cg_launch_prepare_page_drafts();
    foreach ($again as $slug => $result) {
        launch_identity_check($result['result'] === 'preserved' && $result['id'] === $drafts[$slug]['id'] && !empty($result['proposed_content']), 'repeated apply preserves the page and offers reviewable proposed copy: ' . $slug);
    }
    launch_identity_check(strpos(get_post_field('post_content', $drafts['privacy-policy']['id']), '日志的实际保存期限依服务配置及服务商规则而定') !== false, 'privacy draft does not invent log retention or promise no data processing');
    $definitions = cg_launch_page_drafts();
    if (!function_exists('cg_baidu_analytics_enabled') || !cg_baidu_analytics_enabled()) {
        launch_identity_check(strpos($definitions['privacy-policy']['post_content'], '尚未启用真实站点统计代码') !== false && strpos($definitions['privacy-policy']['post_content'], 'Cloudflare') === false, 'privacy draft describes Baidu as planned until real analytics is enabled');
    }
    $links = cg_launch_footer_links();
    $urls = array_column($links, 'url');
    launch_identity_check(!in_array(get_permalink($drafts['about']['id']), $urls, true) && !in_array(get_permalink($drafts['contact']['id']), $urls, true) && !in_array(get_permalink($drafts['privacy-policy']['id']), $urls, true), 'footer never links to unpublished launch drafts');
    wp_update_post(array('ID' => $drafts['about']['id'], 'post_status' => 'publish'));
    wp_update_post(array('ID' => $drafts['contact']['id'], 'post_status' => 'publish', 'post_password' => 'private-fixture'));
    $urls = array_column(cg_launch_footer_links(), 'url');
    launch_identity_check(in_array(get_permalink($drafts['about']['id']), $urls, true) && !in_array(get_permalink($drafts['contact']['id']), $urls, true), 'footer includes public pages and excludes password-protected pages');
    $assigned_id = wp_insert_post(array('post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Existing privacy draft', 'post_name' => 'launch-existing-privacy', 'post_content' => 'Keep existing privacy copy.'));
    $created[] = $assigned_id;
    update_option('wp_page_for_privacy_policy', $assigned_id);
    $assigned_results = cg_launch_prepare_page_drafts();
    launch_identity_check($assigned_results['privacy-policy']['id'] === $assigned_id && get_post_field('post_content', $assigned_id) === 'Keep existing privacy copy.', 'assigned existing WordPress privacy draft is preserved with no duplicate or overwrite');

    if (!is_admin()) {
        launch_identity_check(apply_filters('script_loader_tag', '<script>reach</script>', 'hostinger-reach-embed') === '' && apply_filters('script_loader_tag', '<script>keep</script>', 'unrelated-script') === '<script>keep</script>', 'frontend script filter removes only the unused Reach embed');
        wp_enqueue_script('hostinger-reach-embed', 'https://cdn-reach.hostinger.com/js/embed.js');
        cg_launch_remove_reach_embed();
        launch_identity_check(!wp_script_is('hostinger-reach-embed', 'enqueued'), 'Reach script is dequeued on public requests');
        wp_deregister_script('hostinger-reach-embed');
    }
} finally {
    foreach (array_unique($created) as $id) { wp_delete_post($id, true); }
    foreach ($moved as $id => $slug) { wp_update_post(array('ID' => $id, 'post_name' => $slug)); }
    foreach ($old_options as $key => $value) {
        if ($value === null) { delete_option($key); } else { update_option($key, $value); }
    }
    if (function_exists('rank_math') && isset(rank_math()->settings) && is_callable(array(rank_math()->settings, 'reset'))) {
        rank_math()->settings->reset();
    }
    wp_set_current_user($old_user);
}
