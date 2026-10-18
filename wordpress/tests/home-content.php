<?php
// Integration checks for the disposable, packaged WordPress installation only.
if(home_url()!=='http://localhost:8088'){throw new RuntimeException('Disposable local test only');}
function home_check($ok,$label){if(!$ok){throw new RuntimeException($label);}echo "PASS: $label\n";}
$created=array();$users=array();$purged=array();$old_user=get_current_user_id();$old_sticky=get_option('sticky_posts');$old_post=$GLOBALS['post']??null;$old_main=$GLOBALS['wp_the_query']??null;$old_query=$GLOBALS['wp_query']??null;
$admin=get_user_by('login','smoke-admin');wp_set_current_user($admin->ID);
add_action('litespeed_purge_tag',function($tag)use(&$purged){$purged[]=$tag;});
$create=function($type='post',$status='publish',$password='',$date='2026-10-18 00:00:00')use(&$created,$admin){
    $id=wp_insert_post(array('post_type'=>$type,'post_status'=>$status,'post_title'=>'HomeEditorialSearchNeedle '.$type.' '.count($created),'post_content'=>'Original body marker; literal wallet security phrase.','post_excerpt'=>'Original excerpt.','post_author'=>$admin->ID,'post_password'=>$password,'post_date'=>$date,'post_date_gmt'=>$date),true);
    if(is_wp_error($id)){throw new RuntimeException($id->get_error_message());}$created[]=$id;return $id;
};
$ids=function($query){return array_map(fn($p)=>(int)$p->ID,$query->posts);};
$search=function($term,$page=1)use(&$old_main,&$old_query){
    $query=new WP_Query();$GLOBALS['wp_the_query']=$GLOBALS['wp_query']=$query;$query->parse_query(array('s'=>$term,'paged'=>$page));$query->get_posts();return $query;
};
try{
    $public=array();foreach(array('post','cg_review','cg_compare','cg_ranking','cg_guide') as $type){$public[]=$create($type);}
    $hidden=array();foreach(array('draft','pending','private','trash','future') as $status){$hidden[]=$create('post',$status,'',$status==='future'?'2031-01-01 00:00:00':'2026-10-18 00:00:00');}
    $hidden[]=$create('post','publish','local-secret');$hidden[]=$create('cg_compare','publish','local-secret');$hidden[]=$create('page');$hidden[]=$create('cg_exchange');
    $all=$ids(cg_home_articles(1,30));
    home_check(!array_diff($public,$all),'home includes native posts and all four existing editorial post types');
    home_check(!array_intersect($hidden,$all),'home excludes draft, pending, future, private, trash, password, pages and exchange records while logged in');
    update_option('sticky_posts',array_merge((array)get_option('sticky_posts'),array($public[0])));
    update_post_meta($public[1],'_cg_home_order','2');update_post_meta($public[2],'_cg_home_order','1');add_post_meta($public[2],'_cg_home_order','1');
    $all=$ids(cg_home_articles(1,30));home_check(array_slice($all,0,2)===array($public[2],$public[1]),'positive manual priorities sort before the default feed, smaller values first');
    home_check(count(array_keys($all,$public[2],true))===1,'duplicate order metadata never creates duplicate article cards');
    update_post_meta($public[1],'_cg_home_order','1');$all=$ids(cg_home_articles(1,30));home_check(array_slice($all,0,2)===array($public[2],$public[1]),'equal manual ranks use stable publish-date and ID sorting');
    delete_post_meta($public[1],'_cg_home_order');delete_post_meta($public[2],'_cg_home_order');
    $all=$ids(cg_home_articles(1,30));$ours=array_values(array_intersect($all,$public));home_check($ours===array_reverse($public),'default home order is newest publish date then descending ID, ignoring sticky posts');
    $normal=new WP_Query(array('post_type'=>'post','post_status'=>'publish','posts_per_page'=>100,'orderby'=>'ID','order'=>'ASC'));
    home_check(in_array($hidden[5],$ids($normal),true),'home privacy and ordering restrictions do not leak into unrelated post queries');
    for($i=0;$i<8;$i++){$public[]=$create('post','publish','','2026-10-17 00:00:00');}
    $one=cg_home_articles(1,8);$two=cg_home_articles(2,8);home_check(count($one->posts)===8&&$one->max_num_pages>1&&!array_intersect($ids($one),$ids($two)),'home pagination has eight articles per page and no duplicates between pages');

    $target=$public[0];$original=get_post($target);$url=get_permalink($target);$terms=wp_get_object_terms($target,'cg_topic',array('fields'=>'ids'));
    $nonce=wp_create_nonce('cg_home_order');$purged=array();$changed=cg_save_home_order(array($target=>'3'),$nonce);
    home_check($changed===1&&get_post_meta($target,'_cg_home_order',true)==='3','authorized administrator saves an explicit homepage priority');
    home_check(in_array('cg_home_articles',$purged,true),'changing manual priority purges the home article cache tag');
    $purged=array();home_check(cg_save_home_order(array($target=>'3'),$nonce)===0&&!$purged,'repeating an identical order save is idempotent');
    $bad_values=array('0','-1','10000','1.5','1e2','hello',array('2'));
    foreach($bad_values as $bad){$result=cg_save_home_order(array($target=>$bad),$nonce);home_check(is_wp_error($result)&&get_post_meta($target,'_cg_home_order',true)==='3','malformed or out-of-range priority rejected: '.wp_json_encode($bad));}
    home_check(is_wp_error(cg_save_home_order(array($target=>'2'),''))&&get_post_meta($target,'_cg_home_order',true)==='3','missing nonce cannot save homepage priorities');
    home_check(is_wp_error(cg_save_home_order(array($target=>'2'),'invalid-nonce'))&&get_post_meta($target,'_cg_home_order',true)==='3','invalid nonce cannot save homepage priorities');
    $result=cg_save_home_order(array($target=>'2',$hidden[0]=>'1'),$nonce);home_check(is_wp_error($result)&&get_post_meta($target,'_cg_home_order',true)==='3','invalid article in a batch rejects all changes without a partial save');
    $result=cg_save_home_order(array($hidden[7]=>'1'),$nonce);home_check(is_wp_error($result)&&get_post_meta($hidden[7],'_cg_home_order',true)==='','pages cannot be manually inserted into the editorial feed');
    $author=wp_insert_user(array('user_login'=>'home-author-'.wp_generate_password(6,false),'user_pass'=>wp_generate_password(),'role'=>'author'));$users[]=$author;wp_set_current_user($author);
    home_check(is_wp_error(cg_save_home_order(array($target=>'1'),wp_create_nonce('cg_home_order')))&&get_post_meta($target,'_cg_home_order',true)==='3','author without edit_others_posts cannot reorder the homepage');
    $editor=wp_insert_user(array('user_login'=>'home-editor-'.wp_generate_password(6,false),'user_pass'=>wp_generate_password(),'role'=>'editor'));$users[]=$editor;wp_set_current_user($editor);
    home_check(!current_user_can('manage_options')&&cg_save_home_order(array($target=>'2'),wp_create_nonce('cg_home_order'))===1,'an editor can manage homepage order without administrator privileges');
    $deny=function($caps,$cap,$user,$args)use($target){return $cap==='edit_post'&&($args[0]??null)===$target?array('do_not_allow'):$caps;};add_filter('map_meta_cap',$deny,20,4);
    home_check(is_wp_error(cg_save_home_order(array($public[1]=>'1',$target=>'1'),wp_create_nonce('cg_home_order')))&&get_post_meta($public[1],'_cg_home_order',true)==='','each article edit capability is checked before any item in the batch is saved');remove_filter('map_meta_cap',$deny,20);
    wp_set_current_user($admin->ID);$nonce=wp_create_nonce('cg_home_order');$purged=array();home_check(cg_save_home_order(array($target=>''),$nonce)===1&&get_post_meta($target,'_cg_home_order',true)==='','clearing priority restores default date order');
    home_check(get_post_field('post_content',$target)===$original->post_content&&get_post_field('post_excerpt',$target)===$original->post_excerpt&&get_post_field('post_date',$target)===$original->post_date&&get_permalink($target)===$url&&wp_get_object_terms($target,'cg_topic',array('fields'=>'ids'))===$terms,'ordering preserves original body, excerpt, date, permalink and topic associations');
    $purged=array();wp_update_post(array('ID'=>$target,'post_excerpt'=>'Updated feed excerpt.'));home_check(in_array('cg_home_articles',$purged,true),'editing editorial content purges the homepage feed');
    $purged=array();wp_update_post(array('ID'=>$target,'post_password'=>'now-protected'));home_check(!in_array($target,$ids(cg_home_articles(1,30)),true)&&in_array('cg_home_articles',$purged,true),'adding a password removes the article and purges the feed');wp_update_post(array('ID'=>$target,'post_password'=>''));
    $purged=array();wp_trash_post($target);home_check(!in_array($target,$ids(cg_home_articles(1,30)),true)&&in_array('cg_home_articles',$purged,true),'trashing an article removes its homepage card and purges cached listings');wp_untrash_post($target);wp_update_post(array('ID'=>$target,'post_status'=>'publish'));

    $one=$search('HomeEditorialSearchNeedle');$two=$search('HomeEditorialSearchNeedle',2);
    home_check($one->get('posts_per_page')===9&&count($one->posts)===9&&$one->max_num_pages>1&&!array_intersect($ids($one),$ids($two)),'native article search uses nine results per page with stable pagination');
    home_check(!array_diff($public,array_merge($ids($one),$ids($two)))&&!array_intersect($hidden,array_merge($ids($one),$ids($two))),'search includes public editorials and excludes all protected/unpublished/non-editorial content while logged in');
    $robots=apply_filters('wp_robots',array());$rankmath=apply_filters('rank_math/frontend/robots',array());home_check(!empty($robots['noindex'])&&in_array('noindex',$rankmath,true),'search results are noindex with native WordPress and Rank Math robots filters');
    $phrase=$search('"wallet security phrase"');home_check(in_array($target,$ids($phrase),true),'native quoted-phrase search reads ordinary article body');
    wp_set_current_user(0);$public_search=$search('HomeEditorialSearchNeedle');home_check($ids($public_search)===$ids($one),'anonymous and authenticated readers receive the same public search results');
}finally{
    $GLOBALS['wp_the_query']=$old_main;$GLOBALS['wp_query']=$old_query;$GLOBALS['post']=$old_post;update_option('sticky_posts',$old_sticky);wp_set_current_user($admin->ID);foreach($created as $id){wp_delete_post($id,true);}require_once ABSPATH.'wp-admin/includes/user.php';foreach($users as $id){wp_delete_user($id);}wp_set_current_user($old_user);
}
