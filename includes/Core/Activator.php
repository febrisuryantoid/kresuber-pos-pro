<?php
namespace Kresuber\POS_Pro\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Activator {

    public static function activate() {
        // 1. Tambahkan Rule
        add_rewrite_rule( '^pos/?$', 'index.php?kresuber_pos=1', 'top' );
        
        // 2. Flush
        flush_rewrite_rules();

        // 3. Tambahkan Capabilities ke Administrator & Shop Manager
        $roles = [ 'administrator', 'shop_manager' ];
        
        foreach ( $roles as $role_name ) {
            $role = get_role( $role_name );
            if ( $role ) {
                $role->add_cap( 'manage_woocommerce' ); // Pastikan punya akses
                $role->add_cap( 'view_kresuber_pos' );
            }
        }
        
        // Buat tabel transient jika perlu (opsional, biasanya WP handle ini)
    }
}