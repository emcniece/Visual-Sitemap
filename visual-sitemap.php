<?php
/**
 * @package Visual_Sitemap
 * @version 0.9
 */
/*
Plugin Name: Visual Sitemap
Plugin URI: http://wordpress.org/plugins/visual-sitemap/
Description: Display an interactive visual sitemap of pages, tags, and categories in admin.
Author: ThemeBoy
Version: 0.9
Author URI: http://themeboy.com/
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Plugin setup
 *
 * @since 0.9
*/
class Visual_Sitemap {

	/**
	 * Visual Sitemap Constructor.
	 * @access public
	 */
	public function __construct() {

		// Define constants
		$this->define_constants();

		// Hooks
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'admin_styles' ) );
	}

	/**
	 * Define constants
	*/
	private function define_constants() {
		if ( !defined( 'VISUAL_SITEMAP_VERSION' ) )
			define( 'VISUAL_SITEMAP_VERSION', '0.9' );

		if ( !defined( 'VISUAL_SITEMAP_URL' ) )
			define( 'VISUAL_SITEMAP_URL', plugin_dir_url( __FILE__ ) );

		if ( !defined( 'VISUAL_SITEMAP_DIR' ) )
			define( 'VISUAL_SITEMAP_DIR', plugin_dir_path( __FILE__ ) );
	}

	/**
	 * Init plugin when WordPress Initialises.
	 */
	public function init() {
		// Set up localisation
		$this->load_plugin_textdomain();

		// Set up shortcode
		add_shortcode( 'visual-sitemap', array(&$this, 'sitemap_content') );
	}

	/**
	 * Load Localisation files.
	 *
	 * Note: the first-loaded translation file overrides any following ones if the same translation is present
	 */
	public function load_plugin_textdomain() {
		$locale = apply_filters( 'plugin_locale', get_locale(), 'visual-sitemap' );
		
		// Global + Frontend Locale
		load_plugin_textdomain( 'visual-sitemap', false, plugin_basename( dirname( __FILE__ ) . "/languages" ) );
	}

	/**
	 * Add menu item
	 */
	public function admin_menu() {
		global $menu;

		add_menu_page( __( 'Sitemap', 'visual-sitemap' ), __( 'Sitemap', 'visual-sitemap' ), 'manage_categories', 'visual-sitemap', array( $this, 'page_content' ), 'dashicons-networking' );
	}

	/**
	 * Shortcode handler
	 */
	public function sitemap_content(){
		$output .= '<div class="visual-sitemap">';

		if ( ! isset( $_GET['taxonomy'] ) ): // Pages

			$pages = get_pages( array( 'sort_column' => 'menu_order' ) );
			$child_pages = array();

			// Loop through pages to find child pages
			foreach ( $pages as $index => $page ):
				if ( $page->post_parent ):
					if ( ! array_key_exists( $page->post_parent, $child_pages ) ) $child_pages[ $page->post_parent ] = array();
					$child_pages[ $page->post_parent ][] = $page;
					unset( $pages[ $index ] );
				endif;
			endforeach;

			$object = get_post_type_object( 'page' );
			$category = get_taxonomy( 'category' );
			$tag = get_taxonomy( 'post_tag' );


			$output .= '<ul class="vs-utility">
				<li><a class="button" href="'. esc_url( admin_url( add_query_arg( array( 'page' => 'visual-sitemap', 'taxonomy' => 'category' ), 'admin.php' ) ) ) .'">'. $category->labels->name.'</a></li>
				<li><a class="button" href="'. esc_url( admin_url( add_query_arg( array( 'page' => 'visual-sitemap', 'taxonomy' => 'post_tag' ), 'admin.php' ) ) ) .'">'. $tag->labels->name .'</a></li>
			</ul>

			<ul class="vs-primary col'. (max( sizeof( $pages ), 1 ) + 1).'">
				<li class="vs-home"><a class="button disabled">'. get_bloginfo( 'name' ).'<span class="dashicons dashicons-admin-home"></span></a></li>';
			if ( sizeof( $pages ) ):
				foreach ( $pages as $page ):
					$output .= '<li><a class="button button-primary action" href="'. esc_url( get_edit_post_link( $page->ID ) ).'">'. wp_strip_all_tags( $page->post_title ).'<span class="dashicons dashicons-admin-page"></span></a>';
					if ( array_key_exists( $page->ID, $child_pages ) ): $children = $child_pages[ $page->ID ];
						$output .= '<ul>';
						foreach ( $children as $child ):
							$output .= '<li><a class="button action" href="'. esc_url( get_edit_post_link( $child->ID ) ).'">'. wp_strip_all_tags( $child->post_title ).'<span class="dashicons dashicons-admin-page wp-ui-text-notification"></span></a>';
							if ( array_key_exists( $child->ID, $child_pages ) ): $grandchildren = $child_pages[ $child->ID ];
								$output .= '<ul>';
								foreach ( $grandchildren as $grandchild ):
									$output .= '<li><a class="button action" href="'. esc_url( get_edit_post_link( $grandchild->ID ) ).'">'. wp_strip_all_tags( $grandchild->post_title ).'<span class="dashicons dashicons-admin-page wp-ui-text-highlight"></span></a></li>';
								endforeach;
								$output .= '</ul>';
							endif;
							$output .= '</li>';
						endforeach;
						$output .= '</ul>';
					endif;
					$output .= '</li>';
				endforeach;
			else:
				$output .= '<li><a class="button disabled">'. $object->labels->not_found.'</a></li>';
			endif;

			if( is_admin()):
				$output .= '<li><a class="button button-primary action" href="'. esc_url( admin_url( add_query_arg( array( 'post_type' => 'page' ), 'post-new.php' ) ) ).'">'. $object->labels->add_new_item.'<span class="dashicons dashicons-plus"></span></a></li>';
			endif;

			$output .= '</ul>';

		else: // Posts

			$page = get_post_type_object( 'page' );
			$taxonomy = $_GET['taxonomy'];
			if ( $taxonomy == 'category' ):
				$category = $object = get_taxonomy( 'category' );
				$tag = get_taxonomy( 'post_tag' );
				$terms = get_categories();
				$sub_terms = array();

				// Loop through categories to find subcategories
				foreach ( $terms as $index => $term ):
					if ( $term->parent ):
						if ( ! array_key_exists( $term->parent, $sub_terms ) ) $sub_terms[ $term->parent ] = array();
						$sub_terms[ $term->parent ][] = $term;
						unset( $terms[ $index ] );
					endif;
				endforeach;
			else:
				$terms = get_tags( array( 'parent' => 0 ) );
				$category = get_taxonomy( 'category' );
				$tag = $object = get_taxonomy( 'post_tag' );
			endif;


			$output .= '<ul class="vs-utility">';
			$output .= '<li><a class="button" href="'. esc_url( admin_url( add_query_arg( array( 'page' => 'visual-sitemap' ), 'admin.php' ) ) ).'">'. $page->labels->name.'</a></li>';
			if ( $taxonomy == 'category' ):
				$output .= '<li><a class="button" href="'. esc_url( admin_url( add_query_arg( array( 'page' => 'visual-sitemap', 'taxonomy' => 'post_tag' ), 'admin.php' ) ) ).'">'. $tag->labels->name.'</a></li>';
			else:
				$output .= '<li><a class="button" href="'.  esc_url( admin_url( add_query_arg( array( 'page' => 'visual-sitemap', 'taxonomy' => 'category' ), 'admin.php' ) ) ).'">'. $category->labels->name.'</a></li>';
			endif;
			$output .= '</ul>';

			$output .= '<ul class="vs-primary col'.  (max( sizeof( $terms ), 1 ) + 1).'">';
			$output .= '<li class="vs-home"><a class="button disabled">'. get_bloginfo( 'name' ).'<span class="dashicons dashicons-admin-home"></span></a></li>';
			if ( sizeof( $terms ) ):
				foreach ( $terms as $term ):
					$href = esc_url( admin_url( add_query_arg( array( 'action' => 'edit', 'taxonomy' => $taxonomy, 'tag_ID' => $term->term_id, 'post_type' => 'post' ), 'edit-tags.php' ) ) );
					$dashicon = ($taxonomy == 'category') ? 'category' : 'tag';
					$output .= '<li><a class="button button-primary action" href="'. $href.'">'. wp_strip_all_tags( $term->name ).'<span class="dashicons dashicons-'. $dashicon.'"></span></a>';

					if ( $taxonomy == 'category' )
						$posts = get_posts( array( 'category' => $term->term_id ) );
					else
						$posts = get_posts( array( 'tag' => $term->slug ) );

					if ( $posts ):
						$output .= '<ul>';
							foreach ( $posts as $post ):
								$output .= '<li><a class="button action" href="'. esc_url( get_edit_post_link( $post->ID ) ).'">'. wp_strip_all_tags( $post->post_title ).'<span class="dashicons dashicons-admin-page wp-ui-text-notification"></span></a></li>';
							endforeach;
						$output .= '</ul>';
					endif;
					if ( $taxonomy == 'category' && array_key_exists( $term->term_id, $sub_terms ) ): $subcategories = $sub_terms[ $term->term_id ];
						if ( $subcategories ):
							$output .= '<ul>';
							foreach ( $subcategories as $subcategory ):
								$output .= '<li><a class="button action" href="'. esc_url( admin_url( add_query_arg( array( 'action' => 'edit', 'taxonomy' => $taxonomy, 'tag_ID' => $subcategory->term_id, 'post_type' => 'post' ), 'edit-tags.php' ) ) ).'">'. wp_strip_all_tags( $subcategory->name ).'<span class="dashicons dashicons-'. $taxonomy == 'category' ? 'category' : 'tag'.' wp-ui-text-highlight"></span></a>';
								$posts = get_posts( array( $taxonomy => $subcategory->term_id ) );
								if ( $posts ):
									$output .= '<ul>';
									foreach ( $posts as $post ):
										$output .= '<li><a class="button action" href="'. esc_url( get_edit_post_link( $post->ID ) ).'">'. wp_strip_all_tags( $post->post_title ).'<span class="dashicons dashicons-admin-page wp-ui-text-notification"></span></a></li>';
									endforeach;
									$output .= '</ul>';
								endif;
								$output .= '</li>';
							endforeach;
							$output .= '</ul>';
						endif;
					endif;
					$output .= '</li>';
				endforeach;
			else:
				$output .= '<li><a class="button disabled">'. $object->labels->not_found.'</a></li>';
			endif;
			$output .= '<li><a class="button button-primary action" href="'. esc_url( admin_url( add_query_arg( array( 'taxonomy' => $taxonomy ), 'edit-tags.php' ) ) ).'">'. $object->labels->add_new_item.'<span class="dashicons dashicons-plus"></span></a></li>';
			$output .= '</ul>';

		endif;
		$output .= '</div>';

		return $output;
	}

	/**
	 * Page content
	 */
	public function page_content() {
		?>
		<div class="wrap visual-sitemap-wrap">
			<h2>
				<?php _e( 'Sitemap', 'visual-sitemap' ); ?>
			</h2>

			<?php echo $this->sitemap_content(); ?>

		</div>
		<p>
			<a href="http://wordpress.org/support/view/plugin-reviews/visual-sitemap?rate=5#postform">
				<?php _e( 'Love Visual Sitemap? Help spread the word by rating us 5★ on WordPress.org', 'visual-sitemap' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Enqueue styles
	 */
	public function admin_styles() {
		//$screen = get_current_screen();

		//if ( $screen->id == 'toplevel_page_visual-sitemap' )
			wp_enqueue_style( 'visual-sitemap-styles', VISUAL_SITEMAP_URL . '/assets/css/visual-sitemap.css', array(), VISUAL_SITEMAP_VERSION );
	}
}

new Visual_Sitemap();
