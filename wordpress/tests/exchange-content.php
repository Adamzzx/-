<?php
if(home_url()!=='http://localhost:8088'){throw new RuntimeException('Disposable local test only');}
function hub_check($ok,$label){if(!$ok){throw new RuntimeException($label);}echo "PASS: $label\n";}
$binance=cg_exchange('binance');$bybit=cg_exchange('bybit');$created=array();$purged=array();
add_action('litespeed_purge_post',function($id)use(&$purged){$purged[]=(int)$id;});
$create=function($type,$status,$related,$password='')use(&$created){
    $args=array('post_type'=>$type,'post_status'=>$status,'post_title'=>'Hub acceptance '.$type.' '.$status,'post_content'=>'Test body','post_password'=>$password);
    if($status==='future'){$args['post_date']='2030-01-01 00:00:00';$args['post_date_gmt']='2030-01-01 00:00:00';}
    $id=wp_insert_post($args,true);if(is_wp_error($id)){throw new RuntimeException($id->get_error_message());}$created[]=$id;
    update_post_meta($id,'_cg_related_exchanges',$related);return $id;
};
$ids=function($query){return array_map(fn($p)=>(int)$p->ID,$query->posts);};
try{
    $visible=array();foreach(array('post','cg_review','cg_compare','cg_ranking','cg_guide') as $type){$visible[]=$create($type,'publish',array($binance->ID));}
    $multi=$create('cg_compare','publish',array($binance->ID,$bybit->ID));$visible[]=$multi;
    $legacy=$create('cg_guide','publish',array((string)$binance->ID));$visible[]=$legacy;
    $hidden=array();foreach(array('draft','pending','future','private') as $status){$hidden[]=$create('post',$status,array($binance->ID));}
    $hidden[]=$create('cg_guide','publish',array($binance->ID),'protected');
    $hidden[]=$create('cg_review','publish',array($bybit->ID));
    $hidden[]=$create('post','publish',array($binance->ID*100+7));
    $hidden[]=$create('post','publish',array_fill(0,$binance->ID+1,$bybit->ID));
    $all=$ids(cg_exchange_articles($binance->ID,1,30));
    hub_check(!array_diff($visible,$all),'all published associated editorial types and legacy IDs appear');
    hub_check(!array_intersect($hidden,$all),'draft/private/future/password/unrelated/index-collision content excluded');
    hub_check(count($all)===count(array_unique($all)),'multi-association yields no duplicate articles');
    hub_check(in_array($multi,$ids(cg_exchange_articles($bybit->ID,1,30)),true),'comparison appears on both exchange hubs');
    $one=cg_exchange_articles($binance->ID,1,2);$two=cg_exchange_articles($binance->ID,2,2);
    hub_check($one->max_num_pages>1&&count($one->posts)===2&&!array_intersect($ids($one),$ids($two)),'pagination returns distinct pages');
    wp_update_post(array('ID'=>$hidden[0],'post_status'=>'publish'));
    hub_check(in_array($hidden[0],$ids(cg_exchange_articles($binance->ID,1,30)),true),'publishing exposes article and invalidates hub');
    wp_update_post(array('ID'=>$visible[0],'post_status'=>'trash'));
    hub_check(!in_array($visible[0],$ids(cg_exchange_articles($binance->ID,1,30)),true),'trashed article disappears');
    wp_set_current_user(get_user_by('login','smoke-admin')->ID);
    $_POST=array('cg_meta_nonce'=>wp_create_nonce('cg_save_meta'),'cg_related_exchanges_present'=>'1','cg_related_exchanges'=>array($bybit->ID,$bybit->ID,999999));
    cg_save_meta($visible[1]);
    hub_check(get_post_meta($visible[1],'_cg_related_exchanges',true)===array($bybit->ID),'checkbox handler validates and deduplicates exchange IDs');
    hub_check(!in_array($visible[1],$ids(cg_exchange_articles($binance->ID,1,30)),true)&&in_array($visible[1],$ids(cg_exchange_articles($bybit->ID,1,30)),true),'reassignment moves article to correct hub');
    $_POST=array('cg_meta_nonce'=>wp_create_nonce('cg_save_meta'),'cg_related_exchanges_present'=>'1');cg_save_meta($visible[1]);
    hub_check(get_post_meta($visible[1],'_cg_related_exchanges',true)===array(),'unchecking all platforms clears association');
    hub_check(in_array($binance->ID,$purged,true)&&in_array($bybit->ID,$purged,true),'old and new hubs invalidated');
}finally{$_POST=array();foreach($created as $id){wp_delete_post($id,true);}}
