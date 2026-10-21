</div>
<div class="plog-controls plog-row"><?php echo plogger_download_selected_button(); ?><?php echo plogger_print_button(); ?></div>
<?php echo plogger_download_selected_form_end(); ?>
<?php if (($GLOBALS['plogger_level'] ?? '') === 'picture' && isset($GLOBALS['current_picture'])) echo plogger_display_comments(); ?>
<footer class="plog-gallery-footer plog-stack">
<nav class="plog-pagination" aria-label="<?php echo plogger_escape(plog_tr('Pages')); ?>"><?php echo plogger_pagination_control(); ?></nav>
<div class="plog-controls plog-row"><?php echo plogger_slideshow_link(); ?><?php echo plogger_rss_feed_button(); ?></div>
<?php if (($GLOBALS['plogger_level'] ?? '') === 'album') : ?>
<details class="plog-controls"><summary><?php echo plog_tr('Sort by'); ?></summary>
<nav class="plog-nav" aria-label="<?php echo plogger_escape(plog_tr('Sort photographs')); ?>">
<?php foreach (['date'=>plog_tr('Date Submitted'),'date_taken'=>plog_tr('Date Taken'),'caption'=>plog_tr('Caption'),'filename'=>plog_tr('Filename'),'number_of_comments'=>plog_tr('Number of Comments')] as $field=>$label) : ?>
<a href="<?php echo generate_url('album', $GLOBALS['plogger_id'], [1=>'sorted','sortby'=>$field,'sortdir'=>'asc']); ?>"><?php echo plogger_escape($label); ?> — <?php echo plog_tr('Ascending'); ?></a>
<a href="<?php echo generate_url('album', $GLOBALS['plogger_id'], [1=>'sorted','sortby'=>$field,'sortdir'=>'desc']); ?>"><?php echo plogger_escape($label); ?> — <?php echo plog_tr('Descending'); ?></a>
<?php endforeach; ?>
</nav></details>
<?php endif; ?>
<p class="plog-muted"><?php echo plogger_link_back(); ?></p>
</footer>
</div>
</section>
