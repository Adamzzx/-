<?php
if(home_url()!=='http://localhost:8088'){throw new RuntimeException('Disposable local test only');}
function crypto_check($ok,$label){if(!$ok){throw new RuntimeException($label);}echo "PASS: $label\n";}
$created=array();$created_terms=array();$purged=array();$root=cg_find_topic('crypto');$beginners=cg_find_topic('beginners',$root->term_id);$wallets=cg_find_topic('wallets-security',$root->term_id);$tokenized=cg_find_topic('tokenized-stocks');
add_action('litespeed_purge_tag',function($tag)use(&$purged){$purged[]=$tag;});
$old_main=$GLOBALS['wp_the_query'];$old_query=$GLOBALS['wp_query'];$old_post=$GLOBALS['post']??null;$old_user=get_current_user_id();$old_post_data=$_POST;$old_description=$beginners->description;
$make=function($type='post',$status='publish',$terms=array(),$password='')use(&$created){$args=array('post_type'=>$type,'post_status'=>$status,'post_title'=>'Local crypto '.$type.' '.$status,'post_content'=>'Original crypto archive body','post_password'=>$password);if($status==='future'){$args['post_date']=$args['post_date_gmt']='2031-01-01 00:00:00';}$id=wp_insert_post($args,true);if(is_wp_error($id)){throw new RuntimeException($id->get_error_message());}$created[]=$id;if($terms){wp_set_object_terms($id,array_map('intval',$terms),'cg_topic');}return $id;};
$archive=function($term,$page=1){$q=new WP_Query();$GLOBALS['wp_the_query']=$q;$GLOBALS['wp_query']=$q;$q->query(array('cg_topic'=>$term->slug,'paged'=>$page));return $q;};
$ids=function($q){return array_map(fn($p)=>(int)$p->ID,$q->posts);};
try{
    crypto_check(cg_is_crypto_topic($root)&&cg_is_crypto_topic($beginners)&&!cg_is_crypto_topic($tokenized),'crypto scope includes root and descendants only');
    crypto_check(cg_topic_is_open($root)&&cg_topic_is_open($wallets),'crypto navigation opens even without published articles');
    $sections=cg_crypto_sections();crypto_check(array_map(fn($t)=>$t->slug,$sections)===array('beginners','wallets-security','deposits-withdrawals','yield-products'),'four category filters use confirmed order');
    foreach(array_merge(array($root),$sections) as $term){crypto_check(trim(wp_strip_all_tags(cg_crypto_topic_intro($term)))!=='','default introduction exists for '.$term->slug);}
    wp_update_term($beginners->term_id,'cg_topic',array('description'=>'<p>Owner edited archive introduction.</p>'));crypto_check(str_contains(cg_crypto_topic_intro(get_term($beginners->term_id,'cg_topic')),'Owner edited archive introduction.'),'native term description overrides default introduction');
    $unsafe=clone $beginners;$unsafe->description='<p>Safe introduction <a href="javascript:alert(1)">unsafe URL</a></p><script>alert(1)</script>';crypto_check(!str_contains(cg_crypto_topic_intro($unsafe),'<script')&&!str_contains(cg_crypto_topic_intro($unsafe),'javascript:'),'archive introduction strips unsafe HTML and URL protocols');
    $crumbs=cg_crypto_breadcrumbs($beginners);crypto_check(array_column($crumbs,'name')===array('首页',$root->name,$beginners->name)&&$crumbs[1]['url']===get_term_link($root)&&$crumbs[2]['url']===get_term_link($beginners),'child breadcrumb follows home, parent and current category URLs');
    $native=$make('post','publish',array($beginners->term_id,$wallets->term_id));$original=get_post($native);$url=get_permalink($native);$original_terms=wp_get_object_terms($native,'cg_topic',array('fields'=>'ids'));
    $parent_only=$make('post','publish',array($root->term_id));$legacy=array();foreach(array('cg_review','cg_compare','cg_ranking','cg_guide') as $type){$legacy[]=$make($type,'publish',array($beginners->term_id));}
    $hidden=array();foreach(array('draft','pending','future','private','trash') as $status){$hidden[]=$make('post',$status,array($beginners->term_id));}$hidden[]=$make('post','publish',array($beginners->term_id),'local-password');$hidden[]=$make('page','publish',array($beginners->term_id));$hidden[]=$make('cg_exchange','publish',array($beginners->term_id));$unrelated=$make('post','publish',array($tokenized->term_id));
    // Query as administrator too: visitor-facing archive privacy must not depend on login.
    wp_set_current_user(get_user_by('login','smoke-admin')->ID);$all=$archive($root);$all_ids=$ids($all);
    crypto_check($all->get('posts_per_page')===9&&$all->get('post_status')==='publish'&&$all->get('has_password')===false,'main archive sets nine cards and explicit public, password-free visibility');
    crypto_check(in_array($native,$all_ids,true)&&in_array($parent_only,$all_ids,true)&&!array_diff($legacy,$all_ids),'parent archive includes native, legacy and descendant articles');
    crypto_check(count(array_keys($all_ids,$native,true))===1,'one article assigned to two categories produces one root card');
    crypto_check(!array_intersect($hidden,$all_ids)&&!in_array($unrelated,$all_ids,true),'draft, private, password, noneditorial and unrelated content stays out while logged in');
    $child=$archive($beginners);crypto_check(in_array($native,$ids($child),true)&&!in_array($parent_only,$ids($child),true),'child archive contains associated articles without unrelated parent-only content');
    wp_set_current_user(0);crypto_check($ids($archive($beginners))===$ids($child),'anonymous archive visibility matches logged-in archive');
    for($i=0;$i<12;$i++){$make('post','publish',array($beginners->term_id));}$one=$archive($root);$two=$archive($root,2);crypto_check(count($one->posts)===9&&count($two->posts)>0&&!array_intersect($ids($one),$ids($two)),'date and ID ordering provides distinct paginated results');
    $ordinary=new WP_Query(array('post_type'=>'post','post_status'=>'publish','posts_per_page'=>100));crypto_check(in_array($unrelated,$ids($ordinary),true),'archive scope does not restrict unrelated post queries');
    crypto_check(get_permalink($native)===$url&&get_post_field('post_content',$native)===$original->post_content&&wp_get_object_terms($native,'cg_topic',array('fields'=>'ids'))===$original_terms,'archive inclusion preserves original article URL, body and taxonomy associations');
    $purged=array();wp_update_post(array('ID'=>$native,'post_excerpt'=>'Changed archive excerpt'));crypto_check(in_array('cg_crypto_topics',$purged,true),'editing an associated article purges root, category and pagination caches');
    $purged=array();wp_set_object_terms($native,array($tokenized->term_id),'cg_topic');crypto_check(in_array('cg_crypto_topics',$purged,true),'moving an article out of crypto invalidates emptied old categories');
    $purged=array();wp_set_object_terms($native,array($wallets->term_id),'cg_topic');crypto_check(in_array('cg_crypto_topics',$purged,true),'new category association invalidates crypto archive caches');
    $purged=array();wp_remove_object_terms($native,array($wallets->term_id),'cg_topic');crypto_check(in_array('cg_crypto_topics',$purged,true),'removing the final native taxonomy relationship purges archives');
    wp_set_object_terms($native,array($beginners->term_id),'cg_topic');$purged=array();wp_update_post(array('ID'=>$native,'post_status'=>'draft'));crypto_check(in_array('cg_crypto_topics',$purged,true)&&!in_array($native,$ids($archive($beginners)),true),'unpublishing invalidates caches and removes article');
    $purged=array();wp_delete_post($native,true);crypto_check(in_array('cg_crypto_topics',$purged,true),'deleting an associated article purges archives');
    $purged=array();wp_update_term($wallets->term_id,'cg_topic',array('description'=>$wallets->description));crypto_check(in_array('cg_crypto_topics',$purged,true),'native category editing purges archive metadata and filter caches');
}finally{
    $_POST=$old_post_data;wp_set_current_user($old_user);$GLOBALS['wp_the_query']=$old_main;$GLOBALS['wp_query']=$old_query;$GLOBALS['post']=$old_post;
    foreach($created as $id){wp_delete_post($id,true);}wp_update_term($beginners->term_id,'cg_topic',array('description'=>$old_description));foreach($created_terms as $id){wp_delete_term($id,'cg_topic');}
}
