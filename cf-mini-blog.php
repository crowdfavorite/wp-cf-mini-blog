<?php 
/*
Plugin Name: CF Mini-Blog
Plugin URI: 
Description: Creates the ability to have specific blog elements (header image, sidebars, menus, etc.) created 
Version: 0.1
Author: Crowd Favorite
Author URI: http://crowdfavorite.com
*/

/*
* Admin listing of terms
* Add Term
* Activate 
* Deactivate
* Delete

* create sidebar
* create menu

* leaderboard ad code
* tracking code
* image upload
* save Mini-Blog setting for post
* setting for Mini-Blog for excluding from home page
- add custom image size

- README
- CHANGELOG

Nice to Haves
- carousel settings
- Rename term (never spec'd)
*/
require_once('cfmb-template-helper.php');

class CF_Mini_Blog {
	
	static $instance;
	
	var $ver = '0.1';
	
	public $term_sidebar_map = array();
	public $term_menu_map = array();
	
	private function __construct() {
		/* Define what our "action" is that we'll 
		listen for in our request handlers */
		if (file_exists(trailingslashit(get_stylesheet_directory()).'plugins/'.basename(dirname(__FILE__)))) {
        		$this->url = trailingslashit(trailingslashit(get_stylesheet_directory_uri()).'plugins/'.basename(dirname(__FILE__)));
		}
		else {
        		$this->url = trailingslashit(plugin_dir_url(__FILE__));
		}
		
		$this->primary_meta_key = '_cfmb_primary_mb';
		$this->action = 'cf_mini_blog_action';
		$this->taxonomy = 'cf_mini_blog';
		$this->post_type = 'cf_mini_blog_post';
		$this->taxonomy_slug = 'blog';
		$this->option_name = 'cf_mini_blog_settings';
		$this->error_code = 'cf_mini_blog_error';
		
		// Menu Stuff
		$this->menu_page_slug = 'cf_mini_blog';
		$this->menu_page_url = add_query_arg(array('page' => $this->menu_page_slug), admin_url('options-general.php'));
		
		// Settings Format
		$this->defaults = array(
			'active_mini_blogs' => array(),
			'select_multiple' => 0,
			'another_setting' => '',
		);
	}

	public function factory() {
		if (!isset(self::$instance)) {
			self::$instance = new CF_Mini_Blog;
		}
		return self::$instance;
	}
	
	public function add_actions() {
		// Register our taxonomy before other actions

		add_action('init', array($this, 'register_taxonomy'), 0); // Same priority as create_initial_taxonomies()
		add_action('init', array($this, 'register_post_type'), 1); 
		
		// Register Sidebars for each Mini-Blog term
		add_action('widgets_init', array($this, 'register_sidebars'));
		add_action('widgets_init', array($this, 'register_menus'));
		
		// Exclude posts if necessary
		add_action('pre_get_posts', array($this, 'maybe_filter_home_page_posts'));
		
		// Feed site URL should be mini blog URL
		add_filter('get_bloginfo_rss', array($this, 'feed_link_filter'), 0, 2);
		
		if (is_admin()) {
			add_action('init', array($this, 'admin_resource_handler'), 0);
			add_action('init', array($this, 'admin_request_handler'));
			add_action('admin_menu', array($this, 'register_settings_page'));
			add_filter('plugin_action_links', array($this, 'plugin_action_links'), null, 2);
			add_action('load-settings_page_'.$this->menu_page_slug, array($this, 'load_admin_page'));
			add_action('add_meta_boxes_post', array($this, 'add_meta_box'), null, 1);
			add_action('save_post', array($this, 'save_mini_blog_assignment'), null, 2);
		}
	}
	
	public function feed_link_filter($option_val, $option_name) {
		if ($option_name == 'url' && is_tax($this->taxonomy)) {
			//if is mini blog
			$term = get_queried_object();
			$term_url = get_term_link($term, $this->taxonomy);
			if (!empty($term_url) && !is_wp_error($term_url)) {
				$option_val = $term_url;
			}
		}
		
		return $option_val;
	}
	
	/**
	 * Registers the cf_mini_blogs taxonomy
	 *
	 * @return void
	 */
	public function register_taxonomy() {
		$args = array(
			'public' => true,
			// We create our own custom UI
			'show_ui' => false,
			'labels' => array(
				'name' => __('Mini-Blogs', 'cf_mini_blog'),
				'singular_name' => __('Mini-Blog', 'cf_mini_blog'),
			),
			'hierarchical' => true,
			'rewrite' => array(
				'slug' => $this->taxonomy_slug
			)
		);
		register_taxonomy($this->taxonomy, 'post', $args);
	}
	
	
	/**
	 * Register the Mini-Blogs post type, so that we can attach meta to the various Mini-Blogs
	 *
	 * @return void
	 */
	public function register_post_type() {
		register_post_type($this->post_type, array( 
			'labels' => array(
				'name' => __('Mini-Blogs', 'cf_mini_blog'),
				'singular_name' => __('Mini-Blog', 'cf_mini_blog'),
			),
			'description' => __('Mini-Blogs have a one-to-one relationship with the mini-blogs taxonomy.  This is an easy way to house meta about the taxonomy.', 'cf_mini_blog'),
			'public' => false,
			'hierarchical' => false,
			'supports' => array(
				'title',
				'thumbnail',
			),
			'taxonomies' => array(
				$this->taxonomy
			),
		));
	}
	
	
	/**
	 * Register the Mini-Blogs meta box for the post-edit screen
	 *
	 * @param obj $post 
	 * @return void
	 */
	public function add_meta_box($post) {
		add_meta_box('cfmb_mini_blogs', __('Mini-Blogs', 'cf_mini_blog'), array($this, 'output_mini_blogs_meta_box'), 'post', 'side');
	}
	
	
	/**
	 * Populate a metabox that allows the author to choose which Mini-Blogs 
	 * the post is associated with.
	 *
	 * @param object $post 
	 * @return void
	 */
	public function output_mini_blogs_meta_box($post) {
		// Get our post id
		$id = is_object($post) && isset($post->ID) ? $post->ID : null;
		$selected = wp_get_post_terms($id, $this->taxonomy, array('fields' => 'ids'));
		$name = esc_attr('tax_input['.$this->taxonomy.'][]');
		$terms = get_terms($this->taxonomy, array(
			'hide_empty' => false,
		));
		$inactive = $this->get_inactive_mini_blogs();
		
		if ($this->get_setting('select_multiple')) {
			$primary = get_post_meta($post->ID, $this->primary_meta_key, true);
?>
	<p><?php _e('Select Mini-Blogs to associate to this post.', 'cf_mini_blog'); ?></p>
	<div class="categorydiv">
		<label for="cfmb-primary"> 
			<?php _e('Primary Mini Blog ', 'cf_mini_blog');	 ?>
			<select id="cfmb-primary" name="cfmb_mini_blog_primary" >
				<option value="0"><?php _e('None', 'cf_mini_blog'); ?>
				<?php 
					foreach ($terms as $term) {
						if (!in_array($term->term_id, $inactive)) {
							echo '<option value="'.esc_attr($term->term_id).'"'.selected($term->term_id, $primary, false).'>'.esc_html($term->name).'</option>';
						}
					}

				 ?>
			</select>
		</label>
	</div>
	<p></p>
	<div id="<?php echo esc_attr('taxonomy-'.$this->taxonomy) ?>" class="<?php echo esc_attr($this->taxonomy.'div categorydiv'); ?>">
		<div id="category-all" class="tabs-panel">
			<input type="hidden" name="<?php echo $name; ?>" value="0">
			<ul id="<?php echo esc_attr($this->taxonomy.'checklist'); ?>" data-wp-lists="<?php echo esc_attr('list:'.$this->taxonomy); ?>" class="categorychecklist form-no-clear">	
				<?php 
					foreach ($terms as $term) {
						if (!in_array($term->term_id, $inactive)) {
							echo '<li id="'.esc_attr($this->taxonomy.'-'.$term->term_id).'">
									<label class="selectit">
										<input value="'.esc_attr($term->term_id).'" type="checkbox" name="'.$name.'" id="'.esc_attr('in-'.$this->taxonomy.'-'.$term->term_id).'"'.checked(true, in_array($term->term_id, $selected), false).'> '.esc_html($term->name).
									'</label>
								</li>';
						}
					}
				 ?>
			</ul>
		</div>
	</div>
<?php 
		} 
		else {
		
		// Only one can be selected here
			$selected = is_array($selected) ? array_shift($selected) : 0;
		?>
		
	<p><?php _e('Select a Mini-Blog to associate to this post.', 'cf_mini_blog'); ?></p>
	<select name="cfmb_mini_blog_dropdown" id="cfmb_mini_blog_dropdown" class="postform">
		<option value="-1"><?php _e('&mdash; Please Select &mdash;', 'cf_mini_blog'); ?></option>
		<?php 
			foreach ($terms as $term) {
				if (!in_array($term->term_id, $inactive)) {
					echo '<option value="'.esc_attr($term->term_id).'"'.selected($selected, $term->term_id, false).'>'.esc_html($term->name).'</option>';
				}
			}
		 ?>
	</select>		
		<?php
		}
	}
	
	
	/**
	 * Save the Mini-Blog selection for a post
	 *
	 * @param int $post_id 
	 * @param obj $post 
	 * @return void
	 */
	public function save_mini_blog_assignment($post_id, $post) {
		if ($post->post_type == 'post' && isset($_POST['cfmb_mini_blog_dropdown'])) {
			// sanitize the $_POST[]
			$mb_id = intval($_POST['cfmb_mini_blog_dropdown']);
			// Assign the term
			wp_set_post_terms($post_id, array($mb_id), $this->taxonomy);
		}
		else if ($post->post_type == 'post' && isset($_POST['cfmb_mini_blog_primary'])) { 
			$mb_id = intval($_POST['cfmb_mini_blog_primary']);
			// Assign the term
			wp_set_post_terms($post_id, array($mb_id), $this->taxonomy, true);
			update_post_meta($post_id, $this->primary_meta_key, $mb_id);
		}
	}
	
	/**
	 * Set the terms for a post
	 * 
	 * @param int $post_id 
	 * @param int $mb_id
	 * @return void
	 */ 
	public function set_mini_blog_term($post_id, $mb_id) {
		wp_set_post_terms($post_id, array($mb_id), $this->taxonomy);
	}
	
	/**
	 * Early request handler for the admin resources
	 *
	 * @return void
	 */
	public function admin_resource_handler() {
		if (isset($_GET[$this->action])) {
			switch ($_GET[$this->action]) {
				case 'admin_js':
					$this->admin_js();
					exit;
					break;
			}
		}
	}
	
	
	/**
	 * Wrapper function for getting all mini blogs
	 *
	 * @param array $args 
	 * @return array
	 */
	public function get_mini_blogs($args = array()) {
		// Prep args for get_terms()
		$defaults = array(
			'hide_empty' 	=> false,
		);
		
		// Get all the terms, we'll cut out the unwanteds later
 		$terms = get_terms($this->taxonomy, array_merge($defaults, $args));

		// Only return arrays from this method
		return is_array($terms) ? $terms : array();
	}
	
	
	/**
	 * Just get all the Mini-Blog IDs 
	 *
	 * @return array - array of integers
	 */
	public function get_mini_blog_ids() {
		$all_mini_blogs = $this->get_mini_blogs();
		$ids = array();
		foreach ($all_mini_blogs as $mb_term) {
			$ids[] = $mb_term->term_id;
		}
		return $ids;
	}
	
	
	/**
	 * Loops over the active mini blogs, and creates a sidebar for each one
	 *
	 * @return void
	 */
	public function register_sidebars() {
		$mini_blogs = $this->get_mini_blogs();
		$sidebar_args = array(
			'before_widget' => '<li id="%1$s" class="widget-container %2$s">',
			'after_widget' => '</li>',
			'before_title' => '<h3 class="widget-title">',
			'after_title' => '</h3>',
		);
		foreach ($mini_blogs as $mini_blog) {
			$sidebar_args['name'] 		 = sprintf(__('%s - Mini-Blog Sidebar', 'cf_mini_blog'), $mini_blog->name);
			$sidebar_args['id'] 		 = 'mini-blog-sidebar-'.$mini_blog->slug; // @TODO this may be better suited as the term_id
			$sidebar_args['description'] = sprintf(__('Sidebar for the "%s" Mini-Blog', 'cf_mini_blog'), $mini_blog->name);
			$sidebar_args = apply_filters('cfmb_sidebar_args', $sidebar_args, $mini_blog);
			
			$this->term_sidebar_map[$mini_blog->slug] = register_sidebar($sidebar_args);
		}
	}
	
	/**
	 * Loops over the active mini-blogs and creates a menu for each one
	 *
	 * @return void
	 */
	public function register_menus() {
		$menu_map = array();
		$mini_blogs = $this->get_mini_blogs();
		foreach ($mini_blogs as $mini_blog) {
			$menu_name = sprintf(__('Mini-Blog: %s', 'cf_mini_blog'), $mini_blog->name);
			
			$menu_id = wp_create_nav_menu($menu_name);
			if (is_wp_error($menu_id)) {
				$menu = wp_get_nav_menu_object($menu_name);
				$menu_id = $menu->term_id;
			}
			$menu_map[$mini_blog->slug] = $menu_id;
		}
		$this->term_menu_map = $menu_map;
	}
	
	
	/**
	 * Loads stuff for the specific admin page
	 *
	 * @return void
	 */
	public function load_admin_page() {
		// Bring in our list table class
		require_once 'cf-mini-blog-list-table.class.php';
		
		// Bring in our Network Carousel Mini-Blog
		// require_once 'carousel-plugin.php'; // @TODO this will be developed later
		
		// Handle messages
		add_action('admin_notices', array($this, 'admin_notices'));
		
		// bring in custom JS
		wp_enqueue_script('cfmb_admin', add_query_arg(array($this->action => 'admin_js'), admin_url()), array('jquery'), $this->ver);
		
		wp_enqueue_style('cfmb_admin', $this->url.'css/admin.css', array(), $this->ver);
	}
	
	
	/**
	 * Output the admin JavaScript
	 *
	 * @return void
	 */
	private function admin_js() {
		if (!headers_sent()) {
			header('Content-Type: text/javascript');
		}
		require 'js/admin.js';
	}
	
	/**
	 * Handle our messages from the request handler
	 *
	 * @return void
	 */
	public function admin_notices() {
		if (isset($_GET['msg'])) {
			$class = (isset($_GET['r']) && $_GET['r'] == 'success') ? 'updated' : 'error';
			?>
			<div class="<?php echo esc_attr($class); ?>">
				<p><?php echo esc_html(urldecode(stripslashes($_GET['msg']))); ?></p>
			</div>
			<?php 
		}
	}
	
	
	/**
	 * Registers the admin page
	 *
	 * @return void
	 */
	public function register_settings_page() {
		add_options_page(
			__('CF Mini-Blog', 'cf_mini_blog'),
			__('CF Mini-Blog', 'cf_mini_blog'),
			'manage_options',
			$this->menu_page_slug,
			array($this, 'output_settings_page')
		);
	}
	
	
	/**
	 * Prepends a "Manage Mini-Blogs" link for our plugin on the plugins.php page
	 *
	 * @param array $links 
	 * @param string $file -- filename of plugin 
	 * @return array
	 */
	public function plugin_action_links($links, $file) {
		if (basename($file) == basename(__FILE__)) {
			$settings_link = '<a href="'.$this->menu_page_url.'">'.__('Manage Mini-Blogs', 'cf_mini_blog').'</a>';
			array_unshift($links, $settings_link);
		}
		return $links;
	}
	
	
	/**
	 * Outputs the HTML for the Administration page
	 *
	 * @return void
	 */
	public function output_settings_page() {
		// add_screen_option( 'per_page', array('label' => $title, 'default' => 20) );
		// echo screen_options();
		
		$table = new CF_Mini_blog_List_Table;
		$table->prepare_items(); // Gather & Prep all the data for the table
		
		?>
		<div class="wrap">
			<?php screen_icon(); ?>
			<h2><?php _e('Mini-Blog Administration', 'cf_mini_blog'); ?></h2>
			
			<?php
			// Output the form to add new Mini-Blogs
			$this->output_new_term_form();
			
			// Output the Mini-Blog Table
			$table->display();
			?>
		</div>
		<?php
	}
	
	
	/**
	 * Outputs the small "New Mini BLog" Form
	 *
	 * @return void
	 */
	private function output_new_term_form() {
		?>
		<form action="<?php echo esc_url(admin_url()); ?>" method="post">
			<h3><?php _e('Mini-Blog Plugin Options', 'cf_mini_blog'); ?></h3>
			<label><?php _e('Allow users to assign posts to multiple mini-blogs', 'cf_mini_blog'); ?> <input name="cfmb_select_multiple" type="checkbox" value="1"<?php checked($this->get_setting('select_multiple'), '1'); ?>/><br />
				<span class="cfmb-help"><?php _e('*Note that posts assigned to mini-blogs with this option enabled will remain in multiple mini-blogs when disabeld until that post is updated.', 'cf_mini_blog'); ?></span>
			</label>
			<p class="submit">
				<?php $this->output_hidden_form_fields('save_settings'); ?>
				<input type="submit" class="button" value="<?php _e('Save', 'cf_mini_blog'); ?>" />
			</p>

		</form>
		<form action="<?php echo esc_url(admin_url()); ?>" method="post">
		
			<?php $this->output_hidden_form_fields('add_new_term'); ?>
			
			<h3><?php _e('Add a New Mini-Blog', 'cf_mini_blog'); ?></h3>
			<label for="cfmb_new_term"><?php _e('Name of Mini-Blog', 'cf_mini_blog'); ?></label>: <input type="text" name="cfmb_new_term" id="cfmb_new_term" value="" />
			<button type="submit" class="button-primary"><?php _e('Create', 'cf_mini_blog'); ?></button>
			<input type="hidden" name="paged" id="paged" value="<?php echo esc_attr($this->get_pagenum()); ?>" />
			
		</form>
		<?php
	}
	
	
	/**
	 * Output a nonce and our hidden request handler field
	 *
	 * @param string $name 
	 * @return void
	 */
	private function output_hidden_form_fields($name) {
		// Output a nonce field
		echo wp_nonce_field('cfmb_'.$name, '_cfmb_'.$name);
		
		// Now our request handler field
		?>
		<input type="hidden" name="<?php echo $this->action; ?>" value="<?php echo esc_attr($name); ?>" />
		<?php
	}
	
	
	/**
	 * Adds a new taxonomy term
	 *
	 * @param string $term_name **SHOULD BE SANITIZED ALREADY**
	 * @return bool|int - False on fail, Int of new term
	 */
	public function add_new_term($term_name) {
		$r = wp_insert_term($term_name, $this->taxonomy);
		// Make sure it got added successfully
		if (is_wp_error($r) || empty($r)) {
			return false;
		}
		
		// $r = array('term_id' => $term_id, 'term_taxonomy_id' => $tt_id);
		return $r['term_id'];
	}
	
	
	/**
	 * Get what page we're on
	 *
	 * @return int
	 */
	private function get_pagenum() {
		return isset( $_REQUEST['paged'] ) ? absint( $_REQUEST['paged'] ) : 0;
	}
	
	
	/**
	 * Uploads an image and returns the post_id of the attachment
	 *
	 * @param string $name 
	 * @param string $type - image/jpeg 
	 * @param string $bits - bits from file_get_contents()
	 * @return int | WP_Error - Integer of attachment post ID or WP_Error on fail
	 */
	private function upload_image($name, $type, $bits) {
		$upload = wp_upload_bits($name, NULL, $bits);
		
		if ( ! empty($upload['error']) ) {
			$errorString = sprintf(__('Could not write file %1$s (%2$s)'), $name, $upload['error']);
			return new WP_Error($this->error_code, $errorString);
		}
		
		// Construct the attachment array
		$attachment = array(
			'post_title' => $name,
			'post_content' => '',
			'post_type' => 'attachment',
			'post_parent' => 0,
			'post_mime_type' => $type,
			'guid' => $upload[ 'url' ]
		);
		
		/** WordPress Image Administration API */
		require_once(ABSPATH . 'wp-admin/includes/image.php');

		// Save the data
		$id = wp_insert_attachment($attachment, $upload['file']);
		
		// Generates the different image sizes
		wp_update_attachment_metadata($id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
		
		return $id;
	}
	/**
	 * Request handler for admin requests
	 *
	 * @return void
	 */
	public function admin_request_handler() {
		if (isset($_POST[$this->action])) {
			switch ($_POST[$this->action]) {
				case 'save_settings':
					if (check_admin_referer('cfmb_save_settings', '_cfmb_save_settings') && current_user_can('manage_options')) {
						if (isset($_POST['cfmb_select_multiple'])) {
							$select_multiple = 1;
						}
						else {
							$select_multiple = 0;
						}
						$this->set_setting('select_multiple', $select_multiple);
						$args = array(
							'r' => 'success',
							'msg' => urlencode($this->get_success_fail_string('success', 'save_settings')),
						);
					}

					// Stay on the same page
					$args['paged'] = $this->get_pagenum();
					
					// Actually redirect
					wp_redirect(add_query_arg($args, $this->menu_page_url));
					break;
				case 'add_new_term':
					// Should we be here?
					if (!check_admin_referer('cfmb_add_new_term', '_cfmb_add_new_term') && current_user_can('manage_options')) {
						wp_die(__("You shouldn't be here", 'cf_mini_blog')); // Nope
					}
					
					// Sanitize our input
					$term_name = stripslashes(wp_filter_nohtml_kses($_POST['cfmb_new_term']));
					
					// Set return URL args based on results of $this->add_new_term()
					if ($this->add_new_term($term_name)) {
						$r = 'success';
						$args = array(
							'r' => $r,
							'msg' => urlencode(sprintf($this->get_success_fail_string($r, 'add_new_term'), $term_name)),
						);
					}
					else {
						$r = 'fail';
						$args = array(
							'r' => $r,
							'msg' => urlencode(sprintf($this->get_success_fail_string($r, 'add_new_term'), $term_name)),
						);
					}
					
					// Stay on the same page
					$args['paged'] = $this->get_pagenum();
					
					// Actually redirect
					wp_redirect(add_query_arg($args, $this->menu_page_url));
					exit;
					break;
				case 'edit_mb':
					// Should we be here?
					if (!check_admin_referer('edit_mb', '_edit_mb') && current_user_can('manage_options')) {
						wp_die(__("You shouldn't be here", 'cf_mini_blog')); // Nope
					}
					
					$id = absint($_POST['id']);
					$mini_blog = get_term($id, $this->taxonomy); 
					
					// Default thumbnail
					$thumbnail = !empty($_POST['image-'.$id]) ? (int) $_POST['image-'.$id] : 0;
					if (is_array($_FILES) && isset($_FILES['image-'.$id]) && !empty($_FILES['image-'.$id]['tmp_name'])) {
						$image_upload = $_FILES['image-'.$id];
						
						// Prep vars for upload_image method
						$name = sanitize_file_name($image_upload['name']);
						$type = $image_upload['type'];
						$bits = file_get_contents($image_upload['tmp_name']);
						
						// Create the attachment post
						$upload_id = $this->upload_image($name, $type, $bits);
						
						if (is_wp_error($upload_id)) {
							$args = array(
								'r' => 'fail',
								'paged' => $this->get_pagenum(),
								'msg' => urlencode(sprintf($this->get_success_fail_string('fail', 'file_upload'), $name)),
							);
							wp_redirect(add_query_arg($args, $this->menu_page_url));
							die();
						}
						
						// Assign the attachment ID as postmeta
						$thumbnail = $upload_id;
					}
					
					// @TODO need to allow javascript, but not HTML?
					$leaderboard_code = stripslashes($_POST['leaderboard_code-'.$id]);
					$analytics_code = stripslashes($_POST['analytics_code-'.$id]);
					$exclude_on_home = empty($_POST['exclude_on_home-'.$id]) ? 0 : 1;
					$dark_theme = empty($_POST['dark_theme-'.$id]) ? 0 : 1;
					$image_only_excerpts = empty($_POST['image_only_excerpts-'.$id]) ? 0 : 1;
					
					$result = $this->save_mini_blog_meta($id, compact(
						'leaderboard_code', 
						'analytics_code', 
						'thumbnail', 
						'exclude_on_home',
						'dark_theme',
						'image_only_excerpts'
					));
					
					if (!is_wp_error($result)) {
						$args = array(
							'success' => 1,
							'msg' => sprintf($this->get_success_fail_string('success', 'edit_mini_blog'), $mini_blog->name),
							'id' => $mini_blog->term_id,
						);
					}
					else {
						$args = array(
							'success' => 0,
							'msg' => sprintf($this->get_success_fail_string('fail', 'edit_mini_blog'), $mini_blog->name, $result->get_error_message()),
							'id' => $mini_blog->term_id,
						);
					}
					
					
					if (!empty($_POST['doingAjax'])) {
						echo json_encode($args);
					}
					else {
						// Stay on the same page
						$args['paged'] = $this->get_pagenum();
						
						// Change args for URL instead of ajax
						$args['r'] = ($args['success']) ? 'success' : 'fail';
						unset($args['success']);
						
						// URL encode message
						$args['msg'] = urlencode($args['msg']);
						
						// Actually redirect
						wp_redirect(add_query_arg($args, $this->menu_page_url));
					}
					
					exit;
					break;
				default:
					break;
			}
		}
		if (isset($_GET[$this->action])) {
			switch ($_GET[$this->action]) {
				case 'delete_thumb':
					// Should we be here?
					if (!check_admin_referer('delete_thumb') && current_user_can('manage_options')) {
						wp_die(__("You shouldn't be here", 'cf_mini_blog')); // Nope
					}
					
					$mini_blog_id = (int) $_GET['id'];
					
					// Keep our term name around for the message
					$term_name = get_term_field('name', $mini_blog_id, $this->taxonomy);
					
					if ($this->delete_mini_blog_meta($mini_blog_id, 'thumbnail')) {
						$r = 'success';
						$args = array(
							'r' => $r,
							'msg' => urlencode(sprintf($this->get_success_fail_string($r, 'delete_thumb'), $term_name)),
						);
					}
					else {
						$r = 'fail';
						$args = array(
							'r' => $r,
							'msg' => urlencode(sprintf($this->get_success_fail_string($r, 'delete_thumb'), $term_name)),
						);
					}
					// Stay on the same page we were on
					$args['paged'] 	= $this->get_pagenum();
					$args['id'] 	= $mini_blog_id;
					
					// Actually redirect
					wp_redirect(add_query_arg($args, $this->menu_page_url));
					exit;
					break;
				case 'delete':
				case 'activate':
				case 'deactivate':
					// Should we be here?
					if (!check_admin_referer('mini_blog_action') && current_user_can('manage_options')) {
						wp_die(__("You shouldn't be here", 'cf_mini_blog')); // Nope
					}
					
					// Sanitize what we're accepting
					$term_id = (int) $_GET['id'];
					$action = strip_tags(stripslashes($_GET[$this->action]));
					$func_name = $action.'_mini_blog';
					
					
					// Simple sanitize
					if (!method_exists($this, $func_name)) {
						wp_die(sprintf(__("Called a bad method: %s", 'cf_mini_blog'), 
							'CF_Mini_Blog::'.$func_name
						));
					}
					
					// Keep our term name around for the message
					$term_name = get_term_field('name', $term_id, $this->taxonomy);
					
					// Perform the actual action
					if ($this->$func_name($term_id)) {
						$r = 'success';
						$args = array(
							'r' => $r,
							'msg' => urlencode(sprintf($this->get_success_fail_string($r, $action), $term_name)),
						);
					}
					else {
						$r = 'fail';
						$args = array(
							'r' => $r,
							'msg' => urlencode(sprintf($this->get_success_fail_string($r, $action), $term_name)),
						);
					}
					
					// Stay on the same page we were on
					$args['paged'] = $this->get_pagenum();
					
					// Actually redirect
					wp_redirect(add_query_arg($args, $this->menu_page_url));
					exit;
					break;
				default:
					break;
			}
		}
	}
	
	
	/**
	 * Deletes a Mini-Blog's meta
	 *
	 * @param int $mini_blog_term_id 
	 * @param string $meta_field 
	 * @return bool
	 */
	public function delete_mini_blog_meta($mini_blog_term_id, $meta_field) {
		// Look up our Mini-Blog post-type so we can get the post meta
		$mini_blog = $this->get_mini_blog_post_type($mini_blog_term_id);
		if (!$mini_blog) { 
			return false;
		}
		delete_post_meta($mini_blog->ID, $meta_field);
		return true;
	}
	
	
	/**
	 * Gets the meta value for a Mini-Blog
	 *
	 * @param int $mini_blog_term_id 
	 * @param string $meta_field 
	 * @return mixed
	 */
	public function get_mini_blog_meta($mini_blog_term_id, $meta_field) {
		if (!$mini_blog_term_id || !$meta_field) {
			return false;
		}
		// Look up our Mini-Blog post-type so we can get the post meta
		$mini_blog = $this->get_mini_blog_post_type($mini_blog_term_id);
		if (!$mini_blog) { 
			return false;
		}
		return get_post_meta($mini_blog->ID, $meta_field, true);
	}
	
	public function get_mini_blogs_by_meta($meta_key, $meta_value) {
		$term_array = array();
		
		$args = array(
			'post_type' => $this->post_type,
			'meta_query' => array(
				array(
					'key' => $meta_key,
					'value' => $meta_value,
				),
			),
		);
 		$posts = get_posts($args);

		foreach ($posts as $post) {
			// Not the cleanest, but workable with what we have
			$mini_blog_id = str_replace('mini-blog-id-', '', $post->post_name);
			$term_array[] = (int) $mini_blog_id;
		}
		
		return $term_array;
	}
	
	/**
	 * Saves an array of meta to a Mini-Blog post type
	 *
	 * @param int $mini_blog_term_id 
	 * @param array $meta - (meta_key => meta_value)
	 * @return bool | WP_Error
	 */
	public function save_mini_blog_meta($mini_blog_term_id, $meta = array()) {
		$default_meta = array(
			'leaderboard_code' => '',
			'analytics_code' => '',
		);
		$meta = array_merge($default_meta, $meta);
		
		// Look up our Mini-Blog post-type so we can get the post meta
		$mini_blog = $this->get_mini_blog_post_type($mini_blog_term_id);
		
		// If we couldn't find an associated Mini-Blog, create one
		if (!$mini_blog) {
			$mini_blog = $this->create_mini_blog_post_type($mini_blog_term_id);
			if (is_wp_error($mini_blog)) {
				return $mini_blog;
			}
		}
		
		// Finally save the meta to the Mini-Blog post object
		foreach ($meta as $key => $val) {
			update_post_meta($mini_blog->ID, $key, $val);
		}
		
		// Let callers know we're good
		return true;
	}
	
	
	/**
	 * Returns the Mini-Blog custom post type object related to 
	 * the Mini-Blog taxonomy term ID
	 *
	 * @param int $id 
	 * @return bool | obj - False on failure, Post object on success
	 */
	private function get_mini_blog_post_type($id) {
		if (!$id) {
			return false;
		}
		
		$args = array(
			'post_type' => $this->post_type,
			'tax_query' => array(
				array(
					'taxonomy' 	=> $this->taxonomy,
					'field'		=> 'term_id',
					'terms' 	=> $id
				),
			),
		);
		$posts = get_posts($args);
		$post = empty($posts) ? false : array_shift($posts);
		return $post;
	}
	
	
	/**
	 * Creates the post type object for a Mini-Blog.  This houses the meta for the Mini-Blog
	 * such as ad/analytics codes, featured image, etc.
	 *
	 * @param int $id - Term ID for the Mini-Blog
	 * @return obj - WP_Error | Post Object
	 */
	private function create_mini_blog_post_type($id) {
		$args = array(
			'post_type' => $this->post_type,
			'post_status' => 'publish',
			'tax_input' => array(
				$this->taxonomy => $id
			),
			'post_content' => '',
			'post_title' => 'Mini-Blog ID: '.$id,
		);
		$post_id = wp_insert_post($args, true);
		
		if (is_wp_error($post_id)) {
			return new WP_Error($this->error_code, $post_id->get_error_message());
		}
		else if (!$post_id) {
			return new WP_Error($this->error_code, 'Could not create a post for term ID: '.$id);
		}
		return get_post($post_id);
	}
	
	
	/**
	 * One place for translatable success/fail messages
	 *
	 * @param string $success_or_fail - Either 'success' or 'fail'
	 * @param string $action 
	 * @return string - Translated and filtered message string
	 */
	private function get_success_fail_string($success_or_fail, $action) {
		$msg = '';
		$actions = array(
			'add_new_term' 	=> array(
				'success' 	=> __('Added Mini-Blog: %s.', 'cf_mini_blog'),
				'fail' 		=> __('Could not add Mini-Blog: %s', 'cf_mini_blog'),
			),
			'delete' 		=> array(
				'success' 	=> __('Deleted Mini-Blog: %s.', 'cf_mini_blog'),
				'fail'		=> __('Could not delete Mini-Blog: %s.', 'cf_mini_blog'),
			),
			'activate' 		=> array(
				'success' 	=> __('Activated Mini-Blog: %s.', 'cf_mini_blog'),
				'fail'		=> __('Could not activate Mini-Blog: %s.', 'cf_mini_blog'),
			),
			'deactivate' 	=> array(
				'success' 	=> __('Deactivated Mini-Blog: %s.', 'cf_mini_blog'),
				'fail'		=> __('Could not deactivate Mini-Blog: %s.', 'cf_mini_blog'),
			),
			'edit_mini_blog'=> array(
				'success'	=> __('Successfully saved Mini-Blog: %s.', 'cf_mini_blog'),
				'fail' 		=> __('Could not save Mini-Blog: %s.  Reason: %s.', 'cf_mini_blog'),
			),
			'file_upload' 	=> array(
				'success' 	=> __('Successfully uploaded image (%s) for Mini-Blog.', 'cf_mini_blog'),
				'fail'		=> __('Unable to upload image: %s.', 'cf_mini_blog'),
			),
			'delete_thumb' 	=> array(
				'success' 	=> __('Deleted attached image for Mini-Blog: %s.', 'cf_mini_blog'),
				'fail'		=> __('Could not delete image for Mini-Blog: %s.', 'cf_mini_blog'),
			),
			'save_settings' 	=> array(
				'success' 	=> __('Settings saved.', 'cf_mini_blog'),
				'fail'		=> __('Could not update settings.', 'cf_mini_blog'),
			),
		);
		if (isset($actions[$action]) && isset($actions[$action][$success_or_fail])) {
			$msg = $actions[$action][$success_or_fail];
		}
		return apply_filters('cfmb_success_fail_string', $msg, compact('success_or_fail', 'action'));
	}
	
	/**
	 * Gets the plugin's option, and then the setting inside the option
	 *
	 * @param string $setting 
	 * @return mixed array|string 
	 */
	public function get_setting($setting) {
		$option = get_option($this->option_name, $this->defaults);
		return $option[$setting];
	}
	
	/**
	 * Sets the plugin's option with an array key of the setting
	 *
	 * @param string $setting 
	 * @param mixed $value - Whatever it needs to be array or string, etc.
	 * @return void
	 */
	private function set_setting($setting, $value) {
		$option = get_option($this->option_name, $this->defaults);
		$option[$setting] = $value;
		return update_option($this->option_name, $option);
	}
	
	
	/**
	 * Returns all the active Mini-Blog IDs 
	 *
	 * @return array
	 */
	private function get_active_mini_blogs() {
		return (array) $this->get_setting('active_mini_blogs');
	}
	
	
	/**
	 * Returns all the inactive Mini-Blog IDs
	 *
	 * @return array
	 */
	public function get_inactive_mini_blogs() {
		$all = $this->get_mini_blog_ids();
		$actives = $this->get_active_mini_blogs();
		return array_diff($all, $actives);
	}
	
	
	/**
	 * Delete a Mini-Blog by ID
	 *
	 * @param int $term_id 
	 * @return bool
	 */
	private function delete_mini_blog($mini_blog_id) {
		$term = get_term($mini_blog_id, $this->taxonomy);
		
		// Remove it from the active blogs
		$this->deactivate_mini_blog($mini_blog_id);
		
		// Delete the Nav Menu
		wp_delete_nav_menu(sprintf(__('Mini-Blog: %s', 'cf_mini_blog'), $term->name));

		// Finally delete the Mini-Blog Term
		$r = wp_delete_term($mini_blog_id, $this->taxonomy);
		
		// Make sure it got removed successfully
		if (is_wp_error($r) || empty($r)) {
			return false;
		}
		
		return true;
	}
	
	
	/**
	 * Activate Mini-Blog by ID
	 *
	 * @param int $mini_blog_id 
	 * @return bool
	 */
	public function activate_mini_blog($mini_blog_id) {
		$actives = $this->get_active_mini_blogs();
		
		// If we're not in the active mini-blogs, add this
		if (!in_array($mini_blog_id, $actives)) {
			$actives[] = intval($mini_blog_id);
		}
		
		return $this->set_setting('active_mini_blogs', $actives);
	}
	
	
	/**
	 * Deactivate Mini-Blog by ID
	 *
	 * @param int $mini_blog_id 
	 * @return bool
	 */
	private function deactivate_mini_blog($mini_blog_id) {
		$actives = $this->get_active_mini_blogs();
		
		// Seek and destroy
		$key = array_search(intval($mini_blog_id), $actives);
		if ($key !== false) {
			unset($actives[$key]);
		}
		
		return $this->set_setting('active_mini_blogs', $actives);
	}
	
	
	/**
	 * Is the passed Mini-Blog active
	 *  
	 * @param obj $mini_blog - Taxonomy Term
	 * @return bool
	 */
	public function is_mini_blog_active($mini_blog) {
		return in_array(intval($mini_blog->term_id), $this->get_active_mini_blogs());
	}
	
	
	/**
	 * Returns various action links based on the Mini-Blog Term passed
	 *
	 * @param obj $mini_blog - Taxonomy Term
	 * @return string - HTML of the action links
	 */
	function get_action_links($mini_blog) {
		$actions = array(
			'edit' => __('Edit', 'cf_mini_blog'),
		);
		
		// Conditionally add the (de)activate actions
		if ($this->is_mini_blog_active($mini_blog)) {
			$actions['deactivate'] = __('Deactivate', 'cf_mini_blog');
		} 
		else {
			$actions['activate'] = __('Activate', 'cf_mini_blog');
		}
		
		// Add the delete action to the end of the $actions
		$actions['delete'] = __('Delete', 'cf_mini_blog');
		
		$links = array();
		foreach ($actions as $action => $text) {
			switch ($action) {
				case 'edit':
					$links[] = sprintf('<a href="%s" class="edit_mb" data-mbid="%s">%s</a>',
						add_query_arg(array($this->action => 'edit_mb', 'id' => $mini_blog->term_id), admin_url()),
						esc_attr($mini_blog->term_id),
						esc_html($text)
					);
					break;
				default:
					$links[] = sprintf('<a href="%s" title="%s">%s</a>', 
						wp_nonce_url(add_query_arg(array($this->action => $action, 'id' => $mini_blog->term_id), admin_url()), 'mini_blog_action'),
						esc_attr($text),
						esc_html($text)
					);
					break;
			}
		}
		return implode(' | ', $links);
	}
	
	
	/**
	 * Populate the URLs for the various columns (sidebar, menu, etc.)
	 *
	 * @param string $column_name 
	 * @param string $item 
	 * @return void
	 */
	function get_manage_url($column_name, $item) {
		switch ($column_name) {
			case 'sidebar':
				$url = admin_url('widgets.php');
				break;
			case 'menu':
				$url = admin_url('nav-menus.php');
				break;
			default:
				$url = '';
				break;
		}
		return $url;
	}
	
	/**
	 * Modify the home page posts, to exclude any of our active Mini-Blogs that 
	 * selected to have their posts removed from the home page loop
	 *
	 * @param obj $wp_query - Current WP_Query
	 * @return void - Modifies the WP_Query obj
	 */
	function maybe_filter_home_page_posts($wp_query) {
		global $wp_rewrite;
		// Only do something on the home page
		if ($wp_query->is_home()) {
			$active_mini_blogs = $this->get_active_mini_blogs();
			$excludes = array();
			foreach ($active_mini_blogs as $mb_term_id) {
				if ($this->get_mini_blog_meta($mb_term_id, 'exclude_on_home')) {
					$excludes[] = $mb_term_id;
				}
			}
			
			// If we have excludes, then change the wp_query
			if (!empty($excludes)) {
				// Get any current tax queries
				$current_tax_queries = $wp_query->get('tax_query');
				$current_tax_queries = empty($current_tax_queries) ? array() : $current_tax_queries;
				
				// Add in our excludes
				$current_tax_queries[] = array(
					'taxonomy' => $this->taxonomy,
					'operator' => 'NOT IN',
					'terms' => $excludes, // an array, not string
				);
				$wp_query->set('tax_query', $current_tax_queries);
			}
		}
	}
}
CF_Mini_Blog::factory()->add_actions();

?>
