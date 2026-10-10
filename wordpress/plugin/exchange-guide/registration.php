<?php
if(!defined('ABSPATH')){exit;}
add_action('init',function(){add_rewrite_rule('^register/([a-z0-9-]+)/?$','index.php?cg_registration=$matches[1]','top');});
add_filter('query_vars',function($vars){$vars[]='cg_registration';return $vars;});
add_filter('pre_handle_404',function($handled,$query){
    $slug=$query->get('cg_registration');if($slug&&cg_exchange($slug)&&isset(cg_definitions()[$slug])){$query->is_404=false;$query->is_home=false;return true;}if($slug){$query->set_404();status_header(404);return true;}return $handled;
},10,2);
add_filter('template_include',function($template){
    $slug=get_query_var('cg_registration');if($slug&&cg_exchange($slug)&&isset(cg_definitions()[$slug])){status_header(200);$file=get_theme_file_path('registration.php');if(file_exists($file)){return $file;}}
    return $template;
});
add_filter('document_title_parts',function($parts){$slug=get_query_var('cg_registration');if($slug&&cg_exchange($slug)){$parts['title']=cg_exchange($slug)->post_title.' 注册说明与官方入口';}return $parts;});
add_filter('wp_robots',function($robots){if(get_query_var('cg_registration')){$robots['noindex']=true;unset($robots['index']);}return $robots;});
function cg_registration_cards(){
    $html='<div class="cg-registration-cards">';
    foreach(array('binance','okx','bitget','bybit') as $slug){
        $post=cg_exchange($slug);if(!$post){continue;}
        $image=(int)get_post_meta($post->ID,'_cg_banner_attachment',true);
        $html.='<a class="cg-registration-card" href="'.esc_url(home_url('/register/'.$slug.'/')).'">';
        if($image && in_array(get_post_mime_type($image),array('image/jpeg','image/png','image/webp','image/avif'),true)){
            $html.=wp_get_attachment_image($image,'medium_large',false,array('alt'=>$post->post_title.' 注册说明','loading'=>'lazy','decoding'=>'async'));
        }else{
            $html.='<span class="cg-registration-label">'.esc_html($post->post_title).'</span><strong>了解注册条件与入口</strong><span class="cg-registration-action">查看注册说明 ↗</span>';
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
