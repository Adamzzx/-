<?php
if (!defined('ABSPATH')) { exit; }
add_action('after_setup_theme', function () { add_theme_support('title-tag'); add_theme_support('post-thumbnails'); add_theme_support('responsive-embeds'); add_theme_support('html5', array('search-form','comment-form','comment-list','gallery','caption','style','script')); register_nav_menus(array('primary'=>'主导航')); });
add_action('wp_enqueue_scripts', function () { wp_enqueue_style('cg-theme', get_stylesheet_uri(), array(), '1.4.0'); });
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
        if($has_children){$output.='<button class="cg-submenu-toggle" aria-expanded="false" aria-label="展开'.esc_attr($item->title).'菜单"><svg class="cg-chevron" width="16" height="16" viewBox="0 0 20 20" aria-hidden="true"><path d="m4 7 6 6 6-6" fill="none" stroke="currentColor" stroke-width="2"/></svg></button>';}
    }
    public function start_lvl(&$output,$depth=0,$args=null){$output.='<ul class="sub-menu">';}
    public function end_lvl(&$output,$depth=0,$args=null){$output.='</ul>';}
    public function end_el(&$output,$item,$depth=0,$args=null){$output.='</li>';}
}
function cg_theme_default_navigation(){
    echo '<ul class="cg-menu">';
    foreach(cg_theme_navigation_groups() as $group){
        $pending=!empty($group['term'])&&!cg_topic_has_content($group['term']->term_id);
        echo '<li class="cg-nav-item cg-has-children">';
        echo $group['url']&&!$pending?'<a href="'.esc_url($group['url']).'">'.esc_html($group['title']).'</a>':'<span class="cg-nav-label">'.esc_html($group['title']).'</span>';
        echo '<button class="cg-submenu-toggle" aria-expanded="false" aria-label="展开'.esc_attr($group['title']).'菜单"><svg class="cg-chevron" width="16" height="16" viewBox="0 0 20 20" aria-hidden="true"><path d="m4 7 6 6 6-6" fill="none" stroke="currentColor" stroke-width="2"/></svg></button><ul class="sub-menu">';
        foreach($group['children'] as $child){$pending=!empty($child['term'])&&!cg_topic_has_content($child['term']->term_id);echo '<li>';echo $child['url']&&!$pending?'<a href="'.esc_url($child['url']).'">'.esc_html($child['title']).'</a>':'<span class="cg-nav-label" title="有已发布内容后开放">'.esc_html($child['title']).'</span>';echo '</li>';}
        echo '</ul></li>';
    }
    echo '</ul>';
}
add_action('wp_enqueue_scripts',function(){wp_enqueue_script('cg-navigation',get_template_directory_uri().'/navigation.js',array(),'1.4.0',true);});

// Home modules read published editorial content; no generated placeholder articles.
function cg_media_section($title,$args,$label='编辑内容',$heading=true){
    $posts=get_posts(array_merge(array('post_status'=>'publish','posts_per_page'=>3,'ignore_sticky_posts'=>true),$args));
    if(!$posts){return;}
    echo '<section class="cg-media-section">';
    if($heading){echo '<div class="cg-section-head"><h2>'.esc_html($title).'</h2></div>';}
    echo '<div class="cg-media-grid">';
    foreach($posts as $entry){
        echo '<article class="cg-story"><a class="cg-story-link" href="'.esc_url(get_permalink($entry)).'">';
        if(has_post_thumbnail($entry)){echo get_the_post_thumbnail($entry,'medium_large',array('loading'=>'lazy','class'=>'cg-story-image'));}
        echo '<div class="cg-story-body"><span class="cg-story-label">'.esc_html($label).'</span><h3>'.esc_html($entry->post_title).'</h3>';
        $excerpt=wp_trim_words(wp_strip_all_tags(strip_shortcodes($entry->post_excerpt?:$entry->post_content)),32,'…');
        if($excerpt){echo '<p>'.esc_html($excerpt).'</p>';}
        echo '<span class="cg-meta">'.esc_html(get_the_author_meta('display_name',$entry->post_author)).' · '.esc_html(get_the_date('Y-m-d',$entry)).'</span></div></a></article>';
    }
    echo '</div></section>';
}
function cg_media_topic($slug){
    $term=taxonomy_exists('cg_topic')?get_term_by('slug',$slug,'cg_topic'):false;
    if(!$term){return;}
    cg_media_section('',array('post_type'=>array('post','cg_review','cg_compare','cg_ranking','cg_guide'),'posts_per_page'=>3,'tax_query'=>array(array('taxonomy'=>'cg_topic','field'=>'term_id','terms'=>$term->term_id,'include_children'=>true))),$term->name,false);
    $children=get_terms(array('taxonomy'=>'cg_topic','parent'=>$term->term_id,'hide_empty'=>false));
    if(!is_wp_error($children)){echo '<div class="cg-topic-links">';foreach($children as $child){if(function_exists('cg_topic_has_content')&&cg_topic_has_content($child->term_id)){echo '<a href="'.esc_url(get_term_link($child)).'">'.esc_html($child->name).' →</a>';}else{echo '<span>'.esc_html($child->name).'</span>';}}echo '</div>';}
}

function cg_theme_navigation_groups(){
    if(function_exists('cg_navigation_groups')){return cg_navigation_groups();}
    $exchanges=array();
    foreach(array('Binance','OKX','Bitget','Bybit','交易所对比') as $label){$exchanges[]=array('title'=>$label,'url'=>'');}
    return array(
        array('title'=>'交易所','url'=>'','children'=>$exchanges),
        array('title'=>'美股代币','url'=>home_url('/#tokenized-assets'),'children'=>array(array('title'=>'交易指南','url'=>''),array('title'=>'出入金指南','url'=>''))),
        array('title'=>'加密货币','url'=>home_url('/#crypto-investing'),'children'=>array(array('title'=>'新手入门','url'=>''),array('title'=>'钱包与资产安全','url'=>''),array('title'=>'出入金','url'=>''),array('title'=>'加密货币理财','url'=>''))),
    );
}
function cg_theme_registration_cards(){
    $html='<div class="cg-registration-cards">';
    foreach(array('binance'=>'Binance','okx'=>'OKX','bitget'=>'Bitget','bybit'=>'Bybit') as $slug=>$label){
        $exchange=function_exists('cg_exchange')?cg_exchange($slug):null;
        $image=$exchange?(int)get_post_meta($exchange->ID,'_cg_banner_attachment',true):0;
        $url=function_exists('cg_registration_url')?cg_registration_url($slug):($exchange?home_url('/register/'.$slug.'/'):home_url('/#cg-registration-conditions'));
        $html.='<a class="cg-registration-card cg-banner-'.$slug.'" href="'.esc_url($url).'">';
        if($image&&in_array(get_post_mime_type($image),array('image/jpeg','image/png','image/webp','image/avif'),true)){
            $html.=wp_get_attachment_image($image,'medium_large',false,array('alt'=>$label.' 注册说明','loading'=>'lazy','decoding'=>'async'));
        }else{
            $html.='<span class="cg-registration-label">'.esc_html($label).'</span><strong>开户注册 · 条件与指南</strong><span class="cg-registration-action">查看注册说明 <span aria-hidden="true">↗</span></span>';
        }
        $html.='</a>';
    }
    return $html.'</div>';
}
