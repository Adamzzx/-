<?php get_header(); ?>
<section class="cg-hero"><p class="cg-eyebrow">面向中文用户的交易所决策工具</p><h1>找到适合你的交易所，<br>从比较开始。</h1><p class="cg-lead">了解费用条件、地区资格与操作流程，让每一次选择都有依据。</p><a class="cg-button" href="<?php echo esc_url(home_url('/compare/')); ?>">开始比较 ↗</a></section>
<section class="cg-section"><div class="cg-section-head"><h2>六家交易所，一处了解</h2><a href="<?php echo esc_url(home_url('/exchanges/')); ?>">查看全部 →</a></div><p class="cg-empty">按资料核验状态了解平台；未核验信息不构成推荐或安全承诺。</p><?php cg_theme_cards('cg_exchange'); ?></section>
<section class="cg-section"><div class="cg-section-head"><h2>比较分析</h2><a href="<?php echo esc_url(home_url('/compare/')); ?>">探索比较 →</a></div><?php cg_theme_cards('cg_compare', 3); ?></section>
<section class="cg-section"><div class="cg-section-head"><h2>交易所评测</h2><a href="<?php echo esc_url(home_url('/reviews/')); ?>">查看评测 →</a></div><?php cg_theme_cards('cg_review', 3); ?></section>
<section class="cg-section"><div class="cg-section-head"><h2>实用指南</h2><a href="<?php echo esc_url(home_url('/guides/')); ?>">查看指南 →</a></div><?php cg_theme_cards('cg_guide', 3); ?></section>
<section class="cg-principles"><h2>比较有依据，选择更清楚</h2><div class="cg-grid"><div><h3>条件写明白</h3><p>费用与优惠注明适用条件，不用单一数字代替完整信息。</p></div><div><h3>资料有来源</h3><p>核验日期和主要来源公开，缺失资料保留待核验状态。</p></div><div><h3>推广有披露</h3><p>推荐入口集中管理，商业合作明确标注。</p></div></div></section>
<?php if (have_posts()) { the_post(); if (trim(get_the_content())) { ?><section class="cg-content cg-section"><?php the_content(); ?></section><?php } } get_footer(); ?>
