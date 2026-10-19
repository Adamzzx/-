<?php
if(!defined('ABSPATH')){exit;}
global $wp_query;
$is_directory=function_exists('cg_is_exchange_directory')&&cg_is_exchange_directory();
$platforms=array('binance'=>'Binance','okx'=>'OKX','bitget'=>'Bitget','bybit'=>'Bybit');
get_header();
?>
<section class="cg-crypto-archive cg-exchange-archive">
    <nav class="cg-breadcrumb" aria-label="面包屑"><a href="<?php echo esc_url(home_url('/')); ?>">首页</a><span aria-hidden="true"> / </span><span aria-current="page">交易所</span></nav>
    <header class="cg-crypto-intro">
        <h1>交易所</h1>
        <div class="cg-content"><p><?php echo esc_html(function_exists('cg_exchange_directory_intro')?cg_exchange_directory_intro():'汇总交易所评测、平台对比与使用教程，帮助你了解产品、费用、出入金和资产安全。按平台查找相关文章，并结合所在地资格与平台条款判断适用性。'); ?></p></div>
    </header>
    <div class="cg-topic-layout">
        <div class="cg-topic-articles">
            <nav class="cg-topic-filters" aria-label="交易所文章分类">
                <a href="<?php echo esc_url(home_url('/exchanges/')); ?>" aria-current="page">全部文章</a>
                <?php foreach($platforms as $slug=>$name){ ?><a href="<?php echo esc_url(home_url('/exchanges/'.$slug.'/')); ?>"><?php echo esc_html($name); ?></a><?php } ?>
                <a href="<?php echo esc_url(home_url('/compare/')); ?>">交易所对比</a>
            </nav>
            <h2>文章列表</h2>
            <?php if(!$is_directory||!function_exists('cg_article_cards_markup')){ ?>
                <p class="cg-empty">请更新配套内容插件，以启用交易所文章列表。你仍可通过上方入口查看各平台专题和交易所对比。</p>
            <?php }elseif($wp_query->posts){echo cg_article_cards_markup($wp_query->posts);}else{ ?>
                <p class="cg-empty">交易所相关文章正在准备中，文章发布并关联平台后会自动显示在这里。</p>
            <?php } ?>
            <?php if($is_directory&&$wp_query->max_num_pages>1){ ?><nav class="cg-pagination" aria-label="交易所文章分页"><?php echo wp_kses_post(paginate_links(array('current'=>max(1,absint(get_query_var('paged'))),'total'=>$wp_query->max_num_pages,'prev_text'=>'上一页','next_text'=>'下一页','before_page_number'=>'<span class="screen-reader-text">第 </span>','after_page_number'=>'<span class="screen-reader-text"> 页</span>'))); ?></nav><?php } ?>
        </div>
        <?php get_sidebar(); ?>
    </div>
</section>
<?php get_footer(); ?>
