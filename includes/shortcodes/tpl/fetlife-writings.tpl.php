<div class="fetlife-writings-section">
	<?php if (!$writings): ?>
		<p>
			<span class="fetlife-nowriting fetlife-empty"><?php print apply_filters('fetlife_nowriting_notice', __('Content not available.', 'fetlife')); ?></span>
		</p>
		<p>
			<?php print apply_filters('fetlife_nowriting_link', '<a class="fetlife-writing-link" href="' . site_url() . '"><span class="fetlife-writing-button">' . __('Back to homepage', 'fetlife') . '</span></a>'); ?>			
		</p>
	<?php else: ?>
		<?php foreach ($writings as $index => $writing) : ?>
			<?php
				$classes  = 'fetlife-writing';
				$classes .= (count($writings) == 1) ? ' single-fetlife-writing' : ''
			?>
			<div class="<?php print $classes ?>">
				<h1 class="fetlife-writing-title"><?php print $writing['title'] ?><br/><span class="fetlife-writing-meta"><?php print $writing['category'] ?> - <?php print $writing['date'] ?> - <?php print $writing['author'] ?></span></h1> 
				
				<?php if(!empty($writing['content'])): ?>
				<div class="fetlife-writing-content"><?php print $writing['content'] ?></div>
				<?php endif ?>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</div>

