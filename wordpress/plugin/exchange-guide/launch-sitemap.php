<?php
if(!defined('ABSPATH')){exit;}

// The navigation tree declares permanent desks; their first article is not a prerequisite
// for indexing. Other topics still need real public content and their own SEO eligibility.
function cg_launch_formal_topic_ids(){
    $ids=array();
    foreach(cg_topic_tree() as $slug=>$definition){
        $root=cg_find_topic($slug);if(!$root){continue;}
        if(!($slug==='tokenized-stocks'&&cg_stocks_page())){$ids[]=(int)$root->term_id;}
        foreach($definition[1] as $child_slug=>$label){$child=cg_find_topic($child_slug,$root->term_id);if($child){$ids[]=(int)$child->term_id;}}
    }
    return array_values(array_unique($ids));
}
function cg_launch_is_formal_directory(){
    if(is_admin()||is_404()||is_preview()||is_feed()){return false;}
    if(cg_is_stocks_directory()||cg_is_exchange_directory()){return true;}
    $term=get_queried_object();
    return is_tax('cg_topic')&&$term instanceof WP_Term&&in_array((int)$term->term_id,cg_launch_formal_topic_ids(),true);
}
function cg_launch_directory_canonical(){
    if(!cg_launch_is_formal_directory()){return '';}
    if(cg_is_stocks_directory()){return cg_stocks_page_url(cg_stocks_page_number());}
    if(cg_is_exchange_directory()){return cg_exchange_directory_url(max(1,absint(get_query_var('paged'))));}
    return cg_crypto_archive_url(get_queried_object(),max(1,absint(get_query_var('paged'))));
}
add_filter('wp_robots',function($robots){
    if(is_404()){unset($robots['index']);$robots['noindex']=true;return $robots;}
    if(cg_launch_is_formal_directory()){
        if((int)get_option('blog_public')===1){
            unset($robots['noindex'],$robots['nofollow'],$robots['none']);$robots['index']=true;$robots['follow']=true;
        }else{unset($robots['index']);$robots['noindex']=true;}
    }
    return $robots;
},100);
foreach(array('rank_math/frontend/robots','wpseo_robots_array') as $hook){
    add_filter($hook,function($robots){
        if(is_array($robots)&&is_404()){$robots['index']='noindex';return $robots;}
        if(is_array($robots)&&cg_launch_is_formal_directory()){
            if((int)get_option('blog_public')===1){
                unset($robots['noindex'],$robots['nofollow'],$robots['none']);$robots['index']='index';$robots['follow']='follow';
            }else{$robots['index']='noindex';}
        }
        return $robots;
    },100);
}
foreach(array('rank_math/frontend/canonical','wpseo_canonical') as $hook){
    add_filter($hook,function($url){$canonical=cg_launch_directory_canonical();return $canonical?:$url;},100);
}
foreach(array('rank_math/frontend/description','wpseo_metadesc') as $hook){
    add_filter($hook,function($description){
        // Rank Math has no description for a new empty term. Reuse its visible
        // editorial introduction, and always preserve a manual SEO description.
        if(trim((string)$description)===''&&cg_launch_is_formal_directory()&&is_tax('cg_topic')){
            return trim(preg_replace('/\s+/u',' ',wp_strip_all_tags(cg_crypto_topic_intro(get_queried_object()))));
        }
        return $description;
    },100);
}

function cg_launch_uncategorized_id(){
    // Do not exclude an owner's renamed default category or remove its required data.
    $term=get_term_by('slug','uncategorized','category');return $term?(int)$term->term_id:0;
}
function cg_launch_sitemap_home_alias_id(){
    $id=absint(get_option('cg_launch_home_alias_id'));$post=$id?get_post($id):null;
    // Match only the generated internal object whose extra URL cleanup redirects.
    // A stale option must not remove an owner's page or the real posts directory;
    // adding real content to a non-front internal page also ends its alias status.
    if(!$post||$post->post_type!=='page'||$post->post_name!=='home'||$post->post_status!=='publish'||get_post_meta($id,'_cg_created',true)!=='1'||(int)get_option('page_for_posts')===$id){return 0;}
    return (int)get_option('page_on_front')===$id||(function_exists('cg_launch_cleanup_body')&&cg_launch_cleanup_body($post->post_content)==='')?$id:0;
}
function cg_launch_public_lastmod($term_id=0){
    $args=array('post_type'=>array('post','cg_review','cg_compare','cg_ranking','cg_guide'),'post_status'=>'publish','has_password'=>false,'numberposts'=>1,'orderby'=>'modified','order'=>'DESC');
    if($term_id){$args['tax_query']=array(array('taxonomy'=>'cg_topic','field'=>'term_id','terms'=>(int)$term_id,'include_children'=>true));}
    $posts=get_posts($args);return $posts?$posts[0]->post_modified_gmt:'';
}
function cg_launch_sitemap_topics(){
    if((int)get_option('blog_public')!==1){return array();}
    $terms=get_terms(array('taxonomy'=>'cg_topic','hide_empty'=>false,'orderby'=>'term_id','order'=>'ASC'));
    if(is_wp_error($terms)){return array();}
    $formal=cg_launch_formal_topic_ids();$entries=array();
    foreach($terms as $term){
        if(cg_stocks_page()&&cg_stocks_root($term)){continue;}
        $is_formal=in_array((int)$term->term_id,$formal,true);
        if(!$is_formal){
            if(!cg_topic_has_content($term->term_id)){continue;}
            if(class_exists('RankMath\\Helper')&&!RankMath\Helper::is_term_indexable($term)){continue;}
            $canonical=get_term_meta($term->term_id,'rank_math_canonical_url',true);
            if(in_array('noindex',(array)get_term_meta($term->term_id,'rank_math_robots',true),true)){continue;}
            if(class_exists('RankMath\\Helper')&&in_array((int)$term->term_id,wp_parse_id_list(RankMath\Helper::get_settings('sitemap.exclude_terms')),true)){continue;}
        }
        $url=get_term_link($term);if(is_wp_error($url)){continue;}
        if(!$is_formal&&!empty($canonical)&&$canonical!==$url){continue;}
        $entry=array('loc'=>$url);$modified=cg_launch_public_lastmod($term->term_id);if($modified&&$modified!=='0000-00-00 00:00:00'){$entry['mod']=$modified;}
        // Honor other extensions' term exclusions without introducing a second XML writer.
        $entry=apply_filters('rank_math/sitemap/entry',$entry,'term',$term);if($entry){$entries[]=$entry;}
    }
    return $entries;
}
function cg_launch_sitemap_sections(){
    if((int)get_option('blog_public')!==1){return array();}
    $home=array('loc'=>home_url('/'));$modified=cg_launch_public_lastmod();if($modified&&$modified!=='0000-00-00 00:00:00'){$home['mod']=$modified;}
    $entries=array($home);$exchange=get_post_type_object('cg_exchange');
    if($exchange&&is_post_type_viewable($exchange)&&$exchange->has_archive){
        $entry=array('loc'=>cg_exchange_directory_url());$args=cg_exchange_directory_query_args(1,1);$args['orderby']=array('modified'=>'DESC','ID'=>'DESC');$query=new WP_Query($args);
        if($query->posts){$entry['mod']=$query->posts[0]->post_modified_gmt;}$entries[]=$entry;
    }
    $stocks=cg_stocks_page();
    if($stocks){$modified=max($stocks->post_modified_gmt,cg_launch_public_lastmod(cg_stocks_root()?cg_stocks_root()->term_id:0));$entries[]=array('loc'=>get_permalink($stocks),'mod'=>$modified);}
    return $entries;
}

// Rank Math remains the only XML generator/router/cache. Its native post/page/exchange
// providers keep all normal future publications; these two providers add the real desks.
class CG_Launch_Sitemap_Provider {
    private $type;
    public function __construct($type){$this->type=$type;}
    public function handles_type($type){return $type===$this->type;}
    private function entries(){return $this->type==='cg_topic'?cg_launch_sitemap_topics():cg_launch_sitemap_sections();}
    public function get_index_links($max_entries){
        $entries=$this->entries();if(!$entries){return array();}
        $limit=max(1,absint($max_entries));$pages=(int)ceil(count($entries)/$limit);$links=array();
        for($page=1;$page<=$pages;$page++){
            $file=$this->type.'-sitemap'.($pages>1?$page:'').'.xml';
            $loc=class_exists('RankMath\\Sitemap\\Router')?RankMath\Sitemap\Router::get_base_url($file):home_url('/'.$file);
            $item=array('loc'=>$loc);$dates=array_filter(array_column(array_slice($entries,($page-1)*$limit,$limit),'mod'));
            if($dates){$item['lastmod']=max($dates);}
            $links[]=$item;
        }
        return $links;
    }
    public function get_sitemap_links($type,$max_entries,$current_page){
        if(!$this->handles_type($type)){return array();}
        $limit=max(1,absint($max_entries));return array_slice($this->entries(),(max(1,absint($current_page))-1)*$limit,$limit);
    }
}
add_filter('rank_math/sitemap/providers',function($providers){
    if(!is_array($providers)){return $providers;}
    foreach(array('cg_topic','cg_sections') as $type){
        $exists=false;foreach($providers as $provider){if($provider instanceof CG_Launch_Sitemap_Provider&&$provider->handles_type($type)){$exists=true;break;}}
        if(!$exists){$providers[]=new CG_Launch_Sitemap_Provider($type);}
    }
    return $providers;
});
add_filter('rank_math/sitemap/exclude_taxonomy',function($exclude,$taxonomy){
    if($exclude||$taxonomy==='cg_topic'){return true;}
    $uncategorized=cg_launch_uncategorized_id();
    if($taxonomy==='category'&&$uncategorized){
        $terms=get_terms(array('taxonomy'=>'category','hide_empty'=>true,'exclude'=>array($uncategorized)));
        if(!is_wp_error($terms)){
            foreach($terms as $term){if(!class_exists('RankMath\\Helper')||RankMath\Helper::is_term_indexable($term)){return false;}}
            return true;
        }
    }
    return $exclude;
},30,2);
add_filter('rank_math/sitemap/entry',function($entry,$type,$object){
    if($type==='term'&&$object instanceof WP_Term&&$object->taxonomy==='category'&&(int)$object->term_id===cg_launch_uncategorized_id()){return false;}
    if($type==='post'&&is_object($object)&&isset($object->ID)){
        $stocks=cg_stocks_page();if($stocks&&(int)$object->ID===(int)$stocks->ID){return false;}
        if(cg_launch_sitemap_home_alias_id()===(int)$object->ID){return false;}
        if((int)get_option('page_on_front')===(int)$object->ID&&get_option('show_on_front')==='page'){return false;}
    }
    return $entry;
},30,3);
add_filter('rank_math/sitemap/post_type_archive_link',function($url,$type){return $type==='cg_exchange'?false:$url;},30,2);
add_filter('rank_math/sitemap/url',function($xml,$url){
    // Rank Math inserts the nonstatic homepage into both post and page sitemaps before
    // its entry filter. Keep its single copy in the sections provider instead.
    if(isset($url['loc'])&&untrailingslashit($url['loc'])===untrailingslashit(home_url('/'))&&get_query_var('sitemap')!=='cg_sections'){return '';}
    return $xml;
},30,2);

// Native WP sitemap fallback: the same term selection, without globally enabling empties.
add_filter('wp_sitemaps_posts_query_args',function($args,$post_type){
    $alias=cg_launch_sitemap_home_alias_id();
    // Native WP uses a static front-page object's permalink for its only root entry.
    // It does not need to exclude that object: only its extra /home/ address redirects.
    $is_front=$alias&&get_option('show_on_front')==='page'&&(int)get_option('page_on_front')===$alias;
    if($post_type==='page'&&$alias&&!$is_front){$args['post__not_in']=array_values(array_unique(array_merge(wp_parse_id_list($args['post__not_in']??array()),array($alias))));}
    return $args;
},30,2);
add_filter('wp_sitemaps_taxonomies_query_args',function($args,$taxonomy){
    if($taxonomy==='cg_topic'){
        $ids=array();$urls=array_column(cg_launch_sitemap_topics(),'loc');
        $terms=get_terms(array('taxonomy'=>'cg_topic','hide_empty'=>false));
        if(!is_wp_error($terms)){foreach($terms as $term){$url=get_term_link($term);if(!is_wp_error($url)&&in_array($url,$urls,true)){$ids[]=(int)$term->term_id;}}}
        if(!empty($args['include'])){$ids=array_values(array_intersect($ids,wp_parse_id_list($args['include'])));}
        $args['include']=$ids?:array(0);$args['hide_empty']=false;
    }
    if($taxonomy==='category'&&cg_launch_uncategorized_id()){$args['exclude']=array_values(array_unique(array_merge(wp_parse_id_list($args['exclude']??array()),array(cg_launch_uncategorized_id()))));}
    return $args;
},30,2);

function cg_launch_invalidate_sitemaps(){
    if(class_exists('RankMath\\Sitemap\\Cache_Watcher')){
        RankMath\Sitemap\Cache_Watcher::invalidate('cg_topic');RankMath\Sitemap\Cache_Watcher::invalidate('cg_sections');
    }
}
// Rank Math instantiates its cache watcher only on admin/cron requests. REST and
// command-line publication must also flush the same queued cache types at shutdown.
add_action('shutdown',function(){
    if(class_exists('RankMath\\Sitemap\\Cache_Watcher')){RankMath\Sitemap\Cache_Watcher::clear_queued();}
},99);
function cg_launch_invalidate_native_post_sitemap($id){
    if(class_exists('RankMath\\Sitemap\\Cache_Watcher')&&!wp_is_post_revision($id)){RankMath\Sitemap\Cache_Watcher::invalidate_post($id);}
}
add_action('save_post',function($id,$post){
    if(!wp_is_post_revision($id)&&!wp_is_post_autosave($id)&&in_array($post->post_type,array('post','page','cg_exchange','cg_review','cg_compare','cg_ranking','cg_guide'),true)){cg_launch_invalidate_sitemaps();cg_launch_invalidate_native_post_sitemap($id);}
},90,2);
add_action('deleted_post',function($id,$post){
    cg_launch_invalidate_sitemaps();
    if($post instanceof WP_Post&&class_exists('RankMath\\Sitemap\\Cache_Watcher')){RankMath\Sitemap\Cache_Watcher::invalidate($post->post_type);}
},30,2);
foreach(array('created_cg_topic','edited_cg_topic','delete_cg_topic','update_option_blog_public') as $hook){add_action($hook,'cg_launch_invalidate_sitemaps');}
add_action('set_object_terms',function($id,$terms,$tt_ids,$taxonomy){if($taxonomy==='cg_topic'){cg_launch_invalidate_sitemaps();}},30,4);
add_action('deleted_term_relationships',function($id,$tt_ids,$taxonomy){if($taxonomy==='cg_topic'){cg_launch_invalidate_sitemaps();}},30,3);
foreach(array('added_post_meta','updated_post_meta','deleted_post_meta') as $hook){
    add_action($hook,function($meta_id,$id,$key){if(in_array($key,array('_cg_related_exchanges','_cg_comparison','rank_math_robots','rank_math_canonical_url'),true)){cg_launch_invalidate_sitemaps();cg_launch_invalidate_native_post_sitemap($id);}},30,3);
}
foreach(array('added_term_meta','updated_term_meta','deleted_term_meta') as $hook){
    add_action($hook,function($meta_id,$id,$key){if(in_array($key,array('rank_math_robots','rank_math_canonical_url'),true)){cg_launch_invalidate_sitemaps();}},30,3);
}
