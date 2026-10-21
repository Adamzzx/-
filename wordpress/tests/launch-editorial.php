<?php
// wp eval-file; all fixtures are removed. Existing articles/settings are left intact.
if (home_url() !== 'http://localhost:8088') { throw new RuntimeException('Disposable local test only'); }
function le_check($condition,$message){if(!$condition){throw new RuntimeException($message);}echo "PASS $message\n";}
$created=array();$old_user=get_current_user_id();$admin=get_users(array('role'=>'administrator','number'=>1));wp_set_current_user($admin[0]->ID);
$log=get_option('cg_launch_editorial_log',null);
try{
    foreach(array('binance-review-2026','binance-vs-okx-2026','okx-review-2026','crypto-wallet-security-guide-2026') as $slug){le_check(!get_page_by_path($slug,OBJECT,array('post','cg_review','cg_compare','cg_guide')),'fixture slug is unused: '.$slug);}
    $binance=wp_insert_post(array('post_type'=>'post','post_status'=>'publish','post_name'=>'binance-review-2026','post_title'=>'币安 Binance 使用评测','post_content'=>'<p>Original review unchanged.</p>'));$created[]=$binance;
    $compare=wp_insert_post(array('post_type'=>'post','post_status'=>'publish','post_name'=>'binance-vs-okx-2026','post_title'=>'币安 Binance vs 欧易 OKX','post_content'=>'<p>Original comparison.</p>'));$created[]=$compare;
    $paths=array_keys(cg_launch_legacy_article_paths());$old=home_url($paths[0]);$relative=implode('',array_map(function($part){return rawurlencode($part);},explode('/',trim($paths[1],'/'))));$relative='/'.$relative.'/';
    $content='<!-- wp:paragraph --><p><a href="'.esc_url($old).'">Keep link text</a> <a href="'.esc_attr($relative).'">Second link</a> <a href="https://example.org'.$paths[0].'">External unchanged</a></p><!-- /wp:paragraph -->';
    $article=wp_insert_post(wp_slash(array('post_type'=>'post','post_status'=>'publish','post_name'=>'okx-review-2026','post_title'=>'欧易 OKX 使用评测','post_content'=>$content)));$created[]=$article;update_post_meta($article,'rank_math_title','Owner custom SEO');
    $original=get_post($article);$before=cg_launch_editorial_plan();le_check($before[$article]['links']===2,'dry run finds both internal aliases without changing article');le_check(get_post_field('post_content',$article)===$content,'dry run is read only');
    le_check(cg_launch_internal_legacy_target($old)===get_permalink($binance),'known 404 alias has exact equivalent target');le_check(cg_launch_internal_legacy_target('https://example.org'.$paths[0])==='','external URL is untouched');le_check(cg_launch_internal_legacy_target('/unrelated-missing/')==='','unknown path is not redirected');
    $image_body='';$media_ids=array();$i=1;
    foreach(cg_launch_wallet_alt_definitions() as $name=>$definition){
        $source=__DIR__.'/launch-wallet/'.$name;le_check(is_readable($source),'audited image fixture exists');
        $upload=wp_upload_bits($name,null,file_get_contents($source));le_check(!$upload['error'],'image fixture uploaded');
        $media=wp_insert_attachment(array('post_mime_type'=>'image/webp','post_status'=>'inherit','post_title'=>'Fixture wallet '.$i),$upload['file']);$created[]=$media;$media_ids[]=$media;
        // Store the audited basename: production matching must not rely on public IDs.
        $src=wp_get_attachment_url($media);$image_body.='<!-- wp:image {"id":'.$media.',"alt":""} --><figure class="wp-block-image"><img class="wp-image-'.$media.'" src="'.esc_url($src).'" alt="" /></figure><!-- /wp:image -->';$i++;
    }
    $wallet=wp_insert_post(wp_slash(array('post_type'=>'post','post_status'=>'publish','post_name'=>'crypto-wallet-security-guide-2026','post_title'=>'钱包与资产安全','post_content'=>$image_body)));$created[]=$wallet;
    $result=cg_launch_repair_editorial();le_check(!is_wp_error($result)&&$result['links']===2,'only intended links repaired');le_check($result['image_alts']===6&&$result['media_alts']===6,'six audited image and media alts repaired');
    $saved=get_post($article);le_check($saved->post_author===$original->post_author&&$saved->post_date===$original->post_date&&$saved->post_name===$original->post_name&&$saved->post_status===$original->post_status,'author publication date slug and status preserved');le_check(get_post_meta($article,'rank_math_title',true)==='Owner custom SEO','manual article SEO preserved');le_check(str_contains($saved->post_content,'Keep link text')&&str_contains($saved->post_content,'https://example.org'),'text and external source preserved');
    foreach($media_ids as $id){le_check(get_post_meta($id,'_wp_attachment_image_alt',true)!=='','media library alt is available');}
    $again=cg_launch_repair_editorial();le_check(!$again['changed_posts']&&$again['media_alts']===0,'repair is idempotent');
    wp_set_current_user(0);le_check(is_wp_error(cg_launch_repair_editorial()),'anonymous repair rejected');
}finally{
    wp_set_current_user($admin[0]->ID);foreach(array_reverse($created) as $id){if(get_post_type($id)==='attachment'){wp_delete_attachment($id,true);}else{wp_delete_post($id,true);}}
    if($log===null){delete_option('cg_launch_editorial_log');}else{update_option('cg_launch_editorial_log',$log,false);}wp_set_current_user($old_user);
}
