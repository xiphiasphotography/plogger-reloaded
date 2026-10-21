<?php plogger_get_header(); ?>
<?php if (plogger_has_pictures()) : while (plogger_has_pictures()) : plogger_load_picture(); ?>
<article class="plog-stack">
<h2><?php echo plogger_get_picture_caption('code'); ?></h2>
<nav class="plog-nav plog-between" aria-label="<?php echo plogger_escape(plog_tr('Photographs')); ?>">
<?php echo plogger_get_prev_picture_link(); ?><?php echo plogger_get_next_picture_link(); ?>
</nav>
<figure class="plog-picture">
<a href="<?php echo plogger_get_source_picture_url(); ?>"><img src="<?php echo plogger_get_picture_thumb(THUMB_LARGE); ?>" alt="<?php echo plogger_get_picture_caption('clean'); ?>" decoding="async" /></a>
<figcaption><?php echo plogger_get_picture_description(); ?></figcaption>
</figure>
<?php echo plogger_get_picture_actions(); ?>
<?php if (showEXIF()) : ?>
<details class="plog-panel"><summary><?php echo plog_tr('Photograph details'); ?></summary>
<?php echo generate_exif_table(plogger_get_picture_id()); ?>
</details>
<?php endif; ?>
</article>
<?php endwhile; else : ?>
<p><?php echo plog_tr('Sorry, but the image that you requested does not exist.'); ?></p>
<?php endif; ?>
<?php plogger_get_footer(); ?>
