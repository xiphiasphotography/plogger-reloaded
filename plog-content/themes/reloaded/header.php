<section class="plog-ui plog-gallery" aria-label="<?php echo plogger_escape(plog_tr('Photograph gallery')); ?>">
<a class="plog-skip" href="#plog-main"><?php echo plog_tr('Skip to photographs'); ?></a>
<div class="plog-shell">
<header class="plog-gallery-header plog-row plog-between">
<h1><?php echo plogger_escape(stripslashes($GLOBALS['config']['gallery_name'])); ?></h1>
<div class="plog-controls"><label for="searchterms"><?php echo plog_tr('Search'); ?></label><?php echo generate_search_box(); ?></div>
</header>
<nav class="plog-nav" aria-label="<?php echo plogger_escape(plog_tr('Breadcrumbs')); ?>"><?php echo generate_breadcrumb(); ?></nav>
<?php echo plogger_download_selected_form_start(); ?>
<div id="plog-main" tabindex="-1">
