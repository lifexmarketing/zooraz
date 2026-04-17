<?php

function theme_enqueue_styles() {
    wp_enqueue_style( 'avada-parent-stylesheet', get_template_directory_uri() . '/style.css' );
}
add_action( 'wp_enqueue_scripts', 'theme_enqueue_styles' );

function avada_lang_setup() {
	$lang = get_stylesheet_directory() . '/languages';
	load_child_theme_textdomain( 'Avada', $lang );
}
add_action( 'after_setup_theme', 'avada_lang_setup' );

// show current year for copyright notice

function displayTodaysDate( $atts )
	{
		return date('Y');
	}
add_shortcode( 'datetoday', 'displayTodaysDate');

// Change new order email subject
add_filter('woocommerce_email_subject_new_order', 'wc_change_admin_new_order_email_subject', 1, 2);

function wc_change_admin_new_order_email_subject($subject, $order) {
    if (!$order) {
        return $subject; // Return original subject if order is invalid
    }

    $count = $order->get_item_count();
    $subject = sprintf('Order #%s: %s for %s %s', $order->get_id(), $count, $order->get_billing_first_name(), $order->get_billing_last_name());

    // Optional alternative subject line with currency formatting
    // $total = $order->get_total();
    // $subject = sprintf('Order #%s from %s %s (%s) for %s items, Total: %s', $order->get_id(), $order->get_billing_first_name(), $order->get_billing_last_name(), $order->get_billing_email(), $count, wc_price($total));

    return $subject;
}
// add short description text
function tip_board_short_description( $post_excerpt ) {
    global $product;

    // IMPORTANT: Always ensure $product is a valid WC_Product object to prevent errors.
    if ( ! $product instanceof WC_Product ) {
        return $post_excerpt; // Return original excerpt if $product is not valid
    }

    // Check if the product is an 'external' type. If so, return the original short description.
    if ( $product->get_type() === 'external' ) {
        return $post_excerpt;
    }

    // Check if the 'free' or '50-50' tag is applied
    $is_free_product = has_term('free', 'product_tag', $product->get_id());
    $is_50_50_product = has_term('50-50', 'product_tag', $product->get_id());

    // Retrieve product details for the custom description.
    $product_title = $product->get_name();
    $product_price = $product->get_price_html();

    if ($is_free_product) {
        // Customize for 'free' tag
        $post_excerpt = sprintf(
            '<p class="tip-board-short-description">%s %s</p>',
            esc_html__( 'Request your free chance', 'westmead1' ),
            sprintf( esc_html__( 'to win this %s package! One ticket per person, please.', 'westmead1' ), $product_title )
        );
    } else {
        // Original description for non-'free' products
        $post_excerpt = sprintf(
            '<p class="tip-board-short-description">%s %s %s %s %s</p>',
            esc_html__( 'For your', 'westmead1' ),
            $product_price,
            esc_html__( 'donation you will receive', 'westmead1' ),
            esc_html__( '1 chance', 'westmead1' ), // Hardcoded "1 chance"
            sprintf( esc_html__( 'to win the %s package.', 'westmead1' ), $product_title )
        );
    }

    return $post_excerpt;
}
add_filter( 'woocommerce_short_description', 'tip_board_short_description', 10, 1 );

// Load custom Zaraz tracking functions on frontend, not when admin is logged in
function load_zaraz_frontend() {
    if ( ! is_admin() && ! is_user_logged_in() && ! is_feed() && ! is_customize_preview() ) { // Add checks to avoid admin, logged in users, feeds and customizer preview

        $zaraz_file = get_stylesheet_directory() . '/inc/zaraz.php';

        if ( file_exists( $zaraz_file ) ) {
            require_once $zaraz_file;
        } else {
            error_log( 'zaraz.php not found in /inc/ directory of child theme.' );
        }
    }
}

add_action( 'template_redirect', 'load_zaraz_frontend' );

add_filter('woocommerce_product_related_products_heading',function(){

   return 'More Chances to Win';

});

add_filter( 'woocommerce_order_button_text', 'wc_custom_order_button_text' ); 

function wc_custom_order_button_text() {
    return __( 'Complete Donation', 'woocommerce' ); 
}

add_filter( 'the_title', 'woo_title_order_received', 10, 2 );

function woo_title_order_received( $title, $id ) {
	if ( function_exists( 'is_order_received_page' ) && 
	     is_order_received_page() && get_the_ID() === $id ) {
		$title = "Donation Complete";
	}
	return $title;
}

add_filter( 'pre_user_first_name', 'wm1_sync_user_edit_profile_edit_billing_first_name' );
function wm1_sync_user_edit_profile_edit_billing_first_name( $first_name ) {
    if ( isset( $_POST['billing_first_name'] ) ) {
        $first_name = $_POST['billing_first_name'];
    }
    return $first_name;
}
 
add_filter( 'pre_user_last_name', 'wm1_sync_user_edit_profile_edit_billing_last_name' );
function wm1_sync_user_edit_profile_edit_billing_last_name( $last_name ) {
    if ( isset( $_POST['billing_last_name'] ) ) {
        $last_name = $_POST['billing_last_name'];
    }
    return $last_name;
}

add_filter( 'woocommerce_product_tabs', 'wm1_rename_lottery_details_tab' );
function wm1_rename_lottery_details_tab( $tabs ) {
	$tabs[ 'lty_ticket_logs' ][ 'title' ] = 'Ticket Sales';
	return $tabs;
}

/* show product ID as SKU
add_filter( 'woocommerce_product_get_sku', function ( $sku, $product ) {
	return $product->get_id();
}, 10, 2 ); */

function set_product_sku_to_id_only_if_changed( $product ) {
    // Ensure we are only acting on actual product types
    if ( ! $product instanceof WC_Product ) {
        return;
    }

    $product_id = $product->get_id();
    $current_sku = $product->get_sku();

    // Convert product ID to string for comparison with SKU
    $target_sku = (string) $product_id;

    // Only update and save if the current SKU is different from the product ID
    if ( $current_sku !== $target_sku ) {
        $product->set_sku( $target_sku );
        $product->save(); // Save the product to persist the SKU change
    }
}
// add_action( 'woocommerce_admin_process_product_object', 'set_product_sku_to_id_only_if_changed', 10, 1 );

function wm1_continue_shopping_redirect_to_shop( $return_to ) {
    return wc_get_page_permalink( 'shop' );
}
add_filter( 'woocommerce_continue_shopping_redirect', 'wm1_continue_shopping_redirect_to_shop' );

// link to add another product at checkout
function wm1_add_tix_link_to_checkout() {
    $hardcoded_url = '/tix/'; // Your hardcoded URL
    
    echo '<p class="checkout-custom-link">';
    echo '<a href="' . esc_url( $hardcoded_url ) . '">' . esc_html__( 'Add another chance to win!', 'westmead1' ) . '</a>';
    echo '</p>';
	echo '<p style="text-align: center; color: #e10707"><strong>If you do not receive an email confirmation with your ticket numbers after donating, please contact us!</strong></p>';
}
add_action( 'woocommerce_checkout_before_customer_details', 'wm1_add_tix_link_to_checkout' );

function wm1_product_cross_sells_products_heading( $string ) {
    $string = __( 'More chances to win!', 'woocommerce' );
    return $string;
}
add_filter( 'woocommerce_product_cross_sells_products_heading', 'wm1_product_cross_sells_products_heading', 10, 1 );

// add ticket roster link for out of stock products with link in the Ticket Roster field
function display_ticket_roster_if_out_of_stock() {
    global $product;

    // Ensure we are on a single product page and $product is an instance of WC_Product
    if ( ! is_product() || ! $product ) {
        return;
    }

    // Check if the product is out of stock
    if ( ! $product->is_in_stock() ) {
        // Get the value of the ACF custom URL field 'ticket_roster'
        $ticket_roster_url = get_field( 'ticket_roster', $product->get_id() );

        // Check if the URL field is populated
        if ( $ticket_roster_url ) {
            echo '<div class="product_meta ticket-roster-meta">';
            echo '<a class="button block default" href="' . esc_url( $ticket_roster_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View Ticket Roster', 'westmead1' ) . '</a>';
            echo '</div>';
        }
    }
}
add_action( 'woocommerce_product_meta_end', 'display_ticket_roster_if_out_of_stock' );

// clear roster field on duplication
function wm1_empty_fields_and_images_on_product_duplicate( $duplicate, $product ) {
    $new_product_id = $duplicate->get_id();
    
    // 1. Clear ACF fields
    $acf_field_name = 'ticket_roster'; // Replace 'ticket_roster' with your actual ACF field name
    if ( have_rows( $acf_field_name, $new_product_id ) || get_field( $acf_field_name, $new_product_id ) ) {
        update_field( $acf_field_name, '', $new_product_id );
    }
    
    // Clear raffle_winners field
    $raffle_winners_field = 'raffle_winners';
    if ( have_rows( $raffle_winners_field, $new_product_id ) || get_field( $raffle_winners_field, $new_product_id ) ) {
        update_field( $raffle_winners_field, '', $new_product_id );
    }
    
    // 2. Remove Featured Image
    $featured_image_id = get_post_thumbnail_id( $new_product_id );
    // If a featured image exists, remove it
    if ( $featured_image_id ) {
        delete_post_thumbnail( $new_product_id );
    }
    
    // 3. Remove Product Gallery Images
    update_post_meta( $new_product_id, '_product_image_gallery', '' );
}
add_action( 'woocommerce_product_duplicate', 'wm1_empty_fields_and_images_on_product_duplicate', 10, 2 );

function wm1_custom_product_image_overlay() {
    global $product; // Get the global product object

    // Ensure we are on a single product page and the product object exists
    if ( is_product() && $product ) {
        $product_id = $product->get_id();

        // Check if the product has the '50-50' tag
        $is_50_50_product = has_term('50-50', 'product_tag', $product_id);

        if ( $is_50_50_product ) {
            // Your logic for calculating the jackpot and winner's share
            if (function_exists('lty_get_purchased_tickets_count_by_product_id')) {
                $tickets_sold = lty_get_purchased_tickets_count_by_product_id($product_id);
            } else {
                $tickets_sold = 0; // Default to 0 if the function doesn't exist
            }

            $jackpot_total = 100 + ($tickets_sold * 10);
            $winner_receives = 0.5 * $jackpot_total;
            ?>

            <div class="jackpot-overlay">
                <h2>Current Jackpot <span>$<?php echo number_format($jackpot_total, 0); ?></span></h2>
                <p>Winner receives $<?php echo number_format($winner_receives, 0); ?></p>
            </div>

            <?php
        }
    }
}
add_action('woocommerce_before_single_product_summary', 'wm1_custom_product_image_overlay', 15);

// add shortcodes php
require_once get_stylesheet_directory() . '/inc/shortcodes.php';

// Add stock quantity to Fusion product grid
add_action('woocommerce_after_shop_loop_item_title', 'display_stock_quantity_fusion_grid', 15);

function display_stock_quantity_fusion_grid() {
    global $product;
    
    // Check if product manages stock
    if ($product->managing_stock()) {
        $stock_quantity = $product->get_stock_quantity();
        $stock_status = $product->get_stock_status();
        
        // Display stock quantity
        if ($stock_quantity !== null) {
            echo '<div class="fusion-product-stock-info">';
            
            if ($stock_quantity > 0) {
                echo '<span class="stock in-stock">' . sprintf(__('%s tix', 'avada'), $stock_quantity) . '</span>';
            } else {
                echo '<span class="stock out-of-stock">' . __('SOLD OUT', 'avada') . '</span>';
            }
            
            echo '</div>';
        }
    } else {
        // For products not managing stock, show availability status
        if ($product->is_in_stock()) {
            echo '<div class="fusion-product-stock-info">';
            echo '<span class="stock in-stock">' . __('In Stock', 'avada') . '</span>';
            echo '</div>';
        } else {
            echo '<div class="fusion-product-stock-info">';
            echo '<span class="stock out-of-stock">' . __('Out of Stock', 'avada') . '</span>';
            echo '</div>';
        }
    }
}

// return list of Jeep orders with tickets
/**
 * Shortcode to display orders containing product ID 133896 with ticket numbers
 * Usage: [product_orders_table]
 */
function product_orders_table_shortcode() {
    // Get the product ID
    $product_id = 133896;
    
    // Get current page from URL parameter
    $current_page = isset($_GET['orders_page']) ? max(1, intval($_GET['orders_page'])) : 1;
    $per_page = 50;
    $offset = ($current_page - 1) * $per_page;
    
    global $wpdb;
    
    // Get total count of orders
    $total_orders = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(DISTINCT order_items.order_id)
         FROM {$wpdb->prefix}woocommerce_order_items as order_items
         LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_itemmeta_product 
            ON order_items.order_item_id = order_itemmeta_product.order_item_id
         WHERE order_items.order_item_type = 'line_item'
           AND order_itemmeta_product.meta_key IN ('_product_id', '_variation_id')
           AND order_itemmeta_product.meta_value = %d",
        $product_id
    ));
    
    $total_pages = ceil($total_orders / $per_page);
    
    // Query orders containing this product (paginated)
    $orders = $wpdb->get_results( $wpdb->prepare(
        "SELECT order_items.order_id, 
                MAX(order_itemmeta.meta_value) as quantity
         FROM {$wpdb->prefix}woocommerce_order_items as order_items
         LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_itemmeta 
            ON order_items.order_item_id = order_itemmeta.order_item_id
         LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_itemmeta_product 
            ON order_items.order_item_id = order_itemmeta_product.order_item_id
         WHERE order_items.order_item_type = 'line_item'
           AND order_itemmeta.meta_key = '_qty'
           AND order_itemmeta_product.meta_key IN ('_product_id', '_variation_id')
           AND order_itemmeta_product.meta_value = %d
         GROUP BY order_items.order_id
         ORDER BY order_items.order_id DESC
         LIMIT %d OFFSET %d",
        $product_id,
        $per_page,
        $offset
    ));
    
    // Start building the HTML output
    ob_start();
    
    if ( empty( $orders ) ) {
        echo '<p>No orders found for product ID ' . esc_html( $product_id ) . '</p>';
    } else {
        // Pagination info
        echo '<p>Showing orders ' . (($current_page - 1) * $per_page + 1) . ' to ' . min($current_page * $per_page, $total_orders) . ' of ' . $total_orders . ' total orders</p>';
        
        echo '<table class="product-orders-table" style="width:100%; border-collapse: collapse;">';
        echo '<thead>';
        echo '<tr>';
        echo '<th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Order ID</th>';
        echo '<th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Quantity</th>';
        echo '<th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Ticket Numbers</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ( $orders as $order ) {
            // Get ticket numbers for this order
            $ticket_numbers = $wpdb->get_col( $wpdb->prepare(
                "SELECT pm2.meta_value as ticket_number
                 FROM {$wpdb->prefix}postmeta pm1
                 INNER JOIN {$wpdb->prefix}postmeta pm2 
                    ON pm1.post_id = pm2.post_id
                 WHERE pm1.meta_key = 'lty_order_id'
                   AND pm1.meta_value = %d
                   AND pm2.meta_key = 'lty_ticket_number'
                 ORDER BY pm2.meta_value ASC",
                $order->order_id
            ));
            
            $ticket_list = !empty($ticket_numbers) ? implode(', ', $ticket_numbers) : 'No tickets';
            
            echo '<tr>';
            echo '<td style="border: 1px solid #ddd; padding: 8px;">';
            echo '<a href="' . esc_url( admin_url( 'post.php?post=' . $order->order_id . '&action=edit' ) ) . '">';
            echo '#' . esc_html( $order->order_id );
            echo '</a>';
            echo '</td>';
            echo '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html( $order->quantity ) . '</td>';
            echo '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html( $ticket_list ) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        
        // Pagination links
        if ($total_pages > 1) {
            echo '<div style="margin-top: 20px; text-align: center;">';
            
            // Get current URL without orders_page parameter
            $current_url = remove_query_arg('orders_page');
            
            // Previous button
            if ($current_page > 1) {
                echo '<a href="' . esc_url(add_query_arg('orders_page', $current_page - 1, $current_url)) . '" style="margin: 0 5px; padding: 5px 10px; background: #0073aa; color: white; text-decoration: none; border-radius: 3px;">« Previous</a>';
            }
            
            // Page numbers
            for ($i = 1; $i <= $total_pages; $i++) {
                if ($i == $current_page) {
                    echo '<span style="margin: 0 5px; padding: 5px 10px; background: #555; color: white; border-radius: 3px;">' . $i . '</span>';
                } else {
                    // Show first page, last page, current page, and 2 pages around current
                    if ($i == 1 || $i == $total_pages || abs($i - $current_page) <= 2) {
                        echo '<a href="' . esc_url(add_query_arg('orders_page', $i, $current_url)) . '" style="margin: 0 5px; padding: 5px 10px; background: #0073aa; color: white; text-decoration: none; border-radius: 3px;">' . $i . '</a>';
                    } elseif (abs($i - $current_page) == 3) {
                        echo '<span style="margin: 0 5px;">...</span>';
                    }
                }
            }
            
            // Next button
            if ($current_page < $total_pages) {
                echo '<a href="' . esc_url(add_query_arg('orders_page', $current_page + 1, $current_url)) . '" style="margin: 0 5px; padding: 5px 10px; background: #0073aa; color: white; text-decoration: none; border-radius: 3px;">Next »</a>';
            }
            
            echo '</div>';
        }
    }
    
    return ob_get_clean();
}
add_shortcode( 'product_orders_table', 'product_orders_table_shortcode' );

add_action( 'woocommerce_thankyou', 'add_order_confirmation_notice', 10 );
function add_order_confirmation_notice( $order_id ) {
    if ( ! $order_id ) {
        return;
    }
    
    echo '<p style="text-align: left; color: #e10707"><strong>If you do not receive an email confirmation with your ticket numbers, please contact us!</strong></p>';
}
//rename woocommerce shop page title
add_filter( 'pre_get_document_title', 'override_shop_title', 999 );
function override_shop_title( $title ) {
    if ( is_shop() ) {
        return 'Current Raffles - Donate for a Chance to Win!';
    }
    return $title;
}