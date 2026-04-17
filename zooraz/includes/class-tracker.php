<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Zooraz_Tracker {

    public function __construct() {
        if ( is_admin() ) {
            return;
        }

        add_action( 'wp_ajax_get_product_data_for_tracking',        [ $this, 'ajax_get_product_data' ] );
        add_action( 'wp_ajax_nopriv_get_product_data_for_tracking', [ $this, 'ajax_get_product_data' ] );
        add_action( 'wp_enqueue_scripts',                           [ $this, 'maybe_enqueue_scripts' ] );
        add_action( 'template_redirect',                            [ $this, 'maybe_init_tracking' ] );
    }

    private function should_track(): bool {
        return ! is_user_logged_in() && ! is_feed() && ! is_customize_preview();
    }

    // -------------------------------------------------------------------------
    // Scripts
    // -------------------------------------------------------------------------

    public function maybe_enqueue_scripts(): void {
        if ( ! $this->should_track() ) {
            return;
        }

        wp_enqueue_script(
            'zooraz-add-to-cart',
            ZOORAZ_PLUGIN_URL . 'assets/js/zaraz-add-to-cart.js',
            [ 'jquery' ],
            ZOORAZ_VERSION,
            true
        );

        wp_localize_script( 'zooraz-add-to-cart', 'zarazTrackingData', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'zaraz_tracking_nonce' ),
        ] );
    }

    // -------------------------------------------------------------------------
    // Tracking hooks
    // -------------------------------------------------------------------------

    public function maybe_init_tracking(): void {
        if ( ! $this->should_track() ) {
            return;
        }

        add_action( 'woocommerce_thankyou',                [ $this, 'track_order_completed' ] );
        add_action( 'woocommerce_before_cart',             [ $this, 'track_cart_viewed' ] );
        add_action( 'woocommerce_before_checkout_form',    [ $this, 'track_checkout_started' ] );
        add_action( 'woocommerce_single_product_summary',  [ $this, 'track_product_viewed' ], 1 );
    }

    // -------------------------------------------------------------------------
    // Order Completed
    // -------------------------------------------------------------------------

    public function track_order_completed( int $order_id ): void {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $data = $this->build_order_data( $order );
        $this->output_script( 'Order Completed', $data, true );
    }

    private function build_order_data( WC_Order $order ): array {
        $data = [
            'order_id'    => $order->get_order_number(),
            'affiliation' => 'Website',
            'total'       => $this->format_price( $order->get_total() ),
            'revenue'     => $this->format_price( $order->get_subtotal() ),
            'shipping'    => $this->format_price( $order->calculate_shipping() ),
            'tax'         => $this->format_price( $order->get_total_tax() ),
            'currency'    => $order->get_currency(),
            'products'    => [],
        ];

        if ( $order->get_coupon_codes() ) {
            $data['coupon'] = implode( ', ', $order->get_coupon_codes() );
        }

        if ( $order->get_total_discount() ) {
            $data['discount'] = $this->format_price( $order->get_total_discount() );
        }

        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            if ( ! $product ) {
                continue;
            }

            $data['products'][] = [
                'product_id' => (string) $item->get_product_id(),
                'name'       => $item->get_name(),
                'price'      => $this->format_price( $order->get_line_subtotal( $item, true, true ) ),
                'quantity'   => (string) $item->get_quantity(),
                'category'   => strip_tags( wc_get_product_category_list( $item->get_product_id() ) ),
            ];
        }

        return $data;
    }

    // -------------------------------------------------------------------------
    // Cart Viewed
    // -------------------------------------------------------------------------

    public function track_cart_viewed(): void {
        $cart = WC()->cart;
        if ( ! $cart || $cart->is_empty() ) {
            return;
        }

        $this->output_script( 'Cart Viewed', [
            'currency' => get_woocommerce_currency(),
            'items'    => $this->build_cart_items( $cart ),
        ] );
    }

    // -------------------------------------------------------------------------
    // Checkout Started
    // -------------------------------------------------------------------------

    public function track_checkout_started(): void {
        $cart = WC()->cart;
        if ( ! $cart || $cart->is_empty() ) {
            return;
        }

        $this->output_script( 'Checkout Started', [
            'currency' => get_woocommerce_currency(),
            'items'    => $this->build_cart_items( $cart ),
        ] );
    }

    private function build_cart_items( WC_Cart $cart ): array {
        $items = [];

        foreach ( $cart->get_cart() as $cart_item ) {
            $product      = $cart_item['data'];
            $product_id   = $cart_item['product_id'];
            $variation_id = $cart_item['variation_id'];
            $quantity     = $cart_item['quantity'];

            if ( $variation_id ) {
                $variant      = wc_get_product( $variation_id );
                $price        = $variant ? $variant->get_price() : $product->get_price();
                $name         = $product->get_name() . ' - ' . ( $variant ? $variant->get_name() : '' );
            } else {
                $price = $product->get_price();
                $name  = $product->get_name();
            }

            $items[] = [
                'product_id' => (string) $product_id,
                'name'       => $name,
                'price'      => $this->format_price( $price ),
                'quantity'   => (string) $quantity,
                'category'   => strip_tags( wc_get_product_category_list( $product_id ) ),
            ];
        }

        return $items;
    }

    // -------------------------------------------------------------------------
    // Product Viewed
    // -------------------------------------------------------------------------

    public function track_product_viewed(): void {
        global $product;
        if ( ! is_product() || ! $product ) {
            return;
        }

        $this->output_script( 'Product Viewed', [
            'currency' => get_woocommerce_currency(),
            'items'    => [ $this->build_single_product_data( $product ) ],
        ] );
    }

    private function build_single_product_data( WC_Product $product ): array {
        $item = [
            'product_id' => (string) $product->get_id(),
            'name'       => $product->get_name(),
            'price'      => $this->format_price( $product->get_price() ),
            'currency'   => get_woocommerce_currency(),
            'category'   => strip_tags( wc_get_product_category_list( $product->get_id() ) ),
        ];

        if ( $product->is_type( 'variable' ) ) {
            $variations = [];
            foreach ( $product->get_available_variations() as $v ) {
                $variations[] = [
                    'variation_id' => (string) $v['variation_id'],
                    'price'        => $this->format_price( $v['display_price'] ),
                    'attributes'   => $v['attributes'],
                ];
            }
            $item['variations'] = $variations;
        }

        return $item;
    }

    // -------------------------------------------------------------------------
    // AJAX: Product Added (add to cart)
    // -------------------------------------------------------------------------

    public function ajax_get_product_data(): void {
        check_ajax_referer( 'zaraz_tracking_nonce', 'nonce' );

        $product_id   = isset( $_POST['product_id'] )   ? intval( $_POST['product_id'] )   : 0;
        $variation_id = isset( $_POST['variation_id'] ) ? intval( $_POST['variation_id'] ) : 0;
        $quantity     = isset( $_POST['quantity'] )     ? intval( $_POST['quantity'] )     : 1;

        if ( ! $product_id ) {
            wp_send_json_error( 'Invalid product ID' );
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            wp_send_json_error( 'Product not found' );
        }

        $data = [
            'product_id' => (string) $product_id,
            'name'       => $product->get_name(),
            'price'      => $this->format_price( $product->get_price() ),
            'quantity'   => (string) $quantity,
            'category'   => strip_tags( wc_get_product_category_list( $product_id ) ),
        ];

        if ( $variation_id ) {
            $variation = wc_get_product( $variation_id );
            if ( $variation ) {
                $data['variation_id'] = (string) $variation_id;
                $data['price']        = $this->format_price( $variation->get_price() );
                $attrs                = $variation->get_variation_attributes();
                if ( ! empty( $attrs ) ) {
                    $data['variation_attributes'] = $attrs;
                }
            }
        }

        wp_send_json_success( $data );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function format_price( $price ): string {
        return number_format( (float) $price, 2, '.', '' );
    }

    /**
     * @param bool $dom_ready  Wrap in DOMContentLoaded (use for thank-you page where Zaraz may load late).
     */
    private function output_script( string $event, array $data, bool $dom_ready = false ): void {
        $json = wp_json_encode( $data );
        if ( $dom_ready ) {
            ?>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    if (typeof zaraz !== 'undefined' && typeof zaraz.ecommerce === 'function') {
                        zaraz.ecommerce(<?php echo wp_json_encode( $event ); ?>, <?php echo $json; ?>);
                    }
                });
            </script>
            <?php
        } else {
            ?>
            <script>
                if (typeof zaraz !== 'undefined' && typeof zaraz.ecommerce === 'function') {
                    zaraz.ecommerce(<?php echo wp_json_encode( $event ); ?>, <?php echo $json; ?>);
                }
            </script>
            <?php
        }
    }
}
