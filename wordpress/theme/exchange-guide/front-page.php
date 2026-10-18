<?php
if(!defined('ABSPATH')){exit;}
$raw_page=$_GET['cg_home_page']??1;
$page=function_exists('cg_home_page_number')?cg_home_page_number():(is_scalar($raw_page)?(filter_var($raw_page,FILTER_VALIDATE_INT,array('options'=>array('min_range'=>1,'max_range'=>1000000)))?:1):1);
$articles=function_exists('cg_home_articles')?cg_home_articles($page,8):new WP_Query(array(
    'post_type'=>'post','post_status'=>'publish','has_password'=>false,
    'posts_per_page'=>8,'paged'=>$page,'ignore_sticky_posts'=>true,
    'orderby'=>array('date'=>'DESC','ID'=>'DESC'),
));
get_header();
?>
<section id="home-articles" class="cg-home-feed cg-feed-page">
    <h1 class="screen-reader-text">加密资产文章与指南</h1>
    <div class="cg-feed-mobile-search"><?php get_search_form(); ?></div>
    <div class="cg-home-layout">
        <div class="cg-feed-main">
            <?php if($articles->posts){cg_theme_article_feed($articles->posts);}else{ ?>
                <p class="cg-feed-empty"><?php echo $page>1?'这一页暂时没有文章。':'内容正在准备中，文章发布后会在这里显示。'; ?><?php if($page>1){ ?> <a href="<?php echo esc_url(home_url('/')); ?>">返回首页</a><?php } ?></p>
            <?php } ?>
            <?php if($articles->max_num_pages>1){ ?>
                <nav class="cg-pagination cg-feed-pagination" aria-label="首页文章分页"><?php echo wp_kses_post(paginate_links(array(
                    'base'=>add_query_arg('cg_home_page','%#%',home_url('/')).'#home-articles',
                    'format'=>'','current'=>$page,'total'=>$articles->max_num_pages,
                    'prev_text'=>'上一页','next_text'=>'下一页',
                    'before_page_number'=>'<span class="screen-reader-text">第 </span>',
                    'after_page_number'=>'<span class="screen-reader-text"> 页</span>',
                ))); ?></nav>
            <?php } ?>
        </div>
        <?php get_sidebar('feed'); ?>
    </div>
</section>
<?php get_footer(); ?>
