<?php
if(!defined('ABSPATH')){exit;}
// Topic desks aggregate existing content without moving records or changing URLs.
add_action('admin_menu',function(){
    foreach(array('tokenized-stocks'=>array('美股','dashicons-chart-line',26),'crypto'=>array('加密货币','dashicons-shield',27)) as $slug=>$settings){
        add_menu_page($settings[0].' · 内容管理',$settings[0],'edit_posts','cg-content-'.$slug,function()use($slug){cg_content_desk($slug);},$settings[1],$settings[2]);
    }
    add_submenu_page('cg-setup','内容分类','内容分类','manage_categories','edit-tags.php?taxonomy=cg_topic&post_type=post');
},20);
function cg_content_desk($slug){
    if(!current_user_can('edit_posts')){wp_die('没有权限。','',array('response'=>403));}
    $tree=cg_topic_tree();if(!isset($tree[$slug])){return;}
    $term=cg_find_topic($slug);$page=max(1,absint($_GET['paged']??1));
    echo '<div class="wrap"><h1>'.esc_html($tree[$slug][0]).' · 内容管理</h1><p>汇总此主题及其二级分类下的文章、指南、评测、对比和榜单。既有内容类型和地址保持不变。</p>';
    if(!$term){
        echo '<div class="notice notice-info inline"><p>此主题尚未初始化。';
        if(current_user_can('manage_options')){echo '<a href="'.esc_url(admin_url('admin.php?page=cg-setup')).'">到网站管理点击「应用已确认的导航与定位」</a>，创建内容分类。';}else{echo '请联系管理员创建内容分类。';}
        echo '</p></div></div>';return;
    }
    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('cg_topic_draft');
    echo '<input type="hidden" name="action" value="cg_topic_draft"><label for="cg-topic-choice">新文章主题 </label><select id="cg-topic-choice" name="cg_term">';
    echo '<option value="'.absint($term->term_id).'">'.esc_html($term->name).'（综合）</option>';
    foreach($tree[$slug][1] as $child_slug=>$label){$child=cg_find_topic($child_slug,$term->term_id);if($child){echo '<option value="'.absint($child->term_id).'">'.esc_html($label).'</option>';}}
    echo '</select> ';submit_button('新建主题文章','primary','submit',false);echo '</form>';
    echo '<p>';if(current_user_can('manage_categories')){echo '<a href="'.esc_url(admin_url('edit-tags.php?taxonomy=cg_topic&post_type=post')).'">管理内容分类</a> · ';}echo '也可在现有文章的「内容主题」中关联此主题。</p>';
    $args=array('post_type'=>array('post','cg_review','cg_compare','cg_ranking','cg_guide'),'post_status'=>array('publish','draft','pending','future','private'),'posts_per_page'=>20,'paged'=>$page,'tax_query'=>array(array('taxonomy'=>'cg_topic','field'=>'term_id','terms'=>$term->term_id,'include_children'=>true)));
    if(!current_user_can('edit_others_posts')){$args['author']=get_current_user_id();}
    $query=new WP_Query($args);
    echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>标题</th><th>内容类型</th><th>主题</th><th>状态</th><th>更新时间</th></tr></thead><tbody>';
    $visible=0;
    foreach($query->posts as $entry){
        if(!current_user_can('edit_post',$entry->ID)){continue;}$visible++;
        $type=get_post_type_object($entry->post_type);$status=get_post_status_object($entry->post_status);
        $terms=wp_get_object_terms($entry->ID,'cg_topic',array('fields'=>'names'));
        echo '<tr><td><strong><a href="'.esc_url(get_edit_post_link($entry->ID,'raw')).'">'.esc_html($entry->post_title?:'未命名文章').'</a></strong></td><td>'.esc_html($type->labels->singular_name).'</td><td>'.esc_html(is_wp_error($terms)?'':implode(' / ',$terms)).'</td><td>'.esc_html($status->label).'</td><td>'.esc_html(get_the_modified_date('Y-m-d',$entry)).'</td></tr>';
    }
    if(!$visible){echo '<tr><td colspan="5">还没有可编辑的主题内容。使用上方按钮新建，或为已有文章选择对应的内容主题。</td></tr>';}
    echo '</tbody></table>';
    if($query->max_num_pages>1){echo '<div class="tablenav"><div class="tablenav-pages">'.wp_kses_post(paginate_links(array('base'=>add_query_arg('paged','%#%',admin_url('admin.php?page=cg-content-'.$slug)),'format'=>'','current'=>$page,'total'=>$query->max_num_pages))).'</div></div>';}
    echo '</div>';
}
add_action('admin_post_cg_topic_draft',function(){
    if(!current_user_can('edit_posts')){wp_die('没有权限。','',array('response'=>403));}
    check_admin_referer('cg_topic_draft');$term=get_term(absint($_POST['cg_term']??0),'cg_topic');
    $valid=array();foreach(cg_topic_tree() as $slug=>$settings){$parent=cg_find_topic($slug);if($parent){$valid[]=$parent->term_id;foreach($settings[1] as $child_slug=>$label){$child=cg_find_topic($child_slug,$parent->term_id);if($child){$valid[]=$child->term_id;}}}}
    if(!$term||is_wp_error($term)||!in_array((int)$term->term_id,$valid,true)){wp_die('请选择有效的内容主题。','',array('response'=>400));}
    $taxonomy=get_taxonomy('cg_topic');if(!current_user_can($taxonomy->cap->assign_terms)){wp_die('没有主题分配权限。','',array('response'=>403));}
    require_once ABSPATH.'wp-admin/includes/post.php';$post=get_default_post_to_edit('post',true);
    $assigned=wp_set_object_terms($post->ID,array((int)$term->term_id),'cg_topic');
    if(is_wp_error($assigned)){wp_delete_post($post->ID,true);wp_die('主题分配失败，请重试。');}
    update_post_meta($post->ID,'_cg_primary_topic',(int)$term->term_id);
    wp_safe_redirect(get_edit_post_link($post->ID,'raw'));exit;
});
add_action('admin_notices',function(){
    $screen=get_current_screen();if(!$screen||$screen->id!=='edit-cg_exchange'||!current_user_can('manage_options')){return;}
    if(get_posts(array('post_type'=>'cg_exchange','post_status'=>array('publish','draft','pending','private','future'),'numberposts'=>1,'fields'=>'ids'))){return;}
    echo '<div class="notice notice-info"><p>尚未创建交易所资料。请到 <a href="'.esc_url(admin_url('admin.php?page=cg-setup')).'">网站管理</a> 点击「创建基础页面并保存名称」，再应用导航。此操作不会填入虚构的费率或优惠，也不会覆盖已有内容。</p></div>';
});
