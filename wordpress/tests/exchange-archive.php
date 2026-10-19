<?php
// This suite creates and removes fixtures only in the disposable local WordPress.
if(home_url()!=='http://localhost:8088'){throw new RuntimeException('Disposable local test only');}
function directory_check($ok,$label){if(!$ok){throw new RuntimeException($label);}echo "PASS: $label\n";}
$created=array();$purged=array();$old_main=$GLOBALS['wp_the_query'];$old_query=$GLOBALS['wp_query'];$old_post=$GLOBALS['post']??null;$old_user=get_current_user_id();$old_request=$_POST;
add_action('litespeed_purge_tag',function($tag)use(&$purged){$purged[]=$tag;});
$ids=function($query){return array_map(fn($p)=>(int)$p->ID,$query->posts);};
$make=function($type='post',$status='publish',$related=array(),$password='')use(&$created){
    $args=array('post_type'=>$type,'post_status'=>$status,'post_title'=>'Local exchange directory '.$type.' '.$status,'post_content'=>'Original exchange directory article body','post_excerpt'=>'Original short excerpt','post_password'=>$password,'post_date'=>'2020-01-01 00:00:00','post_date_gmt'=>'2020-01-01 00:00:00');
    if($status==='future'){$args['post_date']=$args['post_date_gmt']='2031-01-01 00:00:00';}
    $id=wp_insert_post($args,true);if(is_wp_error($id)){throw new RuntimeException($id->get_error_message());}$created[]=$id;
    if($related){update_post_meta($id,'_cg_related_exchanges',$related);}return $id;
};
$main=function($page=1){$q=new WP_Query();$GLOBALS['wp_the_query']=$q;$GLOBALS['wp_query']=$q;$q->query(array('post_type'=>'cg_exchange','paged'=>$page));return $q;};
$tagged=function()use(&$purged){return in_array('cg_exchange_directory',$purged,true);};
try{
    foreach(array('cg_is_exchange_directory','cg_exchange_directory_intro','cg_exchange_directory_url','cg_exchange_directory_items') as $function){directory_check(function_exists($function),'directory API exists: '.$function);}
    $platforms=array();foreach(array('binance','okx','bitget','bybit','gate','mexc') as $slug){$platform=cg_exchange($slug);directory_check($platform instanceof WP_Post,'local platform fixture exists: '.$slug);$platforms[$slug]=$platform->ID;}
    directory_check(cg_exchange_directory_url()==='http://localhost:8088/exchanges/'&&cg_exchange_directory_url(2)==='http://localhost:8088/exchanges/page/2/','directory keeps the existing root URL and native pagination');
    directory_check(trim(wp_strip_all_tags(cg_exchange_directory_intro()))!=='','directory has a concise introduction');
    $visible=array();foreach(array('post','cg_review','cg_compare','cg_ranking','cg_guide') as $type){$visible[]=$make($type,'publish',array($platforms['binance']));}
    foreach(array('okx','bitget','bybit') as $slug){$visible[]=$make('post','publish',array($platforms[$slug]));}
    $multi=$make('post','publish',array($platforms['binance'],$platforms['okx']));$visible[]=$multi;
    add_post_meta($multi,'_cg_related_exchanges',array($platforms['binance'],$platforms['bitget']));
    $legacy_string=$make('cg_guide','publish',array((string)$platforms['bybit']));$visible[]=$legacy_string;
    $comparison=$make();update_post_meta($comparison,'_cg_comparison','1');add_post_meta($comparison,'_cg_comparison','1');$visible[]=$comparison;
    $legacy_comparison=$make('cg_compare');$visible[]=$legacy_comparison;
    $hidden=array();foreach(array('draft','pending','future','private','trash') as $status){$hidden[]=$make('post',$status,array($platforms['binance']));}
    $hidden[]=$make('post','publish',array($platforms['okx']),'local-secret');$hidden[]=$make('cg_compare','publish',array(),'local-secret');
    $hidden[]=$make('page','publish',array($platforms['binance']));$hidden[]=$make('cg_exchange','publish',array($platforms['binance']));
    $hidden[]=$make('post','publish',array($platforms['gate']));$hidden[]=$make('post','publish',array($platforms['mexc']));
    $unrelated=$make();$hidden[]=$unrelated;
    $hidden[]=$make('post','publish',array($platforms['binance']*100+7));
    $hidden[]=$make('post','publish',array_fill(0,$platforms['binance']+1,$platforms['gate']));
    $false_comparison=$make();update_post_meta($false_comparison,'_cg_comparison','0');$hidden[]=$false_comparison;
    $bad_type=$make('cg_review');update_post_meta($bad_type,'_cg_comparison','1');$hidden[]=$bad_type;
    $original=get_post($multi);$original_url=get_permalink($multi);$relations=get_post_meta($multi,'_cg_related_exchanges',false);
    $root=cg_find_topic('crypto');$child=cg_find_topic('beginners',$root->term_id);wp_set_object_terms($unrelated,array($child->term_id),'cg_topic');
    wp_set_current_user(get_user_by('login','smoke-admin')->ID);$all=cg_exchange_directory_items(1,100);$all_ids=$ids($all);
    directory_check(!array_diff($visible,$all_ids),'auxiliary directory query includes associated native/legacy content from all four platforms plus explicit comparisons');
    directory_check(!array_intersect($hidden,$all_ids),'directory excludes unrelated, retired-platform-only, noneditorial, private, future, draft and password content while logged in');
    directory_check(count($all_ids)===count(array_unique($all_ids))&&count(array_keys($all_ids,$multi,true))===1&&count(array_keys($all_ids,$comparison,true))===1,'multiple platform associations and duplicate meta rows yield one card');
    $one=$main();directory_check(cg_is_exchange_directory($one)&&$one->get('_cg_exchange_directory'),'native main query is recognized and explicitly scoped');
    directory_check($one->get('post_status')==='publish'&&$one->get('has_password')===false&&$one->get('posts_per_page')===9&&$one->get('ignore_sticky_posts'),'main archive uses nine public password-free editorial cards without sticky promotion');
    directory_check($ids($one)===$ids(cg_exchange_directory_items(1,9)),'main and auxiliary query return exactly the same first-page ordering');
    $two=$main(2);directory_check(count($one->posts)===9&&count($two->posts)>0&&!array_intersect($ids($one),$ids($two)),'native second page contains distinct results');
    directory_check($ids($two)===$ids(cg_exchange_directory_items(2,9)),'main and auxiliary pagination agree');
    $all_again=cg_exchange_directory_items(1,100);directory_check($ids($all_again)===$all_ids,'same-date article ordering is deterministic');
    wp_set_current_user(0);directory_check($ids(cg_exchange_directory_items(1,100))===$all_ids,'anonymous and administrator directory inclusion are identical');
    $ordinary=new WP_Query(array('post_type'=>'post','post_status'=>'publish','posts_per_page'=>100));directory_check(in_array($unrelated,$ids($ordinary),true)&&!cg_is_exchange_directory($ordinary),'ordinary queries keep unrelated posts and are not mistaken for the directory');
    $crypto=new WP_Query();$GLOBALS['wp_the_query']=$crypto;$GLOBALS['wp_query']=$crypto;$crypto->query(array('cg_topic'=>$root->slug));
    directory_check(in_array($unrelated,$ids($crypto),true)&&!$crypto->get('_cg_exchange_directory')&&!cg_is_exchange_directory($crypto),'crypto archive remains independent from exchange filtering');
    directory_check(!array_intersect($hidden,$ids($main())),'main archive applies the same privacy and exclusion rules');
    directory_check(get_permalink($multi)===$original_url&&get_post_field('post_content',$multi)===$original->post_content&&get_post_meta($multi,'_cg_related_exchanges',false)===$relations,'listing preserves original article URL, body and all platform associations');
    $purged=array();wp_update_post(array('ID'=>$multi,'post_excerpt'=>'Changed local directory excerpt'));directory_check($tagged(),'editing listed content invalidates directory caches');
    $purged=array();update_post_meta($multi,'_cg_related_exchanges',array($platforms['gate']));directory_check($tagged(),'changing platform metadata invalidates old directory caches');
    $purged=array();delete_post_meta($multi,'_cg_related_exchanges');directory_check($tagged(),'removing the final platform relationship invalidates directory caches');
    $purged=array();update_post_meta($unrelated,'_cg_comparison','1');directory_check($tagged()&&in_array($unrelated,$ids(cg_exchange_directory_items(1,100)),true),'marking a comparison adds its card and invalidates caches');
    $purged=array();delete_post_meta($unrelated,'_cg_comparison');directory_check($tagged()&&!in_array($unrelated,$ids(cg_exchange_directory_items(1,100)),true),'unmarking a comparison removes its card and invalidates caches');
    $purged=array();wp_update_post(array('ID'=>$comparison,'post_password'=>'local-secret'));directory_check($tagged()&&!in_array($comparison,$ids(cg_exchange_directory_items(1,100)),true),'password protection removes a comparison and invalidates cached lists');
    $purged=array();wp_update_post(array('ID'=>$legacy_comparison,'post_status'=>'draft'));directory_check($tagged()&&!in_array($legacy_comparison,$ids(cg_exchange_directory_items(1,100)),true),'unpublishing a legacy comparison invalidates directory caches');
    $purged=array();wp_delete_post($visible[0],true);directory_check($tagged(),'deleting a listed article invalidates directory caches');
}finally{
    $_POST=$old_request;wp_set_current_user($old_user);$GLOBALS['wp_the_query']=$old_main;$GLOBALS['wp_query']=$old_query;$GLOBALS['post']=$old_post;
    foreach($created as $id){wp_delete_post($id,true);}
}
