<?php
if(home_url()!=='http://localhost:8088'){throw new RuntimeException('Local test only');}
function rv_check($yes,$message){if(!$yes){throw new RuntimeException($message);}echo "PASS: $message\n";}
wp_set_current_user(get_user_by('login','smoke-admin')->ID);
$ids=array();$editor_id=0;$admin_id=get_current_user_id();try{
$id=wp_insert_post(array('post_type'=>'post','post_status'=>'publish','post_author'=>get_current_user_id(),'post_title'=>'Review template acceptance','post_content'=>cg_review_pattern()));$ids[]=$id;
update_post_meta($id,'_wp_page_template','templates/exchange-review.php');update_post_meta($id,'_cg_review_exchange','binance');
rv_check(in_array(cg_exchange('binance')->ID,(array)get_post_meta($id,'_cg_related_exchanges',true),true),'native review automatically joins Binance hub');
update_post_meta($id,'_cg_review_exchange','bybit');rv_check(!in_array(cg_exchange('binance')->ID,get_post_meta($id,'_cg_related_exchanges',true),true)&&in_array(cg_exchange('bybit')->ID,get_post_meta($id,'_cg_related_exchanges',true),true),'changing primary platform moves auto-association');
update_post_meta($id,'_cg_review_exchange','binance');
$missing=cg_review_rows(array('rows'=>array(array('label'=>'fee','value'=>'SECRET UNVERIFIED RATE','status'=>'verified','source'=>'','date'=>'2026-10-12'))));rv_check(!str_contains($missing,'SECRET UNVERIFIED RATE')&&str_contains($missing,'未核验'),'incomplete provenance does not show claimed value');
$valid=cg_review_rows(array('rows'=>array(array('label'=>'fee','value'=>'LOCAL TEST VALUE','conditions'=>'test conditions','status'=>'verified','source'=>'https://www.binance.com/en/fee','sourceName'=>'Official test source','date'=>wp_date('Y-m-d')))));rv_check(str_contains($valid,'LOCAL TEST VALUE')&&str_contains($valid,wp_date('Y-m-d'))&&str_contains($valid,'Official test source'),'verified row preserves value, conditions, source and actual date');
rv_check(!str_contains(cg_review_rows(array('rows'=>array(array('value'=>'x','status'=>'verified','source'=>'javascript:alert(1)','date'=>'2030-01-01')))),'javascript:'),'unsafe/future provenance rejected');
rv_check(get_registered_meta_keys('post','post')['_cg_review_sources']['revisions_enabled'],'review source metadata participates in revisions');
rv_check(!cg_review_policy($id,'SG'),'CTA disabled by default');
$p=array('enabled'=>true,'product'=>'test spot product','countries'=>array('SG'),'terms'=>'https://www.binance.com/en/terms','verified'=>wp_date('Y-m-d'),'expiry'=>'2030-01-01');update_post_meta($id,'_cg_review_policy',$p);
rv_check((bool)cg_review_policy($id,'SG')&&!cg_review_policy($id,'US'),'only configured country eligible with valid enabled recommendation');
$r=new WP_REST_Request('POST','/cg/v1/review-eligibility');$r->set_param('post',$id);$r->set_param('country','SG');$r->set_param('confirmed',false);$reply=rest_do_request($r);rv_check($reply->get_status()===403,'explicit reader confirmation required');
$r->set_param('confirmed',true);$reply=rest_do_request($r);rv_check($reply->get_status()===200&&str_contains($reply->get_data()['url'],'signature='),'eligible confirmation yields signed restricted redirect');
$r->set_param('country','US');rv_check(rest_do_request($r)->get_status()===403,'denied country gets no registration link');
$editor_id=wp_insert_user(array('user_login'=>'review-editor-'.wp_generate_password(6,false,false),'user_pass'=>'local-test-only','user_email'=>'editor-test@example.invalid','role'=>'editor'));if(is_wp_error($editor_id)){throw new RuntimeException($editor_id->get_error_message());}wp_set_current_user($editor_id);
$_POST=array('cg_meta_nonce'=>wp_create_nonce('cg_save_meta'),'cg_reviewed'=>wp_date('Y-m-d'));cg_save_meta($id);
rv_check(get_post_meta($id,'_cg_reviewed',true)===wp_date('Y-m-d'),'editor can maintain review verification metadata');
$_POST=array('cg_review_policy_nonce'=>wp_create_nonce('cg_review_policy_save'),'cg_review_policy_enabled'=>'1','cg_review_policy_countries'=>'US');do_action('save_post',$id,get_post($id),true);
rv_check(get_post_meta($id,'_cg_review_policy',true)===$p,'editor cannot overwrite administrator-only recommendation policy');
wp_set_current_user($admin_id);$_POST=array();
wp_update_post(array('ID'=>$id,'post_status'=>'draft'));rv_check(!cg_review_policy($id,'SG'),'unpublished review has no affiliate authorization');
}finally{$_POST=array();wp_set_current_user($admin_id);foreach($ids as $id){wp_delete_post($id,true);}if($editor_id&&!is_wp_error($editor_id)){require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($editor_id);}}
rv_check(str_contains(cg_review_rows(array('rows'=>array(array('label'=>'zero','value'=>'0','status'=>'verified','source'=>'https://www.binance.com/en/fee','date'=>wp_date('Y-m-d'))))),'<td>0'),'explicit verified zero value allowed, never synthesized');
