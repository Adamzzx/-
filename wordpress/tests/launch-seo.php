<?php
// Run through WP CLI only against the disposable local installation.
if(home_url()!=='http://localhost:8088'){throw new RuntimeException('Disposable local test only');}
function launch_seo_check($ok,$label){if(!$ok){throw new RuntimeException($label);}echo 'PASS: '.$label."\n";}
$created=array();$terms_created=array();$old_public=get_option('blog_public');$old_query=$GLOBALS['wp_query'];$old_main=$GLOBALS['wp_the_query'];$old_post=$GLOBALS['post']??null;$old_user=get_current_user_id();
$root=cg_find_topic('crypto');$stocks=cg_stocks_page();$yield=$root?cg_find_topic('yield-products',$root->term_id):null;
if(!$root||!$stocks||!$yield){throw new RuntimeException('Existing permanent sections required');}
$urls=function(){return array_column(cg_launch_sitemap_topics(),'loc');};
$query=function($args){$q=new WP_Query();$GLOBALS['wp_query']=$q;$GLOBALS['wp_the_query']=$q;$q->query($args);return $q;};
$make=function($type,$status,$term_id=0,$password='')use(&$created){$id=wp_insert_post(array('post_type'=>$type,'post_status'=>$status,'post_title'=>'Disposable launch SEO fixture '.wp_generate_password(6,false),'post_content'=>'Original local test content.','post_password'=>$password),true);if(is_wp_error($id)){throw new RuntimeException($id->get_error_message());}$created[]=$id;if($term_id){wp_set_object_terms($id,array((int)$term_id),'cg_topic');}return $id;};
try{
    wp_set_current_user(0);update_option('blog_public',1);
    launch_seo_check(count(cg_launch_formal_topic_ids())===4,'four declared crypto desks are formal while the old Stocks root stays an alias');
    foreach(cg_launch_formal_topic_ids() as $id){launch_seo_check(in_array(get_term_link(get_term($id,'cg_topic')),$urls(),true),'formal topic is mapped independent of article count: '.$id);}
    launch_seo_check(!in_array(get_term_link(cg_stocks_root()),$urls(),true),'301 Stocks taxonomy alias stays out of sitemap');
    $new=wp_insert_term('Disposable future topic','cg_topic',array('slug'=>'launch-seo-future-'.strtolower(wp_generate_password(6,false)),'parent'=>$root->term_id));
    if(is_wp_error($new)){throw new RuntimeException($new->get_error_message());}$terms_created[]=$new['term_id'];$term=get_term($new['term_id'],'cg_topic');$term_url=get_term_link($term);
    launch_seo_check(!in_array($term_url,$urls(),true),'an unrelated empty future category is not globally included');
    $draft=$make('post','draft',$term->term_id);$protected=$make('post','publish',$term->term_id,'local-secret');
    launch_seo_check(!in_array($term_url,$urls(),true),'draft and password-protected articles do not make a new topic public');
    $article=$make('post','publish',$term->term_id);$article_before=get_post($article);$article_url=get_permalink($article);
    launch_seo_check(in_array($term_url,$urls(),true),'future topic joins the dynamic map when its first public article is published');
    $provider=new CG_Launch_Sitemap_Provider('cg_topic');$count=count(cg_launch_sitemap_topics());$parts=$provider->get_index_links(2);$all=array();
    foreach($parts as $i=>$part){$all=array_merge($all,$provider->get_sitemap_links('cg_topic',2,$i+1));}
    launch_seo_check(count($parts)===(int)ceil($count/2)&&count($all)===$count&&count(array_unique(array_column($all,'loc')))===$count,'provider pagination covers dynamic topics once without a fixed URL limit');
    launch_seo_check(!$provider->get_sitemap_links('page',2,1)&&!$provider->get_sitemap_links('cg_topic',2,count($parts)+1),'provider never handles another type or fabricates an out-of-range page');
    update_term_meta($term->term_id,'rank_math_robots',array('noindex'));
    launch_seo_check(!in_array($term_url,$urls(),true),'an explicit noindex on a nonformal future topic remains effective');delete_term_meta($term->term_id,'rank_math_robots');
    wp_update_post(array('ID'=>$article,'post_status'=>'draft'));launch_seo_check(!in_array($term_url,$urls(),true),'unpublishing the only public article removes a nonformal topic');
    wp_update_post(array('ID'=>$article,'post_status'=>'publish'));
    $sections=array_column(cg_launch_sitemap_sections(),'loc');launch_seo_check($sections===array(home_url('/'),cg_exchange_directory_url(),get_permalink($stocks)),'sections provider supplements one homepage, exchange directory and real Stocks page');
    $native=array('loc'=>get_permalink($stocks));launch_seo_check(apply_filters('rank_math/sitemap/entry',$native,'post',$stocks)===false,'Stocks has one sitemap owner rather than duplicate page and sections entries');
    foreach(array('post','page','cg_exchange') as $type){$id=$make($type,'publish');$post=get_post($id);$entry=array('loc'=>get_permalink($post));launch_seo_check(apply_filters('rank_math/sitemap/entry',$entry,'post',$post)===$entry,'normal future '.$type.' stays eligible for the native Rank Math provider');}
    $query(array('cg_topic'=>$yield->slug));
    $robots=apply_filters('rank_math/frontend/robots',array('index'=>'noindex','follow'=>'nofollow','max-snippet'=>-1));
    launch_seo_check($robots['index']==='index'&&$robots['follow']==='follow'&&$robots['max-snippet']===-1,'formal topic corrects empty noindex while preserving unrelated robot directives');
    launch_seo_check(apply_filters('rank_math/frontend/canonical','')===get_term_link($yield),'formal empty topic has its own canonical through the SEO-plugin filter');
    launch_seo_check(apply_filters('rank_math/frontend/description','')!==''&&apply_filters('rank_math/frontend/description','Owner manual description')==='Owner manual description','empty topic reuses visible introduction without overriding manual SEO description');
    update_option('blog_public',0);$robots=apply_filters('rank_math/frontend/robots',array('index'=>'index','follow'=>'follow'));launch_seo_check($robots['index']==='noindex'&&!cg_launch_sitemap_topics()&&!cg_launch_sitemap_sections(),'site-wide privacy remains noindex and empties both supplementary maps');
    update_option('blog_public',1);$GLOBALS['wp_query']->set_404();$robots=apply_filters('rank_math/frontend/robots',array('index'=>'noindex'));launch_seo_check($robots['index']==='noindex'&&apply_filters('rank_math/frontend/canonical','unchanged')==='unchanged','true 404 is not converted into an indexable formal directory');
    $query(array('page_id'=>$stocks->ID));$robots=apply_filters('rank_math/frontend/robots',array('index'=>'noindex'));launch_seo_check($robots['index']==='index'&&apply_filters('rank_math/frontend/canonical','')===get_permalink($stocks),'published Stocks desk has index and self canonical');
    $query(array('post_type'=>'cg_exchange','post__in'=>array(0),'_cg_exchange_directory'=>true));$robots=apply_filters('rank_math/frontend/robots',array('index'=>'noindex'));launch_seo_check($robots['index']==='index','empty permanent exchange directory remains indexable');
    $query(array('p'=>$article));$robots=apply_filters('rank_math/frontend/robots',array('index'=>'noindex','follow'=>'nofollow'));launch_seo_check($robots['index']==='noindex'&&$robots['follow']==='nofollow','ordinary article noindex is never overridden by a directory exception');
    launch_seo_check(get_permalink($article)===$article_url&&get_post_field('post_content',$article)===$article_before->post_content,'sitemap integration preserves article permanent URL and full body');
    $uncategorized=cg_launch_uncategorized_id();if($uncategorized){$category=get_term($uncategorized,'category');launch_seo_check(apply_filters('rank_math/sitemap/entry',array('loc'=>get_term_link($category)),'term',$category)===false&&get_term($uncategorized,'category') instanceof WP_Term,'Uncategorized is excluded without deleting mandatory category data');}
    $marker=new stdClass();$providers=apply_filters('rank_math/sitemap/providers',array('external'=>$marker));launch_seo_check(isset($providers['external'])&&$providers['external']===$marker,'existing external sitemap providers are preserved');
}finally{
    $GLOBALS['wp_query']=$old_query;$GLOBALS['wp_the_query']=$old_main;$GLOBALS['post']=$old_post;wp_set_current_user($old_user);update_option('blog_public',$old_public);
    foreach($created as $id){wp_delete_post($id,true);}foreach($terms_created as $id){wp_delete_term($id,'cg_topic');}
    cg_launch_invalidate_sitemaps();
}
