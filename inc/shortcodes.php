<?php
/**
 * WooCommerce Products Table Shortcode
 * Usage: [wc_products_table]
 */
function wc_products_table_shortcode() {
    // Query WooCommerce products
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC',
        'post_status' => 'publish'
    );
    
    $products = new WP_Query($args);
    
    if (!$products->have_posts()) {
        return '<p>No products found.</p>';
    }
    
    // Start building the table
    $output = '<table class="roster-list">';
    $output .= '<thead>';
    $output .= '<tr>';
    $output .= '<th>Raffle ID</th>';
    $output .= '<th>Raffle Name</th>';
    $output .= '<th>Publish Date</th>';
    $output .= '<th>Roster</th>';
    $output .= '<th>Winner(s)</th>';
    $output .= '</tr>';
    $output .= '</thead>';
    $output .= '<tbody>';
    
    while ($products->have_posts()) {
        $products->the_post();
        $product_id = get_the_ID();
        $product_name = get_the_title();
        $product_url = get_permalink($product_id);
        $publish_date = get_the_date('Y-m-d');
        
        // Get ACF field value (URL type)
        $roster = get_field('ticket_roster', $product_id);
        $roster_display = '';
        
        if ($roster) {
            $roster_url = esc_url($roster);
            $roster_display = '<a href="' . $roster_url . '" target="_blank" rel="noopener noreferrer">View Roster</a>';
        }
        
        // Get ACF winners field (text type)
        $winners = get_field('raffle_winners', $product_id);
        $winners_display = $winners ? esc_html($winners) : '';
        
        $output .= '<tr>';
        $output .= '<td>' . esc_html($product_id) . '</td>';
        $output .= '<td><a href="' . esc_url($product_url) . '">' . esc_html($product_name) . '</a></td>';
        $output .= '<td>' . esc_html($publish_date) . '</td>';
        $output .= '<td>' . $roster_display . '</td>';
        $output .= '<td>' . $winners_display . '</td>';
        $output .= '</tr>';
    }
    
    $output .= '</tbody>';
    $output .= '</table>';
    
    wp_reset_postdata();
    
    return $output;
}
add_shortcode('wc_products_table', 'wc_products_table_shortcode');