<?php
/*
Plugin Name: Felife
Plugin URI: http://beijingkink.com/
Text Domain: fetlife
Description: Fetlife for Wordpress
Version: 1.0
Author: ShanDa DF
Author URI: http://beijingkink.com/
*/

define('FETLIFE_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('FETLIFE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FL_SESSIONS_DIR', WP_CONTENT_DIR);
if (!defined('FETLIFE_MAX_POPULATE')) {
	define('FETLIFE_MAX_POPULATE', 15);
}
if (!defined('FETLIFE_MAX_PAGE')) {
	define('FETLIFE_MAX_PAGE', intval(FETLIFE_MAX_POPULATE / 2));
}

class WP_Fetlife {

	private $fetlifeUser;
	public $isLoggedIn;
	
	/** ============================= MAGIC METHODS ============================= **/

	public function __construct() {

		$require_dirs = array('widgets', 'lib');
		foreach ($require_dirs as $key => $dir_name) {
			$dir = new DirectoryIterator(FETLIFE_PLUGIN_PATH . 'includes/' . $dir_name);
			foreach ($dir as $fileinfo) {
				if (!$fileinfo->isDot() && strpos($fileinfo->getPathname(),'.php')) {
					require_once $fileinfo->getPathname();
				}
			}
		}

		register_activation_hook(FETLIFE_PLUGIN_PATH . 'fetlife.php', array($this, 'fetlifeCron'));

		add_filter('option_fetlife_settings',  array($this, 'get_option'));

		add_action('wp_enqueue_scripts', array($this, 'enqueueStyleFrontend'));
		add_action('admin_enqueue_scripts', array($this, 'enqueueStyleAdmin'));
		add_action('init', array($this, 'enqueueScriptAdmin'));
		add_action('init', array($this, 'fetlifeWritingPostCategory'));
		add_action('admin_bar_menu', array($this, 'refreshMenu'), 999);
		add_action('wp_ajax_refresh_fetlife', array($this, 'refreshFetlifeHandler'));
		add_action('wp_ajax_no_priv_refresh_fetlife', array($this, 'refreshFetlifeRequireLogin'));
		add_action('admin_menu', array($this, 'settingsMenu'));
		add_action('admin_init', array($this, 'registerAndBuildFields'));
		add_action('widgets_init', array($this, 'fetlife_load_widgets'));
		
		$this->addCronAndRefreshFilter(array($this, 'fetchNextEventsByOrganiser'));
		$this->addCronAndRefreshFilter(array($this, 'fetchWritingsByUser'));
		$this->addCronAndRefreshFilter(array($this,'fetlifeTest'));

		add_shortcode('fetlife_events', array($this, 'eventsShortcode'));
		add_shortcode('fetlife_writings', array($this, 'writingsShortcode'));
	}

	public function __call($method, $arguments) {
		if (!$connected = $this->fetlifeConnect()) {
			print_r('failed :(<br/>');
			trigger_error(
	            'Fetlife Connection failed - cannot directly use FetLife library in ' . $trace[0]['file'] .
	            ' on line ' . $trace[0]['line'],
	            E_USER_NOTICE);
			delete_transient("feftlife_refreshing");
			return null;
		}
		print_r('success :)<br/>');
		if (method_exists($this->fetlifeUser, $method)) {
			// error_log("calling " . $method . "\n", 3, '/home/danfroal/public_html/dev/error_log');
			$return = call_user_func_array(array($this->fetlifeUser, $method), $arguments);
			// error_log("called " . $method . "\n", 3, '/home/danfroal/public_html/dev/error_log');
			return $return;
		}

		$trace = debug_backtrace();
		trigger_error(
            'Undefined method via __call(): ' . $method .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line'],
            E_USER_NOTICE);
		delete_transient("feftlife_refreshing");
        return null;
	}

	public function __get($name) {
		if (!$connected = $this->fetlifeConnect()) {
			throw new Exception(".", 2);
			$trace = debug_backtrace();
			trigger_error(
	            'Fetlife Connection failed - cannot directly use FetLife library in ' . $trace[0]['file'] .
	            ' on line ' . $trace[0]['line'],
	            E_USER_NOTICE);
			delete_transient("feftlife_refreshing");
			return null;
		}

		if (property_exists(get_class($this->fetlifeUser), $name)) {
			// error_log("getting " . $name . "\n", 3, '/home/danfroal/public_html/dev/error_log');
			$return = $this->fetlifeUser->{$name};
			// error_log("got " . $name . "\n", 3, '/home/danfroal/public_html/dev/error_log');
			return $return;
		}

		$trace = debug_backtrace();
		trigger_error(
            'Undefined property via __get(): ' . $name .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line'],
            E_USER_NOTICE);
		delete_transient("feftlife_refreshing");
        return null;
	}

	/** ============================= PRIVATE METHODS ============================= **/

	private function fetlifeConnect($username = null, $password = null) {
		self::checkCredentials($username, $password);
		if (!$this->isLoggedIn) {
			if (isset($username) && isset($password)) {
				$fetlifeUser = new FetLifeUser($username, $password);
				$this->fetlifeUser = $fetlifeUser;
				// $this->fetlifeUser->connection->setProxy('auto');
				$this->fetlifeUser->connection->setProxy('104.194.206.10:7808');
				// error_log("connecting\n", 3, '/home/danfroal/public_html/dev/error_log');
				$this->isLoggedIn = $this->fetlifeUser->logIn();
				// error_log("connected? " . $this->isLoggedIn . "\n", 3, '/home/danfroal/public_html/dev/error_log');
				self::secureFetlifeObject($fetlifeUser);
			} else {
				unset($this->fetlifeUser);
			}
		}
		return $this->isLoggedIn;
	}

	/** ============================= PROTECTED METHODS ============================= **/

	protected static function secureFetlifeObject($obj) {
		// error_log("securing object...\n", 3, '/home/danfroal/public_html/dev/error_log');
		$fetlife_settings = get_option('fetlife_settings');
		if (is_object($obj) && !$obj->isSecure) {
			if (property_exists(get_class($obj), 'password')) {
				if ($obj->password == $fetlife_settings['fetlife_password']) {
					$obj->password = self::encrypt($obj->password);
					$obj->isSecure = true;
				}
			}
			foreach ($obj as $attribute => $value) {
				if (is_object($value) && $value instanceof FetLife) {
					self::secureFetlifeObject($value);
				}
			}
		}

		return $obj;
	}

	protected static function revealFetlifeObject($obj) {
		$fetlife_settings = get_option('fetlife_settings');
		if (is_object($obj) && $obj->isSecure) {
			if (property_exists(get_class($obj), 'password')) {
				if($obj->password == $fetlife_settings['fetlife_password']) {
					$obj->password = self::decrypt($obj->password);
					$obj->isSecure = false;
				}
			}
			foreach ($obj as $attribute => $value) {
				if (is_object($value) && $value instanceof FetLife) {
					self::secureFetlifeObject($value);
				}
			}
		}

		return $obj;
	}

	protected static function encrypt($input_string) {
		$key = 'fetlife-secret';
	    $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
	    $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
	    $h_key = hash('sha256', $key, TRUE);
	    return base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $h_key, $input_string, MCRYPT_MODE_ECB, $iv));
	}

	protected static function decrypt($encrypted_input_string) {
		$key = 'fetlife-secret';
	    $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
	    $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
	    $h_key = hash('sha256', $key, TRUE);
	    return trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $h_key, base64_decode($encrypted_input_string), MCRYPT_MODE_ECB, $iv));
	}

	protected static function checkCredentials(&$username, &$password) {
		if (!isset($username) || !isset($password)) {
			$fetlife_settings = get_option('fetlife_settings');
			if (!$fetlife_settings) {
				throw new Exception("Fetlife credentials missing - fetlife_settings option not found in wp_options", 1);
			}
			$username = $fetlife_settings['fetlife_username'];
			$password = $fetlife_settings['fetlife_password'];
		}
	}

	protected static function getFetlifeTransients($transient_category = null) {
		$option_name_pattern = isset($transient_category) ? '%_fetlife_{$transient_category}_%' : '%_fetlife_%';
		global $wpdb;
	    $sql = "SELECT `option_name` AS `name`
	            FROM  $wpdb->options
	            WHERE `option_name` LIKE '{$option_name_pattern}'
	            AND `option_name` NOT LIKE '%_transient_timeout_%'
	            ORDER BY `option_name`";
	    $results = $wpdb->get_results( $sql );
    	$transients = array();
		foreach ($results as $key => $result){
		    $transients[] = $result->name;
		}

		return $transients;
	}

	protected static function cleanFetlifeContent($content) {
		wp_enqueue_script('fetlife_content_clean', FETLIFE_PLUGIN_URL . '/js/fetlife-content-clean.js', array('jquery'), false, true);
		return $content;
	}

	protected static function getFetlifeEvent($fetlife_event_object, $future = false) {
		$start_timestamp = strtotime(str_replace('Z', '', $fetlife_event_object->dtstart));
		$end_timestamp = strtotime(str_replace('Z', '', $fetlife_event_object->dtend));
		$condition = ($future) ? $end_timestamp > strtotime(now) : true;
		$event = false;

		if ($condition) {
			$event = array(
				'date' 				=> self::getFormattedEventDate($start_timestamp, $end_timestamp),
				'timestamp' 		=> $start_timestamp,
				'ongoing' 			=> ($start_timestamp < strtotime('now') && $end_timestamp > strtotime('now')),
				'title' 			=> trim($fetlife_event_object->title),
				'tagline' 			=> trim($fetlife_event_object->tagline),
				'venue_name' 		=> trim($fetlife_event_object->venue_name),
				'venue_address' 	=> trim($fetlife_event_object->venue_address),
				'cost' 				=> trim($fetlife_event_object->cost),
				'dress_code' 		=> trim($fetlife_event_object->dress_code),
				'description' 		=> $fetlife_event_object->description,
				'number_going' 		=> count($fetlife_event_object->going),
				'number_maybegoing' => count($fetlife_event_object->maybegoing),
				'permalink' 		=> $fetlife_event_object->getPermalink(),
				'author' 			=> $fetlife_event_object->creator->nickname,
			);

			// Fix event tagline - unknown mystery white space that takes 2 characters
			$event['tagline'] = (strlen($event['tagline']) > 2) ? $event['tagline'] : null;
		}
		return $event;
	}

	protected static function getFetlifeWriting($fetlife_writing_object, $get_comments = false) {

		$writing = false;
		$published_timestamp = strtotime(str_replace('Z', '', $fetlife_writing_object->dt_published));
		$writing = array(
			'title' 	=> $fetlife_writing_object->title,
			'date' 		=> self::getFormattedDate($published_timestamp),
			'timestamp'	=> $published_timestamp,
			'category' 	=> $fetlife_writing_object->category,
			'privacy' 	=> $fetlife_writing_object->privacy,
			'content' 	=> self::cleanFetlifeContent($fetlife_writing_object->getContentHtml()),
			'author' 	=> $fetlife_writing_object->creator->nickname,
			'permalink' => $fetlife_writing_object->getPermalink(),
			// 'loves' 	=> $fetlife_writing_object->loves,
			'comments' 	=> $get_comments,//($get_comments) ? self::getFetlifeComments($fetlife_writing_object) : $get_comments,

		);

		return $writing;

	}

	protected static function getFetlifeEvents($organisers, $future = false) {
		$events = array();
		$scheduled_events = array();

		if (!isset($organisers)) {
			return $scheduled_events;
		}

		if (is_numeric($organisers)) {
			$organisers = array($organisers);
		} else if ($organisers == 'all') {
			$organisers = array();
			$transients = self::getFetlifeTransients('next_events_organiser');
			foreach ($transients as $key => $transient_name) {
				$organisers[] = str_replace('_transient_fetlife_next_events_organiser_', '', $transient_name);
			}
	    }

	    if (!empty($organisers)) {
			foreach ($organisers as $key => $organiser) {
				$events_data = get_transient('fetlife_next_events_organiser_'.$organiser);
				if ($events_data !== false) {
					$events = array_merge($events, unserialize(base64_decode($events_data)));
				}
			}
	    }

		if (!empty($events)) {
			usort($events, array('WP_Fetlife','sortEvents'));
			foreach ($events as $key => $event) {
				if ($scheduled_event = self::getFetlifeEvent($event, $future)) {
					$scheduled_events[] = $scheduled_event;
				}
			}
		}

		return $scheduled_events;
	}

	protected static function getFetlifeWritings($users, $writing_ids) {
		$user_ids = array();
		$writings = array();
		$displayed_writings = array();

		if (!isset($users) || !isset($writing_ids)) {
			return $displayed_writings;
		}

		if ($users == 'all') {
			$transients = self::getFetlifeTransients('writings');
			foreach ($transients as $key => $transient_name) {
				$user_ids[] = str_replace('_transient_fetlife_writings_', '', $transient_name);
			}
		} else if (is_numeric($users)) {
			$user_ids = array($users);
		} else if (is_array($users)) {
			$user_ids = $users;
		}

		foreach ($user_ids as $key => $user_id) {
			$writings_data = get_transient('fetlife_writings_'.$user_id);

			if ($writings_data !== false) {
				$decoded_writings = unserialize(base64_decode($writings_data));
				if ($users == 'all' || $writing_ids == 'all') {
					$writings = array_merge($writings, $decoded_writings);
				} else {
					foreach ($decoded_writings as $key => $fetlife_writing_object) {
						if (in_array($fetlife_writing_object->id, $writing_ids)) {
							$writings[] = $fetlife_writing_object;
						}
					}
				}
			}
		}

		if (!empty($writings)) {
			usort($writings, array('WP_Fetlife','sortContents'));
			foreach ($writings as $key => $writing) {
				if ($writing = self::getFetlifeWriting($writing)) {
					$displayed_writings[] = $writing;
				}
			}
		}

		return $displayed_writings;
	}

	protected static function getFormattedEventDate($start_timestamp, $end_timestamp = null) {
		$date = '';

		$end_timestamp = (isset($end_timestamp)) ? $end_timestamp : $start_timestamp;

		$from_day = date('d/m/Y', $start_timestamp);
		$to_day = date('d/m/Y', $end_timestamp);

		if ($from_day == $to_day) {
			$date = self::getFormattedDate($start_timestamp) . ' - ' . date(__('h:i A', 'fetlife'), $end_timestamp);
		} else {
			$date = self::getFormattedDate($start_timestamp) . __(' - ') . self::getFormattedDate($end_timestamp);
		}

		return apply_filters('fetlife_event_date_format', $date, $start_timestamp, $end_timestamp);
	}

	protected static function getFormattedDate($date_timestamp) {
		return apply_filters('fetlife_date_format', date(__('d/m/Y · h:i A', 'fetlife'), $date_timestamp), $date_timestamp);;
	}

	protected static function findContentUsingFetlifeShortcode($shortcode_string) { 
		$args = array(
			's' => '['.$shortcode_string,
		);
		$contents = array();
		$the_query = new WP_Query($args);

		if ($the_query->have_posts()) {
			while ( $the_query->have_posts() ) {
			$the_query->the_post();
				$contents[] = get_the_content();
			}
		} 
		wp_reset_postdata();

		$text_widgets = get_option('widget_text');
		foreach ($text_widgets as $key => $text_widget) {
			$contents[] = $text_widget['text'];
		}

		// TODO search in postmeta

		return $contents;
	}

	protected static function sortEvents($a, $b) {
		$order = 0;
		if (strtotime($a->dtstart) == strtotime($b->dtstart)) {
			$order = strtotime($a->dtstart) - strtotime($b->dtstart);
		} else {
			$order = strtotime($a->dtend) - strtotime($b->dtend);
		}
		return $order;
	}

	protected static function sortContents($a, $b) {
		return strtotime($a->dt_published) - strtotime($b->dt_published);
	}

	protected static function didConnect($old_connect, $new_connect) {
		return (isset($connect)) ? ($connect && $new_connect) : $new_connect;
	}

	protected static function cleanFetlifeContentIds($dirty_ids) {
		$clean_ids = array();
		if (isset($dirty_ids)) {
			$dirty_ids = str_replace('"', '', $dirty_ids);
			$dirty_ids = str_replace("'", '', $dirty_ids);
			$dirty_ids = str_replace(' ', '', $dirty_ids);
			$dirty_ids = explode(',', $dirty_ids);;
			foreach ($dirty_ids as $key => $id) {
				if (is_numeric($id)) {
					$clean_ids[] = $id;
				}
			}
		}
		return $clean_ids;
	}

	protected static function clearFetlifeTransients($transient_category = null) {
		$transients = self::getFetlifeTransients($transient_category);
		foreach ($transients as $key => $transient_name) {
			delete_transient(str_replace('_transient_', '', $transient_name));
		}
	}

	protected static function getDefaultFetlifeContentIds($fetlife_settings_ids_key) {
		$fetlife_settings = get_option('fetlife_settings');
		$ids = array();
		if (isset($fetlife_settings[$fetlife_settings_ids_key])) {
			$ids = self::cleanFetlifeContentIds($fetlife_settings[$fetlife_settings_ids_key]);
		}
		return $ids;
	}

	protected function getUpcomingEventsInLocationOrganisedByUser($location, $organiser_id, $username = null, $password = null) {
		self::checkCredentials($username, $password);
		$upcoming_events = array();
		$condition = ($this->isLoggedIn || (!$this->isLoggedIn && $this->fetlifeConnect($username, $password))) && !empty($organiser_id);
		$events = false;

		if ($condition) {
			if (!is_numeric($organiser_id)) {
				$organiser_id = false;
			}
			if ($organiser_id) {
				// Get a maximum of FETLIFE_MAX_PAGE pages to avoid memory issues
				$events = $this->fetlifeUser->getUpcomingEventsInLocation($location, FETLIFE_MAX_PAGE);
				if (!empty($events)) {
					foreach ($events as $key => $event) {
						$event->populate();
						if($event->creator->id == $organiser_id) {
							$upcoming_events[] = $event;
						}
						// Get only FETLIFE_MAX_POPULATE events to avoid memory issues
						if (count($upcoming_events) == FETLIFE_MAX_POPULATE) {
							break;
						}
					}
				}
			}
		}

		if ($upcoming_events) {
			usort($upcoming_events, array('WP_Fetlife','sortEvents'));
		}
		return $upcoming_events;
	}

	protected function getUpcomingEventsInLocation($location, $username = null, $password = null) {
		self::checkCredentials($username, $password);
		$upcoming_events = array();
		$condition = ($this->isLoggedIn || (!$this->isLoggedIn && $this->fetlifeConnect($username, $password))) && !empty($location);
		$events = false;

		if ($condition) {
			// Get a maximum of FETLIFE_MAX_PAGE pages to avoid memory issues
			$events = $this->fetlifeUser->getUpcomingEventsInLocation($location, FETLIFE_MAX_PAGE);

			foreach ($events as $key => $event) {
				$event->populate();
				$upcoming_events[] = $event;
				// Get only FETLIFE_MAX_POPULATE events to avoid memory issues
				if (count($upcoming_events) == FETLIFE_MAX_POPULATE) {
					break;
				}
			}
		}

		if ($upcoming_events) {
			usort($upcoming_events, array('WP_Fetlife','sortEvents'));
		}
		return $upcoming_events;
	}

	protected function getUpcomingEventsOrganisedByUser($organiser_id, $username = null, $password = null) {
		self::checkCredentials($username, $password);
		$upcoming_events = false;
		$condition = ($this->isLoggedIn || (!$this->isLoggedIn && $this->fetlifeConnect($username, $password))) && !empty($organiser_id);

		if ($condition) {
			if (!is_numeric($organiser_id)) {
				$organiser_id = false;
			}
			if ($organiser_id) {
				$upcoming_events = array();
				$events = array();
				$organiser = $this->fetlifeUser->getUserProfile($organiser_id);
				if (is_object($organiser)) {
					$events = $organiser->getEventsOrganised();
				}

				if(!empty($events)) {
					foreach ($events as $key => $event) {
						$event->populate();
						$upcoming_events[] = $event;
						// Get only FETLIFE_MAX_POPULATE events to avoid memory issues
						if (count($upcoming_events) == FETLIFE_MAX_POPULATE) {
							break;
						}
					}
				}
			}
		}
		if ($upcoming_events) {
			usort($upcoming_events, array('WP_Fetlife','sortEvents'));
		}
		return $upcoming_events;
	}

	/** ============================= PUBLIC METHODS ============================= **/

	/** ------ Wordpress actions & filters ----- **/

	public function fetlifeCron() {
		$timestamp = wp_next_scheduled('fetlife_cron');
		if( $timestamp == false ){
			wp_schedule_event(time(), 'daily', 'fetlife_cron');
	 	}
	}

	public function fetlife_load_widgets() {
		register_widget('WP_Widget_Recent_Fetlife_Writings');
	}

	public function get_option($fetlife_settings) {
		$fetlife_settings['fetlife_password'] = $this->decrypt($fetlife_settings['fetlife_password']);
		return $fetlife_settings;
	}
	
	public function enqueueStyleFrontend() {
		wp_enqueue_style('fetlife-style', FETLIFE_PLUGIN_URL . '/css/fetlife.css');
	}

	public function enqueueStyleAdmin() {
		wp_enqueue_style('fetlife-style-admin', FETLIFE_PLUGIN_URL . '/css/fetlife.css');
	}

	public function enqueueScriptAdmin() {
	   wp_register_script('fetlife_script_admin', FETLIFE_PLUGIN_URL . '/js/fetlife-admin.js', array('jquery'));
	   wp_localize_script('fetlife_script_admin', 'Fetlife', array('ajaxurl' => admin_url('admin-ajax.php')));        

	   wp_enqueue_script('jquery');
	   wp_enqueue_script('fetlife_script_admin');
	}

	public function refreshMenu($wp_admin_bar) {
		if (get_transient('feftlife_refreshing')) {
			$label = __('Refreshing fetlife data...', 'fetlife');
		} else {
			$label = __('Refresh fetlife data', 'fetlife');
		}
		$nonce = wp_create_nonce("refresh_fetlife_nonce");
		$link = admin_url('admin-ajax.php?action=refresh_fetlife&nonce=' . $nonce);
		$args = array(
			'id'    => 'refreshFetlifeMenu',
			'title' => $label,
			'href'  => $link,
			'meta'  => array('class' => 'fetlife-refresh-menu')
		);
		$wp_admin_bar->add_node($args);
	}

	public function refreshFetlifeHandler() {
		if (get_transient('feftlife_refreshing')) {
			// error_log("ajax refreshing - already running, aborting.\n", 3, '/home/danfroal/public_html/dev/error_log');
			delete_transient('feftlife_refreshing');
			echo false;
			die();
		}

		set_transient('feftlife_refreshing', true);
		if (!wp_verify_nonce($_REQUEST['nonce'], "refresh_fetlife_nonce")) {
			delete_transient('feftlife_refreshing');
			exit("Invalid nonce");
		}
		// error_log("ajax refreshing - fetlife data\n", 3, '/home/danfroal/public_html/dev/error_log');
		// null: connection status isn't defined.
		$connect = null;
		$response = apply_filters('refreshFetlife', $connect);

		delete_transient('feftlife_refreshing');
		// error_log("ajax refreshed - fetlife data\n", 3, '/home/danfroal/public_html/dev/error_log');
		// error_log("ajax response: " . $response . "\n", 3, '/home/danfroal/public_html/dev/error_log');

		echo $response;
		die();
	}

	public function refreshFetlifeRequireLogin() {
		echo __("Login required", "fetlife");
		die();
	}

	public function settingsMenu() {
	    add_menu_page('Fetlife', 'Fetlife', 'manage_options', 'fetlife', array($this, 'fetlifeSettings'));
	}
	 
	public function fetlifeSettings() {
	    $template = dirname(__FILE__) . '/includes/tpl/fetlife-admin.tpl.php';

		ob_start();
		include($template);
		$output =  ob_get_clean();

		echo $output;
	}

	public function registerAndBuildFields() {   
		register_setting('fetlife_settings', 'fetlife_settings', array($this, 'validateSettings'));
		add_settings_section('fetlife_settings_main_section', 'Fetlife credentials', array($this, 'section'), 'fetlife');

		add_settings_field('fetlife_username', 'Fetlife username:', array($this, 'usernameSetting'), 'fetlife', 'fetlife_settings_main_section');
		add_settings_field('fetlife_password', 'Fetlife password:', array($this, 'passwordSetting'), 'fetlife', 'fetlife_settings_main_section');
		add_settings_field('fetlife_location', 'Fetlife events location:', array($this, 'locationSetting'), 'fetlife', 'fetlife_settings_main_section');
		add_settings_field('fetlife_event_organiser', 'Fetlife events organisers\' IDs:', array($this, 'eventOrganisersSetting'), 'fetlife', 'fetlife_settings_main_section');
	}

	public function fetlifeWritingPostCategory() {
		$exists = false;
		
		if (isset($polylang)) {
			$all_translated = false;
			$languages = $polylang->get_languages_list();
			$slug_suffix = "";
			$first = true;
			foreach ($languages as $key => $language) {
				if (defined(WPLANG)) {
					if ($language->description != WPLANG) {
						$slug_suffix = '-' . $language->slug;
					}
				} else if ($language->description != "en_US") {
					$slug_suffix = '-' . $language->slug;
				}
				$result = get_term_by('slug', 'fetlife-writing' . $slug_suffix, 'category', ARRAY_A);
				if ($result) {
					if ($first) {
						$all_translated = true;
						$first = false;
					}
				} else {
					$all_translated = false;
				}
			}
			$exists = $all_translated;
		} else {
			$result = get_term_by('slug', 'fetlife-writing', 'category', ARRAY_A);
			if ($result) {
				$exists = true;
			}
		}

		if (!$exists) {
			global $polylang;
			if (isset($polylang)) {

				// init storage id (key = lang ; value = id)
				$translation_map = array();
				// get the languages
				$languages = $polylang->get_languages_list();

				// foreach language
				foreach ($languages as $key => $language) {
					$slug_suffix = "";
					if (defined(WPLANG)) {
						if ($language->description != WPLANG) {
							$slug_suffix = '-' . $language->slug;
						}
					} else if ($language->description != "en_US") {
						$slug_suffix = '-' . $language->slug;
					}
					// add a term 
					$args = array(
						'description'=> 'Fetlife writing post - the post should contain a fetlife writing shortcode',
						'slug' => 'fetlife-writing' . $slug_suffix,
					);
					$result = wp_insert_term('Fetlife Writing', 'category', $args);
					// if the result is not an array, get the term by slug, and add its id to the storage id
					if (!(is_array($result) && isset($result['term_id']))) {
						$result = get_term_by('slug', $args['slug'], 'category', ARRAY_A);
					}
					$polylang->set_term_language($result['term_id'], $language->slug);
					$translation_map[$language->slug] = $result['term_id'];
				}
				
				foreach ($translation_map as $key => $term_id) {
					foreach ($translation_map as $key_translated => $translated_id) {
						if ($key_translated != $key) {
							$polylang->save_translations('term', $term_id, array($key => $term_id, $key_translated => $translated_id));
						}
					}
				}
			} else {
				$result = wp_insert_term(
					'Fetlife Writing',
					'category',
					array(
						'description'=> 'Fetlife writing post - the post should contain a fetlife writing shortcode',
						'slug' => 'fetlife-writing',
					)
				);
			}
		}
	} 

	/** ------ Fetlife actions & filters ----- **/

	public function fetchNextEventsByOrganiser($connect = null) {
		// error_log("ajax refreshing - fetlife data events\n", 3, '/home/danfroal/public_html/dev/error_log');
		self::clearFetlifeTransients('next_events_organiser');
		$organisers = self::getDefaultFetlifeContentIds('fetlife_event_organiser');
		$contents = self::findContentUsingFetlifeShortcode("fetlife_events");
		if (!empty($contents)) {
			foreach ($contents as $key => $content) {
				if (preg_match_all('/\[fetlife_events ([^\]]*)?organiser_id=\"([0-9]+(,( )?[0-9]+)*)\"([^\]]*)?\]/i', $content, $match_result)) {
					foreach ($match_result[2] as $key => $values) {
						$organisers = array_merge($organisers, self::cleanFetlifeContentIds($values));
					}
				}
			}
		}

		if (!empty($organisers)) {
			$organisers = array_unique($organisers);
			foreach ($organisers as $key => $organiser) {
				$events = $this->getUpcomingEventsOrganisedByUser($organiser);
				if(!empty($events)) {
					$next_events_data = base64_encode(serialize($events));
					set_transient('fetlife_next_events_organiser_'.$organiser, $next_events_data);
				}
			}
		}
		return self::didConnect($connect, $this->isLoggedIn);
	}

	public function fetchWritingsByUser($connect = null) {
		self::clearFetlifeTransients('writings');
		$contents = self::findContentUsingFetlifeShortcode("fetlife_writings");
		$writing_ids_by_user_ids = array();
		if (!empty($contents)) {
			foreach ($contents as $key => $content) {
				if (preg_match_all('/\[fetlife_writings ([^\]]*)?user_id=\"([0-9]*)\"([^\]]*)?\]/i', $content, $user_match_result)) {
					foreach ($user_match_result[2] as $user_key => $user_values) {
						$writing_ids = array();
						$user_id = reset(self::cleanFetlifeContentIds($user_values));
							if (preg_match_all('/writing_id=\"([0-9]+(,( )?[0-9]+)*)\"/i', $user_match_result[0][$user_key], $writings_match_result)) {
								foreach ($writings_match_result[1] as $writing_key => $writings_values) {
									$writing_ids = array_merge($writing_ids, self::cleanFetlifeContentIds($writings_values));
								}
							} else if(strpos($user_match_result[0][$user_key], 'writing_id="all"')) {
								$writing_ids = 'all';
							}
						if (!empty($writing_ids)) {
							$writing_ids_by_user_ids[$user_id] = $writing_ids;
						}
					}
				} 
				$user_id = reset(self::getDefaultFetlifeContentIds('fetlife_event_organiser'));
				if ($user_id) {
					$writing_ids = array();
					if (preg_match_all('/\[fetlife_writings ([^\]]*)?writing_id=\"([0-9]+(,( )?[0-9]+)*)\"([^\]]*)?\]/i', $content, $writings_match_result)) {
						foreach ($writings_match_result[2] as $writing_key => $writings_values) {
							if (false === strpos($writings_match_result[0][$writing_key], 'user_id')) {
								$writing_ids = array_merge($writing_ids, self::cleanFetlifeContentIds($writings_values));
							}
						}
						if (!empty($writing_ids) && isset($user_id)) {
							$writing_ids_by_user_ids[$user_id] = $writing_ids;
						}
					}
				}
			}
		}
		if (!empty($writing_ids_by_user_ids)) {
			foreach ($writing_ids_by_user_ids as $user_id => $writing_ids) {
				$writing_ids = array_unique($writing_ids);
				$writings = array();
				foreach ($writing_ids as $key => $writing_id) {
					if ($writing_id == "all") {
						$writings[] = $this->getWritingsOf($user_id, FETLIFE_MAX_PAGE);
					} else {
						$writings[] = $this->getWritingOf($writing_id, $user_id);
					}
				}
				if(!empty($writings)) {
					$writings_data = base64_encode(serialize($writings));
					set_transient('fetlife_writings_'.$user_id, $writings_data);
				}
			}
		}
		return self::didConnect($connect, $this->isLoggedIn);
	}

	public function fetlifeTest($connect = null) {

		$FL = $this;
		$debug = array();

		// // Print some basic information about the account you're using.
		// $debug["your user's numeric ID."] = $FL->id;       // your user's numeric ID.
		// $debug["your user's nickname, the name you signed in with"] = $FL->nickname; // your user's nickname, the name you signed in with
		// $debug["your user's password - encrypted"] = $FL->password;       // your user's password - encrypted.

		// // Query FetLife for information about other users.
		// $debug["JohnBaku's ID - prints 1"] = $FL->getUserIdByNickname('JohnBaku'); // prints "1"
		// $debug["prints 'BeijingMunch'"] = $FL->getUserNicknameById(2002568);       // prints "maymay"

		// // Object-oriented access to user info is available as FetLifeProfile objects.
		// $profile = $FL->getUserProfile(1);          // Profile with ID 1
		// $debug["Profile of user ID 1 with some FetLifeProfile methods"] = array(
		// 	'$profile->nickname' => $profile->nickname,
		// 	'$profile->age' => $profile->age,
		// 	'$profile->gender' => $profile->gender,
		// 	'$profile->role' => $profile->role,
		// 	'$profile->adr' => $profile->adr,
		// 	'$profile->getAvatarURL() - optional $size parameter retrieves larger images' => $profile->getAvatarURL(),
		// 	'$profile->isPayingAccount() - true if the profile has a "supporter" badge' => $profile->isPayingAccount(),
		// 	'$profile->getEvents() - array of FetLifeEvent objects listed on the profile' => $profile->getEvents(),
		// 	'$profile->getEventsGoingTo() - array of FetLifeEvent the user has RSVP\'ed "going" to' => $profile->getEventsGoingTo(),
		// 	'$profile->getGroups() - array of FetLifeGroup objects listed on the profile' => $profile->getGroups(),
		// 	'$profile->getGroupsLead() - array of FetLifeGroups the user moderates' => $profile->getGroupsLead(),
		// );

		// $debug['Friends of BeijingMunch - by name, 2 pages'] = $FL->getFriendsOf('BeijingMunch', 2);
		// $debug['Friends of BeijingMunch - by ID, 2 pages'] = $FL->getFriendsOf(2002568, 2);

		// $debug['Members of Beijing Kink - 2 pages'] = $FL->getMembersOfGroup(59828, 2);
		// $debug['Kinksters into aftercare panda - 2 pages'] = $FL->getKinkstersWithFetish(574721, 2);
		// $debug['Kinksters in Beijing - 2 pages'] = $FL->getKinkstersInLocation('administrative_areas/691', 2);
		// $debug['Kinksters maybe going to "Corrupt all the Vanillas Everywhere" - 2 pages'] = $FL->getKinkstersMaybeGoingToEvent(43434, 2);
		// $debug['Kinksters going to "Corrupt all the Vanillas Everywhere" - 2 pages'] = $FL->getKinkstersGoingToEvent(43434, 2);

		// $debug['Writings of BeijingMunch'] = $FL->getWritingsOf('BeijingMunch');
		// $debug['Pictures of BeijingMunch'] = $FL->getPicturesOf('BeijingMunch');

		// $debug['Writing 2647145 of user 501819'] = $FL->getWritingOf(2647145, 501819)->getContentHtml();


							// 	// If you want to fetch comments, you need to populate() the objects.
							// 	$writings_and_pictures = array_merge($writings, $pictures);
							// 	foreach ($writings_and_pictures as $item) {
							// 	    $item->comments;   // currently, returns an NULL
							// 	    $item->populate();
							// 	    $item->comments;   // now, returns an array of FetLifeComment objects.
							// 	}

							// 	// If you already know the event ID, you can just fetch that event.
							// 	$event = $FL->getEventById(151424);
							// 	// "Populate" behavior works the same way.
							// 	$event = $FL->getEventById(151424, true); // Get all availble event data.

							// 	// You can also fetch arrays of events as FetLifeEvent objects.
							// 	$events = $FL->getUpcomingEventsInLocation('cities/5898'); // Get all events in Balitmore, MD.
							// 	// Or get just the first couple pages.
							// 	$events_partial = $FL->getUpcomingEventsInLocation('cities/5898', 2); // Only 2 pages.

							// 	// FetLifeEvent objects are instantiated from minimal data.
							// 	// To fill them out, call their populate() method.
							// 	$events[0]->populate(); // Flesh out data from first event fetched.
							// 	// RSVP lists take a while to fetch, but you can get them, too.
							// 	$events[1]->populate(2); // Fetch first 2 pages of RSVP responses.
							// 	$events[2]->populate(true); // Or fetch all pages of RSVP responses.

							// 	// Now we have access to some basic event data.
							// 	$debug[] = $events[2]->getPermalink();
							// 	$debug[] = $events[2]->venue_name;
							// 	$debug[] = $events[2]->dress_code;
							// 	// etc...

							// 	// Attendee lists are arrays of FetLifeProfile objects, same as friends lists.
							// 	// You can collect a list of all participants
							// 	$everyone = $events[2]->getParticipants();

							// 	// or interact with the separate RSVP lists individually
							// 	foreach ($events[2]->going as $profile) {
							// 	    $debug[] = $profile->nickname; // FetLife names of people who RSVP'd "Going."
							// 	}
							// 	$i = 0;
							// 	$y = 0;
							// 	foreach ($events[2]->maybegoing as $profile) {
							// 	    if ('Switch' === $profile->role) { $i++; }
							// 	    if ('M' === $profile->gender) { $y++; }
							// 	}
							// 	$debug[] = "There are $i Switches and $y male-identified people maybe going to {$events[2]->title}.";

		$debug = base64_encode(serialize($debug));
		// $debug = get_transient('fetlife_writings_501819');
		set_transient('fetlife_debug', $debug);
		// error_log($debug."\n", 3, '/home/danfroal/public_html/dev/error_log');

		return self::didConnect($connect, true);
	}

	/** ------ Wordpress shortcodes ----- **/

	public function eventsShortcode($atts) {
		$fetlife_settings = get_option('fetlife_settings');
		$output = '';

		extract(shortcode_atts(array(
			'organiser_id' => str_replace(' ', '', $fetlife_settings['fetlife_event_organiser']),
			'limit' => 0,
		), $atts));

		if ($organiser_id == 'all') {
			$organisers = $organiser_id;
		} else {
			$organisers = self::cleanFetlifeContentIds($organiser_id);
		}

		if (is_file(get_stylesheet_directory() . '/fetlife/fetlife-events.tpl.php')) {
			$template = get_stylesheet_directory() . '/fetlife/fetlife-events.tpl.php';
		} else {
			$template = FETLIFE_PLUGIN_PATH . 'includes/shortcodes/tpl/fetlife-events.tpl.php';
		}

		if (is_file(get_stylesheet_directory() . '/fetlife/fetlife-events.css')) {
			wp_enqueue_style('fetlife-events-style', get_stylesheet_directory_uri() . '/fetlife/fetlife-events.css');
		} else {
			wp_enqueue_style('fetlife-events-style', FETLIFE_PLUGIN_URL . '/css/fetlife-events.css');
		}
		
		$events = self::getFetlifeEvents($organisers, true);
		$index_next_event = ($events[0]['ongoing']) ? 1 : 0;
		if (empty($events)) {
			$events = false;
		} elseif ($limit != 0) {
			$events = array_slice($events, 0, $limit);
		}

		ob_start();
		include($template);
		$output =  ob_get_clean();

		return $output;
	}

	public function writingsShortcode($atts) {
		$fetlife_settings = get_option('fetlife_settings');
		$output = '';

		extract(shortcode_atts(array(
			'user_id'	 => str_replace(' ', '', array_shift(explode(',', $fetlife_settings['fetlife_event_organiser']))),
			'writing_id' => '',
		), $atts));


		if (!empty($writing_id)) {

			if ($user_id == 'all') {
				$users = $user_id;
			} else {
				$users = self::cleanFetlifeContentIds($user_id);
			}

			if ($writing_id == 'all') {
				$writing_ids = $writing_id;
			} else {
				$writing_ids = self::cleanFetlifeContentIds($writing_id);
			}

			if (is_file(get_stylesheet_directory() . '/fetlife/fetlife-writings.tpl.php')) {
				$template = get_stylesheet_directory() . '/fetlife/fetlife-writings.tpl.php';
			} else {
				$template = FETLIFE_PLUGIN_PATH . 'includes/shortcodes/tpl/fetlife-writings.tpl.php';
			}

			if (is_file(get_stylesheet_directory() . '/fetlife/fetlife-writings.css')) {
				wp_enqueue_style('fetlife-writings-style', get_stylesheet_directory_uri() . '/fetlife/fetlife-writings.css');
			} else {
				wp_enqueue_style('fetlife-writings-style', FETLIFE_PLUGIN_URL . '/css/fetlife-writings.css');
			}

			$writings = self::getFetlifeWritings($users, $writing_ids);
			if (empty($writings)) {
				$writings = false;
			}

			ob_start();
			include($template);
			$output =  ob_get_clean();
		}

		return $output;
	}

	/** ------ Wordpress admin panel callbacks ----- **/

	function section() {}

	function validateSettings($settings) {
		$original_settings = get_option('fetlife_settings');
		$error = false;

		if (empty($settings['fetlife_username'])) {
			add_settings_error('fetlife_username', 'fetlife_username', "Fetlife username is required.");
			$error = true;
		}

		if (empty($settings['fetlife_password'])) {
			add_settings_error('fetlife_password', 'fetlife_password', "Fetlife password is required.");
			$error = true;
		}

		if (!empty($settings['fetlife_event_organiser'])) {
			$ids = explode(',', $settings['fetlife_event_organiser']);;
			foreach ($ids as $key => $id) {
				$id = str_replace(' ', '', $id);
				if (!is_numeric($id)) {
					add_settings_error('fetlife_event_organiser', 'fetlife_event_organiser', "Invalid Fetlife user identifier.");
					$error = true;
					break;
				}
			}
		}

		if (!empty($settings['fetlife_location'])) {
			$location_parts = explode('/', $settings['fetlife_location']);
			$location_parts_num = count($location_parts);
			$error_condition = $location_parts_num =! 2 || !in_array($location_parts[0], array('cities', 'administrative_areas', 'countries')) || !is_numeric($location_parts[1]);
			if ($error_condition) {
				add_settings_error('fetlife_location_format', 'fetlife_location_format', "Malformed Fetlife events location");
				$error = true;
			}
		}

		$settings['fetlife_password'] = $this->encrypt($settings['fetlife_password']);

		if ($error) {
			$settings = $original_settings;
			add_settings_error('fetlife_error', 'fetlife_error', __("The settings were not updated.", "fetlife"));
		}

		return $settings;
	}

	public function usernameSetting() {  
		$options = get_option('fetlife_settings');  
		print "<input name='fetlife_settings[fetlife_username]' type='text' value='{$options['fetlife_username']}' /> <span>required</span>";
	}

	public function passwordSetting() {  
		$options = get_option('fetlife_settings');
		$password_set = (!empty($options['fetlife_password'])) ? '<em>' . __('There is an encrypted password in the database - retype password.','fetlife') . '</em>' : '<em>' . __('No password set yet.','fetlife') . '</em>';
		print "<input name='fetlife_settings[fetlife_password]' type='password' value='' /> <span>" . __("required", "fetlife") . "</span> " . $password_set;
	}

	public function locationSetting() {  
		$options = get_option('fetlife_settings');  
		print "<input name='fetlife_settings[fetlife_location]' type='text' value='{$options['fetlife_location']}' /> <span>" . __("optional", "fetlife") . "</span> <p>" . __("A \"[location_type]/[id]\" string from fetlife url where [location_type] is  \"cities\", \"administrative_areas\", or \"countries\" and [id] the unique identifier of the location of the events to follow.", "fetlife") . "</p>";
	}

	public function eventOrganisersSetting() {  
		$options = get_option('fetlife_settings');  
		print "<input name='fetlife_settings[fetlife_event_organiser]' type='text' value='{$options['fetlife_event_organiser']}' /> <span>" . __("optional", "fetlife") . "</span> <p>" . __("A single ID or a comma separated value list of IDs of fetlife users to follow.", "fetlife") . "</p>";
	}

	/** ------ Other public methods ----- **/

	public function addCronAndRefreshFilter($function_to_add, $priority = 10, $accepted_args = 1) {
		add_action('fetlife_cron', $function_to_add, $priority, $accepted_args);
		add_filter('refreshFetlife', $function_to_add, $priority, $accepted_args);
	}
}

global $fetlife;
$fetlife = new WP_Fetlife();