<?php
/** Confirmed publishing identity and reviewable launch-page drafts. */
if (!defined('ABSPATH')) { exit; }

function cg_launch_identity_config() {
    return array(
        'site_name' => 'DegenDeer 鹿研社',
        'display_name' => '鹿研社',
        'tagline' => '加密货币与美股入门、交易所评测与资产安全',
        'email' => 'hello@degendeer.com',
        'publisher_type' => 'Person',
    );
}

/** Explicit admin operation only: never called on activation or normal requests. */
function cg_launch_apply_identity() {
    if (!current_user_can('manage_options')) {
        return new WP_Error('cg_launch_forbidden', '需要网站管理员权限。');
    }
    $identity = cg_launch_identity_config();
    $options = array(
        'blogname' => $identity['site_name'],
        'blogdescription' => $identity['tagline'],
        'cg_brand' => $identity['site_name'],
        'cg_tagline' => $identity['tagline'],
        'default_comment_status' => 'closed',
        'default_ping_status' => 'closed',
    );
    $changed = array();
    foreach ($options as $key => $value) {
        if (get_option($key) !== $value) {
            update_option($key, $value);
            $changed[] = $key;
        }
    }
    // Merge the verified Rank Math identity keys; leave manual article SEO intact.
    $titles = get_option('rank-math-options-titles', array());
    $titles = is_array($titles) ? $titles : array();
    $updated = array_merge($titles, array(
        'website_name' => $identity['site_name'],
        'website_alternate_name' => $identity['display_name'],
        'knowledgegraph_type' => 'person',
        'knowledgegraph_name' => $identity['display_name'],
        'url' => home_url('/'),
    ));
    if ($updated !== $titles) {
        update_option('rank-math-options-titles', $updated);
        $changed[] = 'rank-math-options-titles';
        if (function_exists('rank_math') && isset(rank_math()->settings) && is_callable(array(rank_math()->settings, 'reset'))) {
            rank_math()->settings->reset();
        }
    }
    return array('changed_options' => $changed, 'identity' => $identity);
}

function cg_launch_publisher() {
    $identity = cg_launch_identity_config();
    return array(
        '@type' => 'Person',
        '@id' => home_url('/#publisher'),
        'name' => $identity['display_name'],
        'url' => home_url('/'),
    );
}

/** Correct only this site's WebSite/publisher entities, preserving author nodes. */
function cg_launch_rankmath_graph($data) {
    if (!is_array($data)) { return $data; }
    if (isset($data['@graph']) && is_array($data['@graph'])) {
        $data['@graph'] = cg_launch_rankmath_graph($data['@graph']);
        return $data;
    }
    $identity = cg_launch_identity_config();
    $publisher_ids = array(home_url('/#organization'), home_url('/#person'), home_url('/#publisher'));
    foreach ($data as $key => &$entity) {
        if (!is_array($entity)) { continue; }
        $types = isset($entity['@type']) ? (array) $entity['@type'] : array();
        $id = isset($entity['@id']) && is_string($entity['@id']) ? $entity['@id'] : '';
        $site_publisher = in_array($id, $publisher_ids, true)
            || ($key === 'publisher' && array_intersect($types, array('Person', 'Organization')));
        if ($site_publisher) {
            $entity['@type'] = 'Person';
            $entity['name'] = $identity['display_name'];
            // Organization-only company facts do not describe this personal site.
            foreach (array('legalName', 'foundingDate', 'numberOfEmployees', 'taxID', 'vatID', 'naics', 'duns', 'leiCode') as $property) {
                unset($entity[$property]);
            }
        } elseif (in_array('WebSite', $types, true)
            && ($id === home_url('/#website') || (isset($entity['url']) && untrailingslashit($entity['url']) === untrailingslashit(home_url('/'))))) {
            $entity['name'] = $identity['site_name'];
            $entity['alternateName'] = $identity['display_name'];
        }
    }
    unset($entity);
    return $data;
}
add_filter('rank_math/json_ld', 'cg_launch_rankmath_graph', 120);
add_filter('rank_math/opengraph/facebook/og_site_name', function ($name) {
    return cg_launch_identity_config()['site_name'];
}, 120);

// No public discussion is offered. Existing comments are not deleted.
add_filter('comments_open', '__return_false', 99);
add_filter('pings_open', '__return_false', 99);

/** Remove only Reach's unused public subscription embed, not Hostinger admin. */
function cg_launch_remove_reach_embed() {
    if (!is_admin()) { wp_dequeue_script('hostinger-reach-embed'); }
}
add_action('wp_enqueue_scripts', 'cg_launch_remove_reach_embed', 999);
add_action('wp_print_scripts', 'cg_launch_remove_reach_embed', 999);
add_action('wp_print_footer_scripts', 'cg_launch_remove_reach_embed', 0);
add_filter('script_loader_tag', function ($tag, $handle) {
    return !is_admin() && $handle === 'hostinger-reach-embed' ? '' : $tag;
}, 999, 2);

function cg_launch_public_page($slug) {
    $page = get_page_by_path($slug, OBJECT, 'page');
    return $page instanceof WP_Post && $page->post_status === 'publish' && $page->post_password === '' ? $page : null;
}

function cg_launch_footer_links() {
    $links = array();
    foreach (array('disclosure' => '商业披露与风险说明', 'methodology' => '比较方法', 'about' => '关于鹿研社', 'contact' => '联系与纠错') as $slug => $label) {
        $page = cg_launch_public_page($slug);
        if ($page) { $links[] = array('label' => $label, 'url' => get_permalink($page)); }
    }
    $privacy_id = (int) get_option('wp_page_for_privacy_policy');
    $privacy = $privacy_id > 0 ? get_post($privacy_id) : null;
    if (!$privacy instanceof WP_Post || $privacy->post_type !== 'page' || $privacy->post_status !== 'publish' || $privacy->post_password !== '') {
        $privacy = cg_launch_public_page('privacy-policy');
    }
    if ($privacy) { $links[] = array('label' => '隐私政策', 'url' => get_permalink($privacy)); }
    return $links;
}

/** Native editable block content; no article text is stored in theme templates. */
function cg_launch_page_drafts() {
    $identity = cg_launch_identity_config();
    $email = esc_html($identity['email']);
    $contact = '<a href="mailto:' . esc_attr($identity['email']) . '">' . $email . '</a>';
    $disclosure = '<a href="' . esc_url(home_url('/disclosure/')) . '">商业披露与风险说明</a>';
    $methodology = '<a href="' . esc_url(home_url('/methodology/')) . '">比较方法</a>';
    $paragraph = static function ($text) { return '<!-- wp:paragraph --><p>' . $text . '</p><!-- /wp:paragraph -->'; };
    $heading = static function ($text) { return '<!-- wp:heading --><h2 class="wp-block-heading">' . $text . '</h2><!-- /wp:heading -->'; };
    $about = $paragraph('DegenDeer 鹿研社是由公开署名“鹿研社”的个人运营的中文内容网站，关注加密货币与美股入门、交易所评测及资产安全。')
        . $paragraph('我们希望把产品规则、操作流程与风险讲清楚，帮助读者找到官方资料并建立自己的判断。文章涵盖交易所使用、费用、出入金、钱包与自托管、理财产品及美股相关内容；具体产品能否使用，取决于所在地、账户资格与平台当时的规则。')
        . $heading('内容如何整理')
        . $paragraph('内容以可核对的官方资料为基础；涉及实际体验时，会说明测试范围。费用、产品状态及活动会变化，请同时查看文章中的资料来源与核验日期。未经核实的数据不作为确定结论，发现问题后会进行更正。')
        . $paragraph('本站是个人内容网站，不是交易所、经纪商或持牌投资顾问，不代办开户、保管资金，也不承诺投资收益。文章用于信息分享，不构成针对个人情况的投资建议。')
        . $heading('商业关系与联系')
        . $paragraph('部分入口可能是推荐链接；存在佣金或赞助关系时会明确标识。请参阅' . $disclosure . '及' . $methodology . '。')
        . $paragraph('联系与内容纠错：' . $contact . '。');
    $contact_page = $paragraph('内容建议、事实更正与合作联系，请发送邮件至：' . $contact . '。')
        . $heading('如何提交内容更正')
        . $paragraph('请附上文章链接、需要核对的原文段落、问题说明，以及可验证的官方资料链接和日期。涉及费用、资格或产品变更时，请说明适用的地区与产品范围，方便核查。')
        . $paragraph('本站不提供公开评论、联系表单或邮件订阅。联系通过电子邮件进行；回复时间取决于问题与核查情况。')
        . $heading('请保护个人资料')
        . $paragraph('请不要发送交易所密码、验证码、助记词、私钥、身份证件或银行账户等敏感资料。本站不代办开户、不代管资金，也不会通过邮件索要上述信息。涉及账户、充值提现或交易争议，请通过相关平台的官方客服渠道处理。')
        . $paragraph('合作邮件请说明品牌、合作形式与商业关系。接受合作不等于承诺作出正面评价；商业内容按实际关系披露。');
    $analytics_enabled = function_exists('cg_baidu_analytics_enabled') && cg_baidu_analytics_enabled();
    $analytics_text = $analytics_enabled
        ? '本站已启用百度统计站长版作为站内访问统计。统计脚本在访客端异步加载，并排除已登录的网站管理员。百度统计可能通过浏览器标识、Cookie及访问或点击信息帮助了解页面使用情况。外链点击只表示用户点击了入口，不代表注册、入金或交易成功。百度对相关信息的处理应结合其实际服务设置与隐私规则理解。'
        : '本站已选择百度统计站长版作为计划采用的唯一站内访问统计，但目前尚未启用真实站点统计代码。启用后应在本页补充实际收集范围与服务规则；不把尚未接入的统计写成已经运行。';
    $privacy = $paragraph('本政策说明 DegenDeer 鹿研社个人网站在内容浏览、功能使用和邮件联系中的信息处理。联系邮箱：' . $contact . '。')
        . $heading('内容浏览与网站运行')
        . $paragraph('网站由 Hostinger 提供托管服务，联系邮箱使用 Hostinger 邮箱。网站与服务商为正常运行、排错及安全防护，可能处理 IP 地址、访问时间、请求网址、浏览器信息以及访问或错误日志。日志的实际保存期限依服务配置及服务商规则而定，本页不承诺尚未确认的固定天数。')
        . $paragraph('普通内容浏览不要求注册本站账户。WordPress 后台登录可能使用身份验证及会话 Cookie；后台登录 Cookie 与面向公开访客的邮件订阅是不同功能。本站不提供公开评论、联系表单或邮件订阅，已停用公开的 Hostinger Reach 订阅嵌入脚本。')
        . $heading('搜索、比较与资格确认')
        . $paragraph('搜索与交易所比较会处理你提交的关键词或平台选项。部分商业入口会要求选择国家或地区并确认资格，以按产品条款控制展示；这些操作不要求填写姓名、邮箱、密码或身份证件。请求参数可能出现在网站访问或安全日志中。资格确认不是本站对平台资格的保证，实际资格仍由平台按其规则判断。')
        . $paragraph('部分推荐跳转可能记录入口的汇总点击次数，用于了解入口的使用情况；点击次数不等于注册、入金或交易成功。')
        . $heading('电子邮件联系')
        . $paragraph('主动来信会向本站及邮箱服务商提供发件地址和邮件内容，用于回复、核查更正或处理合作请求。请仅发送问题所需的信息，不发送账户密码、验证码、助记词、私钥或身份证明。可通过上述邮箱提出与来信资料有关的查询或处理请求；需要留存的安全或更正记录会按实际目的处理。')
        . $heading('访问统计')
        . $paragraph($analytics_text)
        . $heading('外部网站与商业链接')
        . $paragraph('文章可能引用平台官方资料或包含商业推荐入口。点击后会进入外部网站，其账户、Cookie及个人资料处理由对方负责。请在注册或使用前阅读对方的条款、资格限制与隐私政策；本站的披露见' . $disclosure . '。')
        . $heading('政策更新')
        . $paragraph('启用新的数据收集功能、改变服务商或调整统计设置前，应核对并更新本政策。有关本政策或资料处理的问题，请联系' . $contact . '。');
    return array(
        'about' => array('post_title' => '关于鹿研社', 'post_content' => $about),
        'contact' => array('post_title' => '联系与纠错', 'post_content' => $contact_page),
        'privacy-policy' => array('post_title' => '隐私政策', 'post_content' => $privacy),
    );
}

/** Create only missing drafts; preserve any existing page, including default privacy. */
function cg_launch_prepare_page_drafts() {
    if (!current_user_can('manage_options')) {
        return new WP_Error('cg_launch_forbidden', '需要网站管理员权限。');
    }
    $results = array();
    foreach (cg_launch_page_drafts() as $slug => $definition) {
        $existing = get_page_by_path($slug, OBJECT, 'page');
        if ($slug === 'privacy-policy') {
            $privacy_id = (int) get_option('wp_page_for_privacy_policy');
            $assigned = $privacy_id > 0 ? get_post($privacy_id) : null;
            if ($assigned instanceof WP_Post && $assigned->post_type === 'page' && $assigned->post_status !== 'trash') {
                $existing = $assigned;
            }
        }
        if ($existing instanceof WP_Post) {
            $results[$slug] = array('id' => $existing->ID, 'status' => $existing->post_status, 'result' => 'preserved', 'edit_url' => get_edit_post_link($existing->ID, 'raw'), 'proposed_content' => $definition['post_content']);
            continue;
        }
        $id = wp_insert_post(array_merge($definition, array(
            'post_type' => 'page', 'post_name' => $slug, 'post_status' => 'draft',
            'comment_status' => 'closed', 'ping_status' => 'closed',
        )), true);
        if (is_wp_error($id)) {
            $results[$slug] = array('result' => 'error', 'message' => $id->get_error_message());
            continue;
        }
        update_post_meta($id, '_cg_launch_page_draft', '1');
        $results[$slug] = array('id' => $id, 'status' => 'draft', 'result' => 'created', 'edit_url' => get_edit_post_link($id, 'raw'));
    }
    return $results;
}
