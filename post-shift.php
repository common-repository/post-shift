<?php
/*
Plugin Name: Post Shift
Plugin URI: http://sabotagedmedia.com
Description: Post shift takes your oldest posts and shifts them to be your newest posts.
Version: 1.0
Author: Jason Bellamy
Author URI: http://sabotagedmedia.com
License: GPL
*/

if (!class_exists("smPostShift")) {

	class smPostShift {	
		
		protected $date;
		protected $title;
		protected $category;
		protected $id;
		protected $email;
		protected $options;	
		protected $post;

		public function __construct() {
		
			if(get_option('post_shift_data')) { //check if the database has been setup
				/* set up main class properties */
				$this->options = get_option('post_shift_data');
				$this->email = get_option('admin_email'); 
				$this->post = $this->get_post();
			}
		}
		
		/**
		 */
		public function post_shift() {
		
			if($this->post) { // check if a post was found
			
				/* set up post specific properties */
				$this->date  	   = date('Y-m-d H:i:s');
				$this->title	   = trim($this->post->post_title);
				$this->category    = trim($this->post->description);
				$this->id 		   = trim($this->post->id);	
				
				if($this->set_post()) { // check if post was succefully updated
					
					if (in_array (1, $this->options['logging'])) { // send to file
						$this->write_file();
					}
					if (in_array (2, $this->options['logging'])) { // send to email
						$this->send_mail();
					}
				}
			}
		}	
		
		/**
		 * Retreives the oldest post from the database
		 */		
		protected function get_post() {
			global $wpdb;
			
			$prefix = $wpdb->prefix;
			$categories = implode(',',$this->options['categories']);
			
			$post = $wpdb->get_row("
					SELECT id, post_title, term_id, description, guid FROM {$prefix}posts, {$prefix}term_relationships, {$prefix}term_taxonomy 
					WHERE {$prefix}term_taxonomy.term_id = {$this->get_rand_category()}
					AND   {$prefix}term_taxonomy.term_taxonomy_id = {$prefix}term_relationships.term_taxonomy_id 
					AND   {$prefix}term_relationships.object_id = {$prefix}posts.ID 
					AND   {$prefix}posts.post_type = 'post' 
					AND   {$prefix}posts.post_status = 'publish' 
					ORDER BY post_date ASC"
			);
			return $post;
		}

		/**
		 * Sets the oldest post in the database to the newest post
		 */		
		protected function set_post() {
			global $wpdb;
			$result = $wpdb->query("UPDATE $wpdb->posts SET post_date = '$this->date' WHERE ID = '$this->id'");
			return $result;
		}
	
		/**
		 * Saves / Appends to a logfile with details of the latest post shift
		 */	
		protected function write_file() {
			$file = 'wp-content/plugins/post-shift/log.txt';
			$open = fopen($file, 'a');
			$message = 'Post succesfully shifted @ '.$this->date.' - '.$this->title.' - '.$this->category."\n";
			fwrite($open, $message);
			fclose($open);
		}
	
		/**
		 * Sends an email to the administrator email address with details of the latest post shift
		 */
		protected function send_mail() {
			$name  = 'Post Shift ('.get_option('blogname').')';
			$message = 'Post succesfully shifted @ '.$this->date.' - '.$this->title.' - '.$this->category;
			mail($this->email, $name, $message);
		}	

		/**
		 * Retreives a random category id out of the selected categories 
		 */	
		protected function get_rand_category() {
			return array_rand(array_flip($this->options['categories']), 1);
		}

		/**
		 * Add a new scheduled time to the wordpress pseudo crontab
		 * (the time is calculated based on the user input from the control panel)
		 */
		public function schedule_settings() {
			return array('shiftschedule' => array ('interval' => ($this->options['schedule'] * $this->options['time']), 'display' => 'Shift Post Schedule'));
		}
	
		/**
		 * Creates a menu option in the admin setting panel
		 */	
		public function admin_menu() {
			add_options_page('Post Shift Options', 'Post Shift Options', 'manage_options', 'post-shift-options', array($this, 'admin_options'));
		}
		
		/**
		 * Sets up the shift time-frame based on user input
		 */			
		protected function set_time($time) {
		
			switch($time) {
				case 'minutes':
					$time = 60;
					break;
				case 'hours':
					$time = 3600;
					break;
				case 'days':
					$time = 86400;
					break;
			}
			return $time;
		}
			
		/**
		 * Build and display the control panel in the admin settings section
		 */
		public function admin_options() {
		
			$category_ids = get_all_category_ids();
			
			if (isset ($_POST['post-shift-submit']) && is_numeric($_POST['post-shift-schedule']) && $_POST['post-shift-schedule'] > 0) {
		
				/*check to see if the options have been selected (set to 0 if not) */	
				$this->options['schedule']    = $_POST ['post-shift-schedule'];
				$this->options['categories']  = empty($_POST['categories']) ? array(0) : $_POST['categories'];
				$this->options['logging']     = empty($_POST['logging']) ? array(0) : $_POST['logging'];
				$this->options['time'] 		  = empty($_POST['time']) ? array(0) : $this->set_time($_POST['time']);
		
				update_option('post_shift_data', $this->options);
			}
			
			echo '<div class="wrap"> <h2>Post Shift</h2></div>
			<form method="post" action="'.$_SERVER ["REQUEST_URI"].'">
				<h3>Schedule</h3>
				<label for="post-shift-schedule">Shift a post every: </label><input type="text" name="post-shift-schedule" value="'.$this->options ['schedule'].'"/>
				<select name="time">';
				
					echo '<option ';
					if($this->options['time'] == 60) { echo 'selected '; }
					echo 'value="minutes">Minute(s) </option>';
					
					echo '<option ';
					if($this->options['time'] == 3600) { echo 'selected '; }
					echo 'value="hours">Hour(s) </option>';
					
					echo '<option ';
					if($this->options['time'] == 86400) { echo 'selected '; }
					echo 'value="days">Day(s) </option>';
					
			echo '</select>
			<h3>Categories</h3>
			<p>Select which categories you would like posts shifted from.</p>
			';

			foreach ($category_ids as $cat_id) {
				$cat_name = get_cat_name($cat_id) ;
				if (in_array ($cat_id, $this->options['categories'])) {
					echo '<input type="checkbox" name="categories[]" value="'.$cat_id.'" checked="yes">- '.$cat_name.'';
				} else {
					echo '<input type="checkbox" name="categories[]" value="'.$cat_id.'">- '.$cat_name.'';
				}
				echo '<br />';
			}

			echo '<h3>Logging</h3>
				<p>Select how you would like post shift to record its actions.</p>';
			echo '<input type="checkbox" name="logging[]" value="1"';
			if (in_array (1, $this->options['logging'])) { echo 'checked="yes"';}
			echo '>- Send to File <span class="description"><code>log.txt</code></span><br />';	
			echo '<input type="checkbox" name="logging[]" value="2"';
			if (in_array (2, $this->options['logging'])) { echo 'checked="yes"';}
			echo '>- Send to Email <span class="description"><code>'.$this->email.'</code></span>';
			echo '<br />';
			echo '<br /><input type="submit" name="post-shift-submit"  class="button-primary" value="Save Changes" />
				</form>';
			echo '<h3>Shift Que</h3>';
			
			if($this->post) {
				echo 'The next post scheduled to be shifted is: <a href="post.php?action=edit&post='.$this->post->id.'">'.trim($this->post->post_title).'</a>  from <a href="edit.php?cat='.$this->post->term_id.'">'.$this->post->description.'</a>';
			}
			else {
				echo 'There are no posts set to be shifted.';
			}
		}
		
		/**
		 * Sets up and installs this plugin
		 */		
		public function install() {
			$options = array ('schedule' => '5', 'categories' => array(0), 'logging' => array(0), 'time' => 60);
			add_option('post_shift_data', $options);
			wp_schedule_event(time (), 'shiftschedule','post_shift_hook');
		}			
		
		/**
		 * Cleans up and uninstalls all traces of this plugin from wordpress
		 */
		public function uninstall() {
			wp_clear_scheduled_hook('post_shift_hook');
			delete_option('post_shift_data');
		}
	}
}

if (class_exists('smPostShift')) {
	$sm_post_shift = new smPostShift();
}

if (isset($sm_post_shift)) {
	/** Actions **/
	add_action('admin_menu', array($sm_post_shift, 'admin_menu'));	
	add_action('post_shift_hook', array($sm_post_shift, 'post_shift'));
	
	/** Filters **/
	add_filter('cron_schedules', array($sm_post_shift, 'schedule_settings'));
}

/** Plugin Activation **/
register_activation_hook(__FILE__, array($sm_post_shift, 'install'));

/** Plugin Deactivation **/
register_deactivation_hook(__FILE__, array($sm_post_shift, 'uninstall'));
?>
