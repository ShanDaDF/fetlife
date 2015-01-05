<div class="fetlife-schedule">
	<?php if (!$events): ?>
		<p>
			<span class="fetlife-noevent fetlife-empty"><?php print apply_filters('fetlife_noevent_notice', __('No event information available yet.', 'fetlife')); ?></span>
		</p>
		<p>
			<?php print apply_filters('fetlife_noevent_link', '<a class="fetlife-event-link" href="' . site_url() . '"><span class="fetlife-event-button">' . __('Back to homepage', 'fetlife') . '</span></a>'); ?>			
		</p>
	<?php else: ?>
		<?php foreach ($events as $index => $event) : ?>
			<?php
				$classes  = 'fetlife-event';
				$classes .= (count($events) == 1) ? ' single-fetlife-event' : '';
				$classes .= ($event['ongoing']) ? ' fetlife-event-ongoing' : '';
				$classes .= ($index == $index_next_event) ? ' fetlife-event-next' : '';
			?>
			<div class="<?php print $classes ?>">
				<?php if ($event['ongoing']): ?> 
					<span class="fetlife-event-notice"><?php print __('ongoing', 'fetlife'); ?></span>
				<?php endif ?>
				<?php if ($index == $index_next_event): ?> 
					<span class="fetlife-event-notice"><?php print __('coming next', 'fetlife'); ?></span>
				<?php endif ?>
				<h4 class="fetlife-event-title"><?php print $event['title'] ?></h4> 
				<?php if(isset($event['tagline']) && !empty($event['tagline'])): ?>
					<h5 class="fetlife-event-tagline"><?php print $event['tagline'] ?></h5>
				<?php endif ?>
				<span class="fetlife-event-date"><?php print $event['date'] ?></span>
				<?php if(isset($event['cost']) && !empty($event['cost'])): ?>
					<br/><span class="fetlife-event-cost"><label><?php print __('Cost: ', 'fetlife'); ?></label><?php print $event['cost'] ?></span>
				<?php endif ?>
				<?php if(isset($event['dresscode']) && !empty($event['dresscode'])): ?>
					<br/><span class="fetlife-event-dresscode"><label><?php print __('Dress code: ', 'fetlife'); ?></label><?php print $event['dresscode'] ?></span>
				<?php endif ?>
				<?php if($event['going'] + $event['maybegoing'] != 0): ?>
					<br/><span class="fetlife-event-participants"><label><?php print __('Active fetlife users registered: ', 'fetlife'); ?></label><?php print $event['going'] + $event['maybegoing'] ?></span>
				<?php endif ?>
				<?php if(isset($event['description']) && !empty($event['description'])): ?>
				<!-- <p class="fetlife-event-description"><?php print $event['description'] ?></p> -->
				<?php endif ?>
				<p>
					<a class="fetlife-event-link" href="<?php print $event['permalink'] ?>" target="_blank"><span class="fetlife-event-button"><?php print __('See on fetlife', 'fetlife')?></span></a>			
				</p>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</div>

