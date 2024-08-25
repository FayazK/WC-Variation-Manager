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
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

// Define the main plugin class
class WC_Variation_Table_Manager {

	/**
	 * Constructor to initialize the plugin
	 */
	public function __construct() {
		// Hook into the admin menu to add our settings page
		add_action('admin_menu', array($this, 'add_admin_menu'));

		// Hook to load scripts and styles in the admin area
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

		// Add a custom meta box to display variations table
		add_action('add_meta_boxes', array($this, 'add_variations_table_meta_box'));

		add_action('admin_init', array($this, 'handle_variation_form_submission'));
	}

	/**
	 * Add admin menu item
	 */
	public function add_admin_menu() {
		add_menu_page(
			__('Variation Table Manager', 'wc-variation-table-manager'),
			__('Variation Table', 'wc-variation-table-manager'),
			'manage_woocommerce',
			'wc-variation-table-manager',
			array($this, 'admin_page_content'),
			'dashicons-editor-table'
		);
	}

	/**
	 * Enqueue admin scripts and styles
	 */
	public function enqueue_admin_scripts() {
		wp_enqueue_style('wc-variation-table-manager-style', plugin_dir_url(__FILE__) . 'assets/css/admin-style.css');
		wp_enqueue_script('wc-variation-table-manager-script', plugin_dir_url(__FILE__) . 'assets/js/admin-script.js', array('jquery'), null, true);
	}

	/**
	 * Add variations table meta box
	 */
	public function add_variations_table_meta_box(): void {
		add_meta_box(
			'wc_variation_table_manager',
			__('Product Variations Table', 'wc-variation-table-manager'),
			array($this, 'display_variations_table'),
			'product',
			'normal',
			'high'
		);
	}

	/**
	 * Display the variations table in the meta box
	 */
	public function display_variations_table(): void {
		// Check if the product_id parameter is set
		if (!isset($_GET['product_id'])) {
			echo '<p>' . __('Product ID not provided.', 'wc-variation-table-manager') . '</p>';
			return;
		}

		$product_id = intval($_GET['product_id']);

		// Fetch the product object
		$product = wc_get_product($product_id);

		if (!$product || $product->get_type() !== 'variable') {
			echo '<p>' . __('Invalid product ID or the product is not a variable product.', 'wc-variation-table-manager') . '</p>';
			return;
		}

		// Get variation IDs
		$variation_ids = $product->get_children();

		echo '<h3>' . __('Manage Variations for Product ID:', 'wc-variation-table-manager') . ' ' . $product_id . '</h3>';
		echo '<form method="post" action="" enctype="multipart/form-data">';
		echo '<table class="widefat fixed" cellspacing="0">';
		echo '<thead>';
		echo '<tr>';
		echo '<th>' . __('Label', 'wc-variation-table-manager') . '</th>';
		echo '<th>' . __('Picture', 'wc-variation-table-manager') . '</th>';
		echo '<th>' . __('SKU', 'wc-variation-table-manager') . '</th>';
		echo '<th>' . __('Price', 'wc-variation-table-manager') . '</th>';
		echo '</tr>';
		echo '</thead>';
		echo '<tbody>';

		foreach ($variation_ids as $variation_id) {
			$variation_obj = wc_get_product($variation_id);
			$image = wp_get_attachment_image_url($variation_obj->get_image_id(), 'thumbnail');
			$sku = $variation_obj->get_sku();
			$price = $variation_obj->get_regular_price();
			$variation_name = implode(', ', $variation_obj->get_attributes());

			echo '<tr>';
			echo '<td>' . esc_html($variation_name) . '</td>';
			echo '<td>';
			if ($image) {
				echo '<img src="' . esc_url($image) . '" width="50" height="50" />';
			}
			echo '<input type="file" name="variation_image_' . esc_attr($variation_id) . '" />';
			echo '</td>';
			echo '<td><input type="text" name="variation_sku_' . esc_attr($variation_id) . '" value="' . esc_attr($sku) . '" /></td>';
			echo '<td><input type="text" name="variation_price_' . esc_attr($variation_id) . '" value="' . esc_attr($price) . '" /></td>';
			echo '</tr>';
		}

		echo '</tbody>';
		echo '</table>';
		echo '<br />';
		echo '<input type="submit" name="save_variations" value="' . __('Save Changes', 'wc-variation-table-manager') . '" class="button-primary" />';
		echo '</form>';
	}
	/**
	 * Admin page content
	 */
	public function admin_page_content(): void {
		$this->display_variations_table();
	}

	public function handle_variation_form_submission() {
		// Check if the form is submitted
		if (!isset($_POST['save_variations'])) {
			return; // Exit if the form is not submitted
		}

		// Check if the product_id parameter is set
		if (!isset($_GET['product_id'])) {
			return; // Exit if the product ID is not provided
		}

		$product_id = intval($_GET['product_id']);
		$product = wc_get_product($product_id);

		if (!$product || $product->get_type() !== 'variable') {
			return; // Exit if the product is not valid
		}

		// Process each variation
		$variation_ids = $product->get_children(); // Get all variation IDs

		foreach ($variation_ids as $variation_id) {
			$variation_obj = wc_get_product($variation_id);

			// Update SKU
			if (isset($_POST['variation_sku_' . $variation_id])) {
				$new_sku = sanitize_text_field($_POST['variation_sku_' . $variation_id]);
				$variation_obj->set_sku($new_sku);
			}

			// Update Price
			if (isset($_POST['variation_price_' . $variation_id])) {
				$new_price = sanitize_text_field($_POST['variation_price_' . $variation_id]);
				$variation_obj->set_regular_price($new_price);
			}

			// Update Image
			if (isset($_FILES['variation_image_' . $variation_id]) && $_FILES['variation_image_' . $variation_id]['size'] > 0) {
				$uploaded_file = $_FILES['variation_image_' . $variation_id];
				$upload = wp_handle_upload($uploaded_file, array('test_form' => false));
				if ($upload && !isset($upload['error'])) {
					$attachment = array(
						'post_mime_type' => $upload['type'],
						'post_title' => sanitize_file_name($upload['file']),
						'post_content' => '',
						'post_status' => 'inherit',
					);
					$attachment_id = wp_insert_attachment($attachment, $upload['file']);
					require_once(ABSPATH . 'wp-admin/includes/image.php');
					$attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
					wp_update_attachment_metadata($attachment_id, $attachment_data);
					$variation_obj->set_image_id($attachment_id);
				}
			}

			// Save changes to the variation
			$variation_obj->save();
		}

		// Feedback message
		echo '<div class="updated"><p>' . __('Variations updated successfully.', 'wc-variation-table-manager') . '</p></div>';
	}
}

// Initialize the plugin
new WC_Variation_Table_Manager();