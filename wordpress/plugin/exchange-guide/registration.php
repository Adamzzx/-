<?php
if(!defined('ABSPATH')){exit;}
function cg_register_registration_routes(){add_rewrite_rule('^register/([a-z0-9-]+)/?$','index.php?cg_registration=$matches[1]','top');}
add_action('init','cg_register_registration_routes');
add_filter('query_vars',function($vars){$vars[]='cg_registration';return $vars;});
add_filter('pre_handle_404',function($handled,$query){
    $slug=$query->get('cg_registration');if(is_string($slug)&&isset(cg_definitions()[$slug])){$query->is_404=false;$query->is_home=false;return true;}if($slug){$query->set_404();status_header(404);return true;}return $handled;
},10,2);
add_filter('template_include',function($template){
    $slug=get_query_var('cg_registration');if(is_string($slug)&&isset(cg_definitions()[$slug])){status_header(200);$file=get_theme_file_path('registration.php');if(file_exists($file)){return $file;}}
    return $template;
});
add_filter('document_title_parts',function($parts){$slug=get_query_var('cg_registration');if(is_string($slug)&&isset(cg_definitions()[$slug])){$parts['title']=cg_definitions()[$slug]['name'].' 注册入口与官方入口';}return $parts;});
add_filter('wp_robots',function($robots){if(get_query_var('cg_registration')){$robots['noindex']=true;unset($robots['index']);}return $robots;});
function cg_registration_cards(){
    $html='<div class="cg-registration-cards">';
    foreach(array('binance','okx','bitget','bybit') as $slug){
        $post=cg_exchange($slug);$label=cg_definitions()[$slug]['name'];
        $image=$post?(int)get_post_meta($post->ID,'_cg_banner_attachment',true):0;
        $html.='<a class="cg-registration-card" href="'.esc_url(cg_go_url($slug)).'" target="_blank" rel="'.esc_attr(cg_registration_rel($slug)).'">';
        if($image && in_array(get_post_mime_type($image),array('image/jpeg','image/png','image/webp','image/avif'),true)){
            $html.=wp_get_attachment_image($image,'medium_large',false,array('alt'=>$label.' 注册入口','loading'=>'lazy','decoding'=>'async'));
        }else{
            $html.='<span class="cg-registration-label">'.esc_html($label).'</span><strong>开户注册 · '.(cg_active_offer($post)?'推荐入口':'官方入口').'</strong><span class="cg-registration-action">前往注册 ↗</span>';
        }
        $html.='</a>';
    }
    return $html.'</div>';
}
add_shortcode('cg_registration_cards','cg_registration_cards');
add_action('admin_enqueue_scripts',function($hook){
    if(in_array($hook,array('post.php','post-new.php'),true)&&get_current_screen()->post_type==='cg_exchange'&&current_user_can('manage_options')){
        wp_enqueue_media();wp_enqueue_script('cg-banner-admin',plugins_url('assets/banner-admin.js',__FILE__),array('jquery'),'1.2.0',true);
    }
});

add_filter('redirect_canonical',function($redirect){return get_query_var('cg_registration')?false:$redirect;});

function cg_registration_url($slug){
    if(!isset(cg_definitions()[$slug])){return '';}
    $rules=(array)get_option('rewrite_rules',array());
    return isset($rules['^register/([a-z0-9-]+)/?$'])?home_url('/register/'.$slug.'/'):add_query_arg('cg_registration',$slug,home_url('/'));
}

function cg_official_registration_urls(){
    return array('binance'=>'https://www.binance.com/en/register','okx'=>'https://www.okx.com/join','bitget'=>'https://www.bitget.com/register','bybit'=>'https://www.bybit.com/en/register');
}
function cg_registration_destination($slug){
    if(!is_string($slug)||!isset(cg_definitions()[$slug])){return '';}
    $post=cg_exchange($slug);
    if(cg_active_offer($post)){return get_post_meta($post->ID,'_cg_destination',true);}
    $urls=cg_official_registration_urls();$url=$urls[$slug]??'';
    return $url&&cg_safe_destination($slug,$url)?$url:'';
}
function cg_go_url($slug){
    if(!isset(cg_definitions()[$slug])){return '';}
    $rules=(array)get_option('rewrite_rules',array());
    return isset($rules['^go/([a-z0-9-]+)/?$'])?home_url('/go/'.$slug.'/'):add_query_arg('cg_go',$slug,home_url('/'));
}
function cg_registration_rel($slug){return cg_active_offer(cg_exchange($slug))?'noopener noreferrer sponsored':'noopener noreferrer';}
function cg_registration_disclosure(){
    foreach(array_keys(cg_official_registration_urls()) as $slug){if(cg_active_offer(cg_exchange($slug))){return '部分入口为推荐链接，我们可能获得佣金。未配置推荐链接的平台使用官方注册入口；费用、优惠和地区资格以平台条款为准。';}}
    return '当前使用官方注册入口，不含本站推荐码。费用、产品与地区资格以平台官方条款为准。';
}
