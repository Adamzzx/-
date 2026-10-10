<?php get_header(); ?>
<section class="cg-media-intro"><p class="cg-eyebrow">CRYPTO INVESTING & DIGITAL FINANCE</p><h1>理解加密资产，<br>做出更有依据的投资决策</h1><p class="cg-lead">提供交易所评测、加密资产研究、美股代币与投资实践指南，帮助读者理解费用、产品、风险和操作流程。</p></section>
<section class="cg-core-topics" aria-label="核心内容入口">
<a class="cg-core-topic" href="<?php echo esc_url(home_url('/reviews/')); ?>"><span>01 / EXCHANGES</span><h2>交易所评测</h2><p>从费用、产品、地区资格和使用体验了解平台。</p><strong>查看评测 →</strong></a>
<a class="cg-core-topic" href="#tokenized-assets"><span>02 / TOKENIZED ASSETS</span><h2>美股代币</h2><p>理解代币化资产、交易流程与资产权利的差异。</p><strong>探索专题 ↓</strong></a>
<a class="cg-core-topic" href="#crypto-investing"><span>03 / CRYPTO INVESTING</span><h2>加密投资</h2><p>从主流资产、钱包安全到配置与风险管理。</p><strong>阅读指南 ↓</strong></a>
</section>
<?php
$editorial=array('post','cg_review','cg_compare','cg_ranking','cg_guide');
$featured=get_option('sticky_posts',array());
if($featured){cg_media_section('编辑精选',array('post_type'=>'post','post__in'=>$featured,'posts_per_page'=>4),'精选阅读');}
?>
<section class="cg-section cg-exchange-desk"><div class="cg-section-head"><div><p class="cg-eyebrow">PLATFORMS & COMPARISONS</p><h2>交易所比较与评测</h2></div><a href="<?php echo esc_url(home_url('/compare/')); ?>">交易所对比 →</a></div><p class="cg-meta">评估平台时，请同时核实费用条件、产品可用性与所在地资格。</p><div class="cg-platform-links"><?php foreach(array('binance'=>'Binance','okx'=>'OKX','bitget'=>'Bitget','bybit'=>'Bybit') as $slug=>$label){$exchange=function_exists('cg_exchange')?cg_exchange($slug):null;if($exchange){?><a href="<?php echo esc_url(get_permalink($exchange)); ?>"><?php echo esc_html($label); ?> <span>平台资料 ↗</span></a><?php }} ?></div><?php cg_media_section('深入了解平台',array('post_type'=>array('cg_review','cg_compare','cg_ranking'),'posts_per_page'=>3),'平台研究',false); ?></section>
<section id="tokenized-assets" class="cg-section cg-topic-desk"><div class="cg-topic-heading"><p class="cg-eyebrow">TOKENIZED STOCKS & RWA</p><h2>美股代币与代币化资产</h2><p>理解美股代币与真实股票的区别，关注股东权利、发行结构、地区限制及交易风险。</p></div><?php cg_media_topic('tokenized-stocks'); ?></section>
<section id="crypto-investing" class="cg-section cg-topic-desk"><div class="cg-topic-heading"><p class="cg-eyebrow">ASSETS & ALLOCATION</p><h2>加密货币与资产配置</h2><p>认识比特币、以太坊和稳定币，理解资产配置、理财产品及风险管理。加密资产投资并不适合所有人。</p></div><?php cg_media_topic('crypto'); ?></section>
<?php cg_media_section('新手入门与钱包安全',array('post_type'=>$editorial,'posts_per_page'=>3,'tax_query'=>array(array('taxonomy'=>'cg_topic','field'=>'slug','terms'=>array('beginners','wallets-security')))),'投资基础'); ?>
<?php cg_media_section('最新发布',array('post_type'=>$editorial,'posts_per_page'=>6),'最新内容'); ?>
<?php if(function_exists('cg_registration_cards')&&cg_exchange('binance')){ ?><section class="cg-section cg-home-registration"><div class="cg-section-head"><h2>交易所注册入口</h2></div><p class="cg-meta">推荐披露：通过这些入口注册，我们可能获得佣金。活动和资格以平台官方条款为准。</p><?php echo cg_registration_cards(); ?></section><?php } ?>
<section class="cg-editorial-note"><h2>让信息有依据，让风险看得见</h2><p>关注资料来源、核验日期和适用条件。商业推荐明确披露，缺少依据的数据保留待核验状态。</p><a href="<?php echo esc_url(home_url('/methodology/')); ?>">了解我们的比较方法 →</a></section>
<?php if(have_posts()){the_post();if(trim(get_the_content())){ ?><section class="cg-content cg-section"><?php the_content(); ?></section><?php }} get_footer(); ?>
