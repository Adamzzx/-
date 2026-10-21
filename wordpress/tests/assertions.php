<?php
function cg_assert($condition, $name) { if (!$condition) { throw new RuntimeException('FAIL: '.$name); } echo 'PASS: '.$name."\n"; }
$admin = get_user_by('login', 'smoke-admin'); wp_set_current_user($admin->ID);
$binance = cg_exchange('binance');
cg_assert((bool)$binance, 'seeded exchange exists');
$seeded_before=count(get_posts(array('post_type'=>'cg_exchange','numberposts'=>100)));
foreach(array('binance','okx','bitget','bybit') as $slug){cg_assert((bool)cg_exchange($slug),'confirmed seeded identity exists: '.$slug);}
foreach (array('http://www.binance.com/', 'https://binance.com.evil.invalid/', 'https://u:p@www.binance.com/', 'https://www.binance.com:8443/', 'javascript:alert(1)', 'https://www.okx.com/') as $url) { cg_assert(!cg_safe_destination('binance',$url), 'blocked invalid destination '.$url); }
cg_assert(cg_safe_destination('binance','https://www.binance.com/en/register?ref=smoke'), 'official HTTPS accepted');
cg_assert(cg_expiry_timestamp('2026-02-31') === false, 'impossible date rejected');
cg_assert(cg_expiry_timestamp('invalid') === false, 'malformed date rejected');
// Save via the real nonce/capability-protected handler, not direct metadata writes.
$_POST = array('cg_meta_nonce'=>wp_create_nonce('cg_save_meta'),'cg_verified'=>'1','cg_reviewed'=>'2026-10-10','cg_fees'=>'测试费用条件，非真实报价。','cg_regions'=>'测试地区说明。','cg_destination'=>'https://www.binance.com/en/register?ref=smoke','cg_enabled'=>'1','cg_terms'=>'测试条款');
cg_save_meta($binance->ID); cg_assert(cg_active_offer($binance), 'authorized field save activates offer');
$before=get_post_meta($binance->ID,'_cg_fees',true); cg_setup_pages(true); cg_setup_pages(true);
cg_assert(get_post_meta($binance->ID,'_cg_fees',true)===$before, 'repeat initialization preserves edits');
cg_assert(count(get_posts(array('post_type'=>'cg_exchange','numberposts'=>100)))===$seeded_before, 'repeat initialization preserves existing identities without adding duplicates');
$_POST['cg_destination']='https://evil.invalid/'; cg_save_meta($binance->ID); cg_assert(!cg_active_offer($binance), 'invalid destination disables offer');
$_POST['cg_destination']='https://www.binance.com/en/register?ref=smoke';$_POST['cg_expiry']='2000-01-01';cg_save_meta($binance->ID);cg_assert(!cg_active_offer($binance),'expired offer disabled');
$_POST['cg_expiry']='';cg_save_meta($binance->ID);
$subscriber=wp_insert_user(array('user_login'=>'smoke-subscriber','user_pass'=>wp_generate_password(24),'user_email'=>'subscriber@example.invalid','role'=>'subscriber'));
if(is_wp_error($subscriber)){$subscriber=get_user_by('login','smoke-subscriber')->ID;}
wp_set_current_user($subscriber);$_POST['cg_fees']='UNAUTHORIZED';$_POST['cg_meta_nonce']=wp_create_nonce('cg_save_meta');cg_save_meta($binance->ID);
cg_assert(get_post_meta($binance->ID,'_cg_fees',true)===$before,'subscriber cannot edit management fields');
wp_set_current_user($admin->ID);$_POST['cg_fees']='NO_NONCE';unset($_POST['cg_meta_nonce']);cg_save_meta($binance->ID);
cg_assert(get_post_meta($binance->ID,'_cg_fees',true)===$before,'missing nonce cannot edit management fields');
$_POST=array();
wp_insert_post(array('post_type'=>'cg_review','post_name'=>'smoke-published-review','post_title'=>'公开评测测试','post_status'=>'publish','post_content'=>'<p>这是用于验证发布流程的测试正文。</p>'));
wp_insert_post(array('post_type'=>'cg_review','post_name'=>'smoke-draft-review','post_title'=>'隐藏草稿测试','post_status'=>'draft','post_content'=>'草稿不应公开。'));
$home=get_page_by_path('home'); wp_update_post(array('ID'=>$home->ID,'post_content'=>'<p>自定义首页内容不会被覆盖。</p>'));cg_setup_pages(false);
cg_assert(str_contains(get_post($home->ID)->post_content,'自定义首页内容'),'existing page content preserved');
update_option('cg_brand','交易所指南');
