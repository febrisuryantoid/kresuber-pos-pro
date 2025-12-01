<?php
namespace Kresuber\POS_Pro\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Updater {
    private $slug;
    private $plugin_data;
    private $username;
    private $repo;
    private $plugin_file;
    private $github_api_result;

    public function __construct( $plugin_file, $repo_path ) {
        $this->plugin_file = $plugin_file;
        $this->slug = dirname( plugin_basename( $plugin_file ) );
        list( $this->username, $this->repo ) = explode( '/', $repo_path );
        
        // Hooks untuk Update
        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );
        add_filter( 'plugins_api', [ $this, 'get_plugin_info' ], 10, 3 );
        
        // Hook opsional untuk merapikan nama folder setelah update (mencegah folder bernama kresuber-pos-pro-main)
        add_filter( 'upgrader_source_selection', [ $this, 'fix_folder_name' ], 10, 4 );
    }

    private function get_repo_release_info() {
        if ( ! empty( $this->github_api_result ) ) {
            return $this->github_api_result;
        }
        
        // Mengambil rilis terbaru dari GitHub API
        $url = "https://api.github.com/repos/{$this->username}/{$this->repo}/releases/latest";
        $args = [ 'headers' => [ 'User-Agent' => 'WordPress-Updater' ] ]; // User-Agent wajib untuk GitHub
        
        $response = wp_remote_get( $url, $args );
        
        if ( is_wp_error( $response ) ) return false;
        
        $body = wp_remote_retrieve_body( $response );
        $this->github_api_result = json_decode( $body );
        
        return $this->github_api_result;
    }

    public function check_update( $transient ) {
        if ( empty( $transient->checked ) ) return $transient;

        $release = $this->get_repo_release_info();
        
        // Cek jika ada rilis dan versinya lebih baru dari yang terinstall
        if ( $release && isset( $release->tag_name ) ) {
            $current_version = isset( $transient->checked[ $this->slug . '/' . basename( $this->plugin_file ) ] ) 
                ? $transient->checked[ $this->slug . '/' . basename( $this->plugin_file ) ] 
                : KRESUBER_POS_PRO_VERSION;

            if ( version_compare( $release->tag_name, $current_version, '>' ) ) {
                $obj = new \stdClass();
                $obj->slug = $this->slug;
                $obj->new_version = $release->tag_name;
                $obj->url = $release->html_url;
                $obj->package = $release->zipball_url; // URL Download Zip
                
                $transient->response[ $this->slug . '/' . basename( $this->plugin_file ) ] = $obj;
            }
        }

        return $transient;
    }

    public function get_plugin_info( $false, $action, $response ) {
        if ( empty( $response->slug ) || $response->slug !== $this->slug ) return $false;
        
        $release = $this->get_repo_release_info();
        if ( ! $release ) return $false;

        $response->last_updated = $release->published_at;
        $response->slug = $this->slug;
        $response->name = 'Kresuber POS Pro';
        $response->version = $release->tag_name;
        $response->author = $this->username;
        $response->homepage = $release->html_url;
        $response->download_link = $release->zipball_url;
        
        $response->sections = [
            'description' => 'Update terbaru dari GitHub. <br><br>' . nl2br( $release->body ),
            'changelog' => nl2br( $release->body )
        ];

        return $response;
    }

    public function fix_folder_name( $source, $remote_source, $upgrader, $hook_extra = null ) {
        if ( isset( $hook_extra['plugin'] ) && $hook_extra['plugin'] === $this->slug . '/' . basename( $this->plugin_file ) ) {
            global $wp_filesystem;
            $corrected_source = trailingslashit( $remote_source ) . $this->slug . '/';
            if ( strpos( $source, $this->slug ) === false ) {
                $wp_filesystem->move( $source, $corrected_source );
                return $corrected_source;
            }
        }
        return $source;
    }
}