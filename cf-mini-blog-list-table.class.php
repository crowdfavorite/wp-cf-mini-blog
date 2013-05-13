<?php
class CF_Mini_blog_List_Table extends WP_List_Table {
	function __construct() {
		$this->controller = CF_Mini_Blog::factory();
		$this->i18n = $this->controller->i18n;
		
		$this->per_page = $this->get_items_per_page('cf_mini_blog_items_per_page', 10); // number of items per table page
		
		$args = array(
			'plural' 	=> __('Mini-Blogs', $this->i18n),
			'singular' 	=> __('Mini-Blog', $this->i18n),
			'ajax'		=> true,
		);

		if (method_exists(parent, 'WP_List_Table')) {
			parent::WP_List_Table($args);
		}
		else {
			parent::__construct($args);
		}
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
					$manage_str = sprintf(__('%s &mdash; %s', $this->i18n), $this->get_column_name($column_name), $item->name);
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
			'id' 		=> __('ID', $this->i18n),
			'name' 		=> __('Mini-Blog', $this->i18n),
			'actions' 	=> __('Actions', $this->i18n), // deactivate / activate / delete
			'sidebar' 	=> __('Sidebar', $this->i18n),
			'menu'		=> __('Menu', $this->i18n),
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
								<label for="image-<?php echo $item->term_id; ?>"><?php echo esc_html('Custom Image', $this->i18n); ?></label>
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
										<img src="<?php echo esc_url(wp_get_attachment_thumb_url($thumb_id)); ?>" /> <a class="delete-link" href="<?php echo esc_url($delete_url); ?>"><?php _e('Remove Image', $this->i18n); ?></a>
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
							<p class="cfmb-inp-group">
								<label for="leaderboard_code-<?php echo $item->term_id; ?>"><?php echo apply_filters(cfmb_leaderboard_label, esc_html('Leaderboard Ad Code', $this->i18n)); ?></label>
								<textarea name="leaderboard_code-<?php echo $item->term_id; ?>" id="leaderboard_code-<?php echo $item->term_id?>"><?php echo esc_textarea($this->controller->get_mini_blog_meta($item->term_id, 'leaderboard_code')); ?></textarea>
							</p>
							<p class="cfmb-inp-group">
								<label for="analytics_code-<?php echo $item->term_id; ?>"><?php echo esc_html('Analytics Code', $this->i18n); ?></label>
								<textarea name="analytics_code-<?php echo $item->term_id; ?>" id="analytics_code-<?php echo $item->term_id?>"><?php echo esc_textarea($this->controller->get_mini_blog_meta($item->term_id, 'analytics_code')); ?></textarea>
							</p>
							<p class="cfmb-inp-group label-inline">
								<input type="checkbox" value="1" name="exclude_on_home-<?php echo $item->term_id; ?>" id="exclude_on_home-<?php echo $item->term_id?>"<?php checked(1, $this->controller->get_mini_blog_meta($item->term_id, 'exclude_on_home')); ?> />
								<label for="exclude_on_home-<?php echo $item->term_id; ?>"><?php echo esc_html('Exclude posts from Home Page?', $this->i18n); ?></label>
							</p>
						</div>
					</div>
					
					<?php do_action('cfmb_row_after_details', $item, $this); ?>
					<p class="submit inline-edit-save">
						<button type="submit" class="button-primary alignright edit-single-mb" data-form_id="<?php echo esc_attr($form_id); ?>"><?php _e('Save', $this->i18n); ?></button>
						<button class="button-secondary cancel-single-mb alignleft" data-form_id="<?php echo esc_attr($form_id); ?>" data-RowID="<?php echo esc_attr($item->term_id); ?>"><?php _e('Cancel', $this->i18n); ?></button>
						<br class="clear" />
					</p>
				</form>
			</td>
		</tr>
		<?php
	}
}
?>