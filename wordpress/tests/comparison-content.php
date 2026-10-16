<?php
if(home_url()!=='http://localhost:8088'){throw new RuntimeException('Disposable local test only');}
function comparison_check($ok,$label){if(!$ok){throw new RuntimeException($label);}echo "PASS: $label\n";}
$created=array();$users=array();$purged=array();$compare=get_page_by_path('compare');$binance=cg_exchange('binance');$okx=cg_exchange('okx');
add_action('litespeed_purge_post',function($id)use(&$purged){$purged[]=(int)$id;});
$create=function($type='post',$status='publish',$marked=false,$password='')use(&$created){
    $args=array('post_type'=>$type,'post_status'=>$status,'post_title'=>'Comparison inclusion '.$type.' '.$status,'post_content'=>'Original body preserved','post_password'=>$password);
    if($status==='future'){$args['post_date']=$args['post_date_gmt']='2031-01-01 00:00:00';}
    $id=wp_insert_post($args,true);if(is_wp_error($id)){throw new RuntimeException($id->get_error_message());}$created[]=$id;if($marked){update_post_meta($id,'_cg_comparison','1');}return $id;
};
$ids=function($q){return array_map(fn($p)=>(int)$p->ID,$q->posts);};
try{
    $legacy=$create('cg_compare');$native=$create('post','publish',true);
    update_post_meta($native,'_cg_related_exchanges',array($binance->ID,$okx->ID));$original=get_post($native);$original_url=get_permalink($native);
    $unmarked=$create();update_post_meta($unmarked,'_cg_related_exchanges',array($binance->ID,$okx->ID));
    $hidden=array($unmarked);foreach(array('draft','pending','future','private','trash') as $status){$hidden[]=$create('post',$status,true);}
    $hidden[]=$create('post','publish',true,'protected');$hidden[]=$create('cg_compare','publish',false,'protected');
    $hidden[]=$create('page','publish',true);$hidden[]=$create('cg_review','publish',true);
    add_post_meta($native,'_cg_comparison','1');
    $all=$ids(cg_comparison_articles(1,30));
    comparison_check(in_array($legacy,$all,true)&&in_array($native,$all,true),'legacy comparisons and explicitly included ordinary posts both appear');
    comparison_check(count(array_keys($all,$native,true))===1,'duplicate metadata produces one card');
    comparison_check(!array_intersect($hidden,$all),'unmarked multi-platform posts and nonpublic/password/other-type content excluded');
    comparison_check(in_array($native,$ids(cg_exchange_articles($binance->ID,1,30)),true)&&in_array($native,$ids(cg_exchange_articles($okx->ID,1,30)),true),'included post retains both exchange hub associations');
    comparison_check(get_post_type_object('cg_compare')->has_archive===false&&get_permalink($native)===$original_url,'comparison page routing and original article URL unchanged');
    for($i=0;$i<10;$i++){$create('post','publish',true);}
    $one=cg_comparison_articles(1,3);$two=cg_comparison_articles(2,3);
    comparison_check($one->max_num_pages>1&&count($one->posts)===3&&!array_intersect($ids($one),$ids($two)),'comparison pagination returns distinct pages');
    $old=$GLOBALS['post']??null;$GLOBALS['post']=$original;
    comparison_check(str_contains(cg_cards('cg_compare',30),$original_url)&&str_contains(do_shortcode('[cg_content type="comparisons"]'),$original_url),'legacy comparison-card and content-shortcode entry points include native post');
    $GLOBALS['post']=$old;
    $ordinary=new WP_Query(array('post_type'=>'post','post_status'=>'publish','posts_per_page'=>50));
    comparison_check(in_array($unmarked,$ids($ordinary),true),'ordinary post queries are not restricted by comparison filter');

    wp_set_current_user(get_user_by('login','smoke-admin')->ID);$nonce=wp_create_nonce('cg_save_meta');
    $_POST=array('cg_comparison_present'=>'1');cg_save_meta($unmarked);
    comparison_check(get_post_meta($unmarked,'_cg_comparison',true)==='','missing nonce cannot include article');
    $editor=wp_insert_user(array('user_login'=>'comparison-editor-'.wp_generate_password(6,false),'user_pass'=>wp_generate_password(),'role'=>'editor'));$users[]=$editor;
    wp_set_current_user($editor);$nonce=wp_create_nonce('cg_save_meta');
    $_POST=array('cg_meta_nonce'=>$nonce,'cg_comparison_present'=>'1','cg_comparison'=>'1');cg_save_meta($unmarked);
    comparison_check(!current_user_can('manage_options')&&get_post_meta($unmarked,'_cg_comparison',true)==='1','editor without administrator privileges can explicitly include ordinary article');
    comparison_check(get_post_meta($unmarked,'_cg_related_exchanges',true)===array($binance->ID,$okx->ID),'comparison checkbox does not clear existing platform associations');
    $_POST=array('cg_meta_nonce'=>$nonce);cg_save_meta($unmarked);
    comparison_check(get_post_meta($unmarked,'_cg_comparison',true)==='1','save without presence marker preserves inclusion');
    $_POST=array('cg_meta_nonce'=>$nonce,'cg_comparison_present'=>'1','cg_comparison'=>array('1'));cg_save_meta($unmarked);
    comparison_check(get_post_meta($unmarked,'_cg_comparison',true)==='1','malformed checkbox input preserves existing setting');
    $subscriber=wp_insert_user(array('user_login'=>'comparison-subscriber-'.wp_generate_password(6,false),'user_pass'=>wp_generate_password(),'role'=>'subscriber'));$users[]=$subscriber;
    wp_set_current_user($subscriber);$_POST=array('cg_meta_nonce'=>wp_create_nonce('cg_save_meta'),'cg_comparison_present'=>'1');cg_save_meta($unmarked);
    comparison_check(get_post_meta($unmarked,'_cg_comparison',true)==='1','user without edit permission cannot remove inclusion');
    wp_set_current_user(get_user_by('login','smoke-admin')->ID);
    $purged=array();$_POST=array('cg_meta_nonce'=>wp_create_nonce('cg_save_meta'),'cg_comparison_present'=>'1');cg_save_meta($unmarked);
    comparison_check(get_post_meta($unmarked,'_cg_comparison',true)===''&&!in_array($unmarked,$ids(cg_comparison_articles(1,30)),true)&&in_array($compare->ID,$purged,true),'unchecking removes card and purges comparison page');
    $_POST=array();$purged=array();wp_update_post(array('ID'=>$native,'post_excerpt'=>'Updated excerpt'));
    comparison_check(in_array($compare->ID,$purged,true),'editing included content purges comparison page');
    $purged=array();wp_update_post(array('ID'=>$native,'post_password'=>'now-protected'));
    comparison_check(!in_array($native,$ids(cg_comparison_articles(1,30)),true)&&in_array($compare->ID,$purged,true),'adding password removes card and purges cache');
    $purged=array();wp_update_post(array('ID'=>$native,'post_password'=>'','post_status'=>'draft'));
    comparison_check(!in_array($native,$ids(cg_comparison_articles(1,30)),true)&&in_array($compare->ID,$purged,true),'unpublishing removes card and purges cache');
    wp_update_post(array('ID'=>$native,'post_status'=>'publish'));$purged=array();wp_trash_post($native);
    comparison_check(!in_array($native,$ids(cg_comparison_articles(1,30)),true)&&in_array($compare->ID,$purged,true),'trashing removes comparison card and purges cache');
    $purged=array();wp_untrash_post($native);wp_update_post(array('ID'=>$native,'post_status'=>'publish'));
    comparison_check(in_array($native,$ids(cg_comparison_articles(1,30)),true)&&in_array($compare->ID,$purged,true),'restoring and publishing returns comparison card');
    comparison_check(get_permalink($native)===$original_url&&get_post_field('post_content',$native)===$original->post_content,'inclusion does not rewrite original article URL or body');
    $purged=array();wp_delete_post($legacy,true);
    comparison_check(in_array($compare->ID,$purged,true),'deleting legacy comparison purges comparison page');
}finally{
    $_POST=array();wp_set_current_user(get_user_by('login','smoke-admin')->ID);foreach($created as $id){wp_delete_post($id,true);}require_once ABSPATH.'wp-admin/includes/user.php';foreach($users as $id){wp_delete_user($id);}
}
