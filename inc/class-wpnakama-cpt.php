<?php
/**
 * Custom post types class.
 *
 * @package     WPNakama
 * @subpackage  Ingredient
 * @since       0.3.0
 * @version     0.1.0
 * @author      kantbtrue, qdonow, designthingy, savydv
 * @license     GPL-2.0-or-later
 */

// If direct access this file, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'WPNakama_CPT' ) ) {
	/**
	 * Plugin custom post types(CPT)
	 */
	class WPNakama_CPT {

		/**
		 * Custom post type slug that do not exceed 20 characters.
		 * Must be in lowercase and words separated by underscore.
		 *
		 * @var string $slug Post type key or slug.
		 */
		protected $slug;

		/**
		 * General name for the post type, usually plural.
		 *
		 * @var string $plural_name Custom post type plural name.
		 */
		protected $plural_name;

		/**
		 * Singular name for the post type.
		 *
		 * @var string $singular_name Custom post type singular name.
		 */
		protected $singular_name;

		/**
		 * Description of the custom post type.
		 *
		 * @var string $description Custom post type description.
		 */
		protected $description;


		/**
		 * Run when class instantiated.
		 *
		 * @param array $cpt_info_arr Custom post type info with slug, plural_name, singular_name and description.
		 *
		 * @uses add_action()
		 */
		public function __construct( $cpt_info_arr = array() ) {

			$this->slug          = $cpt_info_arr['slug'];
			$this->plural_name   = $cpt_info_arr['plural_name'];
			$this->singular_name = $cpt_info_arr['singular_name'];
			$this->description   = $cpt_info_arr['description'];

			// CPT hook.
			add_action( 'init', array( $this, 'register_cpt' ) );
		}

		/**
		 * Register post type.
		 *
		 * @uses register_post_type()
		 *
		 * @return WP_Post_Type|WP_Error
		 */
		public function register_cpt() {

			$labels = array(

				// Post type plural name.
				'name'                  => ucfirst( $this->plural_name ),

				// Post type singular name.
				'singular_name'         => ucfirst( $this->singular_name ),

				// Admin Menu text.
				'menu_name'             => ucfirst( $this->plural_name ),
				'name_admin_bar'        => ucfirst( $this->singular_name ),

				/* translators: %s: singular name */
				'add_new'               => sprintf( __( 'Add %s', 'plugin-name' ), strtolower( $this->singular_name ) ),
				/* translators: %s: singular name */
				'add_new_item'          => sprintf( __( 'Add new %s', 'plugin-name' ), strtolower( $this->singular_name ) ),
				/* translators: %s: singular name */
				'new_item'              => sprintf( __( 'New %s', 'plugin-name' ), strtolower( $this->singular_name ) ),
				/* translators: %s: singular name */
				'edit_item'             => sprintf( __( 'Edit %s', 'plugin-name' ), strtolower( $this->singular_name ) ),
				/* translators: %s: singular name */
				'view_item'             => sprintf( __( 'View %s', 'plugin-name' ), strtolower( $this->singular_name ) ),
				/* translators: %s: plural name */
				'all_items'             => sprintf( __( 'All %s', 'plugin-name' ), strtolower( $this->plural_name ) ),
				/* translators: %s: plural name */
				'search_items'          => sprintf( __( 'Search for %s', 'plugin-name' ), strtolower( $this->plural_name ) ),
				/* translators: %s: singular name */
				'parent_item'           => sprintf( __( 'Parent %s', 'plugin-name' ), strtolower( $this->singular_name ) ),
				/* translators: %s: singular name */
				'parent_item_colon'     => sprintf( __( 'Parent %s:', 'plugin-name' ), strtolower( $this->singular_name ) ),
				/* translators: %s: singular name */
				'not_found'             => sprintf( __( 'No %s found.', 'plugin-name' ), strtolower( $this->singular_name ) ),
				/* translators: %s: singular name */
				'not_found_in_trash'    => sprintf( __( 'No %s found in Trash.', 'plugin-name' ), strtolower( $this->singular_name ) ),

				// Overrides the “Featured Image” phrase for this post type. Added in 4.3.
				// translators: %s: singular name.
				'featured_image'        => sprintf( __( 'Featured %s image', 'plugin-name' ), strtolower( $this->singular_name ) ),

				// Overrides the “Set featured image” phrase for this post type. Added in 4.3.
				// translators: %s: singular name.
				'set_featured_image'    => sprintf( __( 'Set featured %s image', 'plugin-name' ), strtolower( $this->singular_name ) ),

				// Overrides the “Remove featured image” phrase for this post type. Added in 4.3.
				// translators: %s: singular name.
				'remove_featured_image' => sprintf( __( 'Remove featured %s image', 'plugin-name' ), strtolower( $this->singular_name ) ),

				// Overrides the “Use as featured image” phrase for this post type. Added in 4.3.
				// translators: %s: singular name.
				'use_featured_image'    => sprintf( __( 'Use as %s featured image', 'plugin-name' ), strtolower( $this->singular_name ) ),

				// The post type archive label used in nav menus. Default “Post Archives”. Added in 4.4.
				// translators: %s: singular name.
				'archives'              => sprintf( __( '%s archives', 'plugin-name' ), ucfirst( $this->singular_name ) ),

				// Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post). Added in 4.4.
				// translators: %s: singular name.
				'insert_into_item'      => sprintf( __( 'Insert into %s', 'plugin-name' ), strtolower( $this->singular_name ) ),

				// Overrides the “Uploaded to this post”/”Uploaded to this page” phrase (used when viewing media attached to a post). Added in 4.4.
				// translators: %s: singular name.
				'uploaded_to_this_item' => sprintf( __( 'Uploaded to this %s', 'plugin-name' ), strtolower( $this->singular_name ) ),

				// Screen reader text for the filter links heading on the post type listing screen. Default “Filter posts list”/”Filter pages list”. Added in 4.4.
				// translators: %s: plural name.
				'filter_items_list'     => sprintf( __( 'Filter %s list', 'plugin-name' ), strtolower( $this->plural_name ) ),

				// Screen reader text for the pagination heading on the post type listing screen. Default “Posts list navigation”/”Pages list navigation”. Added in 4.4.
				// translators: %s: plural name.
				'items_list_navigation' => sprintf( __( '%s list navigation', 'plugin-name' ), ucfirst( $this->plural_name ) ),

				// Screen reader text for the items list heading on the post type listing screen. Default “Posts list”/”Pages list”. Added in 4.4.
				// translators: %s: plural name.
				'items_list'            => sprintf( __( '%s list', 'plugin-name' ), ucfirst( $this->plural_name ) ),
			);

			$args = array(

				// An array of labels for the post type.
				'labels'          => $labels,

				// A short descriptive summary of what the post type is.
				'description'     => $this->description,

				// Whether a post type is intended for use publicly.
				'public'          => true,

				// Where to show the post type in the admin menu.
				'show_in_menu'    => 'wpnakama',
				'show_ui'         => true,
				'menu_position'   => 21,

				'rewrite'         => array( 'slug' => $this->slug ),

				// Whether the post type is hierarchical.
				'hierarchical'    => false,
				'capability_type' => 'post',

				'supports'        => array( 'title', 'editor', 'comments', 'revisions', 'trackbacks', 'author', 'excerpt', 'thumbnail', 'custom-fields', 'post-formats' ),
				'taxonomies'      => array(),

				// REST API.
				'show_in_rest'    => true,
			);

			/**
			 * The `{$this->slug}_cpt_args` filter is use to make changes in the
			 * CPT arguments. Where $this->slug is the custom post type slug.
			 *
			 * @param array $args CPT arguments used for registring.
			 */
			return register_post_type( $this->slug, apply_filters( "{$this->slug}_cpt_args", $args ) );
		}

		/**
		 * Add, remove and reorder columns in CPT.
		 * Bult-in columns are: 'cb', 'title', 'author', 'comments', 'date', 'taxonomy-{$taxonomy}', 'post-formats'.
		 *
		 * @link https://developer.wordpress.org/reference/hooks/manage_post_type_posts_columns/
		 *
		 * @param array $columns CPT columns headings.
		 *
		 * @return array
		 */
		public function cpt_columns( $columns ) {
			$custom_columns['cb']    = $columns['cb']; // Checkbox for bulk actions.
			$custom_columns['title'] = $columns['title']; // Title.

			$add_columns_arr     = array();
			$remove_columns_arr  = array();
			$reorder_columns_arr = array();

			/**
			 * The `{$this->slug}_cpt_args` filter is use to add custom columns
			 * between title column and date column.
			 * Where $this->slug is the custom post type slug.
			 *
			 * @param array $add_columns_arr Associate array to add columns. In `key => value` pair.
			 *                               Where value are column name and key is the column key for database.
			 *                               Default empty array.
			 */
			$add_columns = apply_filters( "{$this->slug}_add_columns", $add_columns_arr );
			if ( ! empty( $add_columns ) ) {
				foreach ( $add_columns as $key => $value ) {
					if ( ! array_key_exists( $key, $custom_columns ) ) {
						$custom_columns[ $key ] = $value;
					}
				}
			}

			$custom_columns['date'] = $columns['date']; // The date and publish status.

			/**
			 * The `{$this->slug}_cpt_args` filter is use to remove custom columns
			 * between title column and date column.
			 * Where $this->slug is the custom post type slug.
			 *
			 * @param array $remove_columns_arr Literal array to remove columns. Default empty array.
			 */
			$remove_columns = apply_filters( "{$this->slug}_remove_columns", array() );
			if ( ! empty( $remove_columns ) ) {
				foreach ( $remove_columns as $column ) {
					if ( array_key_exists( $column, $custom_columns ) ) {
						unset( $custom_columns[ $column ] );
					}
				}
			}

			/**
			 * The `{$this->slug}_cpt_args` filter is use to reorder custom columns.
			 * Like if we want to show cb column after title column then reorder array will be`array( 'title', 'cb' ).
			 * NOTE: The remaning columns will be added after the reorder columns.
			 * NOTE: If you want to add custom column then you have to add it in `{$this->slug}_add_columns` filter.
			 * NOTE: If you want to remove custom column then you have to add it in `{$this->slug}_remove_columns` filter.
			 * Where $this->slug is the custom post type slug.
			 *
			 * @param array $reorder_columns_arr Literal array to reorder columns. Default empty array.
			 */
			$reorder_columns = apply_filters( "{$this->slug}_reorder_columns", $reorder_columns_arr );
			if ( ! empty( $reorder_columns ) ) {
				$reorder_arr = array();
				foreach ( $reorder_columns as $order => $column ) {
					if ( array_key_exists( $column, $custom_columns ) ) {
						$reorder_arr[ $column ] = $custom_columns[ $column ];
					}
				}
				$custom_columns = array_merge( $reorder_arr, $custom_columns );
			}

			return $custom_columns;
		}

		/**
		 * Add content to columns in CPT.
		 * Bult-in columns are: 'cb', 'title', 'author', 'comments', 'date', 'taxonomy-{$taxonomy}', 'post-formats'.
		 *
		 * @link https://developer.wordpress.org/reference/hooks/manage_post-post_type_posts_custom_column/
		 *
		 * @param array $column_name CPT column name.
		 * @param int   $post_id CPT post ID.
		 */
		public function cpt_columns_content( $column_name, $post_id ) {

			/**
			 * The `{$this->slug}_cpt_args` filter is use to add content to columns.
			 * Where $this->slug is the custom post type slug.
			 *
			 * @param array $column_name CPT column name.
			 * @param int   $post_id CPT post ID.
			 */
			return apply_filters( "{$this->slug}_columns_content", $column_name, $post_id );
		}
	}
}
