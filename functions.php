<?php

function load_zaraz_frontend() {
    if ( ! is_admin() && ! is_user_logged_in() && ! is_feed() && ! is_customize_preview() ) {
        $zaraz_file = get_stylesheet_directory() . '/inc/zaraz.php';

        if ( file_exists( $zaraz_file ) ) {
            require_once $zaraz_file;
        } else {
            error_log( 'zaraz.php not found in /inc/ directory of child theme.' );
        }
    }
}
add_action( 'template_redirect', 'load_zaraz_frontend' );
