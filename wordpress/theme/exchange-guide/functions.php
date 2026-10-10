<?php
if (!defined('ABSPATH')) { exit; }
add_action('after_setup_theme', function () { add_theme_support('title-tag'); add_theme_support('post-thumbnails'); add_theme_support('responsive-embeds'); add_theme_support('html5', array('search-form','comment-form','comment-list','gallery','caption','style','script')); register_nav_menus(array('primary'=>'主导航')); });
add_action('wp_enqueue_scripts', function () { wp_enqueue_style('cg-theme', get_stylesheet_uri(), array(), '1.0.0'); });
function cg_theme_brand() { return get_option('cg_brand', '交易所指南'); }
function cg_theme_cards($type, $count = 6) {
    if (function_exists('cg_cards')) { echo cg_cards($type, $count); }
    else { echo '<p>请启用「交易所指南 · 内容与推荐管理」插件，再到后台「交易所指南」创建基础页面。</p>'; }
}
