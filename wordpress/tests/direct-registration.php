<?php
function check_direct($condition,$label){if(!$condition){throw new RuntimeException($label);}echo "PASS: $label\n";}
foreach(cg_official_registration_urls() as $slug=>$url){check_direct(cg_safe_destination($slug,$url),'official HTTPS registration host: '.$slug);}
$post=cg_exchange('binance');$keys=array('_cg_destination','_cg_enabled','_cg_expiry');$before=array();foreach($keys as $key){$before[$key]=get_post_meta($post->ID,$key,true);}
try{
    delete_post_meta($post->ID,'_cg_destination');delete_post_meta($post->ID,'_cg_enabled');
    check_direct(cg_registration_destination('binance')==='https://www.binance.com/en/register','blank settings use official registration');
    update_post_meta($post->ID,'_cg_destination','https://www.binance.com/en/register?ref=owned-test');update_post_meta($post->ID,'_cg_enabled','1');delete_post_meta($post->ID,'_cg_expiry');
    check_direct(str_contains(cg_registration_destination('binance'),'ref=owned-test'),'enabled recommendation overrides official default');
    check_direct(str_contains(cg_registration_rel('binance'),'sponsored'),'referral banner marked sponsored');
    update_post_meta($post->ID,'_cg_expiry','2020-01-01');
    check_direct(cg_registration_destination('binance')==='https://www.binance.com/en/register','expired recommendation returns to official registration');
    update_post_meta($post->ID,'_cg_destination','https://evil.invalid/register');delete_post_meta($post->ID,'_cg_expiry');
    check_direct(cg_registration_destination('binance')==='https://www.binance.com/en/register','invalid destination cannot override official registration');
    check_direct(!str_contains(cg_registration_rel('binance'),'sponsored'),'official banner is not marked sponsored');
    wp_update_post(array('ID'=>$post->ID,'post_status'=>'draft'));
    check_direct(cg_registration_destination('binance')==='https://www.binance.com/en/register','no public exchange record still uses official registration');
    check_direct(cg_registration_destination('unknown')==='','unknown platform has no registration destination');
}finally{wp_update_post(array('ID'=>$post->ID,'post_status'=>'publish'));foreach($before as $key=>$value){update_post_meta($post->ID,$key,$value);}}
