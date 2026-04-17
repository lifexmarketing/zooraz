<?php

// Zaraz Event Tracking
// https://developers.cloudflare.com/zaraz/web-api/ecommerce/

// Zaraz Event Tracking: Order Completed (purchase)
function checkout_datalayer($order_id) {
    $order = wc_get_order($order_id);

    if (!$order) {
        return;
    }

    $order_data = build_order_data($order);
    output_datalayer_script($order_data);
}
add_action('woocommerce_thankyou', 'checkout_datalayer');

/**
 * Builds the order data array for the datalayer.
 */
function build_order_data(WC_Order $order) {
    $order_data = [
        'order_id' => $order->get_order_number(),
        'affiliation' => 'Website',
        'total' => format_price($order->get_total()),
        'revenue' => format_price($order->get_subtotal()),
        'shipping' => format_price($order->calculate_shipping()),
        'tax' => format_price($order->get_total_tax()),
        'currency' => $order->get_currency(),
        'products' => [],
    ];

    if ($order->get_coupon_codes()) {
        $order_data['coupon'] = implode(', ', $order->get_coupon_codes());
    }

    if ($order->get_total_discount()) {
        $order_data['discount'] = format_price($order->get_total_discount());
    }

    foreach ($order->get_items() as $item) {
        $product = $item->get_product();

        if (!$product) {
            continue;
        }

        $order_data['products'][] = [
            'product_id' => strval($item->get_product_id()),
            'name' => $item->get_name(),
            'price' => format_price($order->get_line_subtotal($item, true, true)),
            'quantity' => strval($item->get_quantity()),
            'category' => strip_tags(wc_get_product_category_list($item->get_product_id())),
        ];
    }

    return $order_data;
}

// Formats a price to two decimal places.
function format_price($price) {
    return number_format(floatval($price), 2, '.', '');
}

// Outputs the datalayer script with the given order data.
function output_datalayer_script(array $order_data) {
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof zaraz !== 'undefined' && typeof zaraz.ecommerce === 'function') {
                var orderData = <?php echo wp_json_encode($order_data); ?>;
                zaraz.ecommerce('Order Completed', orderData);
            }
        });
    </script>
    <?php
}

// Zaraz Event Tracking: Product Added (add_to_cart)
// This enqueues JavaScript that listens to WooCommerce's add-to-cart events
function enqueue_zaraz_add_to_cart_tracking() {
    if (is_admin()) {
        return;
    }
    
    wp_enqueue_script('zaraz-add-to-cart', get_stylesheet_directory_uri() . '/js/zaraz-add-to-cart.js', array('jquery'), '1.3', true);
    
    // Pass AJAX URL and nonce for security
    wp_localize_script('zaraz-add-to-cart', 'zarazTrackingData', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('zaraz_tracking_nonce')
    ));
}
// add_action('wp_enqueue_scripts', 'enqueue_zaraz_add_to_cart_tracking');

// AJAX handler to get product data
function get_product_data_for_tracking() {
    check_ajax_referer('zaraz_tracking_nonce', 'nonce');
    
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $variation_id = isset($_POST['variation_id']) ? intval($_POST['variation_id']) : 0;
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    
    if (!$product_id) {
        wp_send_json_error('Invalid product ID');
        return;
    }
    
    $product = wc_get_product($product_id);
    
    if (!$product) {
        wp_send_json_error('Product not found');
        return;
    }
    
    $product_data = [
        'product_id' => strval($product_id),
        'name' => $product->get_name(),
        'price' => format_price($product->get_price()),
        'quantity' => strval($quantity),
        'category' => strip_tags(wc_get_product_category_list($product_id)),
    ];
    
    if ($variation_id) {
        $variation = wc_get_product($variation_id);
        if ($variation) {
            $product_data['variation_id'] = strval($variation_id);
            $product_data['price'] = format_price($variation->get_price());
            
            // Get variation attributes
            $attributes = $variation->get_variation_attributes();
            if (!empty($attributes)) {
                $product_data['variation_attributes'] = $attributes;
            }
        }
    }
    
    wp_send_json_success($product_data);
}
// add_action('wp_ajax_get_product_data_for_tracking', 'get_product_data_for_tracking');
// add_action('wp_ajax_nopriv_get_product_data_for_tracking', 'get_product_data_for_tracking');

// Alternative: Direct tracking without AJAX (simpler but less flexible)
function add_product_data_to_fragments($fragments) {
    // This adds product data to cart fragments for immediate tracking
    return $fragments;
}

// Zaraz Event Tracking: Cart Viewed (view_cart)
function view_cart_datalayer() {
    $cart = WC()->cart;

    if ($cart && !$cart->is_empty()) {
        $cart_items = build_cart_items_data($cart);
        output_view_cart_script($cart_items);
    }
}
add_action('woocommerce_before_cart', 'view_cart_datalayer');

// Builds the cart items data array for the datalayer.
function build_cart_items_data(WC_Cart $cart) {
    $items = [];
    foreach ($cart->get_cart() as $cart_item) {
        $product = $cart_item['data'];
        $product_id = $cart_item['product_id'];
        $variation_id = $cart_item['variation_id'];
        $quantity = $cart_item['quantity'];

        if ($variation_id) {
            $variant_product = wc_get_product($variation_id);
            $product_price = $variant_product ? $variant_product->get_price() : $product->get_price();
            $product_name = $product->get_name() . " - " . ($variant_product ? $variant_product->get_name() : '');
        } else {
            $product_price = $product->get_price();
            $product_name = $product->get_name();
        }

        $items[] = [
            'product_id' => strval($product_id),
            'name' => $product_name,
            'price' => format_price($product_price),
            'quantity' => strval($quantity),
            'category' => strip_tags(wc_get_product_category_list($product_id)),
        ];
    }
    return $items;
}

// Outputs the view cart datalayer script.
function output_view_cart_script(array $cart_items) {
    ?>
    <script>
        if (typeof zaraz !== 'undefined' && typeof zaraz.ecommerce === 'function') {
            zaraz.ecommerce('Cart Viewed', {
                'currency': '<?php echo get_woocommerce_currency(); ?>',
                'items': <?php echo wp_json_encode($cart_items); ?>
            });
        }
    </script>
    <?php
}

// Zaraz Event Tracking: Checkout Started
function checkout_started_datalayer() {
    $cart = WC()->cart;

    if ($cart && !$cart->is_empty()) {
        $cart_items = build_cart_items_data($cart);
        output_checkout_started_script($cart_items);
    }
}
add_action('woocommerce_before_checkout_form', 'checkout_started_datalayer');

// Output the checkout started datalayer script.
function output_checkout_started_script(array $cart_items) {
    ?>
    <script>
        if (typeof zaraz !== 'undefined' && typeof zaraz.ecommerce === 'function') {
            zaraz.ecommerce('Checkout Started', {
                'currency': '<?php echo get_woocommerce_currency(); ?>',
                'items': <?php echo wp_json_encode($cart_items); ?>
            });
        }
    </script>
    <?php
}

// Zaraz Event Tracking: Product Viewed (view_item)
function view_item_datalayer() {
    global $product;

    if (is_product() && $product) {
        $item = build_single_product_data($product);
        output_view_item_script($item);
    }
}
add_action('woocommerce_single_product_summary', 'view_item_datalayer', 1);

/**
 * Builds the product data array for a single product.
 */
function build_single_product_data(WC_Product $product) {
    $categories = strip_tags(wc_get_product_category_list($product->get_id()));

    $item = [
        'product_id' => strval($product->get_id()),
        'name' => $product->get_name(),
        'price' => format_price($product->get_price()),
        'currency' => get_woocommerce_currency(),
        'category' => $categories,
    ];

    if ($product->is_type('variable')) {
        $variations = [];
        foreach ($product->get_available_variations() as $variation_data) {
            $variations[] = [
                'variation_id' => strval($variation_data['variation_id']),
                'price' => format_price($variation_data['display_price']),
                'attributes' => $variation_data['attributes'],
            ];
        }
        $item['variations'] = $variations;
    }

    return $item;
}

// Outputs the view item datalayer script.
function output_view_item_script(array $item) {
    ?>
    <script>
        if (typeof zaraz !== 'undefined' && typeof zaraz.ecommerce === 'function') {
            zaraz.ecommerce('Product Viewed', {
                'currency': '<?php echo get_woocommerce_currency(); ?>',
                'items': [
                    <?php echo wp_json_encode($item); ?>
                ]
            });
        }
    </script>
    <?php
}