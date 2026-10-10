<?php
function phase_check($value,$name){if(!$value){throw new RuntimeException($name);}echo 'PASS: '.$name."\n";}
wp_set_current_user(get_user_by('login','smoke-admin')->ID);
$p=cg_exchange('binance');$fees=get_post_meta($p->ID,'_cg_fees',true);$url=get_post_meta($p->ID,'_cg_destination',true);
$old=wp_create_nav_menu('Owner menu '.wp_generate_password(4,false));set_theme_mod('nav_menu_locations',array('primary'=>$old));
$menu=cg_apply_navigation();phase_check(!is_wp_error($menu),'apply navigation');
phase_check((int)get_option('cg_previous_primary_menu')===$old && (bool)wp_get_nav_menu_object($old),'previous owner menu preserved');
$items=wp_get_nav_menu_items($menu);phase_check(count($items)===14,'three parents and eleven children');
$exchange=array_values(array_filter($items,function($i)use($items){return (int)$i->menu_item_parent===(int)$items[0]->ID;}));
phase_check(array_map(function($i){return $i->title;},$exchange)===array('Binance','OKX','Bitget','Bybit','交易所对比'),'confirmed exchange order');
$custom=wp_update_nav_menu_item($menu,0,array('menu-item-parent-id'=>$items[0]->ID,'menu-item-title'=>'Owner link','menu-item-url'=>home_url('/disclosure/'),'menu-item-type'=>'custom','menu-item-status'=>'publish'));
cg_apply_navigation();phase_check((bool)get_post($custom),'owner-added menu item retained');phase_check((int)get_post_meta($custom,'_menu_item_menu_item_parent',true)===$items[0]->ID,'owner child relationship retained');
phase_check(count(wp_get_nav_menu_items($menu))===15,'repeated application does not duplicate generated items');
wp_delete_post($custom,true);
phase_check(get_post_meta($p->ID,'_cg_fees',true)===$fees&&get_post_meta($p->ID,'_cg_destination',true)===$url,'existing exchange data preserved');
phase_check((bool)cg_exchange('gate')&&(bool)cg_exchange('mexc'),'removed navigation exchanges retained');
$existing=get_page_by_path('navigation-topic-smoke',OBJECT,'cg_guide');if($existing){wp_delete_post($existing->ID,true);}
$parent=cg_find_topic('crypto');$term=cg_find_topic('beginners',$parent->term_id);phase_check(!cg_topic_has_content($term->term_id),'empty category initially absent');
$id=wp_insert_post(array('post_type'=>'cg_guide','post_status'=>'publish','post_title'=>'导航分类测试','post_name'=>'navigation-topic-smoke','post_content'=>'主题归档测试正文。'));
wp_set_object_terms($id,array($term->term_id),'cg_topic');phase_check(cg_topic_has_content($parent->term_id),'parent includes descendant articles');
update_option('cg_brand','Owner Brand');cg_apply_navigation();phase_check(get_option('cg_brand')==='Owner Brand','custom brand preserved');
update_option('cg_brand','交易所指南');cg_apply_navigation();phase_check(get_option('cg_brand')==='加密资产投资与数字金融指南','default positioning upgraded');
$_POST=array('cg_meta_nonce'=>wp_create_nonce('cg_save_meta'),'cg_primary_topic'=>$term->term_id,'cg_related_exchanges'=>array($p->ID,$id));
cg_save_meta($id);
phase_check((int)get_post_meta($id,'_cg_primary_topic',true)===$term->term_id && has_term($term->term_id,'cg_topic',$id),'primary topic stored and assigned');
phase_check(get_post_meta($id,'_cg_related_exchanges',true)===array($p->ID),'related entities validated');
