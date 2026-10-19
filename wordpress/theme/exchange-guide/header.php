<?php if (!defined('ABSPATH')) { exit; } ?><!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head><body <?php body_class(); ?>><?php wp_body_open(); ?><a class="cg-skip" href="#main">跳到正文</a><header class="cg-header"><div class="cg-wrap cg-header-inner"><a class="cg-brand dd-brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="DegenDeer 鹿研社首页">
  <img class="dd-brand__mark" src="<?php echo esc_url(get_template_directory_uri().'/assets/degendeer-mark.svg'); ?>" width="52" height="54" alt="" aria-hidden="true">
  <span class="dd-brand__copy">
    <span class="dd-brand__names">
      <span class="dd-brand__en" lang="en">DegenDeer</span>
      <span class="dd-brand__divider" aria-hidden="true"></span>
      <span class="dd-brand__zh">鹿研社</span>
    </span>
    <span class="dd-brand__tagline">加密货币与美股入门、交易所评测与资产安全</span>
  </span>
</a><button class="cg-menu-toggle" aria-expanded="false" aria-controls="cg-primary-nav" hidden>菜单</button><nav id="cg-primary-nav" class="cg-nav" aria-label="主导航"><?php if(has_nav_menu('primary') && wp_get_nav_menu_items(get_nav_menu_locations()['primary'])){wp_nav_menu(array('theme_location'=>'primary','container'=>false,'items_wrap'=>'<ul class="cg-menu">%3$s</ul>','walker'=>new CG_Navigation_Walker()));}else{cg_theme_default_navigation();} ?></nav></div></header><main id="main" class="cg-wrap">
