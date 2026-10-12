<?php
if(!defined('ABSPATH')){exit;}
function cg_review_routes(){add_rewrite_rule('^go/review/([0-9]+)/?$','index.php?cg_review_go=$matches[1]','top');}
add_action('init','cg_review_routes');add_filter('query_vars',function($vars){$vars[]='cg_review_go';return $vars;});
function cg_review_policy($id,$country){
    if(!cg_is_review($id)||get_post_status($id)!=='publish'||post_password_required($id)){return false;}
    $policy=get_post_meta($id,'_cg_review_policy',true);$slug=get_post_meta($id,'_cg_review_exchange',true);
    if(!is_array($policy)||empty($policy['enabled'])||!cg_review_date_valid($policy['verified']??'')||!cg_safe_destination($slug,$policy['terms']??'')||empty($policy['product'])||cg_expiry_timestamp($policy['expiry']??'')===false||cg_expiry_timestamp($policy['expiry'])<=time()){return false;}
    if(!preg_match('/^[A-Z]{2}$/',$country)||!in_array($country,(array)($policy['countries']??array()),true)){return false;}
    // Only a verified, enabled recommendation is allowed here; no official fallback.
    $exchange=cg_exchange($slug);if(!cg_active_offer($exchange)){return false;}
    return array('url'=>get_post_meta($exchange->ID,'_cg_destination',true),'slug'=>$slug,'policy'=>$policy);
}
function cg_review_signature($id,$country,$expires,$policy){return hash_hmac('sha256',$id.'|'.$country.'|'.$expires.'|'.wp_json_encode($policy),wp_salt('auth'));}
add_action('rest_api_init',function(){register_rest_route('cg/v1','/review-eligibility',array('methods'=>'POST','permission_callback'=>'__return_true','callback'=>function($request){
    $id=absint($request->get_param('post'));$country=strtoupper(sanitize_text_field($request->get_param('country')??''));
    if($request->get_param('confirmed')!==true){return new WP_Error('cg_confirmation','请先确认地区和条款。',array('status'=>403));}
    $policy=cg_review_policy($id,$country);if(!$policy){return new WP_Error('cg_unavailable','此地区或产品暂无可展示的推荐入口。',array('status'=>403));}
    $expires=time()+600;$rules=(array)get_option('rewrite_rules',array());
    $base=isset($rules['^go/review/([0-9]+)/?$'])?home_url('/go/review/'.$id.'/'):add_query_arg('cg_review_go',$id,home_url('/'));
    $response=new WP_REST_Response(array('url'=>add_query_arg(array('country'=>$country,'expires'=>$expires,'signature'=>cg_review_signature($id,$country,$expires,$policy)),$base),'label'=>'查看已核验的推荐入口（新标签页）'));
    $response->header('Cache-Control','no-store');return $response;
}));});
add_action('template_redirect',function(){
    $raw=get_query_var('cg_review_go');if(!$raw){return;}nocache_headers();header('X-Robots-Tag: noindex, nofollow');do_action('litespeed_control_set_nocache','Restricted review CTA');
    $id=absint($raw);$country=is_string($_GET['country']??null)?strtoupper(sanitize_text_field(wp_unslash($_GET['country']))):'';$expires=is_scalar($_GET['expires']??null)?absint($_GET['expires']):0;
    $signature=is_string($_GET['signature']??null)?sanitize_text_field(wp_unslash($_GET['signature'])):'';$policy=cg_review_policy($id,$country);
    if(!$policy||$expires<time()||$expires>time()+600||!hash_equals(cg_review_signature($id,$country,$expires,$policy),$signature)){wp_die('入口授权无效、过期或地区条件不符。请返回评测页面重新确认。','入口不可用',array('response'=>403));}
    wp_redirect($policy['url'],302,'Exchange Guide Review');exit;
},0);
add_action('add_meta_boxes',function(){if(current_user_can('manage_options')){foreach(array('post','cg_review') as $type){add_meta_box('cg-review-policy','评测推荐展示规则（管理员）','cg_review_policy_box',$type,'normal');}}});
function cg_review_policy_box($post){
    wp_nonce_field('cg_review_policy_save','cg_review_policy_nonce');$p=(array)get_post_meta($post->ID,'_cg_review_policy',true);
    echo '<p>默认关闭。仅在核实合作条款和产品地区条件后启用；地区自报不证明实际账户资格，最终由平台审核。未启用合作链接不回落到官方注册链接。</p><label><input type="checkbox" name="cg_review_policy_enabled" value="1" '.checked(!empty($p['enabled']),true,false).'> 允许在确认地区后展示</label>';
    foreach(array('product'=>'适用产品说明','countries'=>'允许地区 ISO 两位代码（如 SG,AU；不支持全部地区通配）','terms'=>'平台 HTTPS 合作/资格条款链接','verified'=>'条款核验日期','expiry'=>'展示截止日期（UTC）') as $key=>$label){$value=$p[$key]??'';if(is_array($value)){$value=implode(',',$value);}echo '<p><label>'.esc_html($label).'<input class="widefat" name="cg_review_policy_'.$key.'" type="'.(in_array($key,array('verified','expiry'),true)?'date':($key==='terms'?'url':'text')).'" value="'.esc_attr($value).'"></label></p>';}
}
add_action('save_post',function($id){
    if(!current_user_can('manage_options')||!current_user_can('edit_post',$id)||wp_is_post_revision($id)||wp_is_post_autosave($id)||empty($_POST['cg_review_policy_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cg_review_policy_nonce'])),'cg_review_policy_save')){return;}
    $p=array('enabled'=>!empty($_POST['cg_review_policy_enabled']));foreach(array('product','terms','verified','expiry') as $key){$p[$key]=sanitize_text_field(wp_unslash($_POST['cg_review_policy_'.$key]??''));}
    $p['countries']=array_values(array_unique(array_filter(array_map('trim',explode(',',strtoupper(sanitize_text_field(wp_unslash($_POST['cg_review_policy_countries']??''))))),fn($c)=>preg_match('/^[A-Z]{2}$/',$c))));update_post_meta($id,'_cg_review_policy',$p);do_action('litespeed_purge_post',$id);
},25);
// Suppress existing registration banners/shortcodes anywhere inside a review.
add_filter('do_shortcode_tag',function($output,$tag){if(cg_is_review(get_the_ID())&&in_array($tag,array('cg_affiliate','cg_registration_cards'),true)){return '<p class="cg-meta">推荐入口需在评测末尾确认地区与条款后查看。</p>';}return $output;},10,2);
add_filter('rest_post_dispatch',function($response,$server,$request){if($request->get_route()==='/cg/v1/review-eligibility'){$response=rest_ensure_response($response);if($response instanceof WP_HTTP_Response){$response->header('Cache-Control','no-store');$response->header('X-Robots-Tag','noindex');}}return $response;},10,3);
