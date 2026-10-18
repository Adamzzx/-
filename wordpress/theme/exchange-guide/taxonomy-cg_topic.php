<?php
if(!defined('ABSPATH')){exit;}
$term=get_queried_object();
if(!function_exists('cg_is_crypto_topic')||!cg_is_crypto_topic($term)){
    get_template_part('index');
    return;
}
global $wp_query;
$root=get_term_by('slug','crypto','cg_topic');
$sections=function_exists('cg_crypto_sections')?cg_crypto_sections():array();
$crumbs=function_exists('cg_crypto_breadcrumbs')?cg_crypto_breadcrumbs($term):array(array('name'=>'首页','url'=>home_url('/')),array('name'=>$term->name,'url'=>get_term_link($term)));
$is_root=$root&&(int)$term->term_id===(int)$root->term_id;
get_header();
?>
<section class="cg-crypto-archive">
    <nav class="cg-breadcrumb" aria-label="面包屑"><?php foreach($crumbs as $index=>$crumb){if($index){echo '<span aria-hidden="true"> / </span>';}if($index===count($crumbs)-1){echo '<span aria-current="page">'.esc_html($crumb['name']).'</span>';}else{echo '<a href="'.esc_url($crumb['url']).'">'.esc_html($crumb['name']).'</a>';}} ?></nav>
    <header class="cg-crypto-intro">
        <h1><?php echo esc_html($term->name); ?></h1>
        <div class="cg-content"><?php if(function_exists('cg_crypto_topic_intro')){echo wp_kses_post(cg_crypto_topic_intro($term));}elseif($term->description){echo wp_kses_post(wpautop($term->description));} ?></div>
    </header>
    <div class="cg-topic-layout">
        <div class="cg-topic-articles">
            <nav class="cg-topic-filters" aria-label="加密货币分类">
                <?php if($root){$root_url=get_term_link($root);if(!is_wp_error($root_url)){ ?><a href="<?php echo esc_url($root_url); ?>"<?php echo $is_root?' aria-current="page"':''; ?>>全部文章</a><?php }} ?>
                <?php foreach($sections as $section){$url=get_term_link($section);if(is_wp_error($url)){continue;} ?><a href="<?php echo esc_url($url); ?>"<?php echo (int)$term->term_id===(int)$section->term_id?' aria-current="page"':''; ?>><?php echo esc_html($section->name); ?></a><?php } ?>
            </nav>
            <h2>文章列表</h2>
            <?php if($wp_query->posts&&function_exists('cg_article_cards_markup')){echo cg_article_cards_markup($wp_query->posts);}else{ ?><p class="cg-empty"><?php echo $is_root?'加密货币内容正在准备中，文章发布后会在这里显示。':'这一分类暂未发布文章，新内容发布后会自动显示在这里。'; ?></p><?php } ?>
            <?php if($wp_query->max_num_pages>1){ ?><nav class="cg-pagination" aria-label="加密货币文章分页"><?php echo wp_kses_post(paginate_links(array('current'=>max(1,absint(get_query_var('paged'))),'total'=>$wp_query->max_num_pages,'prev_text'=>'上一页','next_text'=>'下一页','before_page_number'=>'<span class="screen-reader-text">第 </span>','after_page_number'=>'<span class="screen-reader-text"> 页</span>'))); ?></nav><?php } ?>
        </div>
        <?php get_sidebar(); ?>
    </div>
</section>
<?php get_footer(); ?>
