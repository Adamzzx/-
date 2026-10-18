<?php if(!defined('ABSPATH')){exit;} ?>
<form role="search" method="get" class="cg-search-form" action="<?php echo esc_url(home_url('/')); ?>">
    <label><span class="cg-search-label">搜索文章</span><input type="search" name="s" value="<?php echo esc_attr(get_search_query(false)); ?>" placeholder="输入关键词，如手续费、钱包" maxlength="200"></label>
    <button type="submit">搜索</button>
</form>
