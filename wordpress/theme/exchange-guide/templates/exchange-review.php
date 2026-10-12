<?php
/* Template Name: 交易所评测
Template Post Type: post, cg_review */
get_header();while(have_posts()){the_post();$id=get_the_ID();$toc=array();$used=array();
$content=apply_filters('the_content',get_the_content());$content=preg_replace('/<(\/?)h1(\b[^>]*)>/i','<$1h2$2>',$content);
$content=preg_replace_callback('/<h([23])\b([^>]*)>(.*?)<\/h\1>/is',function($m)use(&$toc,&$used){
    $text=wp_strip_all_tags($m[3]);$anchor='section-'.(count($toc)+1);if(preg_match('/\bid=["\']([^"\']+)["\']/i',$m[2],$found)){$anchor=$found[1];}
    $base=$anchor;$n=2;while(isset($used[$anchor])){$anchor=$base.'-'.$n++;}$used[$anchor]=true;
    $attrs=preg_replace('/\s+id=["\'][^"\']*["\']/i','',$m[2]);$toc[]=array('level'=>(int)$m[1],'id'=>$anchor,'text'=>$text);
    return '<h'.$m[1].$attrs.' id="'.esc_attr($anchor).'">'.$m[3].'</h'.$m[1].'>';
},$content);
?>
<article class="cg-review cg-page"><nav class="cg-breadcrumb" aria-label="面包屑"><?php $crumbs=cg_review_breadcrumbs($id);foreach($crumbs as $i=>$crumb){if($i){echo '<span aria-hidden="true"> / </span>';}if($i===count($crumbs)-1){echo '<span aria-current="page">'.esc_html($crumb['name']).'</span>';}else{echo '<a href="'.esc_url($crumb['url']).'">'.esc_html($crumb['name']).'</a>';}} ?></nav>
<header class="cg-review-header"><p class="cg-eyebrow">交易所评测</p><h1><?php the_title(); ?></h1><?php if(has_excerpt()){ ?><p class="cg-lead"><?php echo esc_html(get_the_excerpt()); ?></p><?php } ?><p class="cg-meta">作者：<a href="<?php echo esc_url(get_author_posts_url(get_the_author_meta('ID'))); ?>"><?php the_author(); ?></a> · 发布：<?php echo esc_html(get_the_date('Y-m-d')); ?> · 更新：<?php echo esc_html(get_the_modified_date('Y-m-d')); ?> · 最近核验：<?php $checked=get_post_meta($id,'_cg_reviewed',true);echo esc_html(cg_review_date_valid($checked)?$checked:'尚未核验'); ?></p>
<p class="cg-notice"><?php echo get_post_meta($id,'_cg_sponsored',true)?'赞助披露：本文涉及商业合作。':'本文未标记为赞助内容。'; ?> 推荐入口仅在合作、产品及地区条件确认后展示。加密资产可能损失全部本金。</p>
<?php if(has_post_thumbnail()){ ?><figure class="cg-review-cover"><?php the_post_thumbnail('large',array('loading'=>'eager','fetchpriority'=>'high'));$caption=get_the_post_thumbnail_caption();if($caption){echo '<figcaption>'.esc_html($caption).'</figcaption>';} ?></figure><?php } ?></header>
<div class="cg-review-layout"><div class="cg-review-body"><div class="cg-content"><?php echo $content; ?></div>
<?php $sources=get_post_meta($id,'_cg_review_sources',true);if($sources){ ?><section class="cg-review-sources"><h2>编辑补充来源</h2><p><?php echo make_clickable(nl2br(esc_html($sources))); ?></p></section><?php } ?>
<?php $policy=(array)get_post_meta($id,'_cg_review_policy',true);if(!empty($policy['enabled'])){ ?><section class="cg-review-eligibility"><h2>推荐入口资格确认</h2><p>部分入口可能产生佣金。地区选择不证明账户资格；实际 KYC 和产品资格以官方条款为准。</p><form class="cg-review-eligibility-form" data-post="<?php echo absint($id); ?>" data-endpoint="<?php echo esc_url(rest_url('cg/v1/review-eligibility')); ?>"><label>所在地 ISO 两位代码 <input name="country" pattern="[A-Za-z]{2}" maxlength="2" required placeholder="如 SG"></label><label><input type="checkbox" name="confirmed" required> 我已阅读 <a href="<?php echo esc_url($policy['terms']??'',array('https')); ?>" target="_blank" rel="noopener noreferrer">平台条款</a>，确认所在地与拟使用产品信息属实。</label><p class="cg-meta">适用产品：<?php echo esc_html($policy['product']??'尚待核验'); ?> · 条款核验：<?php echo esc_html($policy['verified']??'尚待核验'); ?></p><button type="submit" class="cg-button">检查入口条件</button><p class="cg-review-eligibility-result" aria-live="polite"></p></form></section><?php } ?>
<?php cg_review_related_cards($id); ?></div><aside class="cg-review-aside"><?php if($toc){ ?><details class="cg-toc" open><summary>文章目录</summary><ol><?php foreach($toc as $heading){echo '<li class="cg-toc-level-'.$heading['level'].'"><a href="#'.esc_attr($heading['id']).'">'.esc_html($heading['text']).'</a></li>';} ?></ol></details><?php } ?><p class="cg-meta">表格来源和日期对应具体资料；文章更新时间不等于所有数据均已重新核验。</p></aside></div></article>
<?php } get_footer(); ?>
