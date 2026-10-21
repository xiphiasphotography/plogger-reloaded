<?php plogger_get_header(); ?>
<?php if (plogger_has_pictures()) : ?>
<ul class="plog-gallery-grid">
<?php while (plogger_has_pictures()) : plogger_load_picture(); $info = plogger_get_thumbnail_info(); ?>
<li class="plog-card">
<a href="<?php echo plogger_get_picture_url(); ?>"><img src="<?php echo plogger_get_picture_thumb(); ?>" alt="<?php echo plogger_get_picture_caption('clean'); ?>" width="<?php echo (int) ($info['width'] ?? 150); ?>" height="<?php echo (int) ($info['height'] ?? 150); ?>" loading="lazy" decoding="async" /></a>
<div class="plog-card-body">
<h2><a href="<?php echo plogger_get_picture_url(); ?>"><?php echo plogger_get_picture_caption('code'); ?></a></h2>

<?php echo plogger_download_checkbox(plogger_get_picture_id(), '<label for="checkbox_'.(int) plogger_get_picture_id().'">'.plog_tr('Select').'</label>'); ?>
</div>
</li>
<?php endwhile; ?>
</ul>
<?php else : ?>
<p><?php echo plog_tr('No Images'); ?></p>
<?php endif; ?>
<?php plogger_get_footer(); ?>
