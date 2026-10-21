<?php
/** Narrow, backed-up remediation. No writes on activation, cron or public requests. */
if (!defined('ABSPATH')) { exit; }

function cg_cleanup_rows() {
    $inventory = cg_launch_cleanup_inventory();
    return array_map(function ($spec) use ($inventory) {
        $row=cg_launch_cleanup_post_row($spec, $inventory);
        $post=$row['id']?get_post($row['id']):null;
        if ($spec['slug']==='home' && $post && $post->post_type==='page' && $post->post_name==='home' && in_array($post->post_title,$spec['titles'],true) && !$post->post_parent && $post->post_status==='publish' && get_option('show_on_front')==='page' && (int)get_option('page_on_front')===(int)$post->ID && (int)get_option('page_for_posts')!==(int)$post->ID && (int)get_option('wp_page_for_privacy_policy')!==(int)$post->ID) {
            $row['action']='internal';$row['reasons']=array('show_on_front/page_on_front 证实为真实静态首页；完整保留正文、模板、元数据及内部依赖，仅规范额外 /home/ 地址。');
        }
        return $row;
    }, cg_launch_cleanup_specs());
}

function cg_cleanup_privacy_plan() {
    $post = get_page_by_path('privacy-policy', OBJECT, 'page');
    $row = array('id'=>0, 'action'=>'keep', 'reason'=>'未发现原 /privacy-policy/ 页面，不创建重复页面。');
    if (!$post) { return $row; }
    $row['id'] = (int)$post->ID;
    $row['fingerprint'] = hash('sha256', $post->post_content);
    if ($post->post_status !== 'publish' || $post->post_password || $post->post_parent || !current_user_can('edit_post', $post->ID)) { $row['reason']='页面状态或权限不符合公开隐私页，保留原文。'; return $row; }
    // Match the default template itself, never merely the title/ID or a short phrase.
    $plain = wp_strip_all_tags($post->post_content);
    $markers = array('Suggested text:', 'Who we are', 'Comments', 'Cookies', 'Embedded content from other websites', 'How long we retain your data');
    foreach ($markers as $marker) { if (stripos($plain, $marker) === false) { $row['reason']='不是已确认的 WordPress 默认英文模板，保留自写正文。'; return $row; } }
    $row['action']='replace_default'; $row['reason']='原 URL、类型与默认英文正文交叉匹配；只替换正文，保留标题、永久链接、SEO 及作者。';
    return $row;
}

function cg_cleanup_privacy_content() {
    $h = function($text) { return '<!-- wp:heading --><h2 class="wp-block-heading">'.esc_html($text).'</h2><!-- /wp:heading -->'; };
    $p = function($text) { return '<!-- wp:paragraph --><p>'.esc_html($text).'</p><!-- /wp:paragraph -->'; };
    $baidu = function_exists('cg_baidu_analytics_enabled') && cg_baidu_analytics_enabled();
    $text = $p('本政策适用于 DegenDeer 鹿研社（'.home_url('/').'）。本站为个人运营的中文内容网站，提供加密货币、美股、交易所评测及资产安全相关资料。与隐私及资料处理有关的问题，请联系 hello@degendeer.com。');
    $text .= $h('访问、搜索与技术日志').$p('浏览网页时，Hostinger 主机、缓存或安全服务可能处理 IP 地址、访问时间、请求网址、浏览器信息、来源页面及错误信息，用于交付页面、排查问题和维护安全。站内搜索处理你输入的关键词；搜索词、比较参数和请求地址可能进入服务日志。请勿在搜索框、网址或请求参数中输入个人敏感信息。');
    $text .= $h('评论、表单、订阅与电子邮件').$p('本站不使用公开文章评论、联系表单或邮件订阅。联系与纠错通过电子邮件进行；你主动来信时，本站及邮箱服务商 Hostinger 会处理发件地址、邮件内容和必要的邮件技术信息，用于回复和核实问题。请只提供处理问题所需的信息，不发送交易所密码、验证码、私钥、助记词或身份证件。');
    $text .= $h('Cookie 与本地存储').$p('WordPress 管理员登录、后台偏好及网站的安全或缓存功能可能使用 Cookie。管理员登录 Cookie 不意味着本站向普通读者开放账户注册。浏览器可以限制或删除 Cookie，但可能影响登录或部分交互。本站不作“完全不使用 Cookie”或“绝不处理访问数据”的承诺。');
    $text .= $h('访问统计与入口点击');
    $text .= $p($baidu ? '本站已在自定义插件中启用百度统计站长版，用于了解页面访问和外链点击。百度统计脚本异步加载；本插件排除具有网站管理权限的登录管理员及预览页面。该服务可能使用 Cookie 等技术处理访问设备、来源及浏览行为，具体处理规则以百度统计官方隐私说明及实际配置为准。' : '本站自定义插件中的百度统计接入当前未启用。本站拟使用百度统计站长版作为唯一的站内访问统计；启用真实代码前应再次核对本政策和实际数据处理配置。此处不表示主机、安全服务没有技术日志，也不表示已核实所有第三方服务的后台设置。');
    $text .= $p('部分交易所入口支持记录汇总点击次数。启用百度统计时，外链点击事件可包含交易所、所在文章标识和按钮位置；点击仅表示打开外部链接，不代表注册、KYC、入金或交易成功。本站不会通过这些跳转收集交易所账户密码或身份核验材料。');
    $text .= $h('外部图片、嵌入与第三方网站').$p('文章可能引用外部资料，或包含远程图片、视频和其他嵌入内容。浏览器加载第三方资源时，对方可能接收 IP 地址、设备信息和来源信息，并按自身规则使用 Cookie。普通外链通常在点击后进入第三方网站；交易所的注册、身份核验和交易由其自行处理。使用前请阅读对方的条款与隐私政策。商业入口的说明见本站“商业披露与风险说明”。');
    $text .= $h('保存、访问与处理请求').$p('资料仅按回复联系、内容纠错、安全维护、统计或适用法律等实际需要处理。本站尚未核实各类 Hostinger 日志、缓存、备份以及邮件的实际保存期限，也未核实所有托管服务内部处理地点，因此不在本政策中编造固定天数或境内保存承诺。你可通过联系邮箱提出查阅、更正或删除与来信相关资料的请求；处理时需核实请求范围及必要的安全、备份或法律限制。');
    $text .= $h('政策维护与待核实事项').$p('待核实事项包括托管平台各类日志和备份的具体期限、平台侧统计或自动化服务设置，以及所有文章是否含有第三方嵌入。上述信息应按实际核查结果补充。启用评论、表单、订阅、新的统计或外部嵌入服务时，应同步审阅和更新本政策。');
    return $text;
}

function cg_cleanup_option_names() {
    return array('cg_home_id','cg_launch_home_alias_id','cg_navigation_item_ids','cg_cleanup_retired_paths','cg_cleanup_runs');
}
function cg_cleanup_capture($ids) {
    global $wpdb;
    $data=array('posts'=>array(), 'meta'=>array(), 'relationships'=>array(), 'options'=>array());
    foreach (array_unique(array_map('absint',$ids)) as $id) {
        if (!$id) { continue; }
        $data['posts'][$id]=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE ID=%d",$id),ARRAY_A);
        $data['meta'][$id]=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->postmeta} WHERE post_id=%d ORDER BY meta_id",$id),ARRAY_A);
        $data['relationships'][$id]=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->term_relationships} WHERE object_id=%d ORDER BY term_taxonomy_id",$id),ARRAY_A);
    }
    foreach (cg_cleanup_option_names() as $name) { $data['options'][$name]=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->options} WHERE option_name=%s",$name),ARRAY_A); }
    return $data;
}
function cg_cleanup_digest($data) {
    // The run registry necessarily changes after snapshot/restore; it is not content.
    unset($data['options']['cg_cleanup_runs']);
    return hash('sha256',serialize($data));
}
function cg_cleanup_backup($rows,$privacy) {
    $ids=array(); foreach ($rows as $row) { if ($row['id']) { $ids[]=$row['id']; } $ids=array_merge($ids,$row['owned_menu_ids']); }
    if ($privacy['id']) { $ids[]=$privacy['id']; }
    $ids=array_values(array_unique($ids));
    $base=defined('CG_CLEANUP_BACKUP_DIR') ? CG_CLEANUP_BACKUP_DIR : dirname(untrailingslashit(ABSPATH)).'/.degendeer-cleanup-backups';
    if (!is_dir($base) && !wp_mkdir_p($base)) { return new WP_Error('backup_directory','无法建立站点公开目录外的备份目录；未执行清理。请先配置 CG_CLEANUP_BACKUP_DIR。'); }
    $base=realpath($base);
    foreach (array(ABSPATH, $_SERVER['DOCUMENT_ROOT']??ABSPATH) as $public) {
        $public=realpath($public);
        if ($public && ($base===$public || str_starts_with($base,$public.DIRECTORY_SEPARATOR))) { return new WP_Error('public_backup','备份目录位于网站公开根目录内，拒绝写入或清理。'); }
    }
    if (!$base || !is_writable($base)) { return new WP_Error('backup_directory','备份目录不可写；未执行清理。'); }
    $run=gmdate('Ymd-His').'-'.bin2hex(random_bytes(8));$dir=$base.'/'.$run;
    if (!mkdir($dir,0700)) { return new WP_Error('backup_directory','无法建立本次私有备份目录。'); }
    @chmod($base,0700);
    $data=array('version'=>1,'site'=>home_url('/'),'created'=>gmdate('c'),'ids'=>$ids,'before'=>cg_cleanup_capture($ids),'rows'=>$rows,'privacy'=>$privacy);
    // Serialized DB rows preserve exact IDs, serialized metadata and SEO; no credentials.
    $bytes=serialize($data);
    if (file_put_contents($dir.'/database.snapshot',$bytes,LOCK_EX)!==strlen($bytes)) { return new WP_Error('backup_write','数据库对象备份写入失败；未执行清理。'); }
    @chmod($dir.'/database.snapshot',0600);
    $files=array();
    foreach (array('plugin'=>__DIR__, 'theme'=>get_stylesheet_directory(), 'mu-plugins'=>WPMU_PLUGIN_DIR) as $kind=>$source) {
        if (!is_dir($source)) { continue; }
        $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source,FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) { continue; }
            $relative=substr($file->getPathname(),strlen($source)+1);$dest=$dir.'/files/'.$kind.'/'.$relative;
            if (!wp_mkdir_p(dirname($dest)) || !copy($file->getPathname(),$dest)) { return new WP_Error('backup_files','相关文件备份未完成；未执行清理。'); }
            @chmod($dest,0600);$files[$kind.'/'.$relative]=hash_file('sha256',$dest);
        }
    }
    $manifest=array('run'=>$run,'site'=>home_url('/'),'scope'=>'相关对象数据库快照与当前主题/自定义插件/mu-plugin文件；不代替整站 SQL、上传目录及 Hostinger 灾备','snapshot_sha256'=>hash('sha256',$bytes),'files'=>$files);
    $json=wp_json_encode($manifest,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    if (file_put_contents($dir.'/manifest.json',$json,LOCK_EX)!==strlen($json)) { return new WP_Error('backup_manifest','备份清单写入失败；未执行清理。'); }
    @chmod($dir.'/manifest.json',0600);
    return array('run'=>$run,'directory'=>$dir,'sha256'=>$manifest['snapshot_sha256'],'ids'=>$ids);
}

function cg_cleanup_purge() {
    $result=array('wordpress_object_cache'=>wp_cache_flush(),'litespeed_hook_registered'=>(bool)has_action('litespeed_purge_all'),'server_cdn'=>'未连接 Hostinger/CDN 管理端；需要运营者清除并公开复验');
    if (function_exists('cg_launch_invalidate_sitemaps')) { cg_launch_invalidate_sitemaps(); }
    if (class_exists('RankMath\\Sitemap\\Cache_Watcher')) {
        foreach (array('post','page','cg_exchange','cg_topic','cg_sections') as $type) { RankMath\Sitemap\Cache_Watcher::invalidate($type); }
        RankMath\Sitemap\Cache_Watcher::clear_queued();
    }
    do_action('litespeed_purge_all');
    return $result;
}
function cg_cleanup_apply($backup_confirmed=false) {
    if (!current_user_can('manage_options')) { return new WP_Error('forbidden','需要网站管理员权限。'); }
    if ($backup_confirmed!==true) { return new WP_Error('backup_required','请先保存可恢复的 Hostinger 文件与完整数据库备份，并确认恢复方法。'); }
    if (!add_option('cg_cleanup_lock',time(),'','no')) { return new WP_Error('busy','已有清理任务锁；请勿并发执行，异常退出时由管理员确认后删除锁。'); }
    try {
        $rows=cg_cleanup_rows();$privacy=cg_cleanup_privacy_plan();
        $backup=cg_cleanup_backup($rows,$privacy);if (is_wp_error($backup)) { return $backup; }
        $result=array('time'=>gmdate('c'),'backup'=>$backup,'front'=>array('show_on_front'=>get_option('show_on_front'),'page_on_front'=>get_option('page_on_front'),'page_for_posts'=>get_option('page_for_posts'),'home_template'=>get_post_meta((int)get_option('page_on_front'),'_wp_page_template',true)),'rows'=>array(),'privacy'=>$privacy,'errors'=>array());
        foreach ($rows as $row) {
            $row['result']='kept';
            if ($row['action']==='trash') {
                if (defined('EMPTY_TRASH_DAYS') && (int)EMPTY_TRASH_DAYS===0) { $row['result']='blocked_trash_disabled';$result['errors'][]=$row['path'].'：回收站禁用，拒绝永久删除。'; }
                else {
                    $row['result']=wp_trash_post($row['id'])?'trashed':'failed';
                    if ($row['result']==='trashed') {
                        $retired=(array)get_option('cg_cleanup_retired_paths',array());$retired[$row['path']]=$row['id'];update_option('cg_cleanup_retired_paths',$retired,false);
                        foreach ($row['owned_menu_ids'] as $menu_id) { $saved=wp_update_post(array('ID'=>$menu_id,'post_status'=>'draft'),true);if (is_wp_error($saved)) { $result['errors'][]='菜单项 #'.$menu_id.' 未撤下。'; } }
                        if ($row['slug']==='home' && (int)get_option('cg_home_id')===$row['id']) { delete_option('cg_home_id'); }
                    } else { $result['errors'][]=$row['path'].'：移入回收站失败。'; }
                }
            } elseif ($row['action']==='internal') {
                // Only a real static front-page binding establishes equivalence with /.
                if (get_option('show_on_front')==='page' && (int)get_option('page_on_front')===$row['id']) { update_option('cg_launch_home_alias_id',$row['id'],false);$row['result']='kept_static_front_alias_301'; }
                else { $row['result']='blocked_dependency';$result['errors'][]=$row['path'].'：存在非首页依赖，保留并报告，不能无依据重定向首页。'; }
            } elseif ($row['action']==='missing') { $row['result']='absent'; }
            elseif ($row['status']!=='trash') { $result['errors'][]=$row['path'].'：身份或依赖核查未通过，未清理。'; }
            $result['rows'][]=$row;
        }
        if ($privacy['action']==='replace_default') {
            $post=get_post($privacy['id']);
            if (!$post || hash('sha256',$post->post_content)!==$privacy['fingerprint']) { $result['errors'][]='隐私正文在预检后发生变化，未覆盖。'; }
            else { $saved=wp_update_post(wp_slash(array('ID'=>$post->ID,'post_content'=>cg_cleanup_privacy_content())),true);$result['privacy']['result']=is_wp_error($saved)?'failed':'updated_original_page';if (is_wp_error($saved)) { $result['errors'][]=$saved->get_error_message(); } }
        } else { $result['privacy']['result']='preserved'; }
        $result['cache']=cg_cleanup_purge();
        $result['after_digest']=cg_cleanup_digest(cg_cleanup_capture($backup['ids']));
        $runs=(array)get_option('cg_cleanup_runs',array());$runs[$backup['run']]=$result;update_option('cg_cleanup_runs',$runs,false);
        return $result;
    } finally { delete_option('cg_cleanup_lock'); }
}

/** Exact related-data rollback; refuse if an affected object was subsequently edited. */
function cg_cleanup_restore($run) {
    if (!current_user_can('manage_options') || !is_string($run)) { return new WP_Error('forbidden','需要管理员权限。'); }
    $runs=(array)get_option('cg_cleanup_runs',array());$record=$runs[$run]??null;
    if (!$record || !empty($record['restored'])) { return new WP_Error('missing','未发现可恢复的执行记录。'); }
    if (!add_option('cg_cleanup_lock',time(),'','no')) { return new WP_Error('busy','已有清理或恢复操作正在执行。'); }
    try {
        $backup=$record['backup'];$path=$backup['directory'].'/database.snapshot';
        if (!is_readable($path) || !hash_equals($backup['sha256'],hash_file('sha256',$path))) { return new WP_Error('integrity','私有备份缺失或校验失败。'); }
        $data=unserialize(file_get_contents($path),array('allowed_classes'=>false));
        if (!is_array($data) || $data['site']!==home_url('/')) { return new WP_Error('site_mismatch','备份不属于当前站点。'); }
        if (!hash_equals($record['after_digest'],cg_cleanup_digest(cg_cleanup_capture($backup['ids'])))) { return new WP_Error('changed','相关对象或设置已有后续编辑，拒绝覆盖；请使用备份进行人工合并恢复。'); }
        global $wpdb;$errors=array();
        foreach (array($wpdb->posts,$wpdb->postmeta,$wpdb->term_relationships,$wpdb->options) as $table) {
            $status=$wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name=%s',$table),ARRAY_A);
            if (!$status || strcasecmp($status['Engine']??'','InnoDB')!==0) { return new WP_Error('transaction_required','相关表并非已核实的 InnoDB 事务表，停止自动恢复，请从完整备份恢复。'); }
        }
        $wpdb->query('START TRANSACTION');
        foreach ($data['before']['posts'] as $id=>$post) {
            if (!$post) { continue; }
            if ($wpdb->update($wpdb->posts,$post,array('ID'=>$id))===false) { $errors[]='posts'; }
            if ($wpdb->delete($wpdb->postmeta,array('post_id'=>$id))===false) { $errors[]='delete_postmeta'; }
            foreach ($data['before']['meta'][$id] as $meta) { if ($wpdb->insert($wpdb->postmeta,$meta)===false) { $errors[]='postmeta'; } }
            if ($wpdb->delete($wpdb->term_relationships,array('object_id'=>$id))===false) { $errors[]='delete_relationships'; }
            foreach ($data['before']['relationships'][$id] as $rel) { if ($wpdb->insert($wpdb->term_relationships,$rel)===false) { $errors[]='relationships'; } }
        }
        foreach ($data['before']['options'] as $name=>$option) {
            if ($name==='cg_cleanup_runs') { continue; }
            if ($wpdb->delete($wpdb->options,array('option_name'=>$name))===false) { $errors[]='delete_options'; }
            if ($option && $wpdb->insert($wpdb->options,$option)===false) { $errors[]='options'; }
        }
        if ($errors) { $wpdb->query('ROLLBACK');return new WP_Error('restore_failed','数据库恢复失败，已回滚事务。'); }
        $wpdb->query('COMMIT');
        wp_cache_flush();foreach ($backup['ids'] as $id) { clean_post_cache($id); }
        // Recount only affected relationships; do not rebuild unrelated classifications.
        $tt=array();foreach ($data['before']['relationships'] as $rels) { foreach ($rels as $rel) { $tt[]=(int)$rel['term_taxonomy_id']; } }
        if ($tt) {
            $groups=array();$ids=implode(',',array_unique(array_map('absint',$tt)));
            foreach ($wpdb->get_results("SELECT term_taxonomy_id,taxonomy FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id IN ($ids)") as $term) { $groups[$term->taxonomy][]=(int)$term->term_taxonomy_id; }
            foreach ($groups as $taxonomy=>$tax_ids) { wp_update_term_count_now($tax_ids,$taxonomy); }
        }
        $record['restored']=gmdate('c');$runs[$run]=$record;update_option('cg_cleanup_runs',$runs,false);cg_cleanup_purge();
        return array('run'=>$run,'restored'=>true,'files'=>'文件未在应用阶段修改；如需回退插件版本，使用操作前插件包或私有 files 目录。');
    } finally { delete_option('cg_cleanup_lock'); }
}

function cg_cleanup_admin() {
    echo '<div class="wrap"><h1>上线整改</h1><p>本次只处理五个已知演示对象和默认英文隐私政策。不重做导航、品牌、正文链接、SEO 或图片 alt；不会删除正式空主题。预检中的依赖不会被强制绕过。</p>';
    cg_launch_cleanup_render_rows(cg_cleanup_rows());
    echo '<h2>隐私政策核查</h2><pre>'.esc_html(wp_json_encode(cg_cleanup_privacy_plan(),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)).'</pre>';
    echo '<p>提交后先在公开根目录外保存相关数据库对象及主题、插件、mu-plugin文件，写入失败即停止。该快照不代替完整整站备份。草稿和回收站对象不公开；无等价替代的闲置页面依靠 WordPress 正常返回真实 404。</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
    wp_nonce_field('cg_launch_cleanup','cg_launch_cleanup_nonce');
    echo '<input type="hidden" name="action" value="cg_launch_cleanup"><p><label><input type="checkbox" name="cg_launch_backup_confirmed" value="1" required> 已保存 Hostinger 当前文件与完整数据库备份，并确认恢复方法。</label></p>';
    submit_button('备份相关对象并执行本轮清理');echo '</form>';
    $runs=(array)get_option('cg_cleanup_runs',array());$last=end($runs);
    if ($last) {
        echo '<h2>最近执行记录</h2><p>以下是数据库及缓存操作报告，不能代替匿名公开 HTTP 验收。</p><pre style="max-height:450px;overflow:auto;white-space:pre-wrap">'.esc_html(wp_json_encode($last,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)).'</pre>';
        if (empty($last['restored'])) { echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('cg_cleanup_restore','cg_cleanup_restore_nonce');echo '<input type="hidden" name="action" value="cg_cleanup_restore"><input type="hidden" name="run" value="'.esc_attr($last['backup']['run']).'">';submit_button('恢复本次相关数据库对象（有后续编辑则停止）','secondary');echo '</form>'; }
    }
    echo '<h2>缓存及公开验收</h2><p>程序请求清理 WordPress 对象缓存、Rank Math 地图缓存和已注册的 LiteSpeed 清理钩子。Hostinger 服务器及实际启用 CDN 缓存须在其管理端另行清除。随后匿名检查全部 sitemap、五个遗留 URL、所有正式文章、四家资料及跳转。未连接托管端时不会声称已清理服务器/CDN。</p></div>';
}
add_action('admin_post_cg_cleanup_restore',function(){
    if (!current_user_can('manage_options')) { wp_die('没有权限。','',array('response'=>403)); }
    check_admin_referer('cg_cleanup_restore','cg_cleanup_restore_nonce');
    $result=cg_cleanup_restore(sanitize_text_field(wp_unslash($_POST['run']??'')));
    if (is_wp_error($result)) { wp_die(esc_html($result->get_error_message()),'恢复未执行',array('response'=>400)); }
    wp_safe_redirect(admin_url('admin.php?page=cg-launch-cleanup'));exit;
});
// Trash remains the actual removal mechanism. Suppress only WP's fuzzy redirect
// guesses on a now-404 retired address; never redirect unrelated missing pages home.
add_filter('redirect_canonical',function($redirect){
    if (!is_404()) { return $redirect; }
    $path=cg_launch_cleanup_path(wp_unslash($_SERVER['REQUEST_URI']??''),true);
    return isset(((array)get_option('cg_cleanup_retired_paths',array()))[$path]) ? false : $redirect;
},100);
