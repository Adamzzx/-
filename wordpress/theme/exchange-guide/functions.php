<?php
if (!defined('ABSPATH')) { exit; }
add_action('after_setup_theme', function () { add_theme_support('title-tag'); add_theme_support('post-thumbnails'); add_theme_support('responsive-embeds'); add_theme_support('html5', array('search-form','comment-form','comment-list','gallery','caption','style','script')); register_nav_menus(array('primary'=>'主导航')); });
add_action('wp_enqueue_scripts', function () { wp_enqueue_style('cg-theme', get_stylesheet_uri(), array(), '1.1.0'); });
function cg_theme_brand() { return get_option('cg_brand', '加密资产投资与数字金融指南'); }
function cg_theme_cards($type, $count = 6) {
    if (function_exists('cg_cards')) { echo cg_cards($type, $count); }
    else { echo '<p>请启用「交易所指南 · 内容与推荐管理」插件，再到后台「交易所指南」创建基础页面。</p>'; }
}
class CG_Navigation_Walker extends Walker_Nav_Menu {
    public function start_el(&$output,$item,$depth=0,$args=null,$id=0){
        $has_children=in_array('menu-item-has-children',(array)$item->classes,true);
        $term_id=(int)get_post_meta($item->ID,'_cg_topic_id',true);
        $pending=$term_id && function_exists('cg_topic_has_content') && !cg_topic_has_content($term_id);
        $url=$pending?'':$item->url;
        $output.='<li class="cg-nav-item'.($has_children?' cg-has-children':'').'">';
        $output.=$url?'<a href="'.esc_url($url).'">'.esc_html($item->title).'</a>':'<span class="cg-nav-label"'.($pending?' title="有已发布内容后开放"':'').'>'.esc_html($item->title).'</span>';
        if($has_children){$output.='<button class="cg-submenu-toggle" aria-expanded="false" aria-label="展开'.esc_attr($item->title).'菜单">⌄</button>';}
    }
    public function start_lvl(&$output,$depth=0,$args=null){$output.='<ul class="sub-menu">';}
    public function end_lvl(&$output,$depth=0,$args=null){$output.='</ul>';}
    public function end_el(&$output,$item,$depth=0,$args=null){$output.='</li>';}
}
function cg_theme_default_navigation(){
    echo '<ul class="cg-menu">';
    foreach(cg_navigation_groups() as $group){
        $pending=!empty($group['term'])&&!cg_topic_has_content($group['term']->term_id);
        echo '<li class="cg-nav-item cg-has-children">';
        echo $group['url']&&!$pending?'<a href="'.esc_url($group['url']).'">'.esc_html($group['title']).'</a>':'<span class="cg-nav-label">'.esc_html($group['title']).'</span>';
        echo '<button class="cg-submenu-toggle" aria-expanded="false" aria-label="展开'.esc_attr($group['title']).'菜单">⌄</button><ul class="sub-menu">';
        foreach($group['children'] as $child){$pending=!empty($child['term'])&&!cg_topic_has_content($child['term']->term_id);echo '<li>';echo $child['url']&&!$pending?'<a href="'.esc_url($child['url']).'">'.esc_html($child['title']).'</a>':'<span class="cg-nav-label" title="有已发布内容后开放">'.esc_html($child['title']).'</span>';echo '</li>';}
        echo '</ul></li>';
    }
    echo '</ul>';
}
add_action('wp_enqueue_scripts',function(){wp_enqueue_script('cg-navigation',get_template_directory_uri().'/navigation.js',array(),'1.1.0',true);});
