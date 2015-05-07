<?php
class CF_Mini_blog_List_Table extends WP_List_Table {
	function __construct() {

		// 4.0.0 introduced the requirement of naming private properties for getter and setters
		$this->compat_fields = array('controller', 'meta_output', 'per_page');

		$this->controller = CF_Mini_Blog::factory();
		$this->per_page = $this->get_items_per_page('cf_mini_blog_items_per_page', 10); // number of items per table page
		$this->meta_output = apply_filters('cf_miniblog_meta_output', array(
			'leaderboard_code' => array(
				'slug' => 'leaderboard_code',
				'label' => __('Leaderboard Ad Code', 'cf_mini_blog'),
				'type' => 'textarea',
			),
			'analytics_code' => array(
				'slug' => 'analytics_code',
				'label' => __('Analytics Code', 'cf_mini_blog'),
				'type' => 'textarea',
			),
			'mobile_ad_code' => array(
				'slug' => 'mobile_ad_code',
				'label' => __('Mobile Ad Code', 'cf_mini_blog'),
				'type' => 'textarea',
			),
			'exclude_on_home' => array(
				'slug' => 'exclude_on_home',
				'label' => __('Exclude posts from Home Page?', 'cf_mini_blog'),
				'type' => 'checkbox',
			),
			'dark_theme' => array(
				'slug' => 'dark_theme',
				'label' => __('Use Dark Theme?', 'cf_mini_blog'),
				'type' => 'checkbox',
			),
			'image_only_excerpts' => array(
				'slug' => 'image_only_excerpts',
				'label' => __('Use Image-only excerpts?', 'cf_mini_blog'),
				'type' => 'checkbox',
			),
		));

		$args = array(
			'plural' 	=> __('Mini-Blogs', 'cf_mini_blog'),
			'singular' 	=> __('Mini-Blog', 'cf_mini_blog'),
			'ajax'		=> true,
		);


		/**
		 * Provisional code removal below due to WP-Engine's request for removal due to a PHP bug:
		 *
		 * The cf-mini-blog plugin that you are using is exercising a bug in PHP + Zend Guard Loader.
		 * The bug is currently open with PHP and can be found here https://bugs.php.net/bug.php?id=51425.
		 */

		//if (method_exists(parent, 'WP_List_Table')) {
		//	parent::WP_List_Table($args);
		//}
		//else {
			parent::__construct($args);
		//}
	}

	/**
	 * Return the friendly (i18n'd) name for a column
	 *
	 * @param string $slug
	 * @return string
	 */
	function get_column_name($slug) {
		$columns = $this->get_columns();
		if (is_array($columns) && isset($columns[$slug])) {
			return $columns[$slug];
		}
		return '';
	}

	/**
	 * Returns the information for the specific item (row)
	 * for the spec'd column.
	 *
	 * @param obj $item - Mini-Blog Taxonomy Term
	 * @param string $column_name
	 * @return string - HTML for the value of the table cell
	 */
	function column_default($item, $column_name) {
		switch ($column_name) {
			case 'name':
				$content = $item->name;
				break;
			case 'actions':
				$content = $this->controller->get_action_links($item);
				break;
			case 'id':
				$content = '<span class="mini-blog-id" data-mini-blog-id="'.$item->term_id.'" />'.$item->term_id.'</span>';
				break;
			case 'sidebar':
			case 'menu':
				$content = '';
				if ($this->controller->is_mini_blog_active($item)) {
					$manage_str = sprintf(__('%s &mdash; %s', 'cf_mini_blog'), $this->get_column_name($column_name), $item->name);
					$content = sprintf('<a href="%s" title="%s">%s</a>',
						$this->controller->get_manage_url($column_name, $item),
						esc_attr($manage_str),
						esc_html($manage_str)
					);
				}
				break;
		}
		return apply_filters('cfmb_table_column_content', $content, compact('item', 'column_name'));
	}

	/**
	 * Return *all* columns (even if hidden)
	 *
	 * @return array
	 */
	function get_columns() {
		$columns = array(
			'id' 		=> __('ID', 'cf_mini_blog'),
			'name' 		=> __('Mini-Blog', 'cf_mini_blog'),
			'actions' 	=> __('Actions', 'cf_mini_blog'), // deactivate / activate / delete
			'sidebar' 	=> __('Sidebar', 'cf_mini_blog'),
			'menu'		=> __('Menu', 'cf_mini_blog'),
		);
		return $columns;
	}

	/**
	 * Return the slugs of the hidden columns
	 *
	 * @return array
	 */
	function get_hidden_columns() {
		$hidden = array(
			'id',
		);
		return $hidden;
	}

	/**
	 * Return the slugs of the sortable columns
	 *
	 * @return array
	 */
	function get_sortable_columns() {
		return array();
	}

	/**
	 * Big 'ol function to get the table's data, so the
	 * WP_List_Table class can do its magic.
	 *
	 * @return void
	 */
	function prepare_items() {
		// Define column headers
		$this->_column_headers = array(
			$this->get_columns(),
			$this->get_hidden_columns(),
			$this->get_sortable_columns(),
		);

		// Get our current page
		$current_page = $this->get_pagenum();
		$offset = ($this->per_page * ($current_page - 1)); // Set our offset

		// Prep args for get_terms()
		$args = array(
			'orderby' 		=> 'name',
			'order'			=> 'asc',
		);

		// Get all the terms, we'll cut out the unwanteds later
		$this->items = $this->controller->get_mini_blogs($args);

		// Store our total count before slicing the items up
		$total_items = count($this->items);

		// Cut out the items we want
		if (count($this->items)) {
			$this->items = array_slice($this->items, $offset, $this->per_page);
		}

		// WP_List_Table method for pagination
		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page' => $this->per_page
		));
	}

	/**
	 * Generates content for a single row of the table
	 *
	 * @since 3.1.0
	 * @access protected
	 *
	 * @param object $item The current item
	 */
	function single_row($item) {
		static $alternate = '';
		$alternate = ($alternate == '') ? ' alternate' : '';
		$form_id = uniqid('cfmb-');

		if (isset($_GET['id']) && !empty($_GET['id']) && $_GET['id'] == $item->term_id) {
			$first_style = ' style="display:none;"';
			$second_style = '';
		}
		else {
			$first_style = '';
			$second_style = ' style="display:none;"';
		}
		?>
		<tr class="summary<?php echo $alternate; ?>" data-mbrowview="<?php echo esc_attr($item->term_id); ?>"<?php echo $first_style; ?>>
			<?php echo $this->single_row_columns($item); ?>
		</tr>

		<tr class="edit<?php echo $alternate; ?> inline-edit-row" data-mbrowedit="<?php echo esc_attr($item->term_id); ?>"<?php echo $second_style; ?>>
			<td colspan="<?php echo $this->get_column_count(); ?>">
				<div id="<?php echo esc_attr('result-'.$item->term_id); ?>"></div>
				<form action="<?php echo esc_url(admin_url()); ?>" method="post" id="<?php echo esc_attr($form_id); ?>" data-RowID="<?php echo esc_attr($item->term_id); ?>" enctype="multipart/form-data">
					<?php wp_nonce_field('edit_mb', '_edit_mb'); ?>
					<input type="hidden" name="<?php echo $this->controller->action; ?>" value="edit_mb" />
					<input type="hidden" name="id" id="id-<?php echo $item->term_id; ?>" value="<?php echo $item->term_id; ?>" />

					<div class="cfmb-inline-edit-col-d">
						<div class="inline-edit-col">
							<h4><?php echo $item->name; ?></h4>
							<p class="cfmb-inp-group">
								<label for="image-<?php echo $item->term_id; ?>"><?php echo esc_html('Custom Image', 'cf_mini_blog'); ?></label>
								<?php
								$thumb_id = $this->controller->get_mini_blog_meta($item->term_id, 'thumbnail');
								if (!empty($thumb_id)) {
									$args = array(
										'id' 						=> $item->term_id,
										$this->controller->action 	=> 'delete_thumb',
									);
									$delete_url = wp_nonce_url(add_query_arg($args, admin_url()), 'delete_thumb');
									?>
									<span class="cfmb-edit-img">
										<img src="<?php echo esc_url(wp_get_attachment_thumb_url($thumb_id)); ?>" /> <a class="delete-link" href="<?php echo esc_url($delete_url); ?>"><?php _e('Remove Image', 'cf_mini_blog'); ?></a>
										<input type="hidden" name="image-<?php echo $item->term_id; ?>" id="image-<?php echo $item->term_id; ?>" value="<?php echo esc_attr($thumb_id); ?>" />
									</span>
									<?php
								}
								else {
									?>
									<input type="file" name="image-<?php echo $item->term_id; ?>" id="image-<?php echo $item->term_id; ?>" value="" />
									<?php
								}
								?>
							</p>
						</div>
					</div>
					<div class="cfmb-inline-edit-col-e">
						<div class="inline-edit-col">
					<?php
						foreach ($this->meta_output as $data) {
							$this->_input_markup($data, $item);
						}
					?>
						</div>
					</div>

					<?php do_action('cfmb_row_after_details', $item, $this); ?>
					<p class="submit inline-edit-save">
						<button type="submit" class="button-primary alignright edit-single-mb" data-form_id="<?php echo esc_attr($form_id); ?>"><?php _e('Save', 'cf_mini_blog'); ?></button>
						<button class="button-secondary cancel-single-mb alignleft" data-form_id="<?php echo esc_attr($form_id); ?>" data-RowID="<?php echo esc_attr($item->term_id); ?>"><?php _e('Cancel', 'cf_mini_blog'); ?></button>
						<br class="clear" />
					</p>
				</form>
			</td>
		</tr>
		<?php
	}

	function _input_markup($data, $item) {
		if (!isset($data['type']) || !isset($data['slug']) || !isset($data['label']) || !method_exists($this, '_input_'.$data['type'])) {
			return;
		}
		$function_name = '_input_'.$data['type'];

		$this->$function_name($data, $item);
	}

	function _input_textarea($data, $item) {
		if (!isset($data['slug']) || !isset($data['label'])) {
			return;
		}
		$slug = trim($data['slug']);
		$label = trim($data['label']);
?>
			<p class="cfmb-inp-group">
				<label for="<?php echo esc_attr($slug.'-'.$item->term_id); ?>"><?php echo esc_html($label); ?></label>
				<textarea name="<?php echo esc_attr($slug.'-'.$item->term_id); ?>" id="<?php echo esc_attr($slug.'-'.$item->term_id); ?>"><?php echo esc_textarea($this->controller->get_mini_blog_meta($item->term_id, $slug)); ?></textarea>
			</p>
<?php
	}

	function _input_text($data, $item) {
		if (!isset($data['slug']) || !isset($data['label'])) {
			return;
		}
		$slug = trim($data['slug']);
		$label = trim($data['label']);

?>
		<p class="cfmb-inp-group label-inline">
			<label for="<?php echo esc_attr($slug.'-'.$item->term_id); ?>"><?php echo $label; ?></label>
			<input type="text" name="<?php echo esc_attr($slug.'-'.$item->term_id); ?>" id="<?php echo esc_attr($slug.'-'.$item->term_id); ?>" value="<?php echo esc_attr($this->controller->get_mini_blog_meta($item->term_id, $slug)); ?>" />
		</p>
<?php
	}

	function _input_checkbox($data, $item) {
		if (!isset($data['slug']) || !isset($data['label'])) {
			return;
		}

		$slug = trim($data['slug']);
		$label = trim($data['label']);
?>
		<p class="cfmb-inp-group label-inline">
			<input type="checkbox" value="1" name="<?php echo esc_attr($slug.'-'.$item->term_id); ?>" id="<?php echo esc_attr($slug.'-'.$item->term_id); ?>"<?php checked(1, $this->controller->get_mini_blog_meta($item->term_id, $slug)); ?> />
			<label for="<?php echo esc_attr($slug.'-'.$item->term_id); ?>"><?php echo $label; ?></label>
		</p>
<?php
	}

	function _input_hidden($data, $item) {
		if (!isset($data['slug']) || !isset($data['label'])) {
			return;
		}
?>
	<input type="hidden" value="<?php echo esc_attr($this->controller->get_mini_blog_meta($item->term_id, $slug)); ?>" name="<?php echo esc_attr($slug.'-'.$item->term_id); ?>" />
<?php
	}
}
