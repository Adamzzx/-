<?php
if(!defined('ABSPATH')){exit;}
function cg_review_breadcrumbs($id){
    $items=array(array('name'=>'首页','url'=>home_url('/')));$platform=cg_article_exchange($id);
    $primary=get_term((int)get_post_meta($id,'_cg_primary_topic',true),'cg_topic');
    if(cg_is_crypto_topic($primary)&&has_term($primary->term_id,'cg_topic',$id)){$items=cg_crypto_breadcrumbs($primary);$items[]=array('name'=>get_the_title($id),'url'=>get_permalink($id));return $items;}
    if($platform){$items[]=array('name'=>'交易所','url'=>get_post_type_archive_link('cg_exchange'));$items[]=array('name'=>$platform->post_title,'url'=>get_permalink($platform));}
    else{$terms=wp_get_object_terms($id,'cg_topic');if(!is_wp_error($terms)){
        $crypto=array_values(array_filter($terms,'cg_is_crypto_topic'));
        usort($crypto,fn($a,$b)=>count(get_ancestors($b->term_id,'cg_topic','taxonomy'))<=>count(get_ancestors($a->term_id,'cg_topic','taxonomy')));
        if($crypto){$items=cg_crypto_breadcrumbs($crypto[0]);}
        else{foreach($terms as $term){if(cg_topic_has_content($term->term_id)){$items[]=array('name'=>$term->name,'url'=>get_term_link($term));break;}}}
    }}
    $items[]=array('name'=>get_the_title($id),'url'=>get_permalink($id));return $items;

}
function cg_review_description($id){$custom=get_post_meta($id,'_cg_review_description',true);$post=get_post($id);return wp_strip_all_tags($custom?:($post->post_excerpt?:wp_trim_words(strip_shortcodes($post->post_content),40,'…')));}
// Rank Math may be installed before its setup wizard enables the frontend output.
function cg_review_external_seo(){return defined('WPSEO_VERSION')||defined('AIOSEO_VERSION')||(defined('RANK_MATH_VERSION')&&has_action('rank_math/head'));}
add_action('wp_head',function(){
    $id=get_queried_object_id();if(!is_singular()||!cg_uses_article_layout($id)||get_post_field('post_password',$id)||get_post_status($id)!=='publish'){return;}
    // Keep WordPress canonical output; yield custom graph/meta to known SEO plugins.
    if(cg_review_external_seo()||!apply_filters('cg_review_builtin_seo',true,$id)){return;}
    $post=get_post($id);$description=cg_review_description($id);$url=get_permalink($id);$image=get_the_post_thumbnail_url($id,'full');$title=get_the_title($id);
    if($description){echo '<meta name="description" content="'.esc_attr($description).'">';}
    foreach(array('og:type'=>'article','og:title'=>$title,'og:description'=>$description,'og:url'=>$url,'og:site_name'=>get_bloginfo('name'),'article:published_time'=>get_post_time('c',true,$post),'article:modified_time'=>get_post_modified_time('c',true,$post)) as $key=>$value){if($value){echo '<meta property="'.esc_attr($key).'" content="'.esc_attr($value).'">';}}
    echo '<meta name="twitter:card" content="'.($image?'summary_large_image':'summary').'">';
    if($image){echo '<meta property="og:image" content="'.esc_url($image).'">';}
    $article=array('@type'=>'Article','@id'=>$url.'#article','headline'=>$title,'description'=>$description,'mainEntityOfPage'=>$url,'datePublished'=>get_post_time('c',true,$post),'dateModified'=>get_post_modified_time('c',true,$post),'inLanguage'=>get_bloginfo('language'),'author'=>array('@type'=>'Person','name'=>get_the_author_meta('display_name',$post->post_author),'url'=>get_author_posts_url($post->post_author)),'publisher'=>array('@type'=>'Organization','name'=>get_bloginfo('name'),'url'=>home_url('/')));
    if($image){$article['image']=array($image);}$list=array();foreach(cg_review_breadcrumbs($id) as $i=>$item){$list[]=array('@type'=>'ListItem','position'=>$i+1,'name'=>$item['name'],'item'=>$item['url']);}
    echo '<script type="application/ld+json">'.wp_json_encode(array('@context'=>'https://schema.org','@graph'=>array($article,array('@type'=>'BreadcrumbList','@id'=>$url.'#breadcrumb','itemListElement'=>$list))),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE).'</script>';
},20);
