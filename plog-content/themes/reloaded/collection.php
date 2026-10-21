<?php plogger_get_header(); ?>
<?php if (plogger_has_albums()) : ?>
<ul class="plog-gallery-grid">
<?php while (plogger_has_albums()) : plogger_load_album(); $info = plogger_get_thumbnail_info(); ?>
<li class="plog-card">
<a href="<?php echo plogger_get_album_url(); ?>"><img src="<?php echo plogger_get_album_thumb(); ?>" alt="<?php echo plogger_get_album_name(true); ?>" width="<?php echo (int) ($info['width'] ?? 150); ?>" height="<?php echo (int) ($info['height'] ?? 150); ?>" loading="lazy" decoding="async" /></a>
<div class="plog-card-body">
<h2><a href="<?php echo plogger_get_album_url(); ?>"><?php echo plogger_get_album_name(true); ?></a></h2>
<div><?php echo plogger_get_album_description(); ?></div>
<?php echo plogger_download_checkbox(plogger_get_album_id(), '<label for="checkbox_'.(int) plogger_get_album_id().'">'.plog_tr('Select').'</label>'); ?>
</div>
</li>
<?php endwhile; ?>
</ul>
<?php else : ?>
<p><?php echo plog_tr('No Images'); ?></p>
<?php endif; ?>
<?php plogger_get_footer(); ?>
