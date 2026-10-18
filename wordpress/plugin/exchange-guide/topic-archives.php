<?php
if(!defined('ABSPATH')){exit;}

// Use the existing hierarchical taxonomy as the single public address for each desk.
function cg_is_crypto_topic($term=null){
    if($term===null){$term=get_queried_object();}
    if(!$term instanceof WP_Term||$term->taxonomy!=='cg_topic'){return false;}
    $root=cg_find_topic('crypto');
    return $root&&($term->term_id===$root->term_id||in_array($root->term_id,get_ancestors($term->term_id,'cg_topic','taxonomy'),true));
}
function cg_topic_is_open($term){
    if(is_numeric($term)){$term=get_term(absint($term),'cg_topic');}
    return $term instanceof WP_Term&&(cg_is_crypto_topic($term)||cg_topic_has_content($term->term_id));
}
function cg_crypto_sections(){
    $root=cg_find_topic('crypto');if(!$root){return array();}
    $terms=get_terms(array('taxonomy'=>'cg_topic','parent'=>$root->term_id,'hide_empty'=>false));
    if(is_wp_error($terms)){return array();}
    $order=array_keys(cg_topic_tree()['crypto'][1]);
    usort($terms,function($a,$b)use($order){
        $aa=array_search($a->slug,$order,true);$bb=array_search($b->slug,$order,true);
        $rank=($aa===false?count($order):$aa)<=>($bb===false?count($order):$bb);
        return $rank?:strcmp($a->name,$b->name);
    });
    return $terms;
}
function cg_crypto_topic_intro($term){
    if(!cg_is_crypto_topic($term)){return '';}
    if(trim(wp_strip_all_tags($term->description))!==''){return wp_kses_post(wpautop($term->description));}
    $copy=array(
        'crypto'=>'从基础概念、钱包安全到出入金与理财产品，理解加密资产的使用方式和风险。按主题查找教程与分析，建立适合自己的知识框架。',
        'beginners'=>'认识比特币、以太坊、稳定币与区块链基础概念，了解账户、交易和资产管理的基本流程。从入门知识开始，先理解风险，再决定是否参与。',
        'wallets-security'=>'了解托管钱包、自托管钱包与硬件钱包的区别，学习私钥和助记词保管、账户防护、转账检查及常见诈骗识别。',
        'deposits-withdrawals'=>'梳理法币买卖、平台充值提币、链上转账和网络选择，帮助你理解操作步骤、费用与到账条件。具体方式及地区资格以对应平台规则为准。',
        'yield-products'=>'理解中心化理财、质押和 DeFi 等产品的运作方式，关注收益来源、锁定期限、赎回条件与本金风险。页面不提供保证收益的承诺。',
    );
    return isset($copy[$term->slug])?'<p>'.esc_html($copy[$term->slug]).'</p>':'';
}
function cg_crypto_breadcrumbs($term=null){
    if($term===null){$term=get_queried_object();}
    $items=array(array('name'=>'首页','url'=>home_url('/')));
    if(!cg_is_crypto_topic($term)){return $items;}
    foreach(array_merge(array_reverse(get_ancestors($term->term_id,'cg_topic','taxonomy')),array($term->term_id)) as $id){
        $ancestor=get_term($id,'cg_topic');$url=get_term_link($ancestor);
        if(!is_wp_error($url)){$items[]=array('name'=>$ancestor->name,'url'=>$url);}
    }
    return $items;
}
function cg_crypto_archive_url($term,$page=1){
    $url=get_term_link($term);if(is_wp_error($url)){return '';}
    $page=max(1,absint($page));if($page===1){return $url;}
    if(!get_option('permalink_structure')){return add_query_arg('paged',$page,$url);}
    global $wp_rewrite;
    return trailingslashit($url).user_trailingslashit($wp_rewrite->pagination_base.'/'.$page,'paged');
}
add_action('pre_get_posts',function($query){
    if(is_admin()||!$query->is_main_query()||!$query->is_tax('cg_topic')||!cg_is_crypto_topic($query->get_queried_object())){return;}
    $query->set('post_type',array('post','cg_review','cg_compare','cg_ranking','cg_guide'));
    $query->set('post_status','publish');$query->set('has_password',false);
    $query->set('posts_per_page',9);$query->set('ignore_sticky_posts',true);
    $query->set('orderby',array('date'=>'DESC','ID'=>'DESC'));
});
function cg_crypto_empty_archive(){
    global $wp_query;
    return is_tax('cg_topic')&&cg_is_crypto_topic()&&$wp_query&&!(int)$wp_query->found_posts;
}
add_filter('wp_robots',function($robots){
    if(cg_crypto_empty_archive()){$robots['noindex']=true;unset($robots['index']);}
    return $robots;
});
// Known SEO plugins may replace WordPress' robots output. Preserve their other rules.
foreach(array('wpseo_robots_array','rank_math/frontend/robots') as $hook){
    add_filter($hook,function($robots){
        if(cg_crypto_empty_archive()&&is_array($robots)){$robots['index']='noindex';}
        return $robots;
    });
}
add_filter('document_title_parts',function($parts){
    if(is_tax('cg_topic')&&cg_is_crypto_topic()){$parts['title']=get_queried_object()->name;}
    return $parts;
});
add_action('wp_head',function(){
    if(!is_tax('cg_topic')||!cg_is_crypto_topic()||is_404()||cg_review_external_seo()||!apply_filters('cg_topic_builtin_seo',true,get_queried_object_id())){return;}
    global $wp_query;
    $term=get_queried_object();$page=max(1,(int)get_query_var('paged'));$url=cg_crypto_archive_url($term,$page);
    $description=trim(preg_replace('/\s+/u',' ',wp_strip_all_tags(cg_crypto_topic_intro($term))));
    $title=$term->name.($page>1?' · 第 '.$page.' 页':'');
    echo '<link rel="canonical" href="'.esc_url($url).'">';
    if($description){echo '<meta name="description" content="'.esc_attr($description).'">';}
    foreach(array('og:type'=>'website','og:title'=>$title,'og:description'=>$description,'og:url'=>$url,'og:site_name'=>get_bloginfo('name')) as $key=>$value){
        echo '<meta property="'.esc_attr($key).'" content="'.esc_attr($value).'">';
    }
    $list=array();foreach(cg_crypto_breadcrumbs($term) as $i=>$item){$list[]=array('@type'=>'ListItem','position'=>$i+1,'name'=>$item['name'],'item'=>$item['url']);}
    $articles=array();foreach($wp_query->posts as $i=>$entry){$articles[]=array('@type'=>'ListItem','position'=>($page-1)*9+$i+1,'name'=>get_the_title($entry),'url'=>get_permalink($entry));}
    $collection=array('@type'=>'CollectionPage','@id'=>$url.'#collection','name'=>$title,'description'=>$description,'url'=>$url,'inLanguage'=>get_bloginfo('language'),'mainEntity'=>array('@type'=>'ItemList','itemListElement'=>$articles));
    echo '<script type="application/ld+json">'.wp_json_encode(array('@context'=>'https://schema.org','@graph'=>array($collection,array('@type'=>'BreadcrumbList','@id'=>$url.'#breadcrumb','itemListElement'=>$list))),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE).'</script>';
},20);

// One tag includes every category and pagination URL, including an emptied old category.
function cg_purge_crypto_archives(){do_action('litespeed_purge_tag','cg_crypto_topics');}
function cg_post_has_crypto_topic($id){
    $terms=wp_get_object_terms($id,'cg_topic');
    if(!is_wp_error($terms)){foreach($terms as $term){if(cg_is_crypto_topic($term)){return true;}}}
    return false;
}
add_action('wp',function(){if(is_tax('cg_topic')&&cg_is_crypto_topic()){do_action('litespeed_tag_add','cg_crypto_topics');}});
add_action('save_post',function($id,$post){
    if(!wp_is_post_revision($id)&&!wp_is_post_autosave($id)&&in_array($post->post_type,array('post','cg_review','cg_compare','cg_ranking','cg_guide'),true)&&cg_post_has_crypto_topic($id)){cg_purge_crypto_archives();}
},50,2);
add_action('set_object_terms',function($id,$terms,$tt_ids,$taxonomy,$append,$old_tt_ids){
    if($taxonomy!=='cg_topic'){return;}
    foreach(array_unique(array_merge($tt_ids,$old_tt_ids)) as $tt){
        $term=get_term_by('term_taxonomy_id',(int)$tt,'cg_topic');
        if(cg_is_crypto_topic($term)){cg_purge_crypto_archives();return;}
    }
},10,6);
add_action('deleted_term_relationships',function($id,$tt_ids,$taxonomy){
    if($taxonomy!=='cg_topic'){return;}
    foreach($tt_ids as $tt){if(cg_is_crypto_topic(get_term_by('term_taxonomy_id',(int)$tt,'cg_topic'))){cg_purge_crypto_archives();return;}}
},10,3);
add_action('before_delete_post',function($id){if(cg_post_has_crypto_topic($id)){cg_purge_crypto_archives();}});
add_action('created_cg_topic',function(){cg_purge_crypto_archives();});
add_action('edited_cg_topic',function(){cg_purge_crypto_archives();});
add_action('delete_cg_topic',function(){cg_purge_crypto_archives();});
