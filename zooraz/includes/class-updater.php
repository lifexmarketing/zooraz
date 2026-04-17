<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Zooraz_Updater {

    private string $plugin_file;
    private string $plugin_slug;
    private string $version;
    private string $repo;
    private string $api_url;

    public function __construct( string $plugin_file, string $version ) {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = dirname( plugin_basename( $plugin_file ) );
        $this->version     = $version;
        $this->repo        = 'lifexmarketing/zooraz';
        $this->api_url     = 'https://api.github.com/repos/' . $this->repo . '/releases/latest';

        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ] );
        add_filter( 'plugins_api',                           [ $this, 'plugin_info' ], 20, 3 );
        add_filter( 'upgrader_post_install',                 [ $this, 'after_install' ], 10, 3 );
    }

    public function check_for_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $release = $this->get_latest_release();
        if ( ! $release ) {
            return $transient;
        }

        $remote_version = ltrim( $release->tag_name, 'v' );

        if ( version_compare( $this->version, $remote_version, '<' ) ) {
            $plugin_key = plugin_basename( $this->plugin_file );

            $transient->response[ $plugin_key ] = (object) [
                'id'          => 'github.com/' . $this->repo,
                'slug'        => $this->plugin_slug,
                'plugin'      => $plugin_key,
                'new_version' => $remote_version,
                'url'         => 'https://github.com/' . $this->repo,
                'package'     => $this->get_download_url( $release ),
                'icons'       => [],
                'banners'     => [],
                'requires_php'=> '7.4',
            ];
        }

        return $transient;
    }

    public function plugin_info( $result, $action, $args ) {
        if ( $action !== 'plugin_information' ) {
            return $result;
        }

        if ( ! isset( $args->slug ) || $args->slug !== $this->plugin_slug ) {
            return $result;
        }

        $release = $this->get_latest_release();
        if ( ! $release ) {
            return $result;
        }

        $plugin_data = get_plugin_data( $this->plugin_file );

        return (object) [
            'name'          => $plugin_data['Name'],
            'slug'          => $this->plugin_slug,
            'version'       => ltrim( $release->tag_name, 'v' ),
            'author'        => $plugin_data['Author'],
            'homepage'      => 'https://github.com/' . $this->repo,
            'download_link' => $this->get_download_url( $release ),
            'sections'      => [
                'description' => $plugin_data['Description'],
                'changelog'   => nl2br( esc_html( $release->body ?? '' ) ),
            ],
        ];
    }

    public function after_install( $response, $hook_extra, $result ) {
        global $wp_filesystem;

        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== plugin_basename( $this->plugin_file ) ) {
            return $result;
        }

        $install_dir = WP_PLUGIN_DIR . '/' . $this->plugin_slug;

        if ( untrailingslashit( $result['destination'] ) === $install_dir ) {
            return $result;
        }

        if ( $wp_filesystem->is_dir( $install_dir ) ) {
            $wp_filesystem->delete( $install_dir, true );
        }

        $wp_filesystem->move( $result['destination'], $install_dir );
        $result['destination'] = $install_dir;

        return $result;
    }

    private function get_download_url( object $release ): string {
        foreach ( $release->assets ?? [] as $asset ) {
            if ( $asset->name === 'zooraz.zip' ) {
                return $asset->browser_download_url;
            }
        }

        return $release->zipball_url;
    }

    private function get_latest_release(): ?object {
        $cache_key = 'zooraz_github_release';
        $cached    = get_transient( $cache_key );

        if ( $cached !== false ) {
            return $cached ?: null;
        }

        $response = wp_remote_get( $this->api_url, [
            'headers' => [
                'Accept'     => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ),
            ],
            'timeout' => 10,
        ] );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            set_transient( $cache_key, false, HOUR_IN_SECONDS );
            return null;
        }

        $release = json_decode( wp_remote_retrieve_body( $response ) );

        if ( empty( $release->tag_name ) ) {
            set_transient( $cache_key, false, HOUR_IN_SECONDS );
            return null;
        }

        set_transient( $cache_key, $release, 12 * HOUR_IN_SECONDS );

        return $release;
    }
}
