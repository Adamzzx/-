<?php
if(!defined('ABSPATH')){exit;}

// Shared by exchange hubs and comparison lists; never execute article shortcodes here.
function cg_article_cards_markup($posts){
    if(!$posts){return '';}
    $html='<div class="cg-media-grid">';
    foreach($posts as $entry){
        $type=get_post_type_object($entry->post_type);$terms=wp_get_object_terms($entry->ID,'cg_topic',array('fields'=>'names'));
        $html.='<article class="cg-story"><a class="cg-story-link" href="'.esc_url(get_permalink($entry)).'">';
        if(has_post_thumbnail($entry)){$html.=get_the_post_thumbnail($entry,'medium_large',array('class'=>'cg-story-image','loading'=>'lazy'));}
        $html.='<div class="cg-story-body"><span class="cg-story-label">'.esc_html($entry->post_type==='post'||!$type?'文章':$type->labels->name).'</span>';
        if(get_post_meta($entry->ID,'_cg_sponsored',true)){$html.='<span class="cg-sponsored-label">赞助内容</span>';}
        $html.='<h3>'.esc_html($entry->post_title).'</h3>';
        $excerpt=wp_trim_words(wp_strip_all_tags(strip_shortcodes($entry->post_excerpt?:$entry->post_content)),30,'…');
        if($excerpt){$html.='<p>'.esc_html($excerpt).'</p>';}
        if(!is_wp_error($terms)&&$terms){$html.='<p class="cg-story-topics">'.esc_html(implode(' / ',$terms)).'</p>';}
        $html.='<span class="cg-meta">'.esc_html(get_the_author_meta('display_name',$entry->post_author)).' · '.esc_html(get_the_modified_date('Y-m-d',$entry)).'</span></div></a></article>';
    }
    return $html.'</div>';
}
