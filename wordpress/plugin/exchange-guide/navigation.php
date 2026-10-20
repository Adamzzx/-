<?php
if (!defined('ABSPATH')) { exit; }
function cg_topic_tree() {
    return array(
        'crypto' => array('加密货币', array('beginners'=>'新手入门','wallets-security'=>'钱包与资产安全','yield-products'=>'加密货币理财')),
        'tokenized-stocks' => array('美股', array()),
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
        if($old_key===$key || ($key==='group-美股'&&($old_key==='group-美股代币'||(!$old_key&&get_the_title($id)==='美股代币'))) || (!$old_key && get_the_title($id)===$args['menu-item-title'])){$existing=$id;break;}
    }
    $result=wp_update_nav_menu_item($menu_id,$existing,$args);
    if(!is_wp_error($result)){update_post_meta($result,'_cg_owned_navigation',$menu_id);update_post_meta($result,'_cg_nav_key',$key);}
    return $result;
}
function cg_apply_navigation() {
    cg_ensure_stocks_topic_name();
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
        if(!empty($group['term'])){update_post_meta($parent,'_cg_topic_id',$group['term']->term_id);}else{delete_post_meta($parent,'_cg_topic_id');}
        foreach($group['children'] as $child){
            $item=cg_navigation_item($menu_id,'child-'.$group['title'].'-'.$child['title'],array('menu-item-position'=>count($ids)+1,'menu-item-title'=>$child['title'],'menu-item-url'=>is_string($child['url'])?$child['url']:'','menu-item-type'=>'custom','menu-item-parent-id'=>$parent,'menu-item-status'=>'publish'),$old_ids);
            if(is_wp_error($item)){return $item;}$ids[]=$item;update_post_meta($item,'_cg_owned_navigation',$menu_id);
            if(!empty($child['term'])){update_post_meta($item,'_cg_topic_id',$child['term']->term_id);}
        }
    }
    cg_remove_retired_navigation_items($menu_id);
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

// A narrow menu upgrade: preserve IDs, owner links, locations and brand settings.
function cg_ensure_stocks_topic_name(){
    $root=cg_find_topic('tokenized-stocks');
    if(!$root){return wp_insert_term('美股','cg_topic',array('slug'=>'tokenized-stocks'));}
    if(in_array($root->name,array('美股代币','代币化股票'),true)){return wp_update_term($root->term_id,'cg_topic',array('name'=>'美股'));}
    return $root->term_id;
}
function cg_remove_retired_navigation_items($menu_id){
    $items=wp_get_nav_menu_items($menu_id,array('post_status'=>'any'));if(!$items){return;}
    $retired=array('child-美股代币-交易指南','child-美股代币-出入金指南','child-美股-交易指南','child-美股-出入金指南','child-加密货币-出入金');
    $removed=array();
    foreach($items as $item){
        if((int)get_post_meta($item->ID,'_cg_owned_navigation',true)!==(int)$menu_id){continue;}
        $key=get_post_meta($item->ID,'_cg_nav_key',true);
        if(in_array($key,$retired,true)){$removed[(int)$item->ID]=(int)$item->menu_item_parent;}
    }
    // Owner descendants survive removal of an obsolete generated entry.
    foreach($items as $item){
        if(isset($removed[(int)$item->menu_item_parent])&&!isset($removed[(int)$item->ID])){
            $parent=(int)$item->menu_item_parent;while(isset($removed[$parent])){$parent=$removed[$parent];}
            update_post_meta($item->ID,'_menu_item_menu_item_parent',$parent);
        }
    }
    foreach(array_keys($removed) as $id){wp_delete_post($id,true);}
    $ids=array_values(array_diff(array_map('absint',(array)get_option('cg_navigation_item_ids',array())),array_keys($removed)));
    update_option('cg_navigation_item_ids',$ids);
}
function cg_upgrade_navigation_layout(){
    $term_result=cg_ensure_stocks_topic_name();if(is_wp_error($term_result)){return $term_result;}
    $menu_id=(int)get_option('cg_navigation_menu_id');
    if($menu_id&&wp_get_nav_menu_object($menu_id)){
        $items=wp_get_nav_menu_items($menu_id,array('post_status'=>'any'));
        $page=get_page_by_path('tokenized-stocks');$root=cg_find_topic('tokenized-stocks');
        $url=$page&&$page->post_status==='publish'?get_permalink($page):($root?get_term_link($root):'');
        foreach((array)$items as $item){
            if((int)get_post_meta($item->ID,'_cg_owned_navigation',true)!==$menu_id){continue;}
            $key=get_post_meta($item->ID,'_cg_nav_key',true);
            if(in_array($key,array('group-美股代币','group-美股'),true)||(!$key&&!(int)$item->menu_item_parent&&in_array($item->title,array('美股代币','美股'),true))){
                $result=wp_update_nav_menu_item($menu_id,$item->ID,array('menu-item-title'=>'美股','menu-item-url'=>is_string($url)?$url:'','menu-item-type'=>'custom','menu-item-status'=>'publish','menu-item-position'=>$item->menu_order,'menu-item-parent-id'=>0));
                if(is_wp_error($result)){return $result;}
                update_post_meta($item->ID,'_cg_nav_key','group-美股');
                if($page&&$page->post_status==='publish'){delete_post_meta($item->ID,'_cg_topic_id');}elseif($root){update_post_meta($item->ID,'_cg_topic_id',$root->term_id);}
            }
        }
        cg_remove_retired_navigation_items($menu_id);
    }
    update_option('cg_navigation_layout_version','1.13.0',false);
    do_action('litespeed_purge_all');
    return true;
}
add_action('admin_init',function(){
    if(current_user_can('manage_options')&&get_option('cg_navigation_layout_version')!=='1.13.0'){cg_upgrade_navigation_layout();}
});

// Reuse the three managed parent slots; all links and child relationships stay intact.
function cg_upgrade_navigation_order(){
    $menu_id=(int)get_option('cg_navigation_menu_id');
    if(!$menu_id||!wp_get_nav_menu_object($menu_id)){return false;}
    $keys=array('group-交易所','group-加密货币','group-美股');$parents=array();$positions=array();
    foreach((array)wp_get_nav_menu_items($menu_id,array('post_status'=>'any','output'=>OBJECT)) as $item){
        if($item->post_type!=='nav_menu_item'||(int)$item->menu_item_parent||(int)get_post_meta($item->ID,'_cg_owned_navigation',true)!==$menu_id){continue;}
        $key=get_post_meta($item->ID,'_cg_nav_key',true);
        if(!in_array($key,$keys,true)){continue;}
        if(isset($parents[$key])){return false;}
        $parents[$key]=$item->ID;$positions[]=(int)get_post_field('menu_order',$item->ID,'raw');
    }
    if(count($parents)!==3||count(array_unique($positions))!==3){return false;}
    sort($positions,SORT_NUMERIC);
    foreach($keys as $index=>$key){
        if((int)get_post_field('menu_order',$parents[$key],'raw')===$positions[$index]){continue;}
        $result=wp_update_post(array('ID'=>$parents[$key],'menu_order'=>$positions[$index]),true);
        if(is_wp_error($result)){return $result;}
    }
    update_option('cg_navigation_order_version','1.13.2',false);
    do_action('litespeed_purge_all');
    return true;
}
add_action('admin_init',function(){
    if(current_user_can('manage_options')&&get_option('cg_navigation_order_version')!=='1.13.2'){cg_upgrade_navigation_order();}
},20);
