<?php
if (!defined('ABSPATH')) { exit; }
$cg_footer_links = array();
if (function_exists('cg_launch_footer_links')) {
    $cg_footer_links = cg_launch_footer_links();
} else {
    // Keep the theme compatible while the matching plugin update is installed.
    foreach (array('disclosure' => '商业披露与风险说明', 'methodology' => '比较方法', 'about' => '关于鹿研社', 'contact' => '联系与纠错', 'privacy-policy' => '隐私政策') as $slug => $label) {
        $page = get_page_by_path($slug, OBJECT, 'page');
        if ($slug === 'privacy-policy') {
            $privacy_id = (int) get_option('wp_page_for_privacy_policy');
            $assigned = $privacy_id > 0 ? get_post($privacy_id) : null;
            if ($assigned instanceof WP_Post && $assigned->post_type === 'page' && $assigned->post_status === 'publish' && $assigned->post_password === '') { $page = $assigned; }
        }
        if ($page instanceof WP_Post && $page->post_status === 'publish' && $page->post_password === '') {
            $cg_footer_links[] = array('label' => $label, 'url' => get_permalink($page));
        }
    }
}
?></main>
<footer class="cg-footer"><div class="cg-wrap">
<strong>先了解，再选择。</strong>
<p>加密资产可能损失全部本金。请核实所在地与产品资格；本站不提供投资建议。</p>
<p>部分入口可能为推荐链接，我们可能因此获得佣金。赞助内容单独标识，未核验资料不会作为评分依据。</p>
<div class="cg-footer-links"><?php foreach ($cg_footer_links as $link) { ?><a href="<?php echo esc_url($link['url']); ?>"><?php echo esc_html($link['label']); ?></a><?php } ?></div>
<p>© <?php echo esc_html(wp_date('Y')); ?> <?php echo esc_html(cg_theme_brand()); ?></p>
</div></footer><?php wp_footer(); ?></body></html>
