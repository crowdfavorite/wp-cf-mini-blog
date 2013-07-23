<?php
class CFMB_Template_Helper {
	/**
	 * @param $ins object (optional) an instance of CF_Mini_Blog
	 * @param $queried_object object (optional) a term or post
	 */
	public function __construct($ins = null, $queried_object = null) {
		if (!($ins instanceof CF_Mini_Blog)) {
			$ins = CF_Mini_Blog::factory();
		}
		$this->ins = $ins;

		if (!$queried_object) {
			$queried_object = get_queried_object();
		}
		$this->queried_object = $queried_object;
		$this->term_sidebar_map = $this->ins->term_sidebar_map;
		$this->term_menu_map = $this->ins->term_menu_map;
	}

	/**
	 * Pass in post or term.
	 * Will tell you if the queried object is a post or term.
	 * @return bool
	 */
	public function queried_object_quacks_like($type) {
		$is_a_duck = false;

		if ($this->queried_object) {
			switch ($type) {
				case 'post':
					$is_a_duck = property_exists($this->queried_object, 'ID');
					break;
				case 'term':
					$is_a_duck = property_exists($this->queried_object, 'term_id');
					break;
			}
		}

		return $is_a_duck;
	}

	public function get_related_terms($only_active_terms = true) {
		$related_terms = array();

		// If it's a post
		if ($this->queried_object_quacks_like('post')) {
			$terms = get_the_terms($this->queried_object->ID, $this->ins->taxonomy);
			if ($terms && !is_wp_error($terms)) {
				
				// Make sure that the primary term is added to the first item of the array.
				// All helper functions assume this to be the 'only' mini blog
				if ($this->ins->get_setting('select_multiple')) {
					$primary_found = false;
					$primary_term = get_post_meta($this->queried_object->ID, $this->ins->primary_meta_key, true);
					foreach ($terms as $term_key => $term) {
						// Add the primary miniblog to the front
						if ($primary_term == $term->term_id) {
							unset($terms[$term_key]);
							array_unshift($terms, $term);
							$primary_found = true;
							break;
						}
					}
					
					if (in_array($primary_term, $this->ins->get_inactive_mini_blogs())) {
						// This is primarily used for display purposes, so setting it empty
						// means display none
						$terms = array();
					}
				}

				/* Double-check to see if the terms gotten are indeed from the
				correct taxonomy. WordPress will send back all terms if
				there are no terms, due to some weird SQL thing */
				$temp_terms = $terms;
				$first = array_shift($temp_terms);
				if ($first->taxonomy == $this->ins->taxonomy) {
					$related_terms = $terms;
				}
			}
		}
		// If it's a term and in our taxonomy...
		elseif (
			$this->queried_object_quacks_like('term')
			&& ($this->queried_object->taxonomy == $this->ins->taxonomy)
		) {
			$related_terms = array($this->queried_object);
		}

		// Make sure the terms that are related are also active
		if ($only_active_terms && count($related_terms)) {
			$tmp = array();
			foreach ($related_terms as $term) {
				if ($this->ins->is_mini_blog_active($term)) {
					$tmp[] = $term;
				}
			}
			$related_terms = $tmp;
		}
		return $related_terms;
	}

	/**
	 * Get back an array of sidebars related to the current query.
	 * Since multiple terms can potentially belong to a query, this function
	 * passes back an array of related sidebars.
	 *
	 * It's related if one of these things is true:
	 * - It's a singular query and the post contains the term for the sidebar
	 * - It's a taxonomy archive query and the taxonomy archive term matches
	 *   a sidebar term.
	 * AND the sidebar that matches these conditions is in use.
	 *
	 * @return array
	 */
	public function get_related_sidebars() {

		if (!isset($this->related_sidebars) || !$this->related_sidebars) {
			$matches = array();
			$map = $this->term_sidebar_map;

			$related_terms = $this->get_related_terms();
			foreach ($related_terms as $term) {
				if (
					array_key_exists($term->slug, $map)
					&& is_active_sidebar($map[$term->slug])
				) {
					$matches[$term->slug] = $map[$term->slug];
				}
			}

			// Memoize
			$this->related_sidebars = $matches;
		}

		return $this->related_sidebars;
	}

	public function get_all_mini_blogs($args = array(), $exclude_inactive = true) {
		if ($exclude_inactive) {
			$args['exclude'] = $this->ins->get_inactive_mini_blogs();
		}
		return $this->ins->get_mini_blogs($args);
	}

	/**
	 * Boolean formulation of get_related_sidebars().
	 * @return bool
	 */
	public function has_related_sidebar() {
		return (bool) count($this->get_related_sidebars());
	}

	/**
	 * Choose a sidebar for the current page based on related.
	 */
	public function choose_dynamic_sidebar() {
		$sidebars = $this->get_related_sidebars();
		if (!count($sidebars)) {
			return;
		}
		$sidebars_temp = $sidebars;
		dynamic_sidebar(array_shift($sidebars_temp));
	}

	public function get_related_menu_id() {
		$terms = $this->get_related_terms();
		if (!$terms) {
			return '';
		}
		$term = array_shift($terms);
		if (array_key_exists($term->slug, $this->term_menu_map)) {
			return $this->term_menu_map[$term->slug];
		}
	}

	public function get_attachment_image($size = 'thumbnail', $attr = '') {
		$attachment_id = $this->get_related_meta('thumbnail');
		if ($attachment_id) {
			return wp_get_attachment_image($attachment_id, $size, false, $attr);
		}
		return '';
	}

	public function get_masthead($size = '') {
		$terms = $this->get_related_terms();
		$term = array_shift($terms);
		if ($term) {
			$taxonomy = $this->ins->taxonomy;
			$term_url = get_term_link($term, $taxonomy);
			$image = $this->get_attachment_image($size);

			if ($image) {
				return '<a class="masthead-banner" href="'.$term_url.'">' . $image . '</a>';
			}
		}
		return '';
	}

	public function mini_blog_url() {
		$terms = $this->get_related_terms();
		$term = array_shift($terms);
		if ($term) {
			return get_term_link($term, $term->taxonomy);
		}
		return '';
	}

	public function mini_blog_link() {
		$terms = $this->get_related_terms();
		$term = array_shift($terms);
		if ($term) {
			$term_url = get_term_link($term, $term->taxonomy);
			return '<a href="'.esc_url($term_url).'">'.esc_html($term->name).'</a>';
		}
		return '';
	}

	public function mini_blog_feed_link_url() {
		$terms = $this->get_related_terms();
		$term = array_shift($terms);
		$feed_url = '';
		if ($term) {
			$feed_url = get_term_feed_link(
				$term->term_id,
				$this->ins->taxonomy,
				'rss2'
			);
		}
		return $feed_url;
	}

	/**
	 * Gets post meta related to current view
	 */
	public function get_related_meta($key) {
		$out = null;
		$terms = $this->get_related_terms();
		if (count($terms)) {
			$term = array_shift($terms);
			if ($term && $term->term_id) {
				$out = $this->ins->get_mini_blog_meta($term->term_id, $key);
			}
		}
		return $out;
	}

	public function get_leaderboard_code() {
		return $this->get_related_meta('leaderboard_code');
	}

	public function get_mobile_ad_code() {
		return $this->get_related_meta('mobile_ad_code');
	}

	public function get_analytics_code() {
		return $this->get_related_meta('analytics_code');
	}

	public function uses_dark_theme() {
		$meta = $this->get_related_meta('dark_theme');
		return $meta == "1";
	}

	public function uses_image_only_excerpts() {
		$meta = $this->get_related_meta('image_only_excerpts');
		return $meta == "1";
	}

	public function render_analytics_code() {
		echo $this->get_analytics_code();
	}
}
