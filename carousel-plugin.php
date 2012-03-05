<?php 

/**
This is being put on hold for now for budget reasons

Development Approach

- Have one chunk of HTML that has a list of all the blogs, and all the categories, and all the mini-blogs
- Move that HTML around via JS (it's a heavy piece -- several blogs, with lots of categories, and list of Mini-Blogs) to the row that's currently being edited
- Change properties on that chunk, so it knows which row it's currently in
- Ajax-add items to either a list or another wp_list_table inside the row, that have the blog ID, the category (name & id), and the Mini-Blog (name & id)
- Actual data storage comes from that list/table
- Items can be removed from that table via delete link

 */

class NGS_Network_Carousel_Table_Plugin {
	/**
	 * This gets constructed only at the load of the Mini-Blog admin page.
	 *
	 */
	function __construct() {
		// Ensure we have the proper function available
		if (!function_exists('cf_get_blog_list')) {
			return false;
		}

		/* Structure:
		array(
			[blog_id] => array(
				'cats' => array(),
			),
		);
		*/
		$this->blogs = cf_get_blog_list(null, 'all');
		foreach ($this->blogs as $blog) {
			$this->build_blogs_array($blog['blog_id']);
			
		}
		
	}
	
	function add_actions() {
		// Run this function for each table row
		add_action('cfmb_row_after_details', array($this, 'output_table_row_section'));
	}
	
	function build_blogs_array($blog_id) {
		$blog_details = array(
			'cats' => $this->get_blog_categories($blog_id),
		);
		$this->blogs[$blog_id] = apply_filters('ngs_carousel_table_plugin_get_blog_info', $blog_details, $blog_id);
	}
	
	function get_blog_categories($blog_id) {
		switch_to_blog($blog_id);
		$terms = get_terms('category', array(
			'hide_empty' => false
		));
		restore_current_blog();
		return $terms;
	}
	
	function get_blog_dropdown() {
		?>
		<select name="ngs_carousel_blog_dropdown">
			
		</select>
		<?php
	}
	
	function output_table_row_section($item, $table) {
		$row_id = $item->term_id;
		?>
		<div class="carousel-selections">
			<?php
			foreach ($this->blogs as $blog_id => $taxonomy) {
				$input_prefix = 'ngs_carousel_items-'.$row_id;
				switch_to_blog($blog_id);
					$blog_name = get_bloginfo('name');
					?>
					<div class="carousel-blog-item">
						<h2><?php echo esc_html($blog_name); ?></h2>
						<?php
						wp_dropdown_categories(array(
							'hide_empty' 	=> false, // @TODO - set to true
							// 'name' 			=> $input_prefix.
						));
						?>
					</div><!-- /carousel-blog-item -->
					<?php
				restore_current_blog();
			}
			?>
		</div><!-- /carousel-selections -->
		<?php
	}
}
$plugin = new NGS_Network_Carousel_Table_Plugin;
$plugin->add_actions();
?>