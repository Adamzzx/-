<?php
// Run only against the disposable local WordPress fixture, via wp eval-file.
if(home_url()!=='http://localhost:8088'){throw new RuntimeException('Disposable local test only');}
function cleanup_check($condition,$label){if(!$condition){throw new RuntimeException($label);}echo 'PASS: '.$label."\n";}
$admin=get_user_by('login','smoke-admin');wp_set_current_user($admin->ID);
$token=strtolower(wp_generate_password(8,false,false));$posts=array();$terms=array();$menus=array();
$old_locations=get_theme_mod('nav_menu_locations');$old_closed=get_option('cg_launch_uncategorized_archive_closed',null);
$saved_home=null;$home_options=array();
$insert=function($type,$slug,$title,$content='')use(&$posts){$id=wp_insert_post(array('post_type'=>$type,'post_name'=>$slug,'post_title'=>$title,'post_content'=>$content,'post_status'=>'publish'),true);if(is_wp_error($id)){throw new RuntimeException($id->get_error_message());}$posts[]=$id;return $id;};
try{
    $before=get_option('cg_launch_cleanup_log',null);$blocked=cg_launch_apply_cleanup(false);
    cleanup_check(is_wp_error($blocked)&&$blocked->get_error_code()==='cg_backup_required','missing full-backup confirmation blocks before any cleanup/helper');
    wp_set_current_user(0);$forbidden=cg_launch_apply_cleanup(true);wp_set_current_user($admin->ID);
    cleanup_check(is_wp_error($forbidden)&&$forbidden->get_error_code()==='cg_forbidden','unauthorized apply rejected even with backup checkbox');
    cleanup_check(get_option('cg_launch_cleanup_log',null)===$before,'rejected apply does not create execution log');
    cleanup_check(cg_launch_cleanup_path(home_url('/guides/?x=1#fees'))===''&&cg_launch_cleanup_path(home_url('/guides/?x=1#fees'),true)===cg_launch_cleanup_path(home_url('/guides/')),'dependencies normalize suffix but route tests remain exact');
    cleanup_check(cg_launch_cleanup_path('https://different.invalid/guides/',true)==='','external same-path URL does not become an internal dependency');
    $slug='cleanup-exchange-'.$token;$id=$insert('cg_exchange',$slug,'Cleanup fixture');
    $spec=array('type'=>'cg_exchange','slug'=>$slug,'titles'=>array('Cleanup fixture'),'placeholder'=>'');
    cleanup_check(cg_launch_cleanup_post_row($spec,cg_launch_cleanup_inventory())['action']==='keep','empty unmarked owner platform is preserved');
    update_post_meta($id,'_cg_created','1');
    cleanup_check(cg_launch_cleanup_post_row($spec,cg_launch_cleanup_inventory())['action']==='trash','exact marked empty fixture is eligible');
    $status_before=get_post_status($id);cg_launch_cleanup_plan();cleanup_check(get_post_status($id)===$status_before,'dry-run does not trash eligible content');
    wp_update_post(array('ID'=>$id,'post_content'=>'<p>Real platform introduction.</p>'));
    cleanup_check(cg_launch_cleanup_post_row($spec,cg_launch_cleanup_inventory())['action']==='keep','real body protects generated object');
    wp_update_post(array('ID'=>$id,'post_content'=>''));update_post_meta($id,'_cg_fees','Verified owner value');
    cleanup_check(cg_launch_cleanup_post_row($spec,cg_launch_cleanup_inventory())['action']==='keep','nonempty real fee field protects object');delete_post_meta($id,'_cg_fees');
    $article=$insert('post','cleanup-article-'.$token,'Cleanup related draft');wp_update_post(array('ID'=>$article,'post_status'=>'draft'));update_post_meta($article,'_cg_related_exchanges',array((string)$id));
    cleanup_check(cg_launch_cleanup_post_row($spec,cg_launch_cleanup_inventory())['action']==='keep','serialized string platform ID in draft blocks cleanup');delete_post_meta($article,'_cg_related_exchanges');
    update_post_meta($article,'_cg_review_exchange',$slug);
    cleanup_check(cg_launch_cleanup_post_row($spec,cg_launch_cleanup_inventory())['action']==='keep','review platform slug in draft blocks cleanup');delete_post_meta($article,'_cg_review_exchange');
    wp_update_post(array('ID'=>$article,'post_content'=>'<a href="'.esc_url(home_url('/exchanges/'.$slug.'/#fees')).'">Details</a>'));
    cleanup_check(cg_launch_cleanup_post_row($spec,cg_launch_cleanup_inventory())['action']==='keep','draft body link with fragment blocks cleanup');wp_update_post(array('ID'=>$article,'post_content'=>''));
    $menu=wp_create_nav_menu('Cleanup owner fixture '.$token);$menus[]=$menu;
    $item=wp_update_nav_menu_item($menu,0,array('menu-item-title'=>'Owner link','menu-item-url'=>home_url('/exchanges/'.$slug.'/?source=menu'),'menu-item-type'=>'custom','menu-item-status'=>'publish'));set_theme_mod('nav_menu_locations',array('primary'=>$menu));
    $row=cg_launch_cleanup_post_row($spec,cg_launch_cleanup_inventory());cleanup_check($row['action']==='keep'&&count($row['dependencies'])===1,'active owner menu with query is a visible blocker');
    update_post_meta($item,'_cg_owned_navigation',$menu);update_post_meta($item,'_cg_nav_key','child-test');
    $row=cg_launch_cleanup_post_row($spec,cg_launch_cleanup_inventory());cleanup_check($row['action']==='trash'&&in_array($item,$row['owned_menu_ids'],true),'known managed leaf link can accompany safe cleanup');
    $child=wp_update_nav_menu_item($menu,0,array('menu-item-title'=>'Keep child','menu-item-url'=>home_url('/'),'menu-item-parent-id'=>$item,'menu-item-type'=>'custom','menu-item-status'=>'publish'));
    cleanup_check(cg_launch_cleanup_post_row($spec,cg_launch_cleanup_inventory())['action']==='keep','owner descendants prevent deleting managed parent link');
    $parent=wp_insert_term('Cleanup root '.$token,'cg_topic',array('slug'=>'cleanup-root-'.$token));$terms[]=$parent['term_id'];
    $term=wp_insert_term('Cleanup child','cg_topic',array('slug'=>'cleanup-child-'.$token,'parent'=>$parent['term_id']));$terms[]=$term['term_id'];
    $term_row=function()use($token){return cg_launch_cleanup_term_row('cleanup-root-'.$token,'cleanup-child-'.$token,'Cleanup child');};
    cleanup_check($term_row()['action']==='delete_term','exact empty retired-topic fixture is eligible');
    wp_set_object_terms($article,array((int)$term['term_id']),'cg_topic');
    cleanup_check($term_row()['action']==='keep','draft association blocks term removal despite public count zero');wp_set_object_terms($article,array(),'cg_topic');
    wp_update_post(array('ID'=>$article,'post_content'=>'<a href="'.esc_url(home_url('/topics/cleanup-root-'.$token.'/cleanup-child-'.$token.'/?source=x#section')).'">Topic</a>'));
    cleanup_check($term_row()['action']==='keep','unassigned draft link with query and fragment protects old term');wp_update_post(array('ID'=>$article,'post_content'=>''));
    update_post_meta($article,'_cg_primary_topic',$term['term_id']);cleanup_check($term_row()['action']==='keep','explicit primary-topic metadata protects term');delete_post_meta($article,'_cg_primary_topic');
    // Temporarily reserve the old path, restoring the original object unchanged below.
    $saved_home=get_page_by_path('home',OBJECT,'page');if($saved_home){wp_update_post(array('ID'=>$saved_home->ID,'post_name'=>'cleanup-reserved-home-'.$token));}
    foreach(array('page_on_front','page_for_posts','show_on_front') as $key){$home_options[$key]=get_option($key);}
    $home=$insert('page','home','首页');update_post_meta($home,'_cg_created','1');update_option('show_on_front','page');update_option('page_on_front',$home);update_option('page_for_posts',0);
    $home_spec=array('type'=>'page','slug'=>'home','titles'=>array('首页'),'placeholder'=>'');
    cleanup_check(cg_launch_cleanup_post_row($home_spec,cg_launch_cleanup_inventory())['action']==='internal','active front object retained as internal while alias may be normalized');
    update_option('page_for_posts',$home);cleanup_check(cg_launch_cleanup_post_row($home_spec,cg_launch_cleanup_inventory())['action']==='keep','active posts page never loses its required public address');
    $uncategorized=get_term_by('slug','uncategorized','category');update_option('cg_launch_uncategorized_archive_closed',true,false);
    if($uncategorized){cleanup_check((bool)get_term_by('slug','uncategorized','category'),'direct Uncategorized lookup remains available after public list exclusion');$listed=get_terms(array('taxonomy'=>'category','hide_empty'=>false,'fields'=>'ids'));cleanup_check(!in_array((int)$uncategorized->term_id,array_map('intval',$listed),true),'ordinary public category lists exclude redundant archive only');}
}finally{
    foreach($posts as $id){wp_delete_post($id,true);}
    if($saved_home){wp_update_post(array('ID'=>$saved_home->ID,'post_name'=>'home'));}
    foreach($home_options as $key=>$value){update_option($key,$value);}
    foreach(array_reverse($terms) as $id){wp_delete_term($id,'cg_topic');}
    foreach($menus as $menu){wp_delete_nav_menu($menu);}set_theme_mod('nav_menu_locations',$old_locations);
    if($old_closed===null){delete_option('cg_launch_uncategorized_archive_closed');}else{update_option('cg_launch_uncategorized_archive_closed',$old_closed,false);}
    wp_set_current_user($admin->ID);
}
