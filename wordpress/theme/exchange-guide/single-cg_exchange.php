<?php get_header(); while(have_posts()){the_post();$id=get_the_ID(); ?>
<article class="cg-page cg-narrow cg-exchange-intro"><a href="<?php echo esc_url(home_url('/exchanges/')); ?>">交易所专题 /</a><h1><?php the_title(); ?></h1>
<div class="cg-content"><?php
if(trim(get_the_content())){the_content();}
elseif(has_excerpt()){echo '<p>'.esc_html(get_the_excerpt()).'</p>';}
else{
    $name=get_the_title();if(get_post_field('post_name',$id)==='binance'){$name.='（币安）';}
    echo '<p>'.esc_html($name).' 是加密资产交易平台。本专题用于汇总平台评测、交易所对比、费用说明、注册与出入金教程及资产安全指南，方便你按主题查找相关文章。</p>';
}
?></div></article>
<?php cg_theme_exchange_articles($id); } get_footer(); ?>
