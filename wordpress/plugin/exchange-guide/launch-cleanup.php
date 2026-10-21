<?php
if (!defined('ABSPATH')) { exit; }

/** Controlled, repeatable cleanup. Loading this file never deletes or trashes data. */
function cg_launch_cleanup_specs() {
    return array(
        array('type'=>'cg_exchange','slug'=>'mexc','titles'=>array('MEXC'),'placeholder'=>''),
        array('type'=>'cg_exchange','slug'=>'gate','titles'=>array('Gate'),'placeholder'=>''),
        array('type'=>'page','slug'=>'best','titles'=>array('推荐榜单'),'placeholder'=>'[cg_content type="rankings"]'),
        array('type'=>'page','slug'=>'guides','titles'=>array('实用指南'),'placeholder'=>'[cg_content type="guides"]'),
        array('type'=>'page','slug'=>'home','titles'=>array('首页'),'placeholder'=>''),
    );
}
function cg_launch_cleanup_empty($value) {
    if (is_array($value)) { foreach ($value as $item) { if (!cg_launch_cleanup_empty($item)) { return false; } } return true; }
    return $value === '' || $value === null || $value === false || $value === 0 || $value === '0';
}
function cg_launch_cleanup_body($content) {
    // Empty Gutenberg paragraphs are equivalent to the original empty seed. Other
    // blocks, images, comments and shortcodes must match the known seed exactly.
    $content = trim((string)$content);
    $content = preg_replace('/<!--\s*\/?wp:paragraph\s*-->/', '', $content);
    $content = preg_replace('/<p>\s*(?:<br\s*\/?\s*>)?\s*<\/p>/i', '', $content);
    return trim($content);
}
function cg_launch_cleanup_path($url, $ignore_suffix=false) {
    $parts = wp_parse_url(html_entity_decode((string)$url, ENT_QUOTES, 'UTF-8'));
    if (!is_array($parts) || (!$ignore_suffix && (!empty($parts['query']) || !empty($parts['fragment'])))) { return ''; }
    $site = wp_parse_url(home_url('/'));
    if (!empty($parts['host']) && strtolower($parts['host']) !== strtolower($site['host'] ?? '')) { return ''; }
    $path=trim(rawurldecode($parts['path'] ?? ''), '/');return $path===''?'/':'/'.$path.'/';
}
function cg_launch_cleanup_reference_matches($value, $id, $slug) {
    if (is_array($value)) { foreach ($value as $item) { if (cg_launch_cleanup_reference_matches($item,$id,$slug)) { return true; } } return false; }
    return is_scalar($value) && ((string)$value === (string)$id || (string)$value === $slug);
}
function cg_launch_cleanup_inventory() {
    $types = array_values(array_diff(get_post_types(array(), 'names'), array('revision','attachment','nav_menu_item')));
    $statuses = array_values(array_diff(get_post_stati(array(), 'names'), array('trash','auto-draft','inherit')));
    return get_posts(array('post_type'=>$types,'post_status'=>$statuses,'posts_per_page'=>-1,'orderby'=>'ID','order'=>'ASC','suppress_filters'=>true));
}
function cg_launch_cleanup_menus($target, $object_id=0, $taxonomy='') {
    $result = array('owned'=>array(),'dependencies'=>array());
    $active = array_map('intval', (array)get_theme_mod('nav_menu_locations', array()));
    foreach ((array)wp_get_nav_menus(array('hide_empty'=>false)) as $menu) {
        $items = (array)wp_get_nav_menu_items($menu->term_id,array('post_status'=>'any'));
        foreach ($items as $item) {
            $match = cg_launch_cleanup_path($item->url,true) === cg_launch_cleanup_path($target,true);
            if ($object_id && (int)$item->object_id === (int)$object_id) {
                $match = $match || ($taxonomy ? ($item->type==='taxonomy' && $item->object===$taxonomy) : $item->type==='post_type');
            }
            if (!$match) { continue; }
            $owned = (int)get_post_meta($item->ID,'_cg_owned_navigation',true)===(int)$menu->term_id && get_post_meta($item->ID,'_cg_nav_key',true)!=='';
            $children = array_filter($items, function($child)use($item){return (int)$child->menu_item_parent===(int)$item->ID;});
            if ($owned && !$children && trim((string)$item->description)==='') {
                $result['owned'][] = (int)$item->ID;
            } else {
                $result['dependencies'][] = array('kind'=>'menu','id'=>(int)$item->ID,'label'=>sprintf('%s菜单「%s」→「%s」 #%d%s',in_array((int)$menu->term_id,$active,true)?'已启用':'已保存',$menu->name,$item->title,$item->ID,$owned?'（有子项或人工说明，保留）':'（非受管链接，保留）'));
            }
        }
    }
    return $result;
}
function cg_launch_cleanup_post_dependencies($post, $inventory) {
    $dependencies = array();
    foreach(get_posts(array('post_type'=>'attachment','post_parent'=>$post->ID,'post_status'=>array('inherit','private','publish'),'posts_per_page'=>-1)) as $attachment){$dependencies[]=array('kind'=>'attachment','id'=>(int)$attachment->ID,'label'=>'已上传附件 #'.$attachment->ID.'「'.$attachment->post_title.'」仍绑定此对象');}
    foreach (array('page_on_front'=>'根首页','page_for_posts'=>'文章列表页','wp_page_for_privacy_policy'=>'隐私政策页') as $option=>$label) {
        if ((int)get_option($option)===(int)$post->ID && ($option!=='page_on_front' || get_option('show_on_front')==='page')) { $dependencies[] = array('kind'=>$option,'id'=>$post->ID,'label'=>$label.'正在使用此对象'); }
    }
    $target = cg_launch_cleanup_path(home_url(($post->post_type==='cg_exchange'?'/exchanges/':'/').$post->post_name.'/'));
    foreach ($inventory as $entry) {
        if ((int)$entry->ID===(int)$post->ID) { continue; }
        $reasons = array();
        foreach (array('_cg_related_exchanges','_cg_review_exchange','_cg_review_auto_exchange') as $key) {
            if (cg_launch_cleanup_reference_matches(get_post_meta($entry->ID,$key,true),$post->ID,$post->post_name)) { $reasons[]=$key; }
        }
        if ((int)$entry->post_parent===(int)$post->ID) { $reasons[]='父级依赖'; }
        if (preg_match_all('/(?:href|src)\s*=\s*["\']([^"\']+)["\']/i',(string)$entry->post_content,$links)) {
            foreach ($links[1] as $url) { if (cg_launch_cleanup_path($url,true)===$target) { $reasons[]='正文链接'; break; } }
        }
        // wp:page-list/query/template and native navigation references are data too.
        if (preg_match('/"(?:id|ref|pageId|postId)"\s*:\s*'.preg_quote((string)$post->ID,'/').'(?:\s*[,}\]])/',(string)$entry->post_content)) { $reasons[]='区块对象引用'; }
        if ($reasons) { $dependencies[]=array('kind'=>'content','id'=>(int)$entry->ID,'label'=>sprintf('%s #%d「%s」（%s）：%s',$entry->post_type,$entry->ID,$entry->post_title,$entry->post_status,implode('、',array_unique($reasons)))); }
    }
    return $dependencies;
}
function cg_launch_cleanup_post_row($spec, $inventory) {
    $path = ($spec['type']==='cg_exchange'?'/exchanges/':'/').$spec['slug'].'/';
    $row = array('kind'=>'post','type'=>$spec['type'],'slug'=>$spec['slug'],'path'=>$path,'id'=>0,'title'=>'','status'=>'','action'=>'missing','reasons'=>array('未发现该路径的对象，无需处理'),'dependencies'=>array(),'owned_menu_ids'=>array());
    $post = get_page_by_path($spec['slug'], OBJECT, $spec['type']);
    if (!$post) { return $row; }
    $row = array_merge($row,array('id'=>(int)$post->ID,'title'=>$post->post_title,'status'=>$post->post_status,'action'=>'keep','reasons'=>array(),'generated'=>(string)get_post_meta($post->ID,'_cg_created',true),'body_bytes'=>strlen((string)$post->post_content)));
    if ($post->post_status==='trash') { $row['reasons'][]='已在回收站'; return $row; }
    if ($post->post_type!==$spec['type'] || $post->post_name!==$spec['slug'] || !in_array($post->post_title,$spec['titles'],true) || (int)$post->post_parent!==0) { $row['reasons'][]='类型、标题、根级路径与已知演示对象不完全一致'; }
    if ($row['generated']!=='1') { $row['reasons'][]='缺少 _cg_created=1 生成标记'; }
    $body = cg_launch_cleanup_body($post->post_content);
    if ($body!=='' && $body!==$spec['placeholder']) { $row['reasons'][]='存在真实正文或未知占位内容'; }
    if (trim((string)$post->post_excerpt)!=='') { $row['reasons'][]='存在人工摘要'; }
    // WordPress date history and Rank Math's processing marker are technical data,
    // not owner-written platform information. Preserve them in trash/backups.
    $ignored = array('_cg_created','_edit_lock','_edit_last','_wp_old_slug','_wp_old_date','_wp_trash_meta_status','_wp_trash_meta_time','_cg_clicks','rank_math_internal_links_processed');
    foreach ((array)get_post_meta($post->ID) as $key=>$values) {
        if (in_array($key,$ignored,true)) { continue; }
        $value = get_post_meta($post->ID,$key,true);
        if ($key==='_wp_page_template' && ($value==='' || $value==='default')) { continue; }
        if (!cg_launch_cleanup_empty($value)) { $row['reasons'][]='保留已填写字段：'.$key; }
    }
    if ((int)get_post_meta($post->ID,'_cg_clicks',true)>0) { $row['reasons'][]='已有真实入口点击记录'; }
    $row['dependencies'] = cg_launch_cleanup_post_dependencies($post,$inventory);
    $menu = cg_launch_cleanup_menus(home_url($path),$post->ID);
    $row['dependencies'] = array_merge($row['dependencies'],$menu['dependencies']);
    $row['owned_menu_ids'] = $menu['owned'];
    if (!$row['reasons'] && !$row['dependencies']) { $row['action']='trash'; $row['reasons'][]='生成标记、类型、路径、标题及空/已知占位正文一致，未发现有效依赖'; }
    elseif ($spec['slug']==='home' && !$row['reasons']) {
        $kinds = array_column($row['dependencies'],'kind');
        if (!in_array('page_for_posts',$kinds,true) && !in_array('wp_page_for_privacy_policy',$kinds,true)) {
            $row['action']='internal'; $row['reasons'][]='首页/内部引用仍依赖对象；保留数据，仅将额外 /home/ 规范跳转至根首页';
        }
    }
    if ($row['action']==='keep' && $row['dependencies']) { $row['reasons'][]='存在有效依赖，跳过自动清理；先人工解除依赖后重新核查'; }
    return $row;
}
function cg_launch_cleanup_term_row($parent_slug, $slug, $name, $inventory=null) {
    $row = array('kind'=>'term','type'=>'cg_topic','slug'=>$slug,'path'=>'/topics/'.$parent_slug.'/'.$slug.'/','id'=>0,'title'=>$name,'action'=>'missing','reasons'=>array('未发现该父主题下的废弃分类'),'dependencies'=>array(),'owned_menu_ids'=>array());
    $parent = cg_find_topic($parent_slug); $term = $parent?cg_find_topic($slug,$parent->term_id):null;
    if (!$term) { return $row; }
    $row = array_merge($row,array('id'=>(int)$term->term_id,'title'=>$term->name,'action'=>'keep','reasons'=>array(),'parent'=>(int)$parent->term_id));
    if ($term->taxonomy!=='cg_topic' || $term->name!==$name || trim((string)$term->description)!=='') { $row['reasons'][]='分类名称、类型或描述不符合已知废弃空分类'; }
    $objects = get_objects_in_term($term->term_id,'cg_topic');
    if (is_wp_error($objects)) { $row['reasons'][]='关联对象查询失败，不执行删除'; }
    elseif ($objects) { foreach ($objects as $id) { $p=get_post($id);$row['dependencies'][]=array('kind'=>'term_object','id'=>(int)$id,'label'=>'关联对象 #'.(int)$id.($p?'「'.$p->post_title.'」（'.$p->post_status.'）':'')); } }
    if($inventory===null){$inventory=cg_launch_cleanup_inventory();}
    $target=cg_launch_cleanup_path(home_url($row['path']));
    foreach($inventory as $entry){
        $linked=false;
        if(preg_match_all('/(?:href|src)\s*=\s*["\']([^"\']+)["\']/i',(string)$entry->post_content,$links)){foreach($links[1] as $url){if(cg_launch_cleanup_path($url,true)===$target){$linked=true;break;}}}
        $primary=(int)get_post_meta($entry->ID,'_cg_primary_topic',true)===(int)$term->term_id;
        $block=preg_match('/"(?:termId|term_id|id)"\s*:\s*'.preg_quote((string)$term->term_id,'/').'(?:\s*[,}\]])/',(string)$entry->post_content)&&str_contains((string)$entry->post_content,'cg_topic');
        if($linked||$primary||$block){$row['dependencies'][]=array('kind'=>'term_reference','id'=>(int)$entry->ID,'label'=>sprintf('%s #%d「%s」（%s）仍通过正文、主要主题或区块引用该分类',$entry->post_type,$entry->ID,$entry->post_title,$entry->post_status));}
    }
    $children = get_terms(array('taxonomy'=>'cg_topic','hide_empty'=>false,'parent'=>$term->term_id));
    if (is_wp_error($children) || $children) { $row['reasons'][]='存在子分类或子分类查询失败'; }
    foreach ((array)get_term_meta($term->term_id) as $key=>$values) { if ($key!=='_cg_created' && !cg_launch_cleanup_empty(get_term_meta($term->term_id,$key,true))) { $row['reasons'][]='保留已编辑分类字段：'.$key; } }
    $menu = cg_launch_cleanup_menus(home_url($row['path']),$term->term_id,'cg_topic');
    $row['dependencies'] = array_merge($row['dependencies'],$menu['dependencies']); $row['owned_menu_ids']=$menu['owned'];
    if (!$row['reasons'] && !$row['dependencies']) { $row['action']='delete_term';$row['reasons'][]='父主题/slug/名称准确匹配，无任何状态对象关联、子分类、人工说明或 owner 菜单依赖'; }
    else { $row['reasons'][]='保留已有数据或有效依赖，跳过自动删除'; }
    return $row;
}
function cg_launch_cleanup_plan() {
    $inventory = cg_launch_cleanup_inventory();$rows=array();
    foreach (cg_launch_cleanup_specs() as $spec) { $rows[]=cg_launch_cleanup_post_row($spec,$inventory); }
    foreach (array(array('tokenized-stocks','trading','交易指南'),array('tokenized-stocks','funding','出入金指南'),array('crypto','deposits-withdrawals','出入金')) as $spec) { $rows[]=cg_launch_cleanup_term_row($spec[0],$spec[1],$spec[2],$inventory); }
    $uncategorized = get_term_by('slug','uncategorized','category');
    $row=array('kind'=>'category','type'=>'category','slug'=>'uncategorized','path'=>'/category/uncategorized/','id'=>$uncategorized?(int)$uncategorized->term_id:0,'title'=>$uncategorized?$uncategorized->name:'Uncategorized','action'=>$uncategorized?'archive_404':'missing','reasons'=>array('保留 WordPress 默认分类数据及所有文章归属；仅移除多余公开归档入口'),'dependencies'=>array(),'owned_menu_ids'=>array());
    if ($uncategorized) {
        $url=get_term_link($uncategorized);if(!is_wp_error($url)){$row['path']=wp_parse_url($url,PHP_URL_PATH);}
        $menu=cg_launch_cleanup_menus(is_wp_error($url)?home_url($row['path']):$url,$uncategorized->term_id,'category');$row['dependencies']=$menu['dependencies'];$row['owned_menu_ids']=$menu['owned'];
        if ($row['dependencies']) { $row['action']='keep';$row['reasons'][]='非受管菜单仍引用此归档，请先人工移除入口后再应用404'; }
    }
    $rows[]=$row;return $rows;
}
function cg_launch_cleanup_menu_snapshot($id) {
    $post=get_post($id);return $post?array('ID'=>(int)$id,'title'=>$post->post_title,'meta'=>get_post_meta($id),'menu_ids'=>wp_get_object_terms($id,'nav_menu',array('fields'=>'ids'))):array();
}
function cg_launch_cleanup_remove_owned_menus($row, &$result) {
    foreach ($row['owned_menu_ids'] as $id) {
        $result['menus'][]=cg_launch_cleanup_menu_snapshot($id);
        if (!wp_delete_post($id,true)) { $result['errors'][]='受管菜单 #'.$id.' 未能移除';return false; }
        $ids=array_values(array_diff(array_map('absint',(array)get_option('cg_navigation_item_ids',array())),array((int)$id)));
        update_option('cg_navigation_item_ids',$ids,false);
    }
    return true;
}
function cg_launch_apply_cleanup($backup_confirmed=false) {
    if (!current_user_can('manage_options')) { return new WP_Error('cg_forbidden','没有上线整改权限。'); }
    if ($backup_confirmed!==true) { return new WP_Error('cg_backup_required','请先保存 Hostinger 完整文件与数据库备份并确认恢复方法；文章 XML 不代替完整备份。'); }
    // Do not trust IDs or plans supplied by the browser. Re-evaluate current data.
    $result=array('time'=>gmdate('c'),'user_id'=>get_current_user_id(),'backup_confirmed'=>true,'rows'=>array(),'menus'=>array(),'helpers'=>array(),'errors'=>array());
    foreach (cg_launch_cleanup_plan() as $row) {
        if (in_array($row['action'],array('trash','delete_term','internal','archive_404'),true)) {
            if($row['action']==='trash'&&defined('EMPTY_TRASH_DAYS')&&(int)EMPTY_TRASH_DAYS===0){$row['result']='skipped';$row['reasons'][]='当前禁用回收站，拒绝永久删除及其受管菜单清理';$result['rows'][]=$row;continue;}
            if (!cg_launch_cleanup_remove_owned_menus($row,$result)) { $row['result']='skipped';$result['rows'][]=$row;continue; }
            if ($row['action']==='trash') {
                // EMPTY_TRASH_DAYS=0 causes wp_trash_post() to hard-delete. Refuse it.
                if (defined('EMPTY_TRASH_DAYS') && (int)EMPTY_TRASH_DAYS===0) { $row['result']='skipped';$row['reasons'][]='当前禁用回收站，拒绝永久删除'; }
                else { $row['result']=wp_trash_post($row['id'])?'trashed':'failed'; }
                if ($row['result']==='trashed' && $row['slug']==='home' && (int)get_option('cg_home_id')===$row['id']) { delete_option('cg_home_id'); }
            } elseif ($row['action']==='delete_term') {
                $deleted=wp_delete_term($row['id'],'cg_topic');$row['result']=!is_wp_error($deleted)&&$deleted?'deleted_empty_term':'failed';
            } elseif ($row['action']==='internal') {
                update_option('cg_launch_home_alias_id',$row['id'],false);$row['result']='kept_internal_alias_to_root';
            } else {
                update_option('cg_launch_uncategorized_archive_closed',true,false);$row['result']='kept_category_data_archive_404';
            }
        } else { $row['result']=$row['action']==='missing'?'absent':'kept'; }
        $result['rows'][]=$row;
    }
    foreach (array('cg_launch_apply_identity','cg_launch_prepare_page_drafts','cg_launch_repair_editorial','cg_launch_invalidate_sitemaps') as $helper) {
        if (!function_exists($helper)) { $result['helpers'][$helper]=array('status'=>'unavailable');continue; }
        try { $value=$helper();$result['helpers'][$helper]=is_wp_error($value)?array('status'=>'error','message'=>$value->get_error_message()):array('status'=>'completed','result'=>$value); }
        catch (Throwable $error) { $result['helpers'][$helper]=array('status'=>'error','message'=>'此步骤未完成，请检查服务器错误日志。'); }
    }
    do_action('litespeed_purge_all');
    $logs=(array)get_option('cg_launch_cleanup_log',array());$logs[]=$result;$logs=array_slice($logs,-10);
    update_option('cg_launch_cleanup_log',$logs,false);return $result;
}
function cg_launch_cleanup_action_label($action) {
    $labels=array('trash'=>'清理：移入回收站','delete_term'=>'清理：删除已核实空分类','internal'=>'内部必要对象：保留并规范额外入口','archive_404'=>'保留分类数据，关闭多余归档','keep'=>'保留／需人工复核','missing'=>'未发现／无需处理');return $labels[$action]??$action;
}
function cg_launch_cleanup_render_rows($rows, $executed=false) {
    echo '<table class="widefat striped"><thead><tr><th>真实对象</th><th>处理</th><th>核查与依赖</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr><td><strong>'.esc_html($row['title']).'</strong><br><code>'.esc_html($row['type'].' #'.$row['id']).'</code><br>'.esc_html($row['path']);
        if ($row['kind']==='post' && $row['id']) { echo '<br>'.esc_html('状态：'.$row['status'].'；生成标记：'.($row['generated']??'').'；正文 '.($row['body_bytes']??0).' bytes'); }
        echo '</td><td>'.esc_html(cg_launch_cleanup_action_label($row['action']));if($executed){echo '<br><strong>'.esc_html($row['result']??'').'</strong>';}echo '</td><td>';
        foreach ($row['reasons'] as $reason) { echo '<p>'.esc_html($reason).'</p>'; }
        foreach ($row['dependencies'] as $dependency) { echo '<p>'.esc_html($dependency['label']).'</p>'; }
        if ($row['owned_menu_ids']) { echo '<p>'.esc_html('同时移除已确认受管菜单项：'.implode(', ',$row['owned_menu_ids'])).'</p>'; }
        echo '</td></tr>';
    }
    echo '</tbody></table>';
}
function cg_launch_cleanup_render_drafts($helpers) {
    $drafts=$helpers['cg_launch_prepare_page_drafts']['result']??array();
    if(!is_array($drafts)||!$drafts){return;}
    echo '<h3>说明页草稿与编辑入口</h3><ul>';
    foreach($drafts as $slug=>$draft){
        $id=absint($draft['id']??0);$post=$id?get_post($id):null;$url=$id?get_edit_post_link($id,'raw'):'';
        echo '<li>';
        if($post&&$url&&current_user_can('edit_post',$id)){echo '<a href="'.esc_url($url).'">'.esc_html($post->post_title).'：打开编辑</a>（'.esc_html($draft['status']??'').'，'.esc_html(($draft['result']??'')==='created'?'本次创建草稿':'保留既有页面').'）';}
        else{echo esc_html($slug.'：'.($draft['message']??'未准备成功'));}
        if(!empty($draft['proposed_content'])){echo '<details><summary>查看建议文案（既有正文未被覆盖）</summary><textarea class="widefat" rows="10" readonly>'.esc_textarea($draft['proposed_content']).'</textarea></details>';}
        echo '</li>';
    }
    echo '</ul>';
}
function cg_launch_cleanup_admin() {
    if (!current_user_can('manage_options')) { return; }
    if (function_exists('cg_cleanup_admin')) { cg_cleanup_admin(); return; }
    echo '<div class="wrap"><h1>上线整改</h1><p>下表为当前真实数据的只读核查。打开此页、启用插件或刷新页面均不会删除内容。只有明确提交后才执行可安全匹配的清理项；草稿、真实正文、人工字段和非受管菜单依赖会保留并说明理由。</p>';
    cg_launch_cleanup_render_rows(cg_launch_cleanup_plan());
    if (function_exists('cg_launch_editorial_plan')) { echo '<h2>文章链接与图片核查（只读）</h2><pre style="white-space:pre-wrap;max-height:320px;overflow:auto">'.esc_html(wp_json_encode(cg_launch_editorial_plan(),JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)).'</pre>'; }
    echo '<h2>本次同时处理</h2><p>统一已确认品牌与个人站发布者信息；仅准备缺失的关于、联系与隐私政策草稿；修复已准确匹配的旧文章链接及已查看图片的 alt；刷新 sitemap 缓存。不会自动发布说明页或安装统计代码。</p>';
    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('cg_launch_cleanup','cg_launch_cleanup_nonce');
    echo '<input type="hidden" name="action" value="cg_launch_cleanup"><p><label><input type="checkbox" name="cg_launch_backup_confirmed" value="1" required> 我已在 Hostinger 保存当前网站的完整文件及数据库备份，并已确认恢复方法（不是文章 XML 导出）。</label></p>';
    submit_button('应用已核查的上线整改');echo '</form><h2>恢复方法</h2><p>文章和页面从对应回收站恢复。已删除的空分类可按日志中的父主题、名称与 slug 重建；受管菜单项可从完整备份恢复，或在「外观 → 菜单」按日志重新添加。若需恢复相同 ID、菜单关系及全部设置，使用操作前 Hostinger 完整文件与数据库备份。内部 /home/ 规范跳转和默认分类归档关闭可使用下方受保护按钮停用，停用不会删除或重建内容。完整备份、数据库和口令不由此页面公开。</p>';
    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('cg_launch_cleanup_restore_routes','cg_launch_cleanup_restore_nonce');echo '<input type="hidden" name="action" value="cg_launch_cleanup_restore_routes">';submit_button('仅停用本次 /home/ 与默认分类归档规则','secondary');echo '</form>';
    $logs=(array)get_option('cg_launch_cleanup_log',array());$last=end($logs);
    if (is_array($last) && !empty($last['rows'])) { echo '<h2>最近一次执行记录</h2><p>'.esc_html($last['time']).'</p>';cg_launch_cleanup_render_rows($last['rows'],true);cg_launch_cleanup_render_drafts($last['helpers']);echo '<h3>辅助步骤结果</h3><pre style="white-space:pre-wrap;max-height:320px;overflow:auto">'.esc_html(wp_json_encode($last['helpers'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)).'</pre>';foreach($last['errors'] as $error){echo '<p>'.esc_html($error).'</p>';} }
    echo '</div>';
}
add_action('admin_menu',function(){add_submenu_page('cg-setup','上线整改','上线整改','manage_options','cg-launch-cleanup','cg_launch_cleanup_admin');},30);
add_action('admin_post_cg_launch_cleanup',function(){
    if (!current_user_can('manage_options')) { wp_die('没有权限。','',array('response'=>403)); }
    check_admin_referer('cg_launch_cleanup','cg_launch_cleanup_nonce');
    $result=function_exists('cg_cleanup_apply') ? cg_cleanup_apply(($_POST['cg_launch_backup_confirmed']??'')==='1') : cg_launch_apply_cleanup(($_POST['cg_launch_backup_confirmed']??'')==='1');
    if (is_wp_error($result)) { wp_die(esc_html($result->get_error_message()),'上线整改未执行',array('response'=>400)); }
    wp_safe_redirect(admin_url('admin.php?page=cg-launch-cleanup'));exit;
});
add_action('admin_post_cg_launch_cleanup_restore_routes',function(){
    if (!current_user_can('manage_options')) { wp_die('没有权限。','',array('response'=>403)); }
    check_admin_referer('cg_launch_cleanup_restore_routes','cg_launch_cleanup_restore_nonce');
    delete_option('cg_launch_home_alias_id');delete_option('cg_launch_uncategorized_archive_closed');
    if(function_exists('cg_launch_invalidate_sitemaps')){cg_launch_invalidate_sitemaps();}do_action('litespeed_purge_all');
    wp_safe_redirect(admin_url('admin.php?page=cg-launch-cleanup'));exit;
});
function cg_launch_cleanup_uncategorized_request() {
    if (is_admin() || (defined('REST_REQUEST')&&REST_REQUEST) || !get_option('cg_launch_uncategorized_archive_closed')) { return false; }
    return !empty($GLOBALS['cg_launch_uncategorized_404']) || is_category('uncategorized');
}
add_action('template_redirect',function(){
    if (is_admin() || (defined('REST_REQUEST')&&REST_REQUEST)) { return; }
    if (cg_launch_cleanup_uncategorized_request()) { global $wp_query;$GLOBALS['cg_launch_uncategorized_404']=true;$wp_query->set_404();status_header(404);nocache_headers();header('X-Robots-Tag: noindex, follow',true); }
    $id=(int)get_option('cg_launch_home_alias_id');$post=$id?get_post($id):null;
    if ($post && $post->post_type==='page' && $post->post_name==='home' && $post->post_status==='publish' && (int)get_option('page_for_posts')!==$id && cg_launch_cleanup_path(wp_unslash($_SERVER['REQUEST_URI']??''),true)===cg_launch_cleanup_path(home_url('/home/')) && is_page($id)) {
        if (get_option('show_on_front')==='page' && (int)get_option('page_on_front')===$id) { wp_safe_redirect(home_url('/'),301,'DegenDeer');exit; }
    }
},0);
add_filter('redirect_canonical',function($redirect){return cg_launch_cleanup_uncategorized_request()?false:$redirect;});
add_filter('wp_robots',function($robots){if(cg_launch_cleanup_uncategorized_request()){unset($robots['index']);$robots['noindex']=true;}return $robots;},200);
add_filter('rank_math/frontend/robots',function($robots){if(cg_launch_cleanup_uncategorized_request()){unset($robots['index']);$robots['noindex']='noindex';}return $robots;},200);
// Remove only this redundant category from ordinary front-end lists after apply.
add_filter('get_terms_args',function($args,$taxonomies){
    if (is_admin() || (defined('REST_REQUEST')&&REST_REQUEST) || !get_option('cg_launch_uncategorized_archive_closed') || (array)$taxonomies!==array('category') || !empty($args['slug']) || !empty($args['include']) || !empty($args['name']) || !empty($args['term_taxonomy_id']) || !empty($GLOBALS['cg_launch_cleanup_category_lookup'])) { return $args; }
    $GLOBALS['cg_launch_cleanup_category_lookup']=true;$term=get_term_by('slug','uncategorized','category');$GLOBALS['cg_launch_cleanup_category_lookup']=false;
    if($term){$args['exclude']=array_values(array_unique(array_merge(wp_parse_id_list($args['exclude']??array()),array((int)$term->term_id))));}return $args;
},30,2);
