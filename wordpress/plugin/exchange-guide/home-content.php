<?php
if(!defined('ABSPATH')){exit;}

function cg_home_page_number(){
    $raw=$_GET['cg_home_page']??1;
    return is_scalar($raw)?(filter_var($raw,FILTER_VALIDATE_INT,array('options'=>array('min_range'=>1,'max_range'=>1000000)))?:1):1;
}
function cg_home_page_url($page=1){
    $url=home_url('/');$page=max(1,absint($page));
    return $page>1?add_query_arg('cg_home_page',$page,$url):$url;
}
function cg_home_articles($page=1,$limit=8){
    return new WP_Query(array(
        'post_type'=>array('post','cg_review','cg_compare','cg_ranking','cg_guide'),
        'post_status'=>'publish','has_password'=>false,'ignore_sticky_posts'=>true,
        'posts_per_page'=>max(1,min(50,absint($limit))),'paged'=>max(1,absint($page)),
        'orderby'=>array('date'=>'DESC','ID'=>'DESC'),'suppress_filters'=>false,
        '_cg_home_listing'=>true,
    ));
}
add_filter('posts_orderby',function($orderby,$query){
    if(!$query->get('_cg_home_listing')){return $orderby;}
    global $wpdb;
    // A correlated aggregate also tolerates duplicate legacy metadata without duplicate cards.
    $priority=$wpdb->prepare("(SELECT MIN(CAST(cg_home_priority.meta_value AS UNSIGNED)) FROM {$wpdb->postmeta} AS cg_home_priority WHERE cg_home_priority.post_id = {$wpdb->posts}.ID AND cg_home_priority.meta_key = %s AND cg_home_priority.meta_value REGEXP '^[1-9][0-9]{0,3}$')",'_cg_home_order');
    return "COALESCE($priority,10000) ASC, {$wpdb->posts}.post_date DESC, {$wpdb->posts}.ID DESC";
},10,2);

// Keep article search on WordPress' normal escaped keyword matching and relevance ordering.
add_filter('request',function($vars){
    if(array_key_exists('s',$vars)&&!is_scalar($vars['s'])){$vars['s']='';}
    return $vars;
});
add_action('pre_get_posts',function($query){
    if(is_admin()||!$query->is_main_query()||!$query->is_search()){return;}
    $query->set('post_type',array('post','cg_review','cg_compare','cg_ranking','cg_guide'));
    $query->set('post_status','publish');$query->set('has_password',false);
    $query->set('posts_per_page',9);$query->set('ignore_sticky_posts',true);
    if(trim((string)$query->get('s'))===''){$query->set('post__in',array(0));}
});
add_filter('wp_robots',function($robots){if(is_search()){$robots['noindex']=true;unset($robots['index']);}return $robots;},99);
foreach(array('rank_math/frontend/robots','wpseo_robots_array') as $hook){
    add_filter($hook,function($robots){if(is_search()&&is_array($robots)){$robots['index']='noindex';}return $robots;},99);
}
foreach(array('get_canonical_url','rank_math/frontend/canonical','wpseo_canonical') as $hook){
    add_filter($hook,function($url){return is_front_page()?cg_home_page_url(cg_home_page_number()):$url;},20);
}
add_action('wp_head',function(){
    if(is_front_page()&&!is_singular()&&!cg_review_external_seo()){echo '<link rel="canonical" href="'.esc_url(cg_home_page_url(cg_home_page_number())).'">';}
},20);
add_filter('document_title_parts',function($parts){if(is_front_page()&&cg_home_page_number()>1){$parts['page']='第 '.cg_home_page_number().' 页';}return $parts;});
foreach(array('rank_math/frontend/title','wpseo_title') as $hook){
    add_filter($hook,function($title){return is_front_page()&&cg_home_page_number()>1?$title.' - 第 '.cg_home_page_number().' 页':$title;},20);
}
add_action('template_redirect',function(){
    if(!is_front_page()||cg_home_page_number()===1){return;}
    $query=cg_home_articles(cg_home_page_number());
    if(!$query->posts){global $wp_query;$wp_query->set_404();status_header(404);nocache_headers();}
});

function cg_home_order_value($id){
    $value=get_post_meta($id,'_cg_home_order',true);
    return is_scalar($value)&&preg_match('/^[1-9][0-9]{0,3}$/',(string)$value)?(string)$value:'';
}
// Validate the whole batch before modifying metadata. Never rewrite content or publication dates.
function cg_save_home_order($values,$nonce){
    if(!current_user_can('edit_others_posts')){return new WP_Error('forbidden','没有调整首页顺序的权限。');}
    if(!is_string($nonce)||!wp_verify_nonce($nonce,'cg_home_order')){return new WP_Error('nonce','验证失败，请刷新排序页面后重试。');}
    if(!is_array($values)||count($values)>200){return new WP_Error('invalid','排序表单格式不正确。');}
    $valid=array();
    foreach($values as $key=>$value){
        if(!ctype_digit((string)$key)||!is_scalar($value)){return new WP_Error('invalid','排序表单格式不正确。');}
        $id=absint($key);$post=get_post($id);
        if(!$post||!in_array($post->post_type,array('post','cg_review','cg_compare','cg_ranking','cg_guide'),true)||$post->post_status!=='publish'||$post->post_password){return new WP_Error('invalid','只可调整已公开且无密码的文章。');}
        if(!current_user_can('edit_post',$id)){return new WP_Error('forbidden','没有编辑其中一篇文章的权限。');}
        $value=trim((string)$value);
        if($value!==''&&(!ctype_digit($value)||strlen($value)>4||(int)$value<1||(int)$value>9999)){return new WP_Error('invalid','首页排序请输入 1 到 9999 的整数，留空恢复按发布时间排列。');}
        $valid[$id]=$value===''?'':(string)(int)$value;
    }
    $changed=0;
    foreach($valid as $id=>$value){
        if($value===''){if(delete_post_meta($id,'_cg_home_order')){$changed++;}}
        elseif(update_post_meta($id,'_cg_home_order',$value)){$changed++;}
    }
    return $changed;
}
add_action('admin_menu',function(){add_submenu_page(current_user_can('manage_options')?'cg-setup':'edit.php','首页文章排序','首页文章排序','edit_others_posts','cg-home-order','cg_home_order_admin');},25);
function cg_home_order_admin(){
    if(!current_user_can('edit_others_posts')){return;}
    $raw=$_GET['paged']??1;$page=is_scalar($raw)?max(1,absint($raw)):1;$query=cg_home_articles($page,20);
    echo '<div class="wrap"><h1>首页文章排序</h1><p>首页直接展示公开文章。填写 1、2、3 等数字，数字越小越靠前；留空的文章排在手动排序文章之后，按发布时间从新到旧排列。相同数字按发布时间排列。</p><p>这里只调整首页顺序，文章正文、网址、发布时间和分类不会改变。搜索结果仍按关键词相关性排列。</p>';
    if(isset($_GET['saved'])){echo '<div class="notice notice-success"><p>首页文章顺序已保存。</p></div>';}
    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('cg_home_order');
    echo '<input type="hidden" name="action" value="cg_home_order"><input type="hidden" name="cg_order_page" value="'.absint($page).'"><table class="widefat striped"><thead><tr><th scope="col">首页排序</th><th scope="col">文章</th><th scope="col">类型</th><th scope="col">发布时间</th></tr></thead><tbody>';
    foreach($query->posts as $entry){
        if(!current_user_can('edit_post',$entry->ID)){continue;}
        $type=get_post_type_object($entry->post_type);
        echo '<tr><td><label class="screen-reader-text" for="cg-home-order-'.absint($entry->ID).'">'.esc_html($entry->post_title).' 首页排序</label><input id="cg-home-order-'.absint($entry->ID).'" name="cg_home_order['.absint($entry->ID).']" type="number" min="1" max="9999" step="1" value="'.esc_attr(cg_home_order_value($entry->ID)).'" placeholder="默认" style="width:100px"></td><td><a href="'.esc_url(get_edit_post_link($entry->ID)).'">'.esc_html($entry->post_title?:'未命名文章').'</a></td><td>'.esc_html($type?$type->labels->singular_name:'文章').'</td><td>'.esc_html(get_the_date('Y-m-d',$entry)).'</td></tr>';
    }
    if(!$query->posts){echo '<tr><td colspan="4">还没有已公开且无密码的文章。请先在「文章」中发布内容。</td></tr>';}
    echo '</tbody></table>';
    if($query->posts){submit_button('保存首页顺序');}
    echo '</form>';
    if($query->max_num_pages>1){echo '<div class="tablenav"><div class="tablenav-pages">'.wp_kses_post(paginate_links(array('base'=>add_query_arg('paged','%#%',admin_url('admin.php?page=cg-home-order')),'format'=>'','current'=>$page,'total'=>$query->max_num_pages))).'</div></div>';}
    echo '</div>';
}
add_action('admin_post_cg_home_order',function(){
    $nonce=$_POST['_wpnonce']??'';$values=$_POST['cg_home_order']??array();
    $result=cg_save_home_order(is_array($values)?wp_unslash($values):$values,is_string($nonce)?wp_unslash($nonce):$nonce);
    if(is_wp_error($result)){wp_die(esc_html($result->get_error_message()),'保存失败',array('response'=>in_array($result->get_error_code(),array('forbidden','nonce'),true)?403:400));}
    $raw=$_POST['cg_order_page']??1;$page=is_scalar($raw)?max(1,absint($raw)):1;
    wp_safe_redirect(add_query_arg(array('page'=>'cg-home-order','saved'=>'1','paged'=>$page),admin_url('admin.php')));exit;
});

function cg_purge_home_articles(){do_action('litespeed_purge_tag','cg_home_articles');}
add_action('wp',function(){if(is_front_page()){do_action('litespeed_tag_add','cg_home_articles');}});
add_action('save_post',function($id,$post){if(!wp_is_post_revision($id)&&!wp_is_post_autosave($id)&&in_array($post->post_type,array('post','cg_review','cg_compare','cg_ranking','cg_guide'),true)){cg_purge_home_articles();}},55,2);
add_action('before_delete_post',function($id){if(in_array(get_post_type($id),array('post','cg_review','cg_compare','cg_ranking','cg_guide'),true)){cg_purge_home_articles();}});
foreach(array('added_post_meta','updated_post_meta','deleted_post_meta') as $hook){
    add_action($hook,function($meta_id,$id,$key){if(in_array($key,array('_cg_home_order','_thumbnail_id','_cg_sponsored'),true)){cg_purge_home_articles();}},10,3);
}
add_action('set_object_terms',function($id,$terms,$tt_ids,$taxonomy){if(in_array($taxonomy,array('cg_topic','category'),true)){cg_purge_home_articles();}},10,4);
add_action('edited_term',function($id,$tt,$taxonomy){if(in_array($taxonomy,array('cg_topic','category'),true)){cg_purge_home_articles();}},10,3);
