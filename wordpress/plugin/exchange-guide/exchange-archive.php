<?php
if(!defined('ABSPATH')){exit;}

function cg_is_exchange_directory($query=null){
    if($query===null){global $wp_query;$query=$wp_query;}
    return $query instanceof WP_Query&&(bool)$query->get('_cg_exchange_directory')&&!$query->is_404();
}
function cg_exchange_directory_intro(){
    return '汇总交易所评测、平台对比与使用教程，帮助你了解产品、费用、出入金和资产安全。按平台查找相关文章，并结合所在地资格与平台条款判断适用性。';
}
function cg_exchange_directory_url($page=1){
    $url=get_post_type_archive_link('cg_exchange');if(!$url){return '';}
    $page=max(1,absint($page));if($page===1){return $url;}
    if(!get_option('permalink_structure')){return add_query_arg('paged',$page,$url);}
    global $wp_rewrite;
    return trailingslashit($url).user_trailingslashit($wp_rewrite->pagination_base.'/'.$page,'paged');
}
function cg_exchange_directory_query_args($page=1,$limit=9){
    return array(
        // Keep the real archive first for WordPress routing and SEO context.
        // The scoped SQL condition below excludes platform records from the results.
        'post_type'=>array('cg_exchange','post','cg_review','cg_compare','cg_ranking','cg_guide'),
        'post_status'=>'publish','has_password'=>false,'ignore_sticky_posts'=>true,
        'posts_per_page'=>max(1,min(30,absint($limit))),'paged'=>max(1,absint($page)),
        'orderby'=>array('date'=>'DESC','ID'=>'DESC'),'suppress_filters'=>false,
        '_cg_exchange_directory'=>true,
    );
}
function cg_exchange_directory_items($page=1,$limit=9){
    return new WP_Query(cg_exchange_directory_query_args($page,$limit));
}
add_action('pre_get_posts',function($query){
    if(is_admin()||!$query->is_main_query()||!$query->is_post_type_archive('cg_exchange')){return;}
    foreach(cg_exchange_directory_query_args(max(1,absint($query->get('paged')))) as $key=>$value){$query->set($key,$value);}
});
add_filter('posts_where',function($where,$query){
    if(!$query->get('_cg_exchange_directory')){return $where;}
    global $wpdb;
    $matches=array();
    foreach(array('binance','okx','bitget','bybit') as $slug){
        $platform=cg_exchange($slug);if(!$platform){continue;}
        $id=(int)$platform->ID;
        // Match serialized array values, including legacy string IDs, never indexes/prefixes.
        $pattern='^a:[0-9]+:[{](i:[0-9]+;(i:[0-9]+;|s:[0-9]+:"[0-9]+";))*i:[0-9]+;(i:'.$id.';|s:[0-9]+:"'.$id.'";)';
        $matches[]=$wpdb->prepare('cg_directory_relation.meta_value REGEXP %s',$pattern);
    }
    $associated=$matches?$wpdb->prepare("EXISTS (SELECT 1 FROM {$wpdb->postmeta} AS cg_directory_relation WHERE cg_directory_relation.post_id = {$wpdb->posts}.ID AND cg_directory_relation.meta_key = %s AND (",'_cg_related_exchanges').implode(' OR ',$matches).'))':'0=1';
    $comparison=$wpdb->prepare("({$wpdb->posts}.post_type = %s OR ({$wpdb->posts}.post_type = %s AND EXISTS (SELECT 1 FROM {$wpdb->postmeta} AS cg_directory_comparison WHERE cg_directory_comparison.post_id = {$wpdb->posts}.ID AND cg_directory_comparison.meta_key = %s AND cg_directory_comparison.meta_value = %s)))",'cg_compare','post','_cg_comparison','1');
    $editorial=$wpdb->prepare("{$wpdb->posts}.post_type IN (%s,%s,%s,%s,%s)",'post','cg_review','cg_compare','cg_ranking','cg_guide');
    // EXISTS avoids duplicate cards/counts when an article has two platforms or duplicate metadata.
    return $where." AND $editorial AND ($associated OR $comparison)";
},20,2);
add_filter('archive_template_hierarchy',function($templates){
    if(cg_is_exchange_directory()){array_unshift($templates,'archive-cg_exchange.php');}
    return $templates;
});
add_filter('get_the_archive_title',function($title){return cg_is_exchange_directory()?'交易所':$title;});
add_filter('document_title_parts',function($parts){if(cg_is_exchange_directory()){$parts['title']='交易所';}return $parts;});
// Rank Math's post-type variables otherwise follow the first listed article.
// Replace only this archive's variables, retaining the owner's SEO title template.
add_filter('rank_math/replacements',function($replacements){
    if(cg_is_exchange_directory()&&is_array($replacements)){
        foreach(array('%title%','%pt_plural%','%pt_single%') as $key){if(array_key_exists($key,$replacements)){$replacements[$key]='交易所';}}
    }
    return $replacements;
});
foreach(array('rank_math/frontend/title','wpseo_title') as $hook){
    add_filter($hook,function($title){return cg_is_exchange_directory()?str_replace('交易所资料','交易所',$title):$title;},20);
}
foreach(array('rank_math/frontend/description','wpseo_metadesc') as $hook){
    add_filter($hook,function($description){return cg_is_exchange_directory()&&trim((string)$description)===''?cg_exchange_directory_intro():$description;},20);
}
foreach(array('rank_math/frontend/canonical','wpseo_canonical') as $hook){
    add_filter($hook,function($url){return cg_is_exchange_directory()?cg_exchange_directory_url(max(1,absint(get_query_var('paged')))):$url;},20);
}
function cg_exchange_directory_empty(){
    global $wp_query;
    return cg_is_exchange_directory()&&!(int)$wp_query->found_posts;
}
add_filter('wp_robots',function($robots){if(cg_exchange_directory_empty()){$robots['noindex']=true;unset($robots['index']);}return $robots;},99);
foreach(array('rank_math/frontend/robots','wpseo_robots_array') as $hook){
    add_filter($hook,function($robots){if(cg_exchange_directory_empty()&&is_array($robots)){$robots['index']='noindex';}return $robots;},99);
}
add_action('wp_head',function(){
    if(!cg_is_exchange_directory()||cg_review_external_seo()||!apply_filters('cg_exchange_directory_builtin_seo',true)){return;}
    global $wp_query;
    $page=max(1,absint(get_query_var('paged')));$url=cg_exchange_directory_url($page);
    $title='交易所'.($page>1?' · 第 '.$page.' 页':'');$description=cg_exchange_directory_intro();
    echo '<link rel="canonical" href="'.esc_url($url).'">';
    echo '<meta name="description" content="'.esc_attr($description).'">';
    foreach(array('og:type'=>'website','og:title'=>$title,'og:description'=>$description,'og:url'=>$url,'og:site_name'=>get_bloginfo('name')) as $key=>$value){echo '<meta property="'.esc_attr($key).'" content="'.esc_attr($value).'">';}
    $list=array();foreach($wp_query->posts as $index=>$entry){$list[]=array('@type'=>'ListItem','position'=>($page-1)*9+$index+1,'name'=>get_the_title($entry),'url'=>get_permalink($entry));}
    $collection=array('@type'=>'CollectionPage','@id'=>$url.'#collection','name'=>$title,'description'=>$description,'url'=>$url,'inLanguage'=>get_bloginfo('language'),'mainEntity'=>array('@type'=>'ItemList','itemListElement'=>$list));
    $breadcrumb=array('@type'=>'BreadcrumbList','@id'=>$url.'#breadcrumb','itemListElement'=>array(array('@type'=>'ListItem','position'=>1,'name'=>'首页','item'=>home_url('/')),array('@type'=>'ListItem','position'=>2,'name'=>'交易所','item'=>cg_exchange_directory_url())));
    echo '<script type="application/ld+json">'.wp_json_encode(array('@context'=>'https://schema.org','@graph'=>array($collection,$breadcrumb)),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE).'</script>';
},20);

function cg_purge_exchange_directory(){do_action('litespeed_purge_tag','cg_exchange_directory');}
add_action('wp',function(){if(cg_is_exchange_directory()){do_action('litespeed_tag_add','cg_exchange_directory');}});
add_action('save_post',function($id,$post){
    if(!wp_is_post_revision($id)&&!wp_is_post_autosave($id)&&in_array($post->post_type,array('post','cg_review','cg_compare','cg_ranking','cg_guide','cg_exchange'),true)){cg_purge_exchange_directory();}
},55,2);
add_action('before_delete_post',function($id){if(in_array(get_post_type($id),array('post','cg_review','cg_compare','cg_ranking','cg_guide','cg_exchange'),true)){cg_purge_exchange_directory();}});
foreach(array('added_post_meta','updated_post_meta','deleted_post_meta') as $hook){
    add_action($hook,function($meta_id,$id,$key){if(in_array($key,array('_cg_related_exchanges','_cg_comparison','_thumbnail_id','_cg_sponsored'),true)){cg_purge_exchange_directory();}},10,3);
}
add_action('set_object_terms',function($id,$terms,$tt_ids,$taxonomy){if($taxonomy==='cg_topic'){cg_purge_exchange_directory();}},10,4);
add_action('edited_term',function($id,$tt,$taxonomy){if($taxonomy==='cg_topic'){cg_purge_exchange_directory();}},10,3);
add_action('profile_update','cg_purge_exchange_directory');
