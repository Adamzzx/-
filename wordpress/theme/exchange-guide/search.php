<?php
if(!defined('ABSPATH')){exit;}
global $wp_query;
$page=max(1,absint(get_query_var('paged')));
$keyword=get_search_query(false);
get_header();
?>
<section id="search-articles" class="cg-search-results cg-feed-page">
    <header class="cg-feed-search-heading"><h1>搜索结果</h1><p><?php echo $keyword!==''?'关键词：'.esc_html($keyword):'输入关键词查找文章。'; ?><?php if($keyword!==''){echo ' · '.esc_html(number_format_i18n($wp_query->found_posts)).' 篇文章';} ?></p></header>
    <div class="cg-feed-mobile-search"><?php get_search_form(); ?></div>
    <div class="cg-home-layout">
        <div class="cg-feed-main">
            <?php if($wp_query->posts){cg_theme_article_feed($wp_query->posts);}else{ ?>
                <div class="cg-feed-empty"><h2>暂未找到相关文章</h2><p>可以缩短关键词，或试试「手续费」「钱包」「入金」等主题。</p><a href="<?php echo esc_url(home_url('/')); ?>">返回首页浏览文章 →</a></div>
            <?php } ?>
            <?php if($wp_query->max_num_pages>1){ ?>
                <nav class="cg-pagination cg-feed-pagination" aria-label="搜索结果分页"><?php echo wp_kses_post(paginate_links(array(
                    'current'=>$page,'total'=>$wp_query->max_num_pages,'prev_text'=>'上一页','next_text'=>'下一页',
                    'before_page_number'=>'<span class="screen-reader-text">第 </span>',
                    'after_page_number'=>'<span class="screen-reader-text"> 页</span>',
                    'add_fragment'=>'#search-articles',
                ))); ?></nav>
            <?php } ?>
        </div>
        <?php get_sidebar('feed'); ?>
    </div>
</section>
<?php get_footer(); ?>
