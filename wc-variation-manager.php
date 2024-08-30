<?php

/**
 * Plugin Name: WC Variation Table Manager
 * Plugin URI: https://github.com/safwanyusufzai/WC-Variation-Table-Manager
 * Description: A plugin to manage variations in WooCommerce.
 * Version: 0.1.0
 * Author: Fayaz Khan
 * Author URI: https://github.com/safwanyusufzai
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

class WC_Variation_Table_Manager {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'admin_init', array( $this, 'handle_variation_form_submission' ) );
		add_action( 'woocommerce_variable_product_before_variations', array( $this, 'add_variation_manager_button' ) );
		add_filter( 'post_row_actions', array( $this, 'add_variation_manager_link' ), 10, 2 );

		add_filter( 'woocommerce_rest_batch_items_limit', function ( $limit ) {
			return 200;
		} );
	}// __construct

	/**
	 * Adds a custom button to the product variation tab.
	 */
	public function add_variation_manager_button(): void {
		global $post;
		$product_id            = $post->ID;
		$variation_manager_url = admin_url( 'admin.php?page=wc-variation-table-manager&product_id=' . $product_id );
		echo '<p class="form-field variation_manager_button">';
		echo '<a href="' . esc_url( $variation_manager_url ) . '" class="button">' . __( 'Manage Variations in Table', 'wc-variation-table-manager' ) . '</a>';
		echo '</p>';
	}// add_variation_manager_button

	public function add_variation_manager_link( $actions, $post ) {
		if ( $post->post_type == 'product' ) {
			$product = wc_get_product( $post->ID );
			if ( $product && $product->is_type( 'variable' ) && $product->get_children() ) {
				$variation_manager_url        = admin_url( 'admin.php?page=wc-variation-table-manager&product_id=' . $post->ID );
				$actions['variation_manager'] = sprintf(
					'<a href="%s">%s</a>',
					esc_url( $variation_manager_url ),
					__( 'Manage Variations', 'wc-variation-table-manager' )
				);
			}
		}

		return $actions;
	}


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
	}// add_admin_menu

	/**
	 * @return void
	 */
	public function enqueue_admin_scripts(): void {
		wp_enqueue_media();
		wp_enqueue_style( 'wc-variation-table-manager-style', plugin_dir_url( __FILE__ ) . 'assets/css/admin-style.css' );
		wp_enqueue_script(
			'wc-variation-table-media-manager',
			plugin_dir_url( __FILE__ ) . 'assets/js/admin-script.js',
			array( 'jquery' ),
			'1.0',
			true
		);
	}// enqueue_admin_scripts

	/**
	 * Admin page content
	 */
	public function admin_page_content(): void {
		if ( ! isset( $_GET['product_id'] ) ) {
			echo '<p>' . __( 'Product ID not provided.', 'wc-variation-table-manager' ) . '</p>';

			return;
		}

		$product_id = intval( $_GET['product_id'] );
		$product    = wc_get_product( $product_id );

		if ( ! $product || $product->get_type() !== 'variable' ) {
			echo '<p>' . __( 'Invalid product ID or the product is not a variable product.', 'wc-variation-table-manager' ) . '</p>';

			return;
		}

		echo '<h3>' . __( 'Manage Variations for Product ID:', 'wc-variation-table-manager' ) . ' ' . $product_id . '</h3>';
		echo '<button type="button" id="sku_generator" class="button">' . __( 'SKU Generator', 'wc-variation-table-manager' ) . '</button>';
		echo '<button type="button" id="bulk_image_update" class="button">' . __( 'Bulk Update Image', 'wc-variation-table-manager' ) . '</button>';
		echo '<button type="button" id="bulk_price_update" class="button">' . __( 'Bulk Update Price', 'wc-variation-table-manager' ) . '</button>';

		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
		echo '<input type="hidden" name="page" value="wc-variation-table-manager" />';
		echo '<input type="hidden" name="product_id" value="' . esc_attr( $product_id ) . '" />';

		// Check and display attributes
		$attributes = $product->get_attributes();
		if ( empty( $attributes ) ) {
			echo '<p>' . __( 'No attributes found for this product.', 'wc-variation-table-manager' ) . '</p>';
		} else {
			foreach ( $attributes as $attribute ) {
				$attribute_name  = $attribute->get_name();
				$attribute_label = wc_attribute_label( $attribute_name );

				echo '<label>' . esc_html( $attribute_label ) . '</label>';

				echo '<select name="' . esc_attr( $attribute_name ) . '">';
				echo '<option value="">' . __( 'Select', 'wc-variation-table-manager' ) . ' ' . esc_html( $attribute_label ) . '</option>';

				if ( $attribute->is_taxonomy() ) {
					$terms = get_terms( [
						'taxonomy'   => $attribute_name,
						'hide_empty' => false
					] );

					if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
						foreach ( $terms as $term ) {
							$selected = ( isset( $_GET[ $attribute_name ] ) && $_GET[ $attribute_name ] == $term->name ) ? ' selected' : '';
							echo '<option value="' . esc_attr( $term->name ) . '"' . $selected . '>' . esc_html( $term->name ) . '</option>';
						}
					}
				} else {
					$options = $attribute->get_options();
					foreach ( $options as $option ) {
						$selected = ( isset( $_GET[ $attribute_name ] ) && $_GET[ $attribute_name ] == $option ) ? ' selected' : '';
						echo '<option value="' . esc_attr( $option ) . '"' . $selected . '>' . esc_html( $option ) . '</option>';
					}
				}

				echo '</select>';
			}
		}

		echo '<button type="submit" class="button">' . __( 'Filter', 'wc-variation-table-manager' ) . '</button>';
		echo '</form>';

		$variation_ids = $product->get_children();

		// Filter variations based on selected attributes
		if ( ! empty( $_GET ) ) {
			$filtered_variations = [];
			foreach ( $variation_ids as $variation_id ) {
				$variation = wc_get_product( $variation_id );
				$match     = true;

				foreach ( $attributes as $attribute ) {
					$attribute_name = $attribute->get_name();
					if ( ! empty( $_GET[ $attribute_name ] ) ) {
						$term            = $_GET[ $attribute_name ];
						$variation_value = $variation->get_attribute( $attribute_name );
						if ( $variation_value !== $term ) {
							$match = false;
							break;
						}
					}
				}

				if ( $match ) {
					$filtered_variations[] = $variation_id;
				}
			}
			$variation_ids = $filtered_variations;
		}

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

		?>
        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                $('#sku_generator').on('click', function () {
                    const baseSku = prompt("<?php _e( 'Enter the base SKU:', 'wc-variation-table-manager' ); ?>");
                    if (baseSku) {
                        $('input[name^="variation_sku_"]').each(function () {
                            const $row = $(this).closest('tr');
                            const label = $row.find('td:first').text();
                            const sku = generateSku(baseSku, label);
                            $(this).val(sku.toUpperCase());
                        });
                    }
                });

                function generateSku(baseSku, label) {
                    const parts = label.toLowerCase().split(',');
                    const suffix = parts.map(function (part) {
                        return part.trim().split(' ').map(function (word) {
                            if (word.includes('-')) {
                                return word.split('-').map(function (word) {
                                    return word.charAt(0);
                                }).join('');
                            } else {
                                return word.charAt(0);
                            }
                        }).join('');
                    }).join('');
                    return baseSku + '-' + suffix;
                }

                // Bulk Price Update
                $('#bulk_price_update').on('click', function() {
                    const newPrice = prompt("<?php _e( 'Enter the new price for all variations:', 'wc-variation-table-manager' ); ?>");
                    if (newPrice !== null && !isNaN(newPrice) && newPrice !== "") {
                        $('input[name^="variation_price_"]').val(parseInt(newPrice));
                    }
                });

                // Bulk Image Update
                $('#bulk_image_update').on('click', function(e) {
                    e.preventDefault();
                    var image_frame;
                    if(image_frame){
                        image_frame.open();
                    }
                    // Define image_frame as wp.media object
                    image_frame = wp.media({
                        title: '<?php _e( "Select Media", "wc-variation-table-manager" ); ?>',
                        multiple : false,
                        library : {
                            type : 'image',
                        }
                    });

                    image_frame.on('close',function() {
                        var selection =  image_frame.state().get('selection');
                        var gallery_ids = new Array();
                        var my_index = 0;
                        selection.each(function(attachment) {
                            gallery_ids[my_index] = attachment['id'];
                            my_index++;
                        });
                        var ids = gallery_ids.join(",");
                        if(ids.length === 0) return;

                        // Update all variation images
                        $('input[name^="variation_image_"]').val(ids);
                        $('img[id^="variation_image_preview_"]').attr('src', selection.first().attributes.url);
                    });

                    image_frame.on('open',function() {
                        var selection =  image_frame.state().get('selection');
                        var ids = $('input[name^="variation_image_"]:first').val().split(',');
                        ids.forEach(function(id) {
                            var attachment = wp.media.attachment(id);
                            attachment.fetch();
                            selection.add( attachment ? [ attachment ] : [] );
                        });
                    });

                    image_frame.open();
                });

            });
        </script>
		<?php
	}

	/**
	 * @return void
	 */
	public function handle_variation_form_submission(): void {
		if ( ! isset( $_POST['save_variations'] ) ) {
			return;
		}

		if ( ! isset( $_GET['product_id'] ) ) {
			return;
		}

		$product_id = intval( $_GET['product_id'] );
		$product    = wc_get_product( $product_id );

		if ( ! $product || $product->get_type() !== 'variable' ) {
			return;
		}

		$variation_ids = $product->get_children();

		foreach ( $variation_ids as $variation_id ) {
			try {
				$variation_obj = wc_get_product( $variation_id );

				if ( isset( $_POST[ 'variation_sku_' . $variation_id ] ) ) {
					$new_sku = sanitize_text_field( $_POST[ 'variation_sku_' . $variation_id ] );
					$variation_obj->set_sku( $new_sku );
				}

				if ( isset( $_POST[ 'variation_price_' . $variation_id ] ) ) {
					$new_price = sanitize_text_field( $_POST[ 'variation_price_' . $variation_id ] );
					$variation_obj->set_regular_price( $new_price );
				}

				if ( isset( $_POST[ 'variation_image_' . $variation_id ] ) ) {
					$image_id = intval( $_POST[ 'variation_image_' . $variation_id ] );
					$variation_obj->set_image_id( $image_id );
				}

				$variation_obj->save();
			} catch ( Exception $e ) {
				echo '<div class="error"><p>' . $e->getMessage() . '</p></div>';
			}
		}

		add_action( 'admin_notices', function () {
			echo '<div class="updated"><p>' . __( 'Variations updated successfully.', 'wc-variation-table-manager' ) . '</p></div>';
		} );
	}// handle_variation_form_submission

}// WC_Variation_Table_Manager

// Initialize the plugin
new WC_Variation_Table_Manager();