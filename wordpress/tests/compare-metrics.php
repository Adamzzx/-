<?php
if(home_url()!=='http://localhost:8088'){throw new RuntimeException('Disposable local WordPress only');}
function metrics_check($condition,$label){if(!$condition){throw new RuntimeException($label);}echo "PASS: $label\n";}
$platform=cg_exchange('binance');$compare=get_page_by_path('compare');
if(!$platform||!$compare){throw new RuntimeException('Initialize the disposable local site first');}
$id=$platform->ID;$original_user=get_current_user_id();$old_post=$_POST;$old_get=$_GET;
$had=metadata_exists('post',$id,'_cg_comparison_metrics');$before=get_post_meta($id,'_cg_comparison_metrics',true);
$preserved_keys=array('_cg_fees','_cg_regions','_cg_destination','_cg_enabled','_cg_expiry','_cg_terms','_cg_banner_attachment');
$preserved=array();foreach($preserved_keys as $key){$preserved[$key]=get_post_meta($id,$key);}
$admin=get_user_by('login','smoke-admin');$error_before=get_transient('cg_error_'.$admin->ID);
$users=array();$purges=array();
add_action('litespeed_purge_post',function($purged)use(&$purges){$purges[]=(int)$purged;});
$entry=function($value='Local verified spot terms',$source='https://www.binance.com/en/fee/trading',$date=null,$status='verified'){
    return array('value'=>$value,'source'=>$source,'date'=>$date??wp_date('Y-m-d'),'status'=>$status);
};
$submit=function($rows,$nonce=true,$present=true)use($id){
    $_POST=array('cg_comparison_metrics'=>$rows);
    if($nonce){$_POST['cg_comparison_metrics_nonce']=wp_create_nonce('cg_comparison_metrics_save');}
    if($present){$_POST['cg_comparison_metrics_present']='1';}
    cg_comparison_metrics_save($id);
};
try{
    metrics_check(array_keys(cg_comparison_platforms())===array('binance','okx','bitget','bybit'),'only the four confirmed platforms appear, in the confirmed order');
    wp_set_current_user(get_user_by('login','smoke-admin')->ID);
    $seed=array('spot'=>$entry(),'futures'=>$entry('Local futures terms'),'token'=>$entry('Local token eligibility'));
    update_post_meta($id,'_cg_comparison_metrics',$seed);
    $submit(array('spot'=>$entry('No nonce must not save')),false);
    metrics_check(get_post_meta($id,'_cg_comparison_metrics',true)===$seed,'missing dedicated nonce cannot change comparison data');
    $_POST=array('cg_comparison_metrics_nonce'=>'invalid','cg_comparison_metrics_present'=>'1','cg_comparison_metrics'=>array('spot'=>$entry('Invalid nonce value')));cg_comparison_metrics_save($id);
    metrics_check(get_post_meta($id,'_cg_comparison_metrics',true)===$seed,'invalid dedicated nonce cannot change comparison data');
    $submit(array('spot'=>$entry('No presence must not save')),true,false);
    metrics_check(get_post_meta($id,'_cg_comparison_metrics',true)===$seed,'unrelated save without form presence leaves data unchanged');
    $_POST=array('cg_comparison_metrics_present'=>'1','cg_comparison_metrics_nonce'=>wp_create_nonce('cg_comparison_metrics_save'),'cg_comparison_metrics'=>'malformed');cg_comparison_metrics_save($id);
    metrics_check(get_post_meta($id,'_cg_comparison_metrics',true)===$seed,'malformed top-level rows cannot erase stored data');
    foreach(array('array-row'=>array('spot'=>'malformed'),'array-value'=>array('spot'=>array('value'=>array('malformed'))),'array-source'=>array('spot'=>array('source'=>array('https://www.binance.com')))) as $case=>$input){
        $submit($input);metrics_check(get_post_meta($id,'_cg_comparison_metrics',true)===$seed,$case.' cannot replace existing comparison data');
    }

    foreach(array('editor','subscriber') as $role){
        $user=wp_insert_user(array('user_login'=>'local-metrics-'.$role.'-'.wp_generate_password(5,false),'user_pass'=>wp_generate_password(),'role'=>$role));
        if(is_wp_error($user)){throw new RuntimeException($user->get_error_message());}$users[]=$user;wp_set_current_user($user);
        $submit(array('spot'=>$entry('Unauthorized '.$role.' update')));
        metrics_check(get_post_meta($id,'_cg_comparison_metrics',true)===$seed,$role.' cannot modify administrator-only comparison figures');
    }
    wp_set_current_user(get_user_by('login','smoke-admin')->ID);$purges=array();
    $submit(array('spot'=>$entry("Maker / Taker local test\nEligible tier described"),'futures'=>$entry('Local contract type and fee basis'),'token'=>$entry('Local platform-token discount and threshold')));
    $saved=get_post_meta($id,'_cg_comparison_metrics',true);
    metrics_check($saved['spot']['value']==="Maker / Taker local test\nEligible tier described"&&$saved['spot']['status']==='verified','administrator can save multiline manually verified terms without numeric coercion');
    metrics_check(in_array($compare->ID,$purges,true),'saving metrics invalidates the comparison page cache');
    $html=cg_comparison_metric_cell($platform,'spot');
    metrics_check(str_contains($html,'Maker / Taker local test')&&str_contains($html,'已核验')&&str_contains($html,'https://www.binance.com/en/fee/trading')&&str_contains($html,wp_date('Y-m-d')),'verified cell includes the value, verification label, source and last checked date');

    $invalid=array(
        'missing-source'=>$entry('Never publish missing-source value',''),
        'insecure-source'=>$entry('Never publish insecure-source value','http://www.binance.com/fee'),
        'credential-source'=>$entry('Never publish credential-source value','https://user:password@www.binance.com/fee'),
        'invalid-date'=>$entry('Never publish invalid-date value','https://www.binance.com/fee','2026-02-30'),
        'future-date'=>$entry('Never publish future-date value','https://www.binance.com/fee','2099-01-01'),
        'empty-value'=>$entry(''),
    );
    foreach($invalid as $case=>$row){
        $submit(array('spot'=>$row));$stored=get_post_meta($id,'_cg_comparison_metrics',true);$html=cg_comparison_metric_cell($platform,'spot');
        metrics_check($stored['spot']['status']!=='verified'&&($row['value']===''||!str_contains($html,$row['value'])),$case.' cannot be presented as verified data');
    }
    foreach(array('unverified'=>'待核验','stale'=>'需重新核验') as $status=>$label){
        $submit(array('spot'=>$entry('Private draft figure must stay hidden','https://www.binance.com/fee',null,$status)));
        $html=cg_comparison_metric_cell($platform,'spot');
        metrics_check(str_contains($html,$label)&&!str_contains($html,'Private draft figure must stay hidden'),'nonverified '.$status.' data remains labelled and its draft value stays hidden');
    }
    $submit(array('spot'=>$entry('<script>alert("x")</script><b>Local sanitized note</b>','javascript:alert(1)')));
    $saved=get_post_meta($id,'_cg_comparison_metrics',true);$html=cg_comparison_metric_cell($platform,'spot');
    metrics_check(!str_contains($saved['spot']['value'],'<')&&!str_contains($html,'<script')&&!str_contains($html,'javascript:'),'stored text and rendered URLs do not allow HTML/script injection');
    update_post_meta($id,'_cg_comparison_metrics',array('spot'=>$entry('<img src=x onerror=alert(1)>Local hostile legacy text')));
    $html=cg_comparison_metric_cell($platform,'spot');
    metrics_check(!str_contains($html,'<img')&&!str_contains($html,'<script'),'renderer escapes hostile legacy metadata independently of form sanitization');

    foreach($preserved as $key=>$value){metrics_check(get_post_meta($id,$key)===$value,'comparison editing preserves existing '.$key);}
    $_GET=array();$html=cg_compare_shortcode();
    $doc=new DOMDocument();@$doc->loadHTML('<?xml encoding="UTF-8">'.$html);$xpath=new DOMXPath($doc);
    $options=array();foreach($xpath->query('//select[@name="a"]/option') as $option){$options[]=$option->getAttribute('value');}
    metrics_check($options===array('binance','okx','bitget','bybit'),'comparison form has no MEXC or Gate options');
    $labels=array();foreach($xpath->query('//table/tbody/tr/th') as $th){$labels[]=trim($th->textContent);}
    metrics_check($labels===array('现货手续费','合约手续费','平台币优惠及门槛','进一步了解'),'only requested comparison data rows replace the three obsolete rows');
    foreach(array(array('a'=>'gate','b'=>'mexc'),array('a'=>array('okx'),'b'=>array('bybit')),array('a'=>'<script>','b'=>'unknown')) as $args){
        $_GET=$args;$html=cg_compare_shortcode();$doc=new DOMDocument();@$doc->loadHTML('<?xml encoding="UTF-8">'.$html);$xpath=new DOMXPath($doc);
        $a=$xpath->query('//select[@name="a"]/option[@selected]')->item(0);$b=$xpath->query('//select[@name="b"]/option[@selected]')->item(0);
        metrics_check($a&&$b&&$a->getAttribute('value')==='binance'&&$b->getAttribute('value')==='okx','retired or malformed selector parameters fall back to the confirmed defaults');
    }
    $purges=array();delete_post_meta($id,'_cg_comparison_metrics');
    metrics_check(in_array($compare->ID,$purges,true),'deleting metrics invalidates the comparison page cache');
    metrics_check(!str_contains(cg_comparison_metric_cell($platform,'spot'),'Local verified spot terms'),'missing data never fabricates a default rate');
}finally{
    $_POST=$old_post;$_GET=$old_get;wp_set_current_user(get_user_by('login','smoke-admin')->ID);
    if($had){update_post_meta($id,'_cg_comparison_metrics',$before);}else{delete_post_meta($id,'_cg_comparison_metrics');}
    if($error_before===false){delete_transient('cg_error_'.$admin->ID);}else{set_transient('cg_error_'.$admin->ID,$error_before,120);}
    require_once ABSPATH.'wp-admin/includes/user.php';foreach($users as $user){wp_delete_user($user);}
    wp_set_current_user($original_user);
}
