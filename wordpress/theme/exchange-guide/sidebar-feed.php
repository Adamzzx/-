<?php if(!defined('ABSPATH')){exit;} ?>
<aside class="cg-feed-sidebar" aria-label="文章搜索与交易所注册入口">
    <div class="cg-feed-sidebar-search"><?php get_search_form(); ?></div>
    <section class="cg-feed-registration cg-home-registration">
        <h2>交易所注册入口</h2>
        <p class="cg-meta"<?php echo !function_exists('cg_go_url')?' id="cg-registration-conditions"':''; ?>><?php echo esc_html(cg_theme_registration_disclosure()); ?></p>
        <?php echo cg_theme_registration_cards(); ?>
    </section>
</aside>
