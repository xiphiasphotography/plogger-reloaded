<?php plogger_get_header(); ?>

		<div id="thumbnail-container">

<?php if (plogger_has_pictures()) : ?>
			<div id="inner-wrapper">

				<div id="big-picture-container">

					<script type="text/javascript">
						<!--
						slides = new slideshow("slides");
						slides.prefetch = 2;
						slides.timeout = 4000;
						// slides.repeat = true;
						slides.redirect = <?php echo plogger_script_json(html_entity_decode(plogger_slideshow_redirect(), ENT_QUOTES, 'UTF-8')); ?>;
<?php while(plogger_has_pictures()) : $pic = plogger_load_picture(); ?>
						// Output a line of javascript for each image
						s = new slide(<?php echo plogger_script_json(html_entity_decode(plogger_get_picture_thumb(THUMB_LARGE), ENT_QUOTES, 'UTF-8')); ?>,
						<?php echo plogger_script_json(html_entity_decode(plogger_get_source_picture_url(), ENT_QUOTES, 'UTF-8')); ?>,
						<?php echo plogger_script_json(plogger_get_picture_caption('code')); ?>,
						"_self","","",<?php echo plogger_script_json(plogger_escape(basename($pic['path']))); ?>);
						slides.add_slide(s);
<?php endwhile; ?>
						// -->
					</script>

				</div><!-- /big-picture-container -->

			</div><!-- /inner-wrapper -->

			<?php echo generate_slideshow_nav(); ?>

			<div id="picture-holder">
				<a href="javascript:slides.hotlink()"><img id="slideshow_image" class="photos-large" src="about:blank" title="<?php echo plogger_get_picture_caption('code'); ?>" alt="<?php echo plogger_get_picture_caption('code'); ?>" /></a>
			</div><!-- /picture-holder -->

			<script type="text/javascript">
				<!--
				if (document.images) {
					slides.set_image(document.images.slideshow_image);
					slides.textid = "picture_caption"; // optional
					slides.imagenameid = "image_name"; // optional
					slides.update();
					slides.play();
				}
				//-->
			</script>

<?php else : ?>
			<div id="no-pictures-msg">
				<h2><?php echo plog_tr('No Images') ?></h2>
				<p><?php echo plog_tr('Sorry, but there are no images in this album to create a slideshow with.') ?></p>
			</div><!-- /no-pictures-msg -->
<?php endif; ?>

		</div><!-- /thumbnail-container -->
<?php plogger_get_footer(); ?>