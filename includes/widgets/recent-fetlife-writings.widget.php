<?php
/**
 * Recent Fetlife Writings widget class
 *
 */
class WP_Widget_Recent_Fetlife_Writings extends WP_Widget {

	public function __construct() {
		$widget_ops = array('classname' => 'widget_recent_fetlife_writings', 'description' => __("Your site&#8217;s most recent Fetlife Writings.", 'fetlife'));
		parent::__construct('recent-fetlife-writings', __('Recent Fetlife Writings'), $widget_ops);
		$this->alt_option_name = 'widget_recent_fetlife_writings';

		add_action('save_post', array($this, 'flush_widget_cache'));
		add_action('deleted_post', array($this, 'flush_widget_cache'));
		add_action('switch_theme', array($this, 'flush_widget_cache'));
	}

	public function widget($args, $instance) {
		$cache = array();
		if (!$this->is_preview()) {
			$cache = wp_cache_get('widget_recent_fetlife_writings', 'widget');
		}

		if (!is_array($cache)) {
			$cache = array();
		}

		if (!isset( $args['widget_id'])) {
			$args['widget_id'] = $this->id;
		}

		if (isset($cache[$args['widget_id']])) {
			echo $cache[$args['widget_id']];
			return;
		}

		ob_start();

		$title = (!empty( $instance['title'])) ? $instance['title'] : __('Recent Feltife Writings', 'fetlife');

		/** This filter is documented in wp-includes/default-widgets.php */
		$title = apply_filters('widget_title', $title, $instance, $this->id_base);

		$number = (!empty($instance['number'])) ? absint($instance['number']) : 5;
		if (!$number)
			$number = 5;
		$show_date = isset($instance['show_date']) ? $instance['show_date'] : false;

		/**
		 * Filter the arguments for the Recent Fetlife Writings widget.
		 *
		 * @see WP_Query::get_posts()
		 *
		 * @param array $args An array of arguments used to retrieve the recent fetlife writings.
		 */

		$all_terms = get_terms('category');
		$fetlife_writings_term_ids = array();
		if (!empty($all_terms)) {
			foreach ($all_terms as $key => $term) {
				if (false !== strpos($term->slug, 'fetlife-writing')) {
					$fetlife_writings_term_ids[] = $term->term_id;
				}
			}
		}

		$r = new WP_Query(apply_filters('widget_fetlife_writings_args', array(
			'posts_per_page'      => $number,
			'no_found_rows'       => true,
			'post_status'         => 'publish',
			'ignore_sticky_posts' => true,
			'category__in' => $fetlife_writings_term_ids,
		)));

		if ($r->have_posts()) :
		?>
			<?php echo $args['before_widget']; ?>
			<?php if ($title) {
				echo $args['before_title'] . $title . $args['after_title'];
			} ?>
			<ul>
			<?php while ($r->have_posts()) : $r->the_post(); ?>
				<?php if(!empty($fetlife_writings_term_ids) && in_category($fetlife_writings_term_ids)): ?>
					<li>
						<a href="<?php the_permalink(); ?>"><?php get_the_title() ? the_title() : the_ID(); ?></a>
					<?php if ( $show_date ) : ?>
						<span class="post-date"><?php echo get_the_date(); ?></span>
					<?php endif; ?>
					</li>
				<?php endif; ?>
			<?php endwhile; ?>
			</ul>
			<?php echo $args['after_widget']; ?>
		<?php
		// Reset the global $the_post as this query will have stomped on it
		wp_reset_postdata();

		endif;

		if (!$this->is_preview()) {
			$cache[ $args['widget_id'] ] = ob_get_flush();
			wp_cache_set( 'widget_recent_posts', $cache, 'widget' );
		} else {
			ob_end_flush();
		}
	}

	public function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['number'] = (int) $new_instance['number'];
		$instance['show_date'] = isset($new_instance['show_date']) ? (bool) $new_instance['show_date'] : false;
		$this->flush_widget_cache();

		$alloptions = wp_cache_get('alloptions', 'options');
		if (isset($alloptions['widget_recent_entries']))
			delete_option('widget_recent_entries');

		return $instance;
	}

	public function flush_widget_cache() {
		wp_cache_delete('widget_recent_posts', 'widget');
	}

	public function form( $instance ) {
		$title     = isset($instance['title']) ? esc_attr($instance['title']) : '';
		$number    = isset($instance['number']) ? absint($instance['number']) : 5;
		$show_date = isset($instance['show_date']) ? (bool) $instance['show_date'] : false;
		?>
			<p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e('Title:'); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" /></p>

			<p><label for="<?php echo $this->get_field_id( 'number' ); ?>"><?php _e('Number of posts to show:'); ?></label>
			<input id="<?php echo $this->get_field_id( 'number' ); ?>" name="<?php echo $this->get_field_name('number'); ?>" type="text" value="<?php echo $number; ?>" size="3" /></p>

			<p><input class="checkbox" type="checkbox" <?php checked($show_date); ?> id="<?php echo $this->get_field_id('show_date'); ?>" name="<?php echo $this->get_field_name('show_date'); ?>" />
			<label for="<?php echo $this->get_field_id( 'show_date' ); ?>"><?php _e('Display post date?'); ?></label></p>
		<?php
	}
}