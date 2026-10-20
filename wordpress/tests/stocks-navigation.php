<?php
if(home_url()!=='http://localhost:8088'){throw new RuntimeException('Disposable local test only');}
function stocks_check($ok,$label){if(!$ok){throw new RuntimeException($label);}echo 'PASS: '.$label."\n";}
$created=array();$menu=0;$root=cg_find_topic('tokenized-stocks');$crypto=cg_find_topic('crypto');
if(!$root||!$crypto){throw new RuntimeException('Existing local topic setup required');}
$old_root=$root;$option_keys=array('cg_navigation_menu_id','cg_navigation_item_ids','cg_navigation_layout_version','cg_brand','cg_tagline');$old_options=array();
foreach($option_keys as $key){$old_options[$key]=array('exists'=>get_option($key,null)!==null,'value'=>get_option($key));}
$old_locations=get_theme_mod('nav_menu_locations');$page=cg_stocks_page();$page_before=$page?get_post($page):null;
$create=function($type,$term,$status='publish',$password='')use(&$created){$args=array('post_type'=>$type,'post_status'=>$status,'post_title'=>'Local stocks query '.$type.' '.$status,'post_content'=>'Unchanged local test body','post_password'=>$password);if($status==='future'){$args['post_date']=$args['post_date_gmt']='2031-01-01 00:00:00';}$id=wp_insert_post($args,true);if(is_wp_error($id)){throw new RuntimeException($id->get_error_message());}$created[]=$id;wp_set_object_terms($id,array((int)$term),'cg_topic');return $id;};
try{
    wp_set_current_user(get_user_by('login','smoke-admin')->ID);
    $tree=cg_topic_tree();stocks_check($tree['tokenized-stocks']===array('美股',array()),'stocks definition is a direct article desk without second-level links');
    stocks_check(array_keys($tree['crypto'][1])===array('beginners','wallets-security','yield-products'),'crypto definition keeps three confirmed children');
    $legacy_children=array();foreach(array('trading'=>'交易指南','funding'=>'出入金指南') as $slug=>$name){$term=cg_find_topic($slug,$root->term_id);if(!$term){$made=wp_insert_term($name,'cg_topic',array('slug'=>$slug,'parent'=>$root->term_id));$term=get_term($made['term_id'],'cg_topic');} $legacy_children[$slug]=$term;}
    $retired=cg_find_topic('deposits-withdrawals',$crypto->term_id);$retired_id=$retired?$retired->term_id:0;
    $menu=wp_create_nav_menu('Local managed navigation upgrade '.wp_generate_password(5,false));$ids=array();
    $add=function($key,$title,$parent=0,$owned=true)use($menu,&$ids){$id=wp_update_nav_menu_item($menu,0,array('menu-item-title'=>$title,'menu-item-url'=>home_url('/'),'menu-item-type'=>'custom','menu-item-status'=>'publish','menu-item-parent-id'=>$parent));if($owned){update_post_meta($id,'_cg_owned_navigation',$menu);update_post_meta($id,'_cg_nav_key',$key);$ids[]=$id;}return $id;};
    $exchange=$add('group-交易所','交易所');foreach(array('Binance','OKX','Bitget','Bybit','交易所对比') as $label){$add('child-交易所-'.$label,$label,$exchange);}
    $stocks=$add('group-美股代币','美股代币');$trading=$add('child-美股代币-交易指南','交易指南',$stocks);$funding=$add('child-美股代币-出入金指南','出入金指南',$stocks);
    $crypto_item=$add('group-加密货币','加密货币');foreach(array('新手入门','钱包与资产安全','出入金','加密货币理财') as $label){$add('child-加密货币-'.$label,$label,$crypto_item);}
    $owner=$add('','Owner untouched link',$exchange,false);$owner_descendant=$add('','Owner nested link',$trading,false);
    update_option('cg_navigation_menu_id',$menu);update_option('cg_navigation_item_ids',$ids);update_option('cg_brand','Owner Brand retained');update_option('cg_tagline','Owner tagline retained');set_theme_mod('nav_menu_locations',array('primary'=>999999));
    wp_update_term($root->term_id,'cg_topic',array('name'=>'美股代币'));
    $before_exchange=get_post($exchange);$root_url=get_term_link($root);$before_relation=get_post_meta($owner,'_menu_item_menu_item_parent',true);
    $result=cg_upgrade_navigation_layout();stocks_check(!is_wp_error($result),'managed menu update succeeds');
    stocks_check(get_post($stocks)&&get_the_title($stocks)==='美股'&&get_post_meta($stocks,'_cg_nav_key',true)==='group-美股','stocks parent is renamed in place with stable ID');
    stocks_check(get_post_meta($stocks,'_menu_item_url',true)===cg_stocks_page_url(),'stocks parent links directly to existing article-list address');
    stocks_check(!get_post($trading)&&!get_post($funding)&&count(wp_get_nav_menu_items($menu))===13,'only three retired generated entries removed');
    stocks_check(get_post($owner)&&get_post_meta($owner,'_menu_item_menu_item_parent',true)===$before_relation,'owner link and surviving parent relationship retained');
    stocks_check(get_post($owner_descendant)&&(int)get_post_meta($owner_descendant,'_menu_item_menu_item_parent',true)===$stocks,'owner descendant is preserved under nearest surviving parent');
    stocks_check(get_post($exchange)->post_title===$before_exchange->post_title&&get_post($exchange)->post_content===$before_exchange->post_content,'exchange navigation parent unchanged');
    stocks_check(get_theme_mod('nav_menu_locations')===array('primary'=>999999)&&get_option('cg_brand')==='Owner Brand retained'&&get_option('cg_tagline')==='Owner tagline retained','automatic update preserves owner menu binding and brand settings');
    $after=wp_get_nav_menu_items($menu);cg_upgrade_navigation_layout();stocks_check(count(wp_get_nav_menu_items($menu))===count($after),'automatic update is idempotent');
    $new_root=cg_find_topic('tokenized-stocks');stocks_check($new_root->term_id===$old_root->term_id&&$new_root->name==='美股'&&$new_root->slug===$old_root->slug&&$new_root->description===$old_root->description,'default topic renamed without replacing ID slug or description');
    wp_update_term($root->term_id,'cg_topic',array('name'=>'Owner custom topic'));cg_upgrade_navigation_layout();stocks_check(cg_find_topic('tokenized-stocks')->name==='Owner custom topic','custom root topic name preserved');
    stocks_check(cg_find_topic('trading',$root->term_id)->term_id===$legacy_children['trading']->term_id&&cg_find_topic('funding',$root->term_id)->term_id===$legacy_children['funding']->term_id,'retired stocks child taxonomy data retained');
    stocks_check(!$retired_id||(int)cg_find_topic('deposits-withdrawals',$crypto->term_id)->term_id===$retired_id,'removed crypto menu category is not deleted');
    stocks_check(!in_array('deposits-withdrawals',array_map(fn($t)=>$t->slug,cg_crypto_sections()),true),'retired crypto entry hidden from archive filters');
    $visible=array();foreach(array('post','cg_review','cg_compare','cg_ranking','cg_guide') as $type){$visible[]=$create($type,$root->term_id);}
    $descendant=$create('post',$legacy_children['trading']->term_id);wp_set_object_terms($descendant,array($root->term_id,$legacy_children['trading']->term_id),'cg_topic');$visible[]=$descendant;
    $hidden=array($create('post',$crypto->term_id),$create('page',$root->term_id),$create('cg_exchange',$root->term_id));
    foreach(array('draft','pending','future','private','trash') as $status){$hidden[]=$create('post',$root->term_id,$status);} $hidden[]=$create('post',$root->term_id,'publish','protected');
    foreach(array(0,get_user_by('login','smoke-admin')->ID) as $user){wp_set_current_user($user);$q=cg_stocks_articles(1,50);$returned=array_map(fn($p)=>(int)$p->ID,$q->posts);stocks_check(count(array_intersect($visible,$returned))===count($visible)&&!array_intersect($hidden,$returned),'public editorial stocks selection excludes hidden unrelated and platform records for user '.$user);stocks_check(count(array_keys($returned,$descendant,true))===1,'parent plus descendant association renders once for user '.$user);}
    wp_set_current_user(get_user_by('login','smoke-admin')->ID);$one=cg_stocks_articles(1,2);$two=cg_stocks_articles(2,2);stocks_check($one->max_num_pages>1&&count($one->posts)===2&&!array_intersect(wp_list_pluck($one->posts,'ID'),wp_list_pluck($two->posts,'ID')),'stocks pagination returns distinct articles');
    if($page_before){$after_page=get_post($page_before->ID);stocks_check($after_page->post_content===$page_before->post_content&&$after_page->post_name===$page_before->post_name&&get_permalink($after_page)===get_permalink($page_before),'existing stocks page body ID slug and URL retained');}
    stocks_check(cg_stocks_page_url(2)===add_query_arg('cg_stocks_page',2,cg_stocks_page_url()),'page two has a distinct canonical address');
    if($page_before){
        $old_query=$GLOBALS['wp_query'];$GLOBALS['wp_query']=new WP_Query(array('page_id'=>$page_before->ID));
        $seo_existed=metadata_exists('post',$page_before->ID,'rank_math_title');$seo_before=get_post_meta($page_before->ID,'rank_math_title',true);
        try{
            $graph=apply_filters('rank_math/json_ld',array('WebPage'=>array('@type'=>'WebPage','@id'=>get_permalink($page_before).'#webpage'),'article'=>array('@type'=>'Article','headline'=>'Saved old page schema'),'blog'=>array('@type'=>'BlogPosting','headline'=>'Old page blog'),'news'=>array('@type'=>'NewsArticle','headline'=>'Old page news')));
            $types=array();foreach($graph as $node){if(is_array($node)&&isset($node['@type'])){$types=array_merge($types,(array)$node['@type']);}}
            stocks_check(in_array('CollectionPage',$types,true)&&!array_intersect($types,array('Article','BlogPosting','NewsArticle')),'stocks graph replaces WebPage and excludes legacy Article schemas without editing saved SEO');
            update_post_meta($page_before->ID,'rank_math_title','Owner custom title');stocks_check(apply_filters('rank_math/frontend/title','Owner custom title')==='Owner custom title','explicit owner Rank Math title remains unchanged');
            $entry=array('loc'=>get_term_link($root));stocks_check(apply_filters('rank_math/sitemap/entry',$entry,'term',$root)===false&&apply_filters('rank_math/sitemap/entry',$entry,'term',$legacy_children['trading'])===$entry,'Rank Math sitemap excludes only the redirected root term');
            $args=apply_filters('wp_sitemaps_taxonomies_query_args',array('exclude'=>array(123456)),'cg_topic');stocks_check(in_array($root->term_id,$args['exclude'],true)&&in_array(123456,$args['exclude'],true),'native sitemap exclusion preserves owner rules and removes root alias only');
        }finally{if($seo_existed){update_post_meta($page_before->ID,'rank_math_title',$seo_before);}else{delete_post_meta($page_before->ID,'rank_math_title');}$GLOBALS['wp_query']=$old_query;}
    }
    $_GET['cg_stocks_page']=array(2);stocks_check(cg_stocks_page_number()===1,'malformed pagination cannot become an arbitrary query');unset($_GET['cg_stocks_page']);
}finally{
    wp_set_current_user(get_user_by('login','smoke-admin')->ID);foreach($created as $id){wp_delete_post($id,true);}if($menu){wp_delete_nav_menu($menu);}wp_update_term($old_root->term_id,'cg_topic',array('name'=>$old_root->name));
    foreach($old_options as $key=>$state){if($state['exists']){update_option($key,$state['value']);}else{delete_option($key);}}
    if($old_locations===false){remove_theme_mod('nav_menu_locations');}else{set_theme_mod('nav_menu_locations',$old_locations);}unset($_GET['cg_stocks_page']);
}
