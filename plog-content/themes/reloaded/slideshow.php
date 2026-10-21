<?php plogger_get_header(); ?>
<h2><?php echo plog_tr('Slideshow'); ?></h2>
<p><?php echo plog_tr('Select a photograph to view it.'); ?></p>
<ol class="plog-slides">
<?php while (plogger_has_pictures()) : plogger_load_picture(); ?>
<li class="plog-slide">
<figure class="plog-picture">
<a href="<?php echo plogger_get_picture_url(); ?>"><img src="<?php echo plogger_get_picture_thumb(THUMB_LARGE); ?>" alt="<?php echo plogger_get_picture_caption('clean'); ?>" loading="lazy" decoding="async" /></a>
<figcaption><?php echo plogger_get_picture_caption('code'); ?></figcaption>
</figure>
</li>
<?php endwhile; ?>
</ol>
<?php plogger_get_footer(); ?>
