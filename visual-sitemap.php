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
	 * Page content
	 */
	public function page_content() {
		?>
		<div class="wrap visual-sitemap-wrap">
			<h2>
				<?php _e( 'Sitemap', 'visual-sitemap' ); ?>
			</h2>
			<div class="visual-sitemap">

				<?php if ( ! isset( $_GET['taxonomy'] ) ): // Pages ?>

					<?php
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
					?>

					<ul class="vs-utility">
						<li><a class="button" href="<?php echo esc_url( admin_url( add_query_arg( array( 'page' => 'visual-sitemap', 'taxonomy' => 'category' ), 'admin.php' ) ) ); ?>"><?php echo $category->labels->name; ?></a></li>
						<li><a class="button" href="<?php echo esc_url( admin_url( add_query_arg( array( 'page' => 'visual-sitemap', 'taxonomy' => 'post_tag' ), 'admin.php' ) ) ); ?>"><?php echo $tag->labels->name; ?></a></li>
					</ul>

					<ul class="vs-primary col<?php echo max( sizeof( $pages ), 1 ) + 1; ?>">
						<li class="vs-home"><a class="button disabled"><?php bloginfo( 'name' ); ?><span class="dashicons dashicons-admin-home"></span></a></li>
						<?php if ( sizeof( $pages ) ): ?>
							<?php foreach ( $pages as $page ): ?>
								<li><a class="button button-primary action" href="<?php echo esc_url( get_edit_post_link( $page->ID ) ); ?>"><?php echo wp_strip_all_tags( $page->post_title ); ?><span class="dashicons dashicons-admin-page"></span></a>
									<?php if ( array_key_exists( $page->ID, $child_pages ) ): $children = $child_pages[ $page->ID ]; ?>
										<ul>
											<?php foreach ( $children as $child ): ?>
												<li><a class="button action" href="<?php echo esc_url( get_edit_post_link( $child->ID ) ); ?>"><?php echo wp_strip_all_tags( $child->post_title ); ?><span class="dashicons dashicons-admin-page wp-ui-text-notification"></span></a>
													<?php if ( array_key_exists( $child->ID, $child_pages ) ): $grandchildren = $child_pages[ $child->ID ]; ?>
														<ul>
															<?php foreach ( $grandchildren as $grandchild ): ?>
																<li><a class="button action" href="<?php echo esc_url( get_edit_post_link( $grandchild->ID ) ); ?>"><?php echo wp_strip_all_tags( $grandchild->post_title ); ?><span class="dashicons dashicons-admin-page wp-ui-text-highlight"></span></a></li>
															<?php endforeach; ?>
														</ul>
													<?php endif; ?>
												</li>
											<?php endforeach; ?>
										</ul>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						<?php else: ?>
							<li><a class="button disabled"><?php echo $object->labels->not_found; ?></a></li>
						<?php endif; ?>
						<li><a class="button button-primary action" href="<?php echo esc_url( admin_url( add_query_arg( array( 'post_type' => 'page' ), 'post-new.php' ) ) ); ?>"><?php echo $object->labels->add_new_item; ?><span class="dashicons dashicons-plus"></span></a></li>
					</ul>

				<?php else: // Posts ?>

					<?php
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
					?>

					<ul class="vs-utility">
						<li><a class="button" href="<?php echo esc_url( admin_url( add_query_arg( array( 'page' => 'visual-sitemap' ), 'admin.php' ) ) ); ?>"><?php echo $page->labels->name; ?></a></li>
						<?php if ( $taxonomy == 'category' ): ?>
							<li><a class="button" href="<?php echo esc_url( admin_url( add_query_arg( array( 'page' => 'visual-sitemap', 'taxonomy' => 'post_tag' ), 'admin.php' ) ) ); ?>"><?php echo $tag->labels->name; ?></a></li>
						<?php else: ?>
							<li><a class="button" href="<?php echo esc_url( admin_url( add_query_arg( array( 'page' => 'visual-sitemap', 'taxonomy' => 'category' ), 'admin.php' ) ) ); ?>"><?php echo $category->labels->name; ?></a></li>
						<?php endif; ?>
					</ul>

					<ul class="vs-primary col<?php echo max( sizeof( $terms ), 1 ) + 1; ?>">
						<li class="vs-home"><a class="button disabled"><?php bloginfo( 'name' ); ?><span class="dashicons dashicons-admin-home"></span></a></li>
						<?php if ( sizeof( $terms ) ): ?>
							<?php foreach ( $terms as $term ): ?>
								<li><a class="button button-primary action" href="<?php echo esc_url( admin_url( add_query_arg( array( 'action' => 'edit', 'taxonomy' => $taxonomy, 'tag_ID' => $term->term_id, 'post_type' => 'post' ), 'edit-tags.php' ) ) ); ?>"><?php echo wp_strip_all_tags( $term->name ); ?><span class="dashicons dashicons-<?php echo $taxonomy == 'category' ? 'category' : 'tag'; ?>"></span></a>
									<?php
									if ( $taxonomy == 'category' )
										$posts = get_posts( array( 'category' => $term->term_id ) );
									else
										$posts = get_posts( array( 'tag' => $term->slug ) );
									?>
									<?php if ( $posts ): ?>
										<ul>
											<?php foreach ( $posts as $post ): ?>
												<li><a class="button action" href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>"><?php echo wp_strip_all_tags( $post->post_title ); ?><span class="dashicons dashicons-admin-page wp-ui-text-notification"></span></a></li>
											<?php endforeach; ?>
										</ul>
									<?php endif; ?>
									<?php if ( $taxonomy == 'category' && array_key_exists( $term->term_id, $sub_terms ) ): $subcategories = $sub_terms[ $term->term_id ]; ?>
										<?php if ( $subcategories ): ?>
											<ul>
												<?php foreach ( $subcategories as $subcategory ): ?>
													<li><a class="button action" href="<?php echo esc_url( admin_url( add_query_arg( array( 'action' => 'edit', 'taxonomy' => $taxonomy, 'tag_ID' => $subcategory->term_id, 'post_type' => 'post' ), 'edit-tags.php' ) ) ); ?>"><?php echo wp_strip_all_tags( $subcategory->name ); ?><span class="dashicons dashicons-<?php echo $taxonomy == 'category' ? 'category' : 'tag'; ?> wp-ui-text-highlight"></span></a>
														<?php $posts = get_posts( array( $taxonomy => $subcategory->term_id ) ); ?>
														<?php if ( $posts ): ?>
															<ul>
																<?php foreach ( $posts as $post ): ?>
																	<li><a class="button action" href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>"><?php echo wp_strip_all_tags( $post->post_title ); ?><span class="dashicons dashicons-admin-page wp-ui-text-notification"></span></a></li>
																<?php endforeach; ?>
															</ul>
														<?php endif; ?>
													</li>
												<?php endforeach; ?>
											</ul>
										<?php endif; ?>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						<?php else: ?>
							<li><a class="button disabled"><?php echo $object->labels->not_found; ?></a></li>
						<?php endif; ?>
						<li><a class="button button-primary action" href="<?php echo esc_url( admin_url( add_query_arg( array( 'taxonomy' => $taxonomy ), 'edit-tags.php' ) ) ); ?>"><?php echo $object->labels->add_new_item; ?><span class="dashicons dashicons-plus"></span></a></li>
					</ul>

				<?php endif; ?>
			</div>
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
		$screen = get_current_screen();

		if ( $screen->id == 'toplevel_page_visual-sitemap' )
			wp_enqueue_style( 'visual-sitemap-styles', VISUAL_SITEMAP_URL . '/assets/css/visual-sitemap.css', array(), VISUAL_SITEMAP_VERSION );
	}
}

new Visual_Sitemap();
