<?php
/**
 * Plugin Name: WooCommerce Variation Table Manager
 * Description: A simple plugin to display all WooCommerce product variations in a table for easy management.
 * Version: 1.0
 * Author: Fayaz Khan
 * Text Domain: wc-variation-table-manager
 * Domain Path: /languages
 */

// Prevent direct access to the file
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Variation_Table_Manager {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		add_action( 'admin_init', array( $this, 'handle_variation_form_submission' ) );

		// Hook to add a custom button to the product variation tab
		add_action( 'woocommerce_variable_product_before_variations', array( $this, 'add_variation_manager_button' ), 10, 2 );
	}// __construct

	/**
	 * Adds a custom button to the product variation tab.
	 *
	 * @param int $loop
	 * @param array $variation_data
	 * @param WP_Post $variation
	 */
	public function add_variation_manager_button( int $loop, array $variation_data, WP_Post $variation ): void {
		$product_id = $variation->post_parent; // Get the product ID
		$variation_manager_url = admin_url( 'admin.php?page=wc-variation-table-manager&product_id=' . $product_id );

		echo '<p class="form-field variation_manager_button">';
		echo '<a href="' . esc_url( $variation_manager_url ) . '" class="button">' . __( 'Manage Variations in Table', 'wc-variation-table-manager' ) . '</a>';
		echo '</p>';
	}// add_variation_manager_button


	/**
	 * Add admin menu item
	 */
	public function add_admin_menu(): void {
		add_menu_page(
			__( 'Variation Table Manager', 'wc-variation-table-manager' ),
			__( 'Variation Table', 'wc-variation-table-manager' ),
			'manage_woocommerce',
			'wc-variation-table-manager',
			array( $this, 'admin_page_content' ),
			'dashicons-editor-table'
		);
	}

	public function enqueue_admin_scripts(): void {
		wp_enqueue_media(); // This is required to use the media uploader
		wp_enqueue_style( 'wc-variation-table-manager-style', plugin_dir_url( __FILE__ ) . 'assets/css/admin-style.css' );
		wp_enqueue_script(
			'wc-variation-table-media-manager',
			plugin_dir_url( __FILE__ ) . 'assets/js/admin-script.js',
			array( 'jquery' ),
			'1.0',
			true
		);
	}


	/**
	 * Admin page content
	 */
	public function admin_page_content(): void {
		// Check if the product_id parameter is set
		if ( ! isset( $_GET['product_id'] ) ) {
			echo '<p>' . __( 'Product ID not provided.', 'wc-variation-table-manager' ) . '</p>';

			return;
		}

		$product_id = intval( $_GET['product_id'] );

		// Fetch the product object
		$product = wc_get_product( $product_id );

		if ( ! $product || $product->get_type() !== 'variable' ) {
			echo '<p>' . __( 'Invalid product ID or the product is not a variable product.', 'wc-variation-table-manager' ) . '</p>';

			return;
		}

		// Get variation IDs
		$variation_ids = $product->get_children();

		echo '<h3>' . __( 'Manage Variations for Product ID:', 'wc-variation-table-manager' ) . ' ' . $product_id . '</h3>';
		echo '<form method="post" action="" enctype="multipart/form-data">';
		echo '<table class="wp-list-table widefat fixed striped table-view-list posts" id="variation_table">';
		echo '<thead>';
		echo '<tr>';
		echo '<th>' . __( 'Label', 'wc-variation-table-manager' ) . '</th>';
		echo '<th>' . __( 'Picture', 'wc-variation-table-manager' ) . '</th>';
		echo '<th>' . __( 'SKU', 'wc-variation-table-manager' ) . '</th>';
		echo '<th>' . __( 'Price', 'wc-variation-table-manager' ) . '</th>';
		echo '</tr>';
		echo '</thead>';
		echo '<tbody>';

		foreach ( $variation_ids as $variation_id ) {
			$variation_obj  = wc_get_product( $variation_id );
			$image          = wp_get_attachment_image_url( $variation_obj->get_image_id(), 'thumbnail' );
			$sku            = $variation_obj->get_sku();
			$price          = $variation_obj->get_regular_price();
			$variation_name = implode( ', ', $variation_obj->get_attributes() );

			echo '<tr>';
			echo '<td>' . esc_html( $variation_name ) . '</td>';
			echo '<td>';
			if ( $image ) {
				echo '<img src="' . esc_url( $image ) . '" width="50" height="50" id="variation_image_preview_' . esc_attr( $variation_id ) . '" />';
			} else {
				echo '<img  width="50" height="50" id="variation_image_preview_' . esc_attr( $variation_id ) . '" />';
			}
			echo '<input type="hidden" name="variation_image_' . esc_attr( $variation_id ) . '" id="variation_image_' . esc_attr( $variation_id ) . '" value="' . esc_attr( $variation_obj->get_image_id() ) . '" />';
			echo '<button type="button" class="button variation_image_button" data-variation-id="' . esc_attr( $variation_id ) . '">' . __( 'Change Image', 'wc-variation-table-manager' ) . '</button>';
			echo '</td>';
			echo '<td><input type="text" name="variation_sku_' . esc_attr( $variation_id ) . '" value="' . esc_attr( $sku ) . '" /></td>';
			echo '<td><input type="text" name="variation_price_' . esc_attr( $variation_id ) . '" value="' . esc_attr( $price ) . '" /></td>';
			echo '</tr>';
		}

		echo '</tbody>';
		echo '</table>';
		echo '<br />';
		echo '<input type="submit" name="save_variations" value="' . __( 'Save Changes', 'wc-variation-table-manager' ) . '" class="button-primary" />';
		echo '</form>';
	}


	/**
	 * @return void
	 * @throws WC_Data_Exception
	 */
	public function handle_variation_form_submission(): void {
		// Check if the form is submitted
		if ( ! isset( $_POST['save_variations'] ) ) {
			return; // Exit if the form is not submitted
		}

		// Check if the product_id parameter is set
		if ( ! isset( $_GET['product_id'] ) ) {
			return; // Exit if the product ID is not provided
		}

		$product_id = intval( $_GET['product_id'] );
		$product    = wc_get_product( $product_id );

		if ( ! $product || $product->get_type() !== 'variable' ) {
			return; // Exit if the product is not valid
		}

		// Process each variation
		$variation_ids = $product->get_children(); // Get all variation IDs

		foreach ( $variation_ids as $variation_id ) {
			$variation_obj = wc_get_product( $variation_id );

			// Update SKU
			if ( isset( $_POST[ 'variation_sku_' . $variation_id ] ) ) {
				$new_sku = sanitize_text_field( $_POST[ 'variation_sku_' . $variation_id ] );
				$variation_obj->set_sku( $new_sku );
			}

			// Update Price
			if ( isset( $_POST[ 'variation_price_' . $variation_id ] ) ) {
				$new_price = sanitize_text_field( $_POST[ 'variation_price_' . $variation_id ] );
				$variation_obj->set_regular_price( $new_price );
			}

			// Update Image
			if ( isset( $_POST[ 'variation_image_' . $variation_id ] ) ) {
				$image_id = intval( $_POST[ 'variation_image_' . $variation_id ] );
				$variation_obj->set_image_id( $image_id );
			}

			// Save changes to the variation
			$variation_obj->save();
		}

		// Feedback message
		echo '<div class="updated"><p>' . __( 'Variations updated successfully.', 'wc-variation-table-manager' ) . '</p></div>';
	}// handle_variation_form_submission

}// WC_Variation_Table_Manager

// Initialize the plugin
new WC_Variation_Table_Manager();