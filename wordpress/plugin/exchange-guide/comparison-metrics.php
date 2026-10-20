<?php
if(!defined('ABSPATH')){exit;}

// Retired platforms remain in the data model; only these four are selectable here.
function cg_comparison_platforms(){
    $all=cg_definitions();$platforms=array();
    foreach(array('binance','okx','bitget','bybit') as $slug){if(isset($all[$slug])){$platforms[$slug]=$all[$slug];}}
    return $platforms;
}
function cg_comparison_metric_labels(){return array('spot'=>'现货手续费','futures'=>'合约手续费','token'=>'平台币优惠及门槛');}
function cg_comparison_metric_source($source){
    if(!is_string($source)){return '';}
    $url=esc_url_raw(trim($source),array('https'));$parts=wp_parse_url($url);
    return is_array($parts)&&strtolower($parts['scheme']??'')==='https'&&!empty($parts['host'])&&!isset($parts['user'])&&!isset($parts['pass'])&&(!isset($parts['port'])||(int)$parts['port']===443)?$url:'';
}
function cg_comparison_metrics($id){
    $stored=get_post_meta($id,'_cg_comparison_metrics',true);
    return is_array($stored)?$stored:array();
}
function cg_comparison_metric_verified($row){
    return is_array($row)&&($row['status']??'')==='verified'
        &&is_string($row['value']??null)&&trim($row['value'])!==''
        &&cg_comparison_metric_source($row['source']??'')!==''
        &&cg_review_date_valid($row['date']??'');
}
function cg_comparison_metric_cell($exchange,$key){
    $rows=$exchange?cg_comparison_metrics($exchange->ID):array();$row=$rows[$key]??array();
    if(!cg_comparison_metric_verified($row)){
        return '<span class="cg-metric-status">'.(is_array($row)&&($row['status']??'')==='stale'?'需重新核验':'待核验').'</span>';
    }
    return '<span class="cg-metric-value">'.esc_html($row['value']).'</span><small class="cg-metric-meta">已核验 · <a href="'.esc_url(cg_comparison_metric_source($row['source'])).'" target="_blank" rel="noopener noreferrer">官方资料来源</a><br>最后核验：'.esc_html($row['date']).'</small>';
}
function cg_comparison_metrics_box($post){
    wp_nonce_field('cg_comparison_metrics_save','cg_comparison_metrics_nonce');
    echo '<input type="hidden" name="cg_comparison_metrics_present" value="1"><p>这三项对应「交易所对比」页的比较表。按实际适用档位填写，注明 Maker／Taker、现货或合约类型、折扣条件及门槛。只有选择已核验并填写内容、官方 HTTPS 来源和有效核验日期后，数值才会公开显示。其他状态显示待核验或需重新核验。</p>';
    $rows=cg_comparison_metrics($post->ID);
    foreach(cg_comparison_metric_labels() as $key=>$label){
        $row=is_array($rows[$key]??null)?$rows[$key]:array();
        $value=function($field)use($row){return is_string($row[$field]??null)?$row[$field]:'';};
        $prefix='cg_comparison_metrics['.$key.']';$id='cg-metric-'.$key;
        echo '<fieldset style="border-top:1px solid #dcdcde;margin-top:20px;padding-top:16px"><legend><strong>'.esc_html($label).'</strong></legend>';
        echo '<p><label for="'.esc_attr($id.'-value').'">比较内容与适用条件</label><textarea class="widefat" rows="3" id="'.esc_attr($id.'-value').'" name="'.esc_attr($prefix.'[value]').'">'.esc_textarea($value('value')).'</textarea></p>';
        echo '<p><label for="'.esc_attr($id.'-source').'">官方资料来源（HTTPS）</label><input class="widefat" type="url" id="'.esc_attr($id.'-source').'" name="'.esc_attr($prefix.'[source]').'" value="'.esc_attr($value('source')).'"></p>';
        echo '<p><label for="'.esc_attr($id.'-date').'">最后核验日期</label> <input type="date" max="'.esc_attr(wp_date('Y-m-d')).'" id="'.esc_attr($id.'-date').'" name="'.esc_attr($prefix.'[date]').'" value="'.esc_attr($value('date')).'"></p>';
        echo '<p><label for="'.esc_attr($id.'-status').'">核验状态</label> <select id="'.esc_attr($id.'-status').'" name="'.esc_attr($prefix.'[status]').'">';
        foreach(array('unverified'=>'未核验','verified'=>'已核验','stale'=>'需重新核验') as $status=>$text){echo '<option value="'.esc_attr($status).'" '.selected($value('status')?:'unverified',$status,false).'>'.esc_html($text).'</option>';}
        echo '</select></p></fieldset>';
    }
}
add_action('add_meta_boxes',function(){
    if(current_user_can('manage_options')){add_meta_box('cg-comparison-metrics','比较表数据（三项）','cg_comparison_metrics_box','cg_exchange','normal','high');}
});
function cg_comparison_metrics_save($id){
    if(get_post_type($id)!=='cg_exchange'||wp_is_post_revision($id)||wp_is_post_autosave($id)||!current_user_can('manage_options')||!current_user_can('edit_post',$id)){return;}
    $nonce=$_POST['cg_comparison_metrics_nonce']??null;
    if(!is_string($nonce)||!wp_verify_nonce(sanitize_text_field(wp_unslash($nonce)),'cg_comparison_metrics_save')||($_POST['cg_comparison_metrics_present']??null)!=='1'){return;}
    $raw=$_POST['cg_comparison_metrics']??null;if(!is_array($raw)){return;}
    $rows=cg_comparison_metrics($id);$incomplete=false;
    foreach(cg_comparison_metric_labels() as $key=>$label){
        if(!isset($raw[$key])||!is_array($raw[$key])){continue;}
        $input=$raw[$key];$valid=true;
        foreach(array('value','source','date','status') as $field){if(isset($input[$field])&&!is_string($input[$field])){$valid=false;break;}}
        if(!$valid){continue;}
        $date=sanitize_text_field(wp_unslash($input['date']??''));
        $row=array('value'=>sanitize_textarea_field(wp_unslash($input['value']??'')),
            'source'=>cg_comparison_metric_source(wp_unslash($input['source']??'')),
            'date'=>cg_review_date_valid($date)?$date:'',
            'status'=>in_array($input['status']??'',array('unverified','verified','stale'),true)?$input['status']:'unverified');
        if($row['status']==='verified'&&!cg_comparison_metric_verified($row)){$row['status']='unverified';$incomplete=true;}
        $rows[$key]=$row;
    }
    update_post_meta($id,'_cg_comparison_metrics',$rows);
    if($incomplete){set_transient('cg_error_'.get_current_user_id(),'比较表数据已保存，但部分项目缺少内容、官方 HTTPS 来源或有效核验日期，已保留为未核验；前台不会显示这些数值。',120);}
}
add_action('save_post','cg_comparison_metrics_save',15);
foreach(array('added_post_meta','updated_post_meta','deleted_post_meta') as $hook){
    add_action($hook,function($meta_id,$id,$key){if($key==='_cg_comparison_metrics'&&get_post_type($id)==='cg_exchange'){cg_purge_comparison_page();}},10,3);
}
function cg_comparison_data_page(){
    if(!current_user_can('manage_options')){return;}
    $slug=is_string($_GET['exchange']??null)?sanitize_key(wp_unslash($_GET['exchange'])):'';
    $definitions=cg_comparison_platforms();
    if(isset($definitions[$slug])){
        $platform=cg_exchange($slug);
        echo '<div class="wrap"><h1>'.esc_html($definitions[$slug]['name']).' · 比较表数据</h1><p><a href="'.esc_url(admin_url('admin.php?page=cg-comparison-data')).'">← 返回四家平台列表</a></p>';
        if(!$platform||!current_user_can('edit_post',$platform->ID)){echo '<p>平台资料尚未创建或无编辑权限。</p></div>';return;}
        if(($_GET['saved']??'')==='1'){echo '<div class="notice notice-success inline"><p>比较数据已保存。前台仅展示核验完整的项目。</p></div>';}
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="max-width:900px"><input type="hidden" name="action" value="cg_comparison_metrics_update"><input type="hidden" name="cg_comparison_exchange" value="'.esc_attr($slug).'">';
        cg_comparison_metrics_box($platform);submit_button('保存比较数据');echo '</form></div>';return;
    }
    echo '<div class="wrap"><h1>交易所比较表数据</h1><p>选择平台，填写现货手续费、合约手续费、平台币优惠及门槛，以及每项来源和核验日期。保存后自动同步到前台比较表。</p><table class="widefat striped"><thead><tr><th>交易所</th><th>已核验项目</th><th>填写入口</th></tr></thead><tbody>';
    foreach(cg_comparison_platforms() as $slug=>$definition){
        $platform=cg_exchange($slug);$verified=0;
        if($platform){$rows=cg_comparison_metrics($platform->ID);foreach(cg_comparison_metric_labels() as $key=>$label){if(cg_comparison_metric_verified($rows[$key]??array())){$verified++;}}}
        echo '<tr><td>'.esc_html($definition['name']).'</td><td>'.absint($verified).' / 3</td><td>';
        if($platform&&current_user_can('edit_post',$platform->ID)){echo '<a href="'.esc_url(add_query_arg('exchange',$slug,admin_url('admin.php?page=cg-comparison-data'))).'">填写／更新比较数据</a>';}
        else{echo '请先在交易所资料中创建此平台记录。';}
        echo '</td></tr>';
    }
    echo '</tbody></table><p><a href="'.esc_url(home_url('/compare/')).'" target="_blank" rel="noopener noreferrer">查看前台交易所对比</a></p></div>';
}
add_action('admin_menu',function(){add_submenu_page('edit.php?post_type=cg_exchange','比较表数据','比较表数据','manage_options','cg-comparison-data','cg_comparison_data_page');});
add_action('admin_post_cg_comparison_metrics_update',function(){
    if(!current_user_can('manage_options')){wp_die('没有权限。','',array('response'=>403));}
    check_admin_referer('cg_comparison_metrics_save','cg_comparison_metrics_nonce');
    $slug=is_string($_POST['cg_comparison_exchange']??null)?sanitize_key(wp_unslash($_POST['cg_comparison_exchange'])):'';
    $platform=isset(cg_comparison_platforms()[$slug])?cg_exchange($slug):null;
    if(!$platform||!current_user_can('edit_post',$platform->ID)){wp_die('请选择有效的平台。','',array('response'=>400));}
    if(($_POST['cg_comparison_metrics_present']??null)!=='1'||!is_array($_POST['cg_comparison_metrics']??null)){wp_die('比较表单不完整，请返回重试。','',array('response'=>400));}
    cg_comparison_metrics_save($platform->ID);
    wp_safe_redirect(add_query_arg(array('exchange'=>$slug,'saved'=>'1'),admin_url('admin.php?page=cg-comparison-data')));exit;
});

function cg_comparison_table_shortcode(){
    $definitions=cg_comparison_platforms();
    $selected=array('a'=>'binance','b'=>'okx');
    foreach($selected as $key=>$fallback){$raw=$_GET[$key]??'';$slug=is_string($raw)?sanitize_title(wp_unslash($raw)):'';if(isset($definitions[$slug])){$selected[$key]=$slug;}}
    $posts=array(cg_exchange($selected['a']),cg_exchange($selected['b']));
    ob_start(); ?>
    <p class="cg-lead">比较现货手续费、合约手续费与平台币优惠及门槛。费率取决于产品、账户档位和活动条件；每项数据附来源与最后核验日期，缺少核验的项目不展示数值。</p>
    <form class="cg-selectors" method="get" action="<?php echo esc_url(home_url('/compare/')); ?>">
    <?php foreach($selected as $key=>$slug){ ?><label><?php echo $key==='a'?'第一家交易所':'第二家交易所'; ?><select name="<?php echo esc_attr($key); ?>"><?php foreach($definitions as $candidate=>$definition){ ?><option value="<?php echo esc_attr($candidate); ?>" <?php selected($slug,$candidate); ?>><?php echo esc_html($definition['name']); ?></option><?php } ?></select></label><?php } ?>
    <button class="cg-button" type="submit">开始比较</button></form>
    <div class="cg-table-wrap cg-comparison-table" role="region" aria-label="交易所比较表" tabindex="0"><table class="cg-table"><caption><?php echo esc_html($definitions[$selected['a']]['name'].' 与 '.$definitions[$selected['b']]['name']); ?></caption><thead><tr><th scope="col">比较项目</th><th scope="col"><?php echo esc_html($definitions[$selected['a']]['name']); ?></th><th scope="col"><?php echo esc_html($definitions[$selected['b']]['name']); ?></th></tr></thead><tbody>
    <?php foreach(cg_comparison_metric_labels() as $key=>$label){ ?><tr><th scope="row"><?php echo esc_html($label); ?></th><?php foreach($posts as $platform){ ?><td><?php echo cg_comparison_metric_cell($platform,$key); ?></td><?php } ?></tr><?php } ?>
    <tr><th scope="row">进一步了解</th><?php foreach($posts as $platform){ ?><td><?php if($platform){ ?><a href="<?php echo esc_url(get_permalink($platform)); ?>">查看平台专题 →</a><?php }else{echo '资料尚未创建';} ?></td><?php } ?></tr></tbody></table></div>
    <section id="comparison-articles"><h2>比较分析</h2><?php $raw=$_GET['cg_compare_page']??1;$page=is_scalar($raw)?max(1,absint($raw)):1;echo cg_comparison_cards($page,9,true); ?></section>
    <?php return ob_get_clean();
}
