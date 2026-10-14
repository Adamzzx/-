<?php
if(!defined('ABSPATH')){exit;}
function cg_uses_article_layout($id){return in_array(get_post_type($id),array('post','cg_review','cg_compare','cg_ranking','cg_guide'),true);}
function cg_article_exchange($id){$primary=cg_exchange(get_post_meta($id,'_cg_review_exchange',true));if($primary){return $primary;}foreach((array)get_post_meta($id,'_cg_related_exchanges',true) as $platform_id){$post=get_post(absint($platform_id));if($post&&$post->post_type==='cg_exchange'&&$post->post_status==='publish'&&isset(cg_definitions()[$post->post_name])){return $post;}}return null;}
function cg_is_review($id){return get_page_template_slug($id)==='templates/exchange-review.php';}
function cg_review_date_valid($date){return is_string($date)&&cg_expiry_timestamp($date)!==false&&$date<=wp_date('Y-m-d');}
function cg_review_rows($attrs){
    $rows=is_array($attrs['rows']??null)?$attrs['rows']:array();if(!$rows){return '<p class="cg-notice">本表尚未填写经核验的数据。</p>';}
    $html='<div class="cg-table-wrap" tabindex="0" role="region" aria-label="核验数据表"><table class="cg-table"><caption>'.esc_html($attrs['title']??'核验资料').'</caption><thead><tr><th scope="col">项目</th><th scope="col">资料与条件</th><th scope="col">核验状态</th><th scope="col">来源与核验日期</th></tr></thead><tbody>';
    foreach(array_slice($rows,0,50) as $row){
        if(!is_array($row)){continue;}foreach(array('label','value','source','sourceName','date','conditions','status') as $key){$row[$key]=is_scalar($row[$key]??null)?(string)$row[$key]:'';}$source=esc_url($row['source']??'',array('https'));$date=$row['date']??'';
        $verified=($row['status']??'')==='verified'&&$source&&cg_review_date_valid($date)&&trim($row['value']??'')!=='';
        $html.='<tr><th scope="row">'.esc_html(trim($row['label'])?:'待填写项目').'</th><td>'.($verified?esc_html($row['value']):'未核验，不展示数值或产品结论');
        if(!empty($row['conditions'])){$html.='<br><small>'.esc_html($row['conditions']).'</small>';}
        $html.='</td><td>'.($verified?'已核验':(($row['status']??'')==='stale'?'需重新核验':'未核验')).'</td><td>';
        if($source){$html.='<a href="'.$source.'" target="_blank" rel="noopener noreferrer">'.esc_html(trim($row['sourceName'])?:'资料来源').'</a>';}
        $html.='<br>'.(cg_review_date_valid($date)?esc_html($date):'有效核验日期待补充').'</td></tr>';
    }return $html.'</tbody></table></div>';
}
function cg_review_pattern(){
    $fact_labels=array('现货手续费','合约手续费','交易产品','资产储备信息','KYC','支持的地区','法币入金方式','美股代币支持情况','理财产品','安全功能');
    $content='';foreach(array('核心结论','交易所关键参数','手续费与适用条件','优点与不足','产品功能与交易体验','理财产品与收益风险','美股代币及代币化资产','实际测试与截图','风险提示','数据来源与核验记录') as $heading){
        $content.='<!-- wp:heading --><h2 class="wp-block-heading">'.$heading.'</h2><!-- /wp:heading -->';
        if(in_array($heading,array('交易所关键参数','手续费与适用条件'),true)){$labels=$heading==='交易所关键参数'?$fact_labels:array('现货 Maker','现货 Taker','合约 Maker','合约 Taker');$rows=array_map(fn($label)=>array('label'=>$label,'status'=>'unverified'),$labels);$content.='<!-- wp:cg/review-table '.wp_json_encode(array('title'=>$heading,'rows'=>$rows),JSON_UNESCAPED_UNICODE).' /-->';}
        else{$content.='<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->';}
    }return $content;
}
add_action('init',function(){
    wp_register_script('cg-review-editor',plugins_url('assets/review-editor.js',__FILE__),array('wp-blocks','wp-element','wp-components','wp-block-editor'),'1.8.0',true);
    register_block_type('cg/review-table',array('api_version'=>3,'editor_script'=>'cg-review-editor','attributes'=>array('title'=>array('type'=>'string','default'=>'核验资料'),'rows'=>array('type'=>'array','default'=>array())),'render_callback'=>'cg_review_rows'));
    register_block_pattern('cg/exchange-review',array('title'=>'交易所评测 · 空白核验结构','categories'=>array('text'),'content'=>cg_review_pattern(),'description'=>'只有章节与空表，不包含测试结论或交易所数据。'));
    foreach(array('post','cg_review') as $type){foreach(array('_cg_review_exchange','_cg_review_description','_cg_review_sources','_cg_reviewed') as $key){
        register_post_meta($type,$key,array('single'=>true,'type'=>'string','show_in_rest'=>true,'revisions_enabled'=>true,'sanitize_callback'=>'sanitize_textarea_field','auth_callback'=>function($allowed,$key,$id){return current_user_can('edit_post',$id);}));
    }}
});
add_action('add_meta_boxes',function(){foreach(array('post','cg_review') as $type){add_meta_box('cg-review-settings','文章补充设置（可选）','cg_review_settings',$type,'normal','high');}});
function cg_review_settings($post){
    wp_nonce_field('cg_review_save','cg_review_nonce');
    echo '<p>普通文章自动使用简洁排版，无需选择模板。来源和核验日期可以直接写在正文中。以下设置均为补充选项；关联交易所和文章摘要可继续使用原有方式。</p><label>主要评测平台 <select name="cg_review_exchange"><option value="">未选择</option>';
    foreach(cg_definitions() as $slug=>$definition){echo '<option value="'.esc_attr($slug).'" '.selected(get_post_meta($post->ID,'_cg_review_exchange',true),$slug,false).'>'.esc_html($definition['name']).'</option>';}
    echo '</select></label><p>选择后自动归入对应平台专题。</p>';
    foreach(array('description'=>'SEO 描述（留空使用文章摘要）','sources'=>'资料来源清单（每行一个名称和 HTTPS 链接；表格须另填逐项来源）') as $key=>$label){echo '<p><label>'.esc_html($label).'<textarea class="widefat" rows="3" name="cg_review_'.$key.'">'.esc_textarea(get_post_meta($post->ID,'_cg_review_'.$key,true)).'</textarea></label></p>';}
}
add_action('save_post',function($id){
    if(wp_is_post_revision($id)||wp_is_post_autosave($id)||!current_user_can('edit_post',$id)||empty($_POST['cg_review_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cg_review_nonce'])),'cg_review_save')){return;}
    foreach(array('description','sources') as $key){if(isset($_POST['cg_review_'.$key])){update_post_meta($id,'_cg_review_'.$key,sanitize_textarea_field(wp_unslash($_POST['cg_review_'.$key])));}}
    if(isset($_POST['cg_review_exchange'])){update_post_meta($id,'_cg_review_exchange',sanitize_key(wp_unslash($_POST['cg_review_exchange'])));}
},20);
function cg_review_sync($id){
    if(!cg_uses_article_layout($id)){return;}$slug=get_post_meta($id,'_cg_review_exchange',true);$platform=cg_exchange($slug);if(!$platform){$previous=absint(get_post_meta($id,'_cg_review_auto_exchange',true));if($previous){$old=(array)get_post_meta($id,'_cg_related_exchanges',true);update_post_meta($id,'_cg_related_exchanges',array_values(array_filter($old,fn($item)=>absint($item)!==$previous)));delete_post_meta($id,'_cg_review_auto_exchange');cg_purge_exchange_hubs($old);}return;}
    $ids=(array)get_post_meta($id,'_cg_related_exchanges',true);$old=$ids;$previous=absint(get_post_meta($id,'_cg_review_auto_exchange',true));
    if($previous&&$previous!==$platform->ID){$ids=array_values(array_filter($ids,fn($item)=>absint($item)!==$previous));}
    $ids[]= $platform->ID;update_post_meta($id,'_cg_review_auto_exchange',$platform->ID);
    update_post_meta($id,'_cg_related_exchanges',array_values(array_unique(array_map('absint',$ids))));cg_purge_exchange_hubs(array_merge($old,$ids));
}
add_action('save_post',function($id){if(!wp_is_post_revision($id)){cg_review_sync($id);}},40);
add_action('rest_after_insert_post',function($post){cg_review_sync($post->ID);},20);
add_action('updated_post_meta',function($meta_id,$id,$key){if(in_array($key,array('_cg_review_exchange','_wp_page_template'),true)){cg_review_sync($id);}},20,3);
add_action('added_post_meta',function($meta_id,$id,$key){if(in_array($key,array('_cg_review_exchange','_wp_page_template'),true)){cg_review_sync($id);}},20,3);
add_action('admin_menu',function(){add_submenu_page('edit.php?post_type=cg_exchange','评测发布模板','评测发布模板','edit_posts','cg-review-start',function(){echo '<div class="wrap"><h1>交易所评测发布模板</h1><p>创建原生文章草稿，预设标题、Binance 关联和普通区块章节，无需选择专用模板。不会填写数据或自动发布。</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('cg_review_create');echo '<input type="hidden" name="action" value="cg_review_create">';submit_button('创建或继续编辑币安评测草稿');echo '</form></div>';});});
add_action('admin_post_cg_review_create',function(){
    if(!current_user_can('edit_posts')){wp_die('没有权限。','',array('response'=>403));}check_admin_referer('cg_review_create');
    $existing=absint(get_user_meta(get_current_user_id(),'_cg_first_binance_review',true));if($existing&&get_post($existing)&&get_post_status($existing)!=='trash'&&current_user_can('edit_post',$existing)){wp_safe_redirect(get_edit_post_link($existing,'raw'));exit;}
    $platform=cg_exchange('binance');if(!$platform){wp_die('请先在网站管理创建 Binance 平台资料。');}
    $id=wp_insert_post(array('post_type'=>'post','post_status'=>'draft','post_author'=>get_current_user_id(),'post_title'=>'币安 Binance 评测 2026：手续费、安全性、交易体验、理财与美股代币全面分析','post_name'=>'binance-review','post_content'=>cg_simple_article_pattern()),true);
    if(is_wp_error($id)){wp_die(esc_html($id->get_error_message()));}
    update_user_meta(get_current_user_id(),'_cg_first_binance_review',$id);
    update_post_meta($id,'_cg_review_exchange','binance');cg_review_sync($id);
    $category=term_exists('交易所评测','category');if(!$category&&current_user_can('manage_categories')){$category=wp_insert_term('交易所评测','category',array('slug'=>'exchange-reviews'));}if($category&&!is_wp_error($category)){wp_set_post_categories($id,array((int)(is_array($category)?$category['term_id']:$category)));}
    wp_safe_redirect(get_edit_post_link($id,'raw'));exit;
});

function cg_simple_article_pattern(){
    $content='';foreach(array('核心结论','平台概况','交易体验与产品功能','手续费与适用条件','账户安全与出入金','理财与代币化资产','使用截图','风险提示与资料来源') as $heading){$content.='<!-- wp:heading --><h2 class="wp-block-heading">'.$heading.'</h2><!-- /wp:heading --><!-- wp:paragraph --><p></p><!-- /wp:paragraph -->';}return $content;
}
add_action('init',function(){register_block_pattern('cg/simple-finance-article',array('title'=>'金融文章 · 简洁结构（普通区块）','categories'=>array('text'),'content'=>cg_simple_article_pattern(),'description'=>'可选章节，不含参数表单、交易所数据或实测结论。'));});
