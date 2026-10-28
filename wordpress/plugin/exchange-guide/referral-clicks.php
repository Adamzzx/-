<?php
/** Separate confirmed browser-click counter. Historical _cg_clicks is never changed here. */
if (!defined('ABSPATH')) { exit; }

function cg_clicks_result($counted=false, $duplicate=false) {
    $response = new WP_REST_Response(array('counted'=>(bool)$counted, 'duplicate'=>(bool)$duplicate));
    $response->header('Cache-Control', 'no-store, private, max-age=0');
    $response->header('X-Robots-Tag', 'noindex, nofollow');
    return $response;
}

function cg_clicks_error($code, $message, $status=400) {
    return new WP_Error($code, $message, array('status'=>$status));
}

function cg_clicks_url_parts($url) {
    if (!is_string($url) || $url === '' || strlen($url)>8192 || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) { return false; }
    $parts=wp_parse_url($url);
    if (!is_array($parts) || !isset($parts['scheme'],$parts['host']) || !in_array(strtolower($parts['scheme']),array('http','https'),true) || isset($parts['user']) || isset($parts['pass'])) { return false; }
    return $parts;
}

function cg_clicks_origin($parts) {
    if (!is_array($parts) || !isset($parts['scheme'],$parts['host'])) { return ''; }
    $scheme=strtolower($parts['scheme']);$port=isset($parts['port'])?(int)$parts['port']:($scheme==='https'?443:80);
    return $scheme.'://'.strtolower($parts['host']).':'.$port;
}

function cg_clicks_same_site_url($url) {
    $parts=cg_clicks_url_parts($url);$home=cg_clicks_url_parts(home_url('/'));
    return $parts && $home && cg_clicks_origin($parts)===cg_clicks_origin($home) ? $parts : false;
}

/** These headers prevent cross-site browser requests; they do not prove a human visitor. */
function cg_clicks_request_permission($request) {
    if ($request->get_header('x-cg-click')!=='1') { return cg_clicks_error('cg_click_header','缺少点击请求标识。',403); }
    $fetch=strtolower(trim($request->get_header('sec-fetch-site')));
    if ($fetch!=='' && !in_array($fetch,array('same-origin','none'),true)) { return cg_clicks_error('cg_click_origin','不接受跨站点击请求。',403); }
    $origin=trim($request->get_header('origin'));
    if ($origin!=='') {
        $parts=cg_clicks_same_site_url($origin);
        if (!$parts || !in_array($parts['path']??'',array('','/'),true) || isset($parts['query']) || isset($parts['fragment'])) { return cg_clicks_error('cg_click_origin','点击请求来源不符。',403); }
    } elseif (!cg_clicks_same_site_url($request->get_header('referer'))) {
        return cg_clicks_error('cg_click_origin','无法确认本站请求来源。',403);
    }
    return true;
}

function cg_clicks_is_prefetch($request) {
    foreach (array('purpose','sec-purpose','x-purpose','x-moz') as $header) {
        if (preg_match('/prefetch|prerender|preview/i',$request->get_header($header))) { return true; }
    }
    return false;
}

function cg_clicks_is_crawler($request) {
    return (bool)preg_match('/bot\b|crawler|spider|slurp|bingpreview|facebookexternalhit|headlesschrome|lighthouse|pagespeed|prerender|curl\/|wget\/|python-requests|python-urllib|go-http-client|libwww-perl|httpclient/i',$request->get_header('user-agent'));
}

function cg_clicks_is_administrator() {
    if (current_user_can('manage_options')) { return true; }
    // REST deliberately treats cookie-only requests without a wp_rest nonce as anonymous.
    // Validate the existing cookie solely to exclude administrators; never store it.
    $user_id=function_exists('wp_validate_auth_cookie')?wp_validate_auth_cookie('', 'logged_in'):false;
    return $user_id && user_can($user_id,'manage_options');
}

function cg_clicks_token_proof($issued) {
    return hash_hmac('sha256','cg-referral-click:v1|'.home_url('/').'|'.$issued,wp_salt('auth'));
}

function cg_clicks_token_valid($issued, $proof) {
    return is_int($issued) && $issued>0 && $issued<=time() && $issued>=time()-600
        && is_string($proof) && (bool)preg_match('/^[a-f0-9]{64}$/D',$proof)
        && hash_equals(cg_clicks_token_proof($issued),$proof);
}

/** A preview/admin source is deliberately ignored; invalid/external sources are rejected. */
function cg_clicks_source_path($url) {
    $parts=cg_clicks_same_site_url($url);
    if (!$parts) { return cg_clicks_error('cg_click_source','来源页面必须属于本站。'); }
    $path=$parts['path']??'/';$decoded=rawurldecode($path);
    if (strlen($path)>2048 || preg_match('/[\x00-\x1f\x7f\\\\]/',$decoded)) { return cg_clicks_error('cg_click_source','来源路径无效。'); }
    $query=array();wp_parse_str($parts['query']??'',$query);
    foreach (array('preview','preview_id','preview_nonce','customize_changeset_uuid','customize_messenger_channel') as $key) { if (array_key_exists($key,$query)) { return false; } }
    if (preg_match('~(?:^|/)(?:wp-admin(?:/|$)|wp-login\.php(?:/|$))~i',$decoded)) { return false; }
    return $path;
}

/** Resolve the actual existing jump target without visiting it or changing any URL. */
function cg_clicks_target($url) {
    $parts=cg_clicks_same_site_url($url);
    if (!$parts || isset($parts['fragment'])) { return cg_clicks_error('cg_click_target','点击目标必须是本站推广入口。'); }
    $path=$parts['path']??'/';$base=untrailingslashit(wp_parse_url(home_url('/'),PHP_URL_PATH)?:'');
    $query=array();wp_parse_str($parts['query']??'',$query);
    $slug='';$article_id=0;$entry='direct';
    if (preg_match('~^'.preg_quote($base,'~').'/go/(binance|okx|bitget|bybit)/?$~D',$path,$match)) {
        if ($query) { return cg_clicks_error('cg_click_target','推广入口参数不符合规范。'); }
        $slug=$match[1];
    } elseif (preg_match('~^'.preg_quote($base,'~').'/go/review/([1-9][0-9]*)/?$~D',$path,$match)) {
        $article_id=(int)$match[1];$entry='review';
        if (array_diff(array_keys($query),array('country','expires','signature'))) { return cg_clicks_error('cg_click_target','评测入口参数不符合规范。'); }
    } elseif (in_array($path,array($base,$base.'/'),true) && isset($query['cg_go']) && is_string($query['cg_go']) && count($query)===1) {
        $slug=$query['cg_go'];
    } elseif (in_array($path,array($base,$base.'/'),true) && isset($query['cg_review_go']) && is_string($query['cg_review_go']) && preg_match('/^[1-9][0-9]*$/D',$query['cg_review_go'])) {
        if (array_diff(array_keys($query),array('cg_review_go','country','expires','signature'))) { return cg_clicks_error('cg_click_target','评测入口参数不符合规范。'); }
        $article_id=(int)$query['cg_review_go'];$entry='review';
    } else { return cg_clicks_error('cg_click_target','目标不是有效的本站推广入口。'); }
    if ($entry==='review') {
        $country=$query['country']??null;$expires=$query['expires']??null;$signature=$query['signature']??null;
        if (!is_string($country) || !preg_match('/^[a-zA-Z]{2}$/D',$country) || !is_string($expires) || !preg_match('/^[0-9]{1,12}$/D',$expires) || !is_string($signature) || !preg_match('/^[a-f0-9]{64}$/D',$signature)) {
            return cg_clicks_error('cg_click_review','评测入口授权无效。',403);
        }
        $country=strtoupper($country);$expires=(int)$expires;$policy=cg_review_policy($article_id,$country);
        if (!$policy || $expires<time() || $expires>time()+600 || !hash_equals(cg_review_signature($article_id,$country,$expires,$policy),$signature)) { return cg_clicks_error('cg_click_review','评测入口授权已失效或资格不符。',403); }
        $slug=$policy['slug'];
    }
    if (!isset(cg_definitions()[$slug])) { return cg_clicks_error('cg_click_target','未知交易所入口。'); }
    $post=cg_exchange($slug);
    return cg_active_offer($post)?array('post'=>$post,'entry_type'=>$entry):false;
}

function cg_clicks_storage_ready() {
    global $wpdb;
    $tables=array($wpdb->posts,$wpdb->postmeta,$wpdb->options);
    $rows=$wpdb->get_results($wpdb->prepare('SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (%s,%s,%s)',...$tables),ARRAY_A);
    if (!is_array($rows) || count($rows)!==3) { return false; }
    foreach ($rows as $row) { if (strtolower((string)$row['ENGINE'])!=='innodb') { return false; } }
    return true;
}

/** UUID is scoped to the browser gesture, not an IP, visitor or time bucket. */
function cg_clicks_record($post, $event, $source_path, $entry_type) {
    global $wpdb;
    if (!cg_clicks_storage_ready()) { return cg_clicks_error('cg_click_storage','点击统计存储暂不可用，入口跳转不受影响。',503); }
    $name='cg_click_event_'.hash('sha256',strtolower($event));$committed=false;$transaction=false;$claimed=false;
    try {
        if ($wpdb->query('START TRANSACTION')===false) { throw new RuntimeException('transaction'); }$transaction=true;
        $locked=$wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID=%d AND post_type='cg_exchange' AND post_status='publish' FOR UPDATE",$post->ID));
        if ((int)$locked!==(int)$post->ID) { throw new RuntimeException('post'); }
        $value=maybe_serialize(array('timestamp'=>time(),'post_id'=>(int)$post->ID,'source_path'=>$source_path,'entry_type'=>$entry_type));
        // Raw SQL avoids publishing an uncommitted option into a persistent object cache.
        $inserted=$wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->options} (option_name,option_value,autoload) VALUES (%s,%s,'no')",$name,$value));
        if ($inserted===false) { throw new RuntimeException('claim'); }
        if ($inserted===0) {
            if (!$wpdb->get_var($wpdb->prepare("SELECT option_id FROM {$wpdb->options} WHERE option_name=%s",$name))) { throw new RuntimeException('claim'); }
            if ($wpdb->query('COMMIT')===false) { throw new RuntimeException('commit'); }$committed=true;
            return cg_clicks_result(false,true);
        }
        $claimed=true;
        $meta=$wpdb->get_results($wpdb->prepare("SELECT meta_id,meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key='_cg_confirmed_clicks' FOR UPDATE",$post->ID),ARRAY_A);
        if (!is_array($meta) || count($meta)>1) { throw new RuntimeException('counter'); }
        $old=$meta?$meta[0]['meta_value']:'0';
        if (!preg_match('/^(?:0|[1-9][0-9]*)$/D',$old) || strlen($old)>strlen((string)PHP_INT_MAX) || (strlen($old)===strlen((string)PHP_INT_MAX) && strcmp($old,(string)PHP_INT_MAX)>=0)) { throw new RuntimeException('counter'); }
        if ($meta) {
            $updated=$wpdb->query($wpdb->prepare("UPDATE {$wpdb->postmeta} SET meta_value=%s WHERE meta_id=%d",(string)((int)$old+1),(int)$meta[0]['meta_id']));
        } else {
            $updated=$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->postmeta} (post_id,meta_key,meta_value) VALUES (%d,'_cg_confirmed_clicks','1')",$post->ID));
        }
        if ($updated!==1) { throw new RuntimeException('counter'); }
        if ($old==='0' && !$wpdb->get_var($wpdb->prepare("SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key='_cg_confirmed_clicks_first' LIMIT 1",$post->ID))) {
            if ($wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->postmeta} (post_id,meta_key,meta_value) VALUES (%d,'_cg_confirmed_clicks_first',%s)",$post->ID,(string)time()))!==1) { throw new RuntimeException('first'); }
        }
        if ($wpdb->query('COMMIT')===false) { throw new RuntimeException('commit'); }$committed=true;
        wp_cache_delete($post->ID,'post_meta');wp_cache_delete($name,'options');wp_cache_delete('notoptions','options');
        return cg_clicks_result(true,false);
    } catch (Throwable $error) {
        return cg_clicks_error('cg_click_storage','本次点击未能确认入账，入口跳转不受影响。',503);
    } finally {
        if ($transaction && !$committed) { $wpdb->query('ROLLBACK'); }
        if ($claimed && !$committed) { wp_cache_delete($name,'options');wp_cache_delete('notoptions','options');wp_cache_delete($post->ID,'post_meta'); }
    }
}

function cg_clicks_token_request($request) {
    if ($request->get_method()!=='GET' || cg_clicks_is_prefetch($request) || cg_clicks_is_crawler($request)) { return cg_clicks_error('cg_click_request','此请求不签发点击凭据。',403); }
    $issued=time();$response=new WP_REST_Response(array('issued'=>$issued,'proof'=>cg_clicks_token_proof($issued)));
    $response->header('Cache-Control','no-store, private, max-age=0');$response->header('X-Robots-Tag','noindex, nofollow');return $response;
}

function cg_clicks_event_request($request) {
    if ($request->get_method()!=='POST') { return cg_clicks_error('cg_click_method','仅接受点击事件。',405); }
    if (cg_clicks_is_administrator() || cg_clicks_is_prefetch($request) || cg_clicks_is_crawler($request)) { return cg_clicks_result(); }
    $event=$request->get_param('event');$issued=$request->get_param('issued');$proof=$request->get_param('proof');$target=$request->get_param('target');$source=$request->get_param('source');
    if (!is_string($event) || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD',$event) || !is_string($target) || !is_string($source)) { return cg_clicks_error('cg_click_event','点击事件格式不正确。'); }
    if (!cg_clicks_token_valid($issued,$proof)) { return cg_clicks_error('cg_click_proof','点击凭据无效或已经过期。',403); }
    $path=cg_clicks_source_path($source);if (is_wp_error($path)) { return $path; }if ($path===false) { return cg_clicks_result(); }
    $resolved=cg_clicks_target($target);if (is_wp_error($resolved)) { return $resolved; }if ($resolved===false) { return cg_clicks_result(); }
    return cg_clicks_record($resolved['post'],$event,$path,$resolved['entry_type']);
}

add_action('rest_api_init',function(){
    register_rest_route('cg/v1','/referral-click-token',array('methods'=>'GET','permission_callback'=>'cg_clicks_request_permission','callback'=>'cg_clicks_token_request'));
    register_rest_route('cg/v1','/referral-click',array('methods'=>'POST','permission_callback'=>'cg_clicks_request_permission','callback'=>'cg_clicks_event_request'));
});
add_filter('rest_post_dispatch',function($response,$server,$request){
    if (in_array($request->get_route(),array('/cg/v1/referral-click-token','/cg/v1/referral-click'),true)) {
        $response=rest_ensure_response($response);if ($response instanceof WP_HTTP_Response) {$response->header('Cache-Control','no-store, private, max-age=0');$response->header('X-Robots-Tag','noindex, nofollow');}
    }return $response;
},10,3);

function cg_clicks_schedule_cleanup() {
    if (!wp_next_scheduled('cg_clicks_cleanup_events')) { wp_schedule_event(time()+HOUR_IN_SECONDS,'hourly','cg_clicks_cleanup_events'); }
}
add_action('admin_init',function(){if(current_user_can('manage_options')){cg_clicks_schedule_cleanup();}});
register_activation_hook(__DIR__.'/exchange-guide.php','cg_clicks_schedule_cleanup');
register_deactivation_hook(__DIR__.'/exchange-guide.php',function(){wp_clear_scheduled_hook('cg_clicks_cleanup_events');});

/** Remove only old idempotency records; never subtract counters or delete real clicks. */
function cg_clicks_cleanup_events() {
    global $wpdb;$prefix='cg_click_event_';$cutoff=time()-7*DAY_IN_SECONDS;$cursor=0;
    for ($batch=0;$batch<10;$batch++) {
        $rows=$wpdb->get_results($wpdb->prepare("SELECT option_id,option_name,option_value FROM {$wpdb->options} WHERE option_name LIKE %s AND option_id>%d ORDER BY option_id ASC LIMIT 500",$wpdb->esc_like($prefix).'%',$cursor),ARRAY_A);
        if (!$rows) { break; }
        foreach ($rows as $row) {
            $cursor=(int)$row['option_id'];$name=$row['option_name'];$data=maybe_unserialize($row['option_value']);
            if (!preg_match('/^cg_click_event_[a-f0-9]{64}$/D',$name) || !is_array($data) || !isset($data['timestamp']) || !is_int($data['timestamp']) || $data['timestamp']>=$cutoff) { continue; }
            // Compare the original value too, so a concurrent replacement cannot be removed.
            $deleted=$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_id=%d AND option_name=%s AND option_value=%s",$row['option_id'],$name,$row['option_value']));
            if ($deleted) { wp_cache_delete($name,'options'); }
        }
        if (count($rows)<500) { break; }
    }
}
add_action('cg_clicks_cleanup_events','cg_clicks_cleanup_events');

function cg_clicks_markup($post) {
    if (!$post instanceof WP_Post || !current_user_can('manage_options')) { return; }
    $old=absint(get_post_meta($post->ID,'_cg_clicks',true));$new=absint(get_post_meta($post->ID,'_cg_confirmed_clicks',true));$first=absint(get_post_meta($post->ID,'_cg_confirmed_clicks_first',true));
    echo '<p>旧版入口请求累计：<strong>'.esc_html(number_format_i18n($old)).'</strong>（历史原值保留；可能包含刷新、探测或自动请求，不代表实际注册。）</p>';
    echo '<p>新版推广点击累计：<strong>'.esc_html(number_format_i18n($new)).'</strong>'.($first?' · 首笔记录：'.esc_html(wp_date('Y-m-d H:i:s',$first)):'').'。</p>';
    if (!cg_clicks_storage_ready()) { echo '<p class="notice notice-error inline">新版统计存储检查未通过，暂不记录新点击；推广跳转不受影响。请确认 WordPress 的文章、文章元数据及 Options 表均支持 InnoDB 事务，并可读取对应存储引擎信息。</p>'; }
    echo '<p class="description">新版统计仅接收浏览器实际激活有效推荐入口的事件，排除管理员、预览及已识别的自动请求。每次激活使用独立事件编号，同一事件重试在 7 天内只计一次；再次点击会产生新事件。官方默认入口不计入推荐点击。此数值不是独立访客、注册、入金或交易量；技术请求仍无法完全证明真人操作。</p>';
    global $wpdb;
    $values=$wpdb->get_col($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id DESC LIMIT 1000",$wpdb->esc_like('cg_click_event_').'%'));
    $events=array();
    foreach ((array)$values as $value) {
        $row=maybe_unserialize($value);
        if (!is_array($row) || (int)($row['post_id']??0)!==$post->ID || !is_int($row['timestamp']??null) || $row['timestamp']<time()-7*DAY_IN_SECONDS) { continue; }
        $events[]=$row;if (count($events)>=20) { break; }
    }
    echo '<details><summary>最近点击记录（仅管理员可见）</summary><p>从最近 1000 条事件中列出本平台近 7 天最多 20 条。时间按 WordPress 站点时区显示。仅保存事件摘要、时间、平台及浏览器上报的来源路径，不保存 IP、访客身份、推荐码或完整目标链接。过期记录由计划任务清理，调度延迟可能延长保留时间；累计数不随清理减少。</p>';
    if (!$events) { echo '<p>暂无新版点击明细。旧版累计数字不能还原为点击记录。</p>'; }
    else {
        echo '<table class="widefat striped"><thead><tr><th>时间</th><th>来源路径（浏览器上报）</th><th>入口类型</th></tr></thead><tbody>';
        foreach ($events as $row) {
            echo '<tr><td>'.esc_html(wp_date('Y-m-d H:i:s',$row['timestamp'])).'</td><td>'.esc_html((string)($row['source_path']??'')).'</td><td>'.esc_html(($row['entry_type']??'')==='review'?'文章确认入口':'普通推荐入口').'</td></tr>';
        }
        echo '</tbody></table>';
    }
    echo '</details>';
}

add_action('wp_enqueue_scripts',function(){
    if (is_admin() || is_preview() || current_user_can('manage_options')) { return; }
    wp_enqueue_script('cg-referral-clicks',plugins_url('assets/referral-clicks.js',__FILE__),array(),'1.14.4',true);
    $config=array('tokenUrl'=>rest_url('cg/v1/referral-click-token'),'eventUrl'=>rest_url('cg/v1/referral-click'),'sitePath'=>wp_parse_url(home_url('/'),PHP_URL_PATH));
    wp_add_inline_script('cg-referral-clicks','window.cgReferralClicks = '.wp_json_encode($config,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';','before');
});
