<?php
if (!defined('ABSPATH')) { exit; }
add_action('after_setup_theme', function () { add_theme_support('title-tag'); add_theme_support('post-thumbnails'); add_theme_support('responsive-embeds'); add_theme_support('html5', array('search-form','comment-form','comment-list','gallery','caption','style','script')); register_nav_menus(array('primary'=>'主导航')); });
add_action('wp_enqueue_scripts', function () { wp_enqueue_style('cg-theme', get_stylesheet_uri(), array(), '1.9.0'); });
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
add_action('wp_enqueue_scripts',function(){wp_enqueue_script('cg-navigation',get_template_directory_uri().'/navigation.js',array(),'1.9.0',true);});

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
        $direct=function_exists('cg_go_url');
        $url=$direct?cg_go_url($slug):home_url('/#cg-registration-conditions');
        $recommended=$direct&&cg_active_offer($exchange);
        $html.='<a class="cg-registration-card cg-banner-'.$slug.'" href="'.esc_url($url).'"'.($direct?' target="_blank" rel="'.esc_attr(cg_registration_rel($slug)).'"':'').'>';
        if($image&&in_array(get_post_mime_type($image),array('image/jpeg','image/png','image/webp','image/avif'),true)){
            $html.=wp_get_attachment_image($image,'medium_large',false,array('alt'=>$label.' 注册入口（新标签页）','loading'=>'lazy','decoding'=>'async'));
        }else{
            $html.='<span class="cg-registration-label">'.esc_html($label).'</span><strong>开户注册 · '.($direct?($recommended?'推荐入口':'官方入口'):'入口待配置').'</strong><span class="cg-registration-action">'.($direct?'前往注册（新标签页）':'查看注册前说明').' <span aria-hidden="true">↗</span></span>';
        }
        $html.='</a>';
    }
    return $html.'</div>';
}

function cg_theme_registration_disclosure(){return function_exists('cg_registration_disclosure')?cg_registration_disclosure():'注册跳转尚待配置，请启用新版内容管理插件。地区资格与条款以官方说明为准。';}

function cg_theme_exchange_articles($exchange_id){
    if(!function_exists('cg_exchange_articles')){return;}
    $raw=$_GET['cg_articles_page']??1;$page=is_scalar($raw)?max(1,absint($raw)):1;
    $query=cg_exchange_articles($exchange_id,$page);$label=get_the_title($exchange_id);
    echo '<section id="exchange-articles" class="cg-section cg-exchange-articles"><div class="cg-section-head"><h2>'.esc_html($label).' 相关文章</h2></div><p class="cg-meta">评测、平台对比、出入金教程与使用指南。</p>';
    if(!$query->posts){echo '<p class="cg-empty">'.($page>1?'此页没有相关文章。':'相关文章尚未发布。内容核验完成后会在这里展示。').'</p>';}
    else{
        echo '<div class="cg-media-grid">';
        foreach($query->posts as $entry){
            $type=get_post_type_object($entry->post_type);$terms=wp_get_object_terms($entry->ID,'cg_topic',array('fields'=>'names'));
            echo '<article class="cg-story"><a class="cg-story-link" href="'.esc_url(get_permalink($entry)).'">';
            if(has_post_thumbnail($entry)){echo get_the_post_thumbnail($entry,'medium_large',array('class'=>'cg-story-image','loading'=>'lazy'));}
            echo '<div class="cg-story-body"><span class="cg-story-label">'.esc_html($entry->post_type==='post'?'文章':$type->labels->name).'</span>';
            if(get_post_meta($entry->ID,'_cg_sponsored',true)){echo '<span class="cg-sponsored-label">赞助内容</span>';}
            echo '<h3>'.esc_html($entry->post_title).'</h3>';
            $excerpt=wp_trim_words(wp_strip_all_tags(strip_shortcodes($entry->post_excerpt?:$entry->post_content)),30,'…');
            if($excerpt){echo '<p>'.esc_html($excerpt).'</p>';}
            if(!is_wp_error($terms)&&$terms){echo '<p class="cg-story-topics">'.esc_html(implode(' / ',$terms)).'</p>';}
            echo '<span class="cg-meta">'.esc_html(get_the_author_meta('display_name',$entry->post_author)).' · '.esc_html(get_the_modified_date('Y-m-d',$entry)).'</span></div></a></article>';
        }
        echo '</div>';
    }
    if($query->max_num_pages>1){echo '<nav class="cg-pagination" aria-label="相关文章分页">'.wp_kses_post(paginate_links(array('base'=>add_query_arg('cg_articles_page','%#%',get_permalink($exchange_id)).'#exchange-articles','format'=>'','current'=>$page,'total'=>$query->max_num_pages,'prev_text'=>'上一页','next_text'=>'下一页'))).'</nav>';}
    echo '</section>';
}

function cg_review_related_cards($id){
    $exchange=cg_theme_article_platform($id);if(!$exchange||!function_exists('cg_exchange_articles')){return;}
    $query=cg_exchange_articles($exchange->ID,1,30);$items=array_filter($query->posts,fn($p)=>$p->ID!==$id);$terms=wp_get_object_terms($id,'cg_topic',array('fields'=>'ids'));
    if(!is_wp_error($terms)&&$terms){usort($items,function($a,$b)use($terms){$aa=wp_get_object_terms($a->ID,'cg_topic',array('fields'=>'ids'));$bb=wp_get_object_terms($b->ID,'cg_topic',array('fields'=>'ids'));return count(array_intersect($terms,is_wp_error($bb)?array():$bb))<=>count(array_intersect($terms,is_wp_error($aa)?array():$aa));});}
    if(!$items){return;}echo '<section class="cg-review-related"><h2>相关文章</h2><div class="cg-media-grid">';foreach(array_slice($items,0,3) as $entry){echo '<article class="cg-story"><a class="cg-story-link" href="'.esc_url(get_permalink($entry)).'"><div class="cg-story-body"><h3>'.esc_html($entry->post_title).'</h3><span class="cg-meta">'.esc_html(get_the_modified_date('Y-m-d',$entry)).'</span></div></a></article>';}echo '</div></section>';
}
add_action('wp_enqueue_scripts',function(){if(function_exists('cg_uses_article_layout')&&cg_uses_article_layout(get_queried_object_id())){wp_enqueue_script('cg-review',get_template_directory_uri().'/assets-review.js',array(),'1.9.0',true);}});

function cg_theme_article_platform($id){
    if(function_exists('cg_article_exchange')){return cg_article_exchange($id);}
    if(!get_post_type_object('cg_exchange')){return null;}
    $primary=function_exists('cg_exchange')?cg_exchange(get_post_meta($id,'_cg_review_exchange',true)):null;if($primary){return $primary;}
    foreach((array)get_post_meta($id,'_cg_related_exchanges',true) as $platform_id){$post=get_post(absint($platform_id));if($post&&$post->post_type==='cg_exchange'&&$post->post_status==='publish'){return $post;}}return null;
}
function cg_theme_article_breadcrumbs($id){
    if(function_exists('cg_article_exchange')&&function_exists('cg_review_breadcrumbs')){return cg_review_breadcrumbs($id);}
    $items=array(array('name'=>'首页','url'=>home_url('/')));$platform=cg_theme_article_platform($id);if($platform){$items[]=array('name'=>$platform->post_title,'url'=>get_permalink($platform));}$items[]=array('name'=>get_the_title($id),'url'=>get_permalink($id));return $items;
}
function cg_theme_article_date_valid($date){
    if(function_exists('cg_review_date_valid')){return cg_review_date_valid($date);}
    if(!is_string($date)){return false;}$parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);return $parsed&&$parsed->format('Y-m-d')===$date&&$date<=wp_date('Y-m-d');
}
