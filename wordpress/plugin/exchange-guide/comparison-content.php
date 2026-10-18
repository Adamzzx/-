<?php
if(!defined('ABSPATH')){exit;}

function cg_comparison_field($post){
    if($post->post_type==='cg_compare'){
        echo '<p><strong>栏目收录</strong><br>此类内容自动收录到「交易所对比」。</p>';
    }elseif($post->post_type==='post'){
        echo '<fieldset><legend><strong>栏目收录</strong></legend><input type="hidden" name="cg_comparison_present" value="1"><label><input type="checkbox" id="cg_comparison" name="cg_comparison" value="1" '.checked(get_post_meta($post->ID,'_cg_comparison',true),'1',false).'> 收录到「交易所对比」</label><p class="description">保存并公开发布后，文章会出现在顶部导航「交易所 → 交易所对比」的文章列表中。平台专题由下方的「关联交易所」控制。</p></fieldset>';
    }
}

// Called only after the existing editorial nonce, capability and autosave checks.
function cg_save_comparison_field($id){
    if(get_post_type($id)!=='post'||($_POST['cg_comparison_present']??null)!=='1'){return;}
    if(isset($_POST['cg_comparison'])&&!is_scalar($_POST['cg_comparison'])){return;}
    if(($_POST['cg_comparison']??'')==='1'){update_post_meta($id,'_cg_comparison','1');}
    else{delete_post_meta($id,'_cg_comparison');}
}

function cg_comparison_articles($page=1,$limit=9){
    return new WP_Query(array(
        'post_type'=>array('post','cg_compare'),'post_status'=>'publish','has_password'=>false,
        'posts_per_page'=>max(1,min(30,absint($limit))),'paged'=>max(1,absint($page)),
        'orderby'=>array('date'=>'DESC','ID'=>'DESC'),'ignore_sticky_posts'=>true,
        'suppress_filters'=>false,'_cg_comparison_listing'=>true,
    ));
}
add_filter('posts_where',function($where,$query){
    if(!$query->get('_cg_comparison_listing')){return $where;}
    global $wpdb;
    // Existing dedicated comparisons remain included; ordinary posts need an explicit mark.
    // EXISTS avoids duplicate rows when legacy metadata contains duplicate entries.
    return $where.$wpdb->prepare(" AND ({$wpdb->posts}.post_type = %s OR EXISTS (SELECT 1 FROM {$wpdb->postmeta} AS cg_comparison_meta WHERE cg_comparison_meta.post_id = {$wpdb->posts}.ID AND cg_comparison_meta.meta_key = %s AND cg_comparison_meta.meta_value = %s))",'cg_compare','_cg_comparison','1');
},10,2);

function cg_comparison_cards($page=1,$limit=9,$pagination=false){
    $query=cg_comparison_articles($page,$limit);$html=$query->posts?cg_article_cards_markup($query->posts):'<p class="cg-empty">内容正在准备中，核验完成后发布。</p>';
    if($pagination&&$query->max_num_pages>1){
        $base=get_permalink(get_queried_object_id());$compare=get_page_by_path('compare');
        if(!$base&&$compare){$base=get_permalink($compare);}
        foreach(array('a','b') as $key){if(isset($_GET[$key])&&is_string($_GET[$key])){$slug=sanitize_title(wp_unslash($_GET[$key]));if(isset(cg_definitions()[$slug])){$base=add_query_arg($key,$slug,$base);}}}
        $links=paginate_links(array('base'=>add_query_arg('cg_compare_page','%#%',$base).'#comparison-articles','format'=>'','current'=>max(1,absint($page)),'total'=>$query->max_num_pages,'prev_text'=>'上一页','next_text'=>'下一页'));
        $html.='<nav class="cg-pagination" aria-label="对比文章分页">'.wp_kses_post($links).'</nav>';
    }
    return $html;
}

function cg_is_comparison_article($id){return get_post_type($id)==='cg_compare'||(get_post_type($id)==='post'&&get_post_meta($id,'_cg_comparison',true)==='1');}
function cg_purge_comparison_page(){
    $page=get_page_by_path('compare');if($page){do_action('litespeed_purge_post',$page->ID);}
}
foreach(array('added_post_meta','updated_post_meta','deleted_post_meta') as $hook){
    add_action($hook,function($meta_id,$id,$key){if($key==='_cg_comparison'&&get_post_type($id)==='post'){cg_purge_comparison_page();}},10,3);
}
add_action('save_post',function($id){if(!wp_is_post_revision($id)&&!wp_is_post_autosave($id)&&cg_is_comparison_article($id)){cg_purge_comparison_page();}},35);
add_action('before_delete_post',function($id){if(cg_is_comparison_article($id)){cg_purge_comparison_page();}});
