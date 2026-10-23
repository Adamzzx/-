<?php
// String-only URL checks and disposable local metadata; never follow an external link.
if(home_url()!=='http://localhost:8088'){throw new RuntimeException('Disposable local WordPress only');}
function referral_check($condition,$label){if(!$condition){throw new RuntimeException($label);}echo "PASS: $label\n";}
function referral_config_snapshot($id){
    $config=array();foreach(array('_cg_destination','_cg_referral_host','_cg_referral_exchange','_cg_enabled','_cg_expiry') as $key){$config[$key]=get_post_meta($id,$key);}
    return $config;
}
$previous_user=get_current_user_id();$previous_post=$_POST;
$users=array();$created=array();$platforms=array();$saved_meta=array();
$keys=array('_cg_destination','_cg_referral_host','_cg_referral_exchange','_cg_referral_last_save','_cg_enabled','_cg_expiry','_cg_clicks','_cg_reviewed','_cg_source','_cg_sponsored','_cg_fees','_cg_regions','_cg_terms','_cg_verified','_cg_banner_attachment');
$approved_urls=array();$officials=cg_official_registration_urls();
try{
    foreach(array('administrator','editor','subscriber') as $role){
        $id=wp_insert_user(array('user_login'=>'local-referral-'.$role.'-'.wp_generate_password(7,false),'user_pass'=>wp_generate_password(30,true,true),'role'=>$role));
        if(is_wp_error($id)){throw new RuntimeException($id->get_error_message());}$users[$role]=$id;
    }
    wp_set_current_user($users['administrator']);
    foreach(array('binance'=>'Binance','okx'=>'OKX','bitget'=>'Bitget','bybit'=>'Bybit') as $slug=>$name){
        $post=get_page_by_path($slug,OBJECT,'cg_exchange');
        if(!$post){
            $id=wp_insert_post(array('post_type'=>'cg_exchange','post_status'=>'publish','post_name'=>$slug,'post_title'=>$name,'post_author'=>$users['administrator']),true);
            if(is_wp_error($id)){throw new RuntimeException($id->get_error_message());}$created[]=$id;$post=get_post($id);
        }
        if($post->post_status!=='publish'){throw new RuntimeException('Initialize a public local '.$slug.' platform fixture before this test');}
        $platforms[$slug]=$post;$saved_meta[$post->ID]=array();
        foreach($keys as $key){$saved_meta[$post->ID][$key]=get_post_meta($post->ID,$key);}
        $approved_urls[$slug]='https://'.$slug.'-invite.example.com/join?ref=local-'.$slug.'&lang=zh-CN';
        foreach(array('_cg_destination','_cg_referral_host','_cg_referral_exchange','_cg_enabled','_cg_expiry') as $key){delete_post_meta($post->ID,$key);}
    }

    referral_check(cg_referral_url_host('https://BiNaNcE-Invite.Example.COM:443/join?ref=local')==='binance-invite.example.com','host normalization preserves an exact lowercase host with explicit HTTPS port 443');
    foreach(array('',array('https://example.com/'),'//example.com/join','example.com/join','http://example.com/join','ftp://example.com/join','javascript:alert(1)','https:///join','https://','https://example.com:80/','https://example.com:444/','https://user@example.com/','https://user:password@example.com/','https://127.0.0.1/','https://8.8.8.8/','https://[::1]/','https://[2001:4860:4860::8888]/','https://2130706433/','https://0x7f000001/','https://localhost/','https://sub.localhost/','https://router.local/','https://bad_host.example.com/','https://-bad.example.com/','https://bad-.example.com/',"https://example.com/\r\nLocation: https://other.example.com/",'https://example.com\\@other.example.com/') as $index=>$url){
        referral_check(cg_referral_url_host($url)==='','invalid URL, protocol, authority, IP or local host is rejected #'.$index);
    }

    foreach($platforms as $slug=>$post){
        $url=$approved_urls[$slug];$host=$slug.'-invite.example.com';
        referral_check(cg_safe_destination($slug,$officials[$slug]),'official registration remains on the original allowlist: '.$slug);
        referral_check(!cg_safe_destination($slug,$url),'the official-terms allowlist is not expanded to the custom referral host: '.$slug);
        referral_check(!cg_referral_destination_allowed($post,$url),'a syntactically valid custom host is not trusted before administrator confirmation: '.$slug);
        $result=cg_save_referral_settings($post,$url,false,'');
        referral_check($result===true&&get_post_meta($post->ID,'_cg_destination',true)===$url,'administrator may save a custom URL as an inactive draft: '.$slug);
        referral_check(!get_post_meta($post->ID,'_cg_referral_host',true)&&!get_post_meta($post->ID,'_cg_referral_exchange',true)&&!get_post_meta($post->ID,'_cg_enabled',true)&&!cg_referral_destination_allowed($post,$url),'an unchecked draft neither enables nor trusts its host: '.$slug);
        referral_check(cg_registration_destination($slug)===$officials[$slug]&&!str_contains(cg_registration_rel($slug),'sponsored'),'an inactive draft uses the official un-sponsored fallback: '.$slug);
        $result=cg_save_referral_settings($post,$url,true,'');
        referral_check($result===true&&get_post_meta($post->ID,'_cg_referral_host',true)===$host&&get_post_meta($post->ID,'_cg_referral_exchange',true)===$slug&&get_post_meta($post->ID,'_cg_enabled',true)==='1','administrator confirmation binds the exact host to this platform slug: '.$slug);
        referral_check(cg_referral_destination_allowed($post,$url)&&cg_active_offer($post)&&cg_registration_destination($slug)===$url,'the verified custom URL becomes the centralized destination: '.$slug);
        referral_check(str_contains(cg_registration_rel($slug),'sponsored'),'an enabled custom referral is disclosed as sponsored: '.$slug);
        referral_check(!cg_safe_destination($slug,$url),'custom referral approval still does not authorize it as official policy/terms: '.$slug);
        foreach(array('https://sub.'.$host.'/join','https://'.$host.'.attacker.example.net/join','https://attacker-'.$host.'/join') as $spoof){
            referral_check(!cg_referral_destination_allowed($post,$spoof),'trusted referral hosts are exact, not suffix/prefix or subdomain matches: '.$slug);
        }
    }
    foreach($platforms as $slug=>$post){
        foreach($approved_urls as $other=>$url){if($other!==$slug){referral_check(!cg_referral_destination_allowed($post,$url),'another platform approval cannot authorize '.$other.' for '.$slug);}}
        foreach($officials as $other=>$url){if($other!==$slug){referral_check(!cg_referral_destination_allowed($post,$url),'another platform official host is not accepted for '.$slug);}}
    }
    $okx=$platforms['okx'];
    update_post_meta($okx->ID,'_cg_referral_host','binance-invite.example.com');update_post_meta($okx->ID,'_cg_referral_exchange','binance');update_post_meta($okx->ID,'_cg_destination',$approved_urls['binance']);
    referral_check(!cg_referral_destination_allowed($okx,$approved_urls['binance'])&&!cg_active_offer($okx)&&cg_registration_destination('okx')===$officials['okx'],'copying another platform exact host and approval metadata cannot transfer its trust');
    cg_save_referral_settings($okx,$approved_urls['okx'],true,'');
    $renamed=clone $platforms['binance'];$renamed->post_name='okx';
    referral_check(!cg_referral_destination_allowed($renamed,$approved_urls['binance']),'a platform slug change cannot inherit a custom approval bound to the previous slug');

    $binance=$platforms['binance'];$before=referral_config_snapshot($binance->ID);
    foreach(array('http://binance-invite.example.com/join','https://127.0.0.1/join','https://binance-invite.example.com:444/join','https://user:password@binance-invite.example.com/join','javascript:alert(1)',array('https://attacker.example.com/')) as $index=>$bad){
        $result=cg_save_referral_settings($binance,$bad,true,'');
        referral_check(is_wp_error($result)&&referral_config_snapshot($binance->ID)===$before,'invalid referral update preserves the existing working configuration #'.$index);
    }
    $result=cg_save_referral_settings($binance,$approved_urls['binance'],true,'2026-02-30');
    referral_check(is_wp_error($result)&&referral_config_snapshot($binance->ID)===$before,'invalid expiry cannot silently replace or disable a valid recommendation');
    $result=cg_save_referral_settings($binance,$approved_urls['binance'],true,'2000-01-01');
    referral_check($result===true&&!cg_active_offer($binance)&&cg_registration_destination('binance')===$officials['binance'],'expired recommendation falls back to the official registration URL');
    referral_check(!str_contains(cg_registration_rel('binance'),'sponsored'),'expired recommendations do not retain sponsored-banner state');
    $result=cg_save_referral_settings($binance,$approved_urls['binance'],true,gmdate('Y-m-d',time()+2*DAY_IN_SECONDS));
    referral_check($result===true&&cg_active_offer($binance),'a future expiry keeps an otherwise verified custom referral active');
    $result=cg_save_referral_settings($binance,$approved_urls['binance'],false,'');
    referral_check($result===true&&!get_post_meta($binance->ID,'_cg_referral_host',true)&&!get_post_meta($binance->ID,'_cg_referral_exchange',true)&&!cg_referral_destination_allowed($binance,$approved_urls['binance'])&&!cg_active_offer($binance),'unchecking a previously approved custom referral clears host/slug trust and activation');
    cg_save_referral_settings($binance,$approved_urls['binance'],true,'');$before=referral_config_snapshot($binance->ID);

    foreach(array('editor','subscriber') as $role){
        wp_set_current_user($users[$role]);
        $result=cg_save_referral_settings($binance,'https://unapproved.example.net/join',true,'');
        referral_check(is_wp_error($result)&&referral_config_snapshot($binance->ID)===$before,$role.' cannot trust a new host via the settings helper');
        $_POST=array('cg_meta_nonce'=>wp_create_nonce('cg_save_meta'),'cg_destination'=>'https://unapproved.example.net/join','cg_enabled'=>'1','cg_expiry'=>'');cg_save_meta($binance->ID);
        referral_check(referral_config_snapshot($binance->ID)===$before,$role.' cannot change referral configuration via the native meta-box save');
    }
    wp_set_current_user(0);$result=cg_save_referral_settings($binance,'https://unapproved.example.net/join',true,'');
    referral_check(is_wp_error($result)&&referral_config_snapshot($binance->ID)===$before,'anonymous callers cannot change referral trust');
    wp_set_current_user($users['administrator']);
    foreach(array(null,'wrong-nonce',array('malformed-nonce')) as $index=>$nonce){
        $_POST=array('cg_destination'=>'https://unapproved.example.net/join','cg_enabled'=>'1','cg_expiry'=>'');if($nonce!==null){$_POST['cg_meta_nonce']=$nonce;}cg_save_meta($binance->ID);
        referral_check(referral_config_snapshot($binance->ID)===$before,'missing/invalid native save nonce cannot trust a new host #'.$index);
    }
    $_POST=wp_slash(array('cg_meta_nonce'=>wp_create_nonce('cg_save_meta'),'cg_destination'=>'https://binance-replacement.example.com/join?ref=new-local','cg_enabled'=>'1','cg_expiry'=>''));cg_save_meta($binance->ID);
    referral_check(get_post_meta($binance->ID,'_cg_referral_host',true)==='binance-replacement.example.com'&&cg_active_offer($binance),'administrator with valid native save nonce can replace the approved host');
    referral_check(!cg_referral_destination_allowed($binance,$approved_urls['binance']),'replacing the approved host revokes the previous custom host');
    $valid=referral_config_snapshot($binance->ID);$_POST=wp_slash(array('cg_meta_nonce'=>wp_create_nonce('cg_save_meta'),'cg_destination'=>'https://user:password@bad.example.com/join','cg_enabled'=>'1','cg_expiry'=>''));cg_save_meta($binance->ID);
    referral_check(referral_config_snapshot($binance->ID)===$valid,'invalid native form update also preserves the last valid referral configuration');
    $_POST=wp_slash(array('cg_meta_nonce'=>wp_create_nonce('cg_save_meta'),'cg_destination'=>'https://binance-replacement.example.com/join?ref=corrected-local','cg_enabled'=>'1','cg_expiry'=>''));cg_save_meta($binance->ID);
    referral_check(empty(get_post_meta($binance->ID,'_cg_referral_last_save',true)['error'])&&get_transient('cg_error_'.$users['administrator'])===false,'a successful native save clears both persistent and pending recommendation errors without requiring a reload');
    $result=cg_save_referral_settings($binance,$officials['binance'].'?ref=local-official',true,'');
    referral_check($result===true&&cg_active_offer($binance)&&cg_registration_destination('binance')===$officials['binance'].'?ref=local-official','official-domain referral URLs continue to work with the same settings flow');
    referral_check(!get_post_meta($binance->ID,'_cg_referral_host',true)&&!get_post_meta($binance->ID,'_cg_referral_exchange',true),'returning to an official host removes obsolete custom host/slug approval');
    $result=cg_save_referral_settings($binance,'',false,'');
    referral_check($result===true&&!cg_active_offer($binance)&&cg_registration_destination('binance')===$officials['binance'],'clearing the URL removes the recommendation and uses the official fallback');
    referral_check(!get_post_meta($binance->ID,'_cg_referral_host',true)&&!get_post_meta($binance->ID,'_cg_referral_exchange',true),'clearing the URL does not leave trusted custom host/slug metadata behind');
    foreach($platforms as $post){referral_check(get_post_meta($post->ID,'_cg_clicks')===$saved_meta[$post->ID]['_cg_clicks'],'settings validation never records a visitor click: '.$post->post_name);}
    referral_check(!cg_referral_destination_allowed(null,$approved_urls['binance']),'a missing platform cannot authorize a referral destination');
    referral_check(cg_registration_destination('unknown')==='','unknown platforms have no centralized destination');
}finally{
    $_POST=$previous_post;wp_set_current_user($users['administrator']??$previous_user);
    foreach($saved_meta as $id=>$meta){foreach($meta as $key=>$values){delete_post_meta($id,$key);foreach($values as $value){add_post_meta($id,$key,wp_slash($value));}}}
    foreach($created as $id){wp_delete_post($id,true);}
    require_once ABSPATH.'wp-admin/includes/user.php';foreach($users as $id){delete_transient('cg_error_'.$id);wp_delete_user($id);}
    wp_set_current_user($previous_user);
}
