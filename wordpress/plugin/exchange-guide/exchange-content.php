<?php
if(!defined('ABSPATH')){exit;}
function cg_exchange_articles($exchange_id,$page=1,$limit=9){
    $id=absint($exchange_id);
    return new WP_Query(array(
        'post_type'=>array('post','cg_review','cg_compare','cg_ranking','cg_guide'),
        'post_status'=>'publish','has_password'=>false,'posts_per_page'=>max(1,min(30,absint($limit))),
        'paged'=>max(1,absint($page)),'orderby'=>array('date'=>'DESC','ID'=>'DESC'),'ignore_sticky_posts'=>true,
        // Match array values, not numeric prefixes or serialized array indexes.
        'meta_query'=>array(array('key'=>'_cg_related_exchanges','compare'=>'REGEXP',
            'value'=>'^a:[0-9]+:[{](i:[0-9]+;(i:[0-9]+;|s:[0-9]+:"[0-9]+";))*i:[0-9]+;(i:'.$id.';|s:[0-9]+:"'.$id.'";)')),

    ));
}
function cg_purge_exchange_hubs($ids){
    foreach(array_unique(array_map('absint',(array)$ids)) as $id){if($id&&get_post_type($id)==='cg_exchange'){do_action('litespeed_purge_post',$id);}}
}
// Updating content, publication status, deletion, or associations invalidates its hub.
add_action('save_post',function($id,$post){if(!wp_is_post_revision($id)&&in_array($post->post_type,array('post','cg_review','cg_compare','cg_ranking','cg_guide'),true)){cg_purge_exchange_hubs(get_post_meta($id,'_cg_related_exchanges',true));}},30,2);
add_action('before_delete_post',function($id){cg_purge_exchange_hubs(get_post_meta($id,'_cg_related_exchanges',true));});
add_action('trashed_post',function($id){cg_purge_exchange_hubs(get_post_meta($id,'_cg_related_exchanges',true));});
add_action('untrashed_post',function($id){cg_purge_exchange_hubs(get_post_meta($id,'_cg_related_exchanges',true));});
