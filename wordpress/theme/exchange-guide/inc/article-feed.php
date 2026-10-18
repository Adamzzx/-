<?php
if(!defined('ABSPATH')){exit;}

// Read raw editorial text: excerpts must never run article blocks or shortcodes.
function cg_theme_feed_excerpt($entry,$length=140){
    $text=$entry->post_excerpt!==''?$entry->post_excerpt:$entry->post_content;
    $text=wp_specialchars_decode(wp_strip_all_tags(strip_shortcodes($text)),ENT_QUOTES);
    $text=trim(preg_replace('/\s+/u',' ',$text));
    return wp_html_excerpt($text,max(1,absint($length)),'…');
}

function cg_theme_article_feed($posts){
    echo '<div class="cg-editorial-feed">';
    foreach($posts as $index=>$entry){
        $url=get_permalink($entry);
        $terms=taxonomy_exists('cg_topic')?wp_get_object_terms($entry->ID,'cg_topic'):array();
        echo '<article class="cg-feed-article cg-home-article">';
        echo '<header><h2 class="cg-feed-title"><a href="'.esc_url($url).'">'.esc_html(get_the_title($entry)).'</a></h2>';
        echo '<div class="cg-feed-meta"><time datetime="'.esc_attr(get_the_date('c',$entry)).'">'.esc_html(get_the_date('Y-m-d',$entry)).'</time>';
        $author=get_the_author_meta('display_name',$entry->post_author);
        if($author){echo '<span class="cg-feed-author">'.esc_html($author).'</span>';}
        if(!is_wp_error($terms)&&$terms){
            echo '<span class="cg-feed-topics">';
            foreach($terms as $term){$term_url=get_term_link($term);if(!is_wp_error($term_url)){echo '<a href="'.esc_url($term_url).'">'.esc_html($term->name).'</a>';}}
            echo '</span>';
        }
        if(get_post_meta($entry->ID,'_cg_sponsored',true)){echo '<span class="cg-feed-sponsored">赞助内容</span>';}
        echo '</div></header>';
        if(has_post_thumbnail($entry)){
            echo '<a class="cg-feed-cover" href="'.esc_url($url).'" tabindex="-1" aria-hidden="true">'.get_the_post_thumbnail($entry,'large',array('class'=>'cg-feed-image','loading'=>$index===0?'eager':'lazy','decoding'=>'async')).'</a>';
        }
        $excerpt=cg_theme_feed_excerpt($entry);
        if($excerpt!==''){echo '<p class="cg-feed-excerpt">'.esc_html($excerpt).'</p>';}
        echo '<a class="cg-feed-readmore" href="'.esc_url($url).'">阅读全文 <span aria-hidden="true">→</span><span class="screen-reader-text">：'.esc_html(get_the_title($entry)).'</span></a></article>';
    }
    echo '</div>';
}
