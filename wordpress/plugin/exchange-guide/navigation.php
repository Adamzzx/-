<?php
if (!defined('ABSPATH')) { exit; }
function cg_topic_tree() {
    return array(
        'tokenized-stocks' => array('美股代币', array('trading'=>'交易指南','funding'=>'出入金指南')),
        'crypto' => array('加密货币', array('beginners'=>'新手入门','wallets-security'=>'钱包与资产安全','deposits-withdrawals'=>'出入金','yield-products'=>'加密货币理财')),
    );
}
add_action('init', function () {
    register_taxonomy('cg_topic', array('post','cg_review','cg_compare','cg_ranking','cg_guide'), array(
        'labels'=>array('name'=>'内容主题','singular_name'=>'内容主题','add_new_item'=>'添加内容主题'),
        'hierarchical'=>true,'public'=>true,'show_in_rest'=>true,'show_admin_column'=>true,
        'rewrite'=>array('slug'=>'topics','hierarchical'=>true,'with_front'=>false),
    ));
});
function cg_topic_has_content($term_id) {
    return (bool)get_posts(array('post_type'=>array('post','cg_review','cg_compare','cg_ranking','cg_guide'),'post_status'=>'publish','has_password'=>false,'numberposts'=>1,'fields'=>'ids','tax_query'=>array(array('taxonomy'=>'cg_topic','field'=>'term_id','terms'=>(int)$term_id,'include_children'=>true))));
}
function cg_find_topic($slug, $parent = 0) {
    $terms=get_terms(array('taxonomy'=>'cg_topic','hide_empty'=>false,'slug'=>$slug,'parent'=>$parent));
    return !is_wp_error($terms) && $terms ? $terms[0] : null;
}
function cg_navigation_groups() {
    $exchange_children=array();
    foreach(array('binance'=>'Binance','okx'=>'OKX','bitget'=>'Bitget','bybit'=>'Bybit') as $slug=>$title){
        $post=cg_exchange($slug);$exchange_children[]=array('title'=>$title,'url'=>$post?get_permalink($post):'','post'=>$post);
    }
    $page=get_page_by_path('compare');$exchange_children[]=array('title'=>'交易所对比','url'=>$page&&$page->post_status==='publish'?get_permalink($page):'','post'=>$page);
    $groups=array(array('title'=>'交易所','url'=>get_post_type_archive_link('cg_exchange'),'children'=>$exchange_children));
    foreach(cg_topic_tree() as $slug=>$definition){
        $parent=cg_find_topic($slug);$children=array();
        foreach($definition[1] as $child_slug=>$title){
            $term=$parent?cg_find_topic($child_slug,$parent->term_id):null;
            $children[]=array('title'=>$title,'url'=>$term?get_term_link($term):'','term'=>$term);
        }
        $page=$slug==='tokenized-stocks'?get_page_by_path($slug):null;
        $groups[]=array('title'=>$definition[0],'url'=>$page&&$page->post_status==='publish'?get_permalink($page):($parent?get_term_link($parent):''),'term'=>$page?null:$parent,'children'=>$children);
    }
    return $groups;
}
function cg_navigation_item($menu_id,$key,$args,$old_ids) {
    $existing=0;
    foreach($old_ids as $id){
        if((int)get_post_meta($id,'_cg_owned_navigation',true)!==$menu_id){continue;}
        $old_key=get_post_meta($id,'_cg_nav_key',true);
        if($old_key===$key || (!$old_key && get_the_title($id)===$args['menu-item-title'])){$existing=$id;break;}
    }
    $result=wp_update_nav_menu_item($menu_id,$existing,$args);
    if(!is_wp_error($result)){update_post_meta($result,'_cg_owned_navigation',$menu_id);update_post_meta($result,'_cg_nav_key',$key);}
    return $result;
}
function cg_apply_navigation() {
    foreach(cg_topic_tree() as $slug=>$definition){
        $parent=cg_find_topic($slug);
        if(!$parent){$result=wp_insert_term($definition[0],'cg_topic',array('slug'=>$slug));if(is_wp_error($result)){return $result;}$parent=get_term($result['term_id'],'cg_topic');}
        foreach($definition[1] as $child_slug=>$label){
            if(!cg_find_topic($child_slug,$parent->term_id)){
                $result=wp_insert_term($label,'cg_topic',array('slug'=>$child_slug,'parent'=>$parent->term_id));if(is_wp_error($result)){return $result;}
            }
        }
    }
    $menu_id=(int)get_option('cg_navigation_menu_id');
    if(!$menu_id || !wp_get_nav_menu_object($menu_id)){
        $menu_id=wp_create_nav_menu('数字金融主导航 · '.wp_generate_password(4,false));if(is_wp_error($menu_id)){return $menu_id;}
        update_option('cg_navigation_menu_id',$menu_id);
    }
    // Stable IDs retain owner-added children and their relationships.
    $old_ids=(array)get_option('cg_navigation_item_ids',array());
    $ids=array();
    foreach(cg_navigation_groups() as $group){
        $parent=cg_navigation_item($menu_id,'group-'.$group['title'],array('menu-item-position'=>count($ids)+1,'menu-item-title'=>$group['title'],'menu-item-url'=>is_string($group['url'])?$group['url']:'','menu-item-type'=>'custom','menu-item-status'=>'publish'),$old_ids);
        if(is_wp_error($parent)){return $parent;}$ids[]=$parent;update_post_meta($parent,'_cg_owned_navigation',$menu_id);
        if(!empty($group['term'])){update_post_meta($parent,'_cg_topic_id',$group['term']->term_id);}
        foreach($group['children'] as $child){
            $item=cg_navigation_item($menu_id,'child-'.$group['title'].'-'.$child['title'],array('menu-item-position'=>count($ids)+1,'menu-item-title'=>$child['title'],'menu-item-url'=>is_string($child['url'])?$child['url']:'','menu-item-type'=>'custom','menu-item-parent-id'=>$parent,'menu-item-status'=>'publish'),$old_ids);
            if(is_wp_error($item)){return $item;}$ids[]=$item;update_post_meta($item,'_cg_owned_navigation',$menu_id);
            if(!empty($child['term'])){update_post_meta($item,'_cg_topic_id',$child['term']->term_id);}
        }
    }
    update_option('cg_navigation_item_ids',$ids);
    $locations=get_theme_mod('nav_menu_locations',array());
    if(isset($locations['primary']) && $locations['primary']!==$menu_id){update_option('cg_previous_primary_menu',$locations['primary']);}
    $locations['primary']=$menu_id;set_theme_mod('nav_menu_locations',$locations);
    $brand=get_option('cg_brand','');
    if(!$brand || $brand==='交易所指南'){update_option('cg_brand','加密资产投资与数字金融指南');if(get_option('blogname')==='交易所指南'){update_option('blogname','加密资产投资与数字金融指南');}}
    update_option('cg_tagline','Crypto Investing & Digital Finance');
    flush_rewrite_rules();return $menu_id;
}
add_action('admin_post_cg_navigation',function(){
    if(!current_user_can('manage_options')){wp_die('没有权限。','',array('response'=>403));}
    check_admin_referer('cg_navigation');$result=cg_apply_navigation();
    if(is_wp_error($result)){set_transient('cg_error_'.get_current_user_id(),$result->get_error_message(),120);}
    wp_safe_redirect(admin_url('admin.php?page=cg-setup&cg_navigation_done=1'));exit;
});
add_action('template_redirect',function(){
    if(is_tax('cg_topic')){$term=get_queried_object();if($term instanceof WP_Term && !cg_topic_is_open($term)){global $wp_query;$wp_query->set_404();status_header(404);nocache_headers();}}
});
