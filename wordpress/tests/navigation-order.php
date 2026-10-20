<?php
if(home_url()!=='http://localhost:8088'){throw new RuntimeException('Disposable local test only');}
function order_check($condition,$label){if(!$condition){throw new RuntimeException($label);}echo 'PASS: '.$label."\n";}
$menu=0;$options=array();$locations=get_theme_mod('nav_menu_locations');
foreach(array('cg_navigation_menu_id','cg_navigation_item_ids','cg_navigation_order_version','cg_brand','cg_tagline') as $key){$options[$key]=array('exists'=>get_option($key,null)!==null,'value'=>get_option($key));}
try{
    wp_set_current_user(get_user_by('login','smoke-admin')->ID);
    $menu=wp_create_nav_menu('Local approved navigation order '.wp_generate_password(6,false));$items=array();
    $add=function($key,$title,$order,$parent=0,$owned=true)use($menu,&$items){
        $id=wp_update_nav_menu_item($menu,0,array('menu-item-title'=>$title,'menu-item-url'=>home_url('/tokenized-stocks/'),'menu-item-position'=>$order,'menu-item-parent-id'=>$parent,'menu-item-type'=>'custom','menu-item-status'=>'publish','menu-item-target'=>'_blank','menu-item-classes'=>'owner-class','menu-item-attr-title'=>'Keep title'));
        if(is_wp_error($id)){throw new RuntimeException($id->get_error_message());}
        if($owned){update_post_meta($id,'_cg_owned_navigation',$menu);update_post_meta($id,'_cg_nav_key',$key);}
        $items[]=$id;return $id;
    };
    $exchange=$add('group-交易所','交易所',1);$binance=$add('child-交易所-Binance','Binance',2,$exchange);
    $owner_child=$add('','Owner nested link',3,$exchange,false);$stocks=$add('group-美股','美股',6);
    $owner=$add('','Owner root link',7,0,false);$crypto=$add('group-加密货币','加密货币',9);$wallet=$add('child-加密货币-钱包与资产安全','钱包与资产安全',10,$crypto);
    // Fixture creation may normalize sibling positions; set explicit stored slots afterwards.
    foreach(array($exchange=>1,$binance=>2,$owner_child=>3,$stocks=>6,$owner=>7,$crypto=>9,$wallet=>10) as $id=>$position){wp_update_post(array('ID'=>$id,'menu_order'=>$position));}
    $before=array();foreach($items as $id){$before[$id]=array('meta'=>get_post_meta($id),'order'=>(int)get_post_field('menu_order',$id,'raw'));}
    update_option('cg_navigation_menu_id',$menu);update_option('cg_navigation_item_ids',$items);set_theme_mod('nav_menu_locations',array('primary'=>987654));update_option('cg_brand','Owner preserved brand');update_option('cg_tagline','Owner preserved tagline');
    order_check(cg_upgrade_navigation_order()===true,'approved menu order upgrade succeeds');
    order_check((int)get_post_field('menu_order',$exchange,'raw')===1&&(int)get_post_field('menu_order',$crypto,'raw')===6&&(int)get_post_field('menu_order',$stocks,'raw')===9,'three existing root slots become exchange, crypto, stocks');
    foreach($items as $id){order_check(get_post_meta($id)===$before[$id]['meta'],'menu attributes and parent relationships retained for '.$id);}
    foreach(array($binance,$owner_child,$owner,$wallet) as $id){order_check((int)get_post_field('menu_order',$id,'raw')===$before[$id]['order'],'child and owner menu positions retained for '.$id);}
    order_check(get_option('cg_navigation_item_ids')===$items&&get_theme_mod('nav_menu_locations')===array('primary'=>987654),'menu IDs and owner primary binding retained');
    order_check(get_option('cg_brand')==='Owner preserved brand'&&get_option('cg_tagline')==='Owner preserved tagline','brand settings preserved');
    $after=array_map(fn($id)=>(int)get_post_field('menu_order',$id,'raw'),$items);cg_upgrade_navigation_order();
    order_check($after===array_map(fn($id)=>(int)get_post_field('menu_order',$id,'raw'),$items)&&count(wp_get_nav_menu_items($menu))===count($items),'repeated upgrade neither moves entries again nor creates duplicates');
    order_check(array_column(cg_navigation_groups(),'title')===array('交易所','加密货币','美股'),'generated navigation uses the same approved order');
    delete_post_meta($stocks,'_cg_owned_navigation');delete_option('cg_navigation_order_version');$before_missing=array_map(fn($id)=>(int)get_post_field('menu_order',$id,'raw'),$items);
    order_check(cg_upgrade_navigation_order()===false&&get_option('cg_navigation_order_version',false)===false&&$before_missing===array_map(fn($id)=>(int)get_post_field('menu_order',$id,'raw'),$items),'missing managed parents are safely left alone');
}finally{
    if($menu){wp_delete_nav_menu($menu);}
    foreach($options as $key=>$value){if($value['exists']){update_option($key,$value['value']);}else{delete_option($key);}}
    if($locations===false){remove_theme_mod('nav_menu_locations');}else{set_theme_mod('nav_menu_locations',$locations);}
}
