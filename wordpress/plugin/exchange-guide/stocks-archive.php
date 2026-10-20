<?php
if(!defined('ABSPATH')){exit;}

// Keep the existing page and topic IDs: this listing never moves article content.
function cg_stocks_page(){
    $page=get_page_by_path('tokenized-stocks');
    return $page&&$page->post_status==='publish'&&!$page->post_password?$page:null;
}
function cg_stocks_root($term=null){
    $root=cg_find_topic('tokenized-stocks');
    if($term===null){return $root;}
    return $root&&$term instanceof WP_Term&&$term->taxonomy==='cg_topic'&&(int)$term->term_id===(int)$root->term_id;
}
function cg_is_stocks_directory(){
    if(is_admin()||is_404()){return false;}
    $page=cg_stocks_page();
    return $page?is_page($page->ID):(is_tax('cg_topic')&&cg_stocks_root(get_queried_object()));
}
function cg_stocks_page_number(){
    $raw=$_GET['cg_stocks_page']??1;
    return is_scalar($raw)?(filter_var($raw,FILTER_VALIDATE_INT,array('options'=>array('min_range'=>1,'max_range'=>1000000)))?:1):1;
}
function cg_stocks_page_url($number=1){
    $page=cg_stocks_page();$root=cg_stocks_root();
    $url=$page?get_permalink($page):($root?get_term_link($root):home_url('/tokenized-stocks/'));
    if(is_wp_error($url)){return '';}
    $number=max(1,absint($number));return $number>1?add_query_arg('cg_stocks_page',$number,$url):$url;
}
function cg_stocks_directory_intro(){
    $root=cg_stocks_root();
    if($root&&trim(wp_strip_all_tags($root->description))!==''){return wp_kses_post(wpautop($root->description));}
    return '<p>从美股基础、交易平台到费用、出入金与资产安全，按文章了解不同产品的使用方式。涉及真实股票、股票代币或其他相关产品时，请区分资产权利，并核实所在地区与账户资格。</p>';
}
function cg_stocks_articles($page=1,$limit=9){
    $root=cg_stocks_root();
    $args=array('post_type'=>array('post','cg_review','cg_compare','cg_ranking','cg_guide'),'post_status'=>'publish','has_password'=>false,'ignore_sticky_posts'=>true,'posts_per_page'=>max(1,min(50,absint($limit))),'paged'=>max(1,absint($page)),'orderby'=>array('date'=>'DESC','ID'=>'DESC'));
    if($root){$args['tax_query']=array(array('taxonomy'=>'cg_topic','field'=>'term_id','terms'=>$root->term_id,'include_children'=>true));}
    else{$args['post__in']=array(0);}
    return new WP_Query($args);
}
function cg_stocks_current_articles(){
    static $query;
    if(!$query){$query=cg_stocks_articles(cg_stocks_page_number());}
    return $query;
}
add_action('template_redirect',function(){
    if(!is_tax('cg_topic')||!cg_stocks_root(get_queried_object())||!cg_stocks_page()){return;}
    $number=max(cg_stocks_page_number(),max(1,absint(get_query_var('paged'))));
    wp_safe_redirect(cg_stocks_page_url($number),301,'Exchange Guide');exit;
},8);
add_action('template_redirect',function(){
    if(!cg_is_stocks_directory()){return;}
    $query=cg_stocks_current_articles();
    if(cg_stocks_page_number()>1&&!$query->posts){global $wp_query;$wp_query->set_404();status_header(404);nocache_headers();}
});
add_filter('template_include',function($template){
    if(cg_is_stocks_directory()){$stocks=locate_template('page-tokenized-stocks.php');if($stocks){return $stocks;}}
    return $template;
});
function cg_stocks_empty_directory(){return cg_is_stocks_directory()&&!(int)cg_stocks_current_articles()->found_posts;}
add_filter('wp_robots',function($robots){if(cg_stocks_empty_directory()){$robots['noindex']=true;unset($robots['index']);}return $robots;},99);
foreach(array('rank_math/frontend/robots','wpseo_robots_array') as $hook){
    add_filter($hook,function($robots){if(cg_stocks_empty_directory()&&is_array($robots)){$robots['index']='noindex';}return $robots;},99);
}
foreach(array('get_canonical_url','rank_math/frontend/canonical','wpseo_canonical') as $hook){
    add_filter($hook,function($url){return cg_is_stocks_directory()?cg_stocks_page_url(cg_stocks_page_number()):$url;},25);
}
add_filter('document_title_parts',function($parts){
    if(cg_is_stocks_directory()){$parts['title']='美股';if(cg_stocks_page_number()>1){$parts['page']='第 '.cg_stocks_page_number().' 页';}}
    return $parts;
});
add_filter('rank_math/replacements',function($replacements){
    if(cg_is_stocks_directory()&&is_array($replacements)&&!get_post_meta(get_queried_object_id(),'rank_math_title',true)&&array_key_exists('%title%',$replacements)){$replacements['%title%']='美股';}
    return $replacements;
});
foreach(array('rank_math/frontend/title','wpseo_title') as $hook){
    add_filter($hook,function($title)use($hook){
        if(!cg_is_stocks_directory()){return $title;}
        $meta=$hook==='wpseo_title'?'_yoast_wpseo_title':'rank_math_title';
        if(!get_post_meta(get_queried_object_id(),$meta,true)){$page=cg_stocks_page();if($page){$title=str_replace($page->post_title,'美股',$title);}}
        return cg_stocks_page_number()>1?$title.' - 第 '.cg_stocks_page_number().' 页':$title;
    },25);
}
foreach(array('rank_math/frontend/description','wpseo_metadesc') as $hook){
    add_filter($hook,function($description)use($hook){
        if(!cg_is_stocks_directory()){return $description;}
        $meta=$hook==='wpseo_metadesc'?'_yoast_wpseo_metadesc':'rank_math_description';
        return get_post_meta(get_queried_object_id(),$meta,true)?$description:trim(preg_replace('/\s+/u',' ',wp_strip_all_tags(cg_stocks_directory_intro())));
    },25);
}
function cg_stocks_collection_schema(){
    $number=cg_stocks_page_number();$url=cg_stocks_page_url($number);$items=array();
    foreach(cg_stocks_current_articles()->posts as $i=>$entry){$items[]=array('@type'=>'ListItem','position'=>($number-1)*9+$i+1,'name'=>get_the_title($entry),'url'=>get_permalink($entry));}
    return array('@type'=>'CollectionPage','@id'=>$url.'#collection','url'=>$url,'name'=>'美股'.($number>1?' · 第 '.$number.' 页':''),'description'=>trim(preg_replace('/\s+/u',' ',wp_strip_all_tags(cg_stocks_directory_intro()))),'inLanguage'=>get_bloginfo('language'),'mainEntity'=>array('@type'=>'ItemList','itemListElement'=>$items));
}
add_filter('rank_math/json_ld',function($data){
    if(!cg_is_stocks_directory()||!is_array($data)){return $data;}
    // A saved page schema may still be Article even though the visible page is now a directory.
    // Filter this response only; preserve all saved SEO settings and article-detail graphs.
    foreach($data as $key=>$node){if(is_array($node)&&isset($node['@type'])&&array_intersect((array)$node['@type'],array('Article','BlogPosting','NewsArticle'))){unset($data[$key]);}}
    $collection=cg_stocks_collection_schema();
    foreach($data as $key=>$node){
        if(!is_array($node)||!isset($node['@type'])){continue;}
        if(array_intersect((array)$node['@type'],array('WebPage','CollectionPage'))){$data[$key]['@type']='CollectionPage';$data[$key]['url']=$collection['url'];$data[$key]['mainEntity']=$collection['mainEntity'];return $data;}
    }
    $data['cgStocksCollection']=$collection;return $data;
},99);
add_action('wp',function(){
    if(cg_is_stocks_directory()){do_action('litespeed_tag_add','cg_stocks_directory');if(!cg_review_external_seo()){remove_action('wp_head','rel_canonical');}}
});
add_action('wp_head',function(){
    if(!cg_is_stocks_directory()||cg_review_external_seo()){return;}
    $schema=cg_stocks_collection_schema();$url=$schema['url'];
    echo '<link rel="canonical" href="'.esc_url($url).'"><meta name="description" content="'.esc_attr($schema['description']).'">';
    foreach(array('og:type'=>'website','og:title'=>$schema['name'],'og:description'=>$schema['description'],'og:url'=>$url,'og:site_name'=>get_bloginfo('name')) as $key=>$value){echo '<meta property="'.esc_attr($key).'" content="'.esc_attr($value).'">';}
    $crumbs=array('@type'=>'BreadcrumbList','@id'=>$url.'#breadcrumb','itemListElement'=>array(array('@type'=>'ListItem','position'=>1,'name'=>'首页','item'=>home_url('/')),array('@type'=>'ListItem','position'=>2,'name'=>'美股','item'=>cg_stocks_page_url())));
    echo '<script type="application/ld+json">'.wp_json_encode(array('@context'=>'https://schema.org','@graph'=>array($schema,$crumbs)),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE).'</script>';
},20);
function cg_purge_stocks_directory(){do_action('litespeed_purge_tag','cg_stocks_directory');}
add_action('save_post',function($id,$post){if(!wp_is_post_revision($id)&&!wp_is_post_autosave($id)&&in_array($post->post_type,array('post','cg_review','cg_compare','cg_ranking','cg_guide','page'),true)){cg_purge_stocks_directory();}},55,2);
add_action('before_delete_post',function($id){if(in_array(get_post_type($id),array('post','cg_review','cg_compare','cg_ranking','cg_guide','page'),true)){cg_purge_stocks_directory();}});
foreach(array('added_post_meta','updated_post_meta','deleted_post_meta') as $hook){add_action($hook,function($meta_id,$id,$key){if(in_array($key,array('_thumbnail_id','_cg_sponsored'),true)){cg_purge_stocks_directory();}},10,3);}
add_action('set_object_terms',function($id,$terms,$tt_ids,$taxonomy){if($taxonomy==='cg_topic'){cg_purge_stocks_directory();}},10,4);
add_action('deleted_term_relationships',function($id,$tt_ids,$taxonomy){if($taxonomy==='cg_topic'){cg_purge_stocks_directory();}},10,3);
add_action('edited_cg_topic','cg_purge_stocks_directory');
add_action('delete_cg_topic','cg_purge_stocks_directory');
add_action('profile_update','cg_purge_stocks_directory');

// The old root is a redirect alias; keep the page and child categories in sitemaps.
// Rank Math 1.0.280 Taxonomy::get_sitemap_links passes ($entry, 'term', WP_Term).
add_filter('rank_math/sitemap/entry',function($entry,$type,$object){
    return $type==='term'&&cg_stocks_page()&&cg_stocks_root($object)?false:$entry;
},20,3);
add_filter('wp_sitemaps_taxonomies_query_args',function($args,$taxonomy){
    $root=cg_stocks_root();
    if($taxonomy==='cg_topic'&&cg_stocks_page()&&$root){$args['exclude']=array_values(array_unique(array_merge(wp_parse_id_list($args['exclude']??array()),array($root->term_id))));}
    return $args;
},20,2);
