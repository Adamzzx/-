<?php
if(!defined('ABSPATH')){exit;}
$ready=function_exists('cg_stocks_articles')&&function_exists('cg_is_stocks_directory')&&cg_is_stocks_directory();
$articles=$ready?cg_stocks_current_articles():null;
get_header();
?>
<section id="stocks-articles" class="cg-crypto-archive cg-stocks-archive">
    <nav class="cg-breadcrumb" aria-label="面包屑"><a href="<?php echo esc_url(home_url('/')); ?>">首页</a><span aria-hidden="true"> / </span><span aria-current="page">美股</span></nav>
    <header class="cg-crypto-intro"><h1>美股</h1><div class="cg-content"><?php if(function_exists('cg_stocks_directory_intro')){echo wp_kses_post(cg_stocks_directory_intro());} ?></div></header>
    <div class="cg-topic-layout"><div class="cg-topic-articles">
        <h2>文章列表</h2>
        <?php if(!$ready||!function_exists('cg_article_cards_markup')){ ?><p class="cg-empty">请更新配套内容插件，以启用美股文章列表。</p><?php }elseif($articles->posts){echo cg_article_cards_markup($articles->posts);}else{ ?><p class="cg-empty">美股内容正在准备中，文章发布并归入「美股」主题后会自动显示在这里。</p><?php } ?>
        <?php if($articles&&$articles->max_num_pages>1){ ?><nav class="cg-pagination" aria-label="美股文章分页"><?php echo wp_kses_post(paginate_links(array('base'=>add_query_arg('cg_stocks_page','%#%',cg_stocks_page_url()).'#stocks-articles','format'=>'','current'=>cg_stocks_page_number(),'total'=>$articles->max_num_pages,'prev_text'=>'上一页','next_text'=>'下一页','before_page_number'=>'<span class="screen-reader-text">第 </span>','after_page_number'=>'<span class="screen-reader-text"> 页</span>'))); ?></nav><?php } ?>
    </div><?php get_sidebar(); ?></div>
</section>
<?php get_footer(); ?>
