<?php
namespace Kresuber\POS_Pro\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin {

    public function register_settings() {
        register_setting( 'kresuber_pos_settings', 'kresuber_pos_logo', 'sanitize_text_field' );
        register_setting( 'kresuber_pos_settings', 'kresuber_pos_theme', 'sanitize_text_field' );
        register_setting( 'kresuber_pos_settings', 'kresuber_qris_image', 'sanitize_text_field' );
        register_setting( 'kresuber_pos_settings', 'kresuber_printer_width', 'sanitize_text_field' );
        
        // Sanitasi custom untuk JSON kasir
        register_setting( 'kresuber_pos_settings', 'kresuber_cashiers', [
            'sanitize_callback' => function( $input ) {
                $decoded = json_decode( $input );
                return ( json_last_error() === JSON_ERROR_NONE ) ? $input : '["Kasir Default"]';
            }
        ]);
    }

    public function register_menu() {
        add_menu_page( 
            'Kresuber POS', 
            'Kresuber POS', 
            'manage_woocommerce', 
            'kresuber-pos', 
            [ $this, 'render_dashboard' ], 
            'dashicons-store', 
            56 
        );
    }

    public function enqueue_styles() {
        wp_enqueue_media();
        wp_enqueue_style( 'kresuber-admin', KRESUBER_POS_PRO_URL . 'assets/css/admin.css', [], KRESUBER_POS_PRO_VERSION );
    }

    public function render_dashboard() {
        $logo     = get_option( 'kresuber_pos_logo' );
        $theme    = get_option( 'kresuber_pos_theme', 'retail' );
        $qris     = get_option( 'kresuber_qris_image' );
        $width    = get_option( 'kresuber_printer_width', '58mm' );
        $cashiers = json_decode( get_option( 'kresuber_cashiers', '["Kasir Default"]' ) ) ?: ['Kasir Default'];
        
        $themes = [
            'retail'    => ['label' => 'Retail (Tosca)', 'color' => '#00A78E', 'bg' => '#F0FBF8'],
            'grosir'    => ['label' => 'Grosir (Biru)', 'color' => '#0B5FFF', 'bg' => '#F1F6FF'],
            'sembako'   => ['label' => 'Sembako (Kuning)', 'color' => '#F59E0B', 'bg' => '#FFF7ED'],
            'kelontong' => ['label' => 'Kelontong (Ungu)', 'color' => '#7C4DFF', 'bg' => '#F8F7FF'],
            'sayur'     => ['label' => 'Sayur (Hijau)', 'color' => '#10B981', 'bg' => '#F0FFF4'],
            'buah'      => ['label' => 'Buah (Merah)', 'color' => '#FF6B6B', 'bg' => '#FFF5F5'],
        ];
        ?>
        <div class="kresuber-wrap">
            <!-- Header Baru -->
            <header class="k-header">
                <div class="k-brand">
                    <div class="k-icon-box"><span class="dashicons dashicons-store"></span></div>
                    <div class="k-brand-text">
                        <h1>Kresuber POS Pro</h1>
                        <p>Sistem Kasir Terintegrasi WooCommerce (HPOS Ready)</p>
                    </div>
                </div>
                <div class="k-actions">
                    <span class="k-pill"><span class="dashicons dashicons-yes" style="font-size:14px;width:14px;height:14px;line-height:14px;"></span> Stok Real-time</span>
                    <span class="k-pill">v<?php echo esc_html( KRESUBER_POS_PRO_VERSION ); ?></span>
                    <a href="<?php echo esc_url( home_url( '/pos' ) ); ?>" target="_blank" class="k-btn k-btn-primary">
                        Buka Aplikasi POS <span class="dashicons dashicons-external"></span>
                    </a>
                </div>
            </header>

            <form method="post" action="options.php" class="k-form">
                <?php settings_fields( 'kresuber_pos_settings' ); do_settings_sections( 'kresuber_pos_settings' ); ?>
                
                <div class="k-grid-layout">
                    <!-- Kolom Utama -->
                    <div class="k-main-col">
                        
                        <!-- Panel Info Fitur (Baru) -->
                        <div class="k-card" style="border-left: 4px solid #3b82f6;">
                            <div class="k-card-body">
                                <h3 style="margin-top:0;">Status Integrasi Sistem</h3>
                                <p style="font-size:13px; color:#64748b; line-height: 1.6;">
                                    Plugin ini telah diperbarui untuk mendukung <strong>High-Performance Order Storage (HPOS)</strong>. 
                                    Semua transaksi POS akan langsung mengurangi stok WooCommerce dan tercatat di menu <em>WooCommerce > Pesanan</em>.
                                    Pastikan browser kasir mendukung LocalStorage untuk performa offline-first.
                                </p>
                            </div>
                        </div>

                        <!-- Panel Tampilan -->
                        <div class="k-card">
                            <div class="k-card-head"><h3>Tampilan & Tema Aplikasi</h3></div>
                            <div class="k-card-body">
                                <div class="k-form-group">
                                    <label class="k-label">Pilih Tema Warna</label>
                                    <div class="k-theme-grid">
                                        <?php foreach ( $themes as $key => $p ): ?>
                                        <label class="k-theme-option <?php echo $theme === $key ? 'selected' : ''; ?>">
                                            <input type="radio" name="kresuber_pos_theme" value="<?php echo esc_attr( $key ); ?>" <?php checked( $theme, $key ); ?> class="hidden">
                                            <div class="k-swatch" style="background: <?php echo esc_attr( $p['bg'] ); ?>; border-color: <?php echo esc_attr( $p['color'] ); ?>;">
                                                <div class="k-swatch-accent" style="background: <?php echo esc_attr( $p['color'] ); ?>;"></div>
                                                <span style="color: <?php echo esc_attr( $p['color'] ); ?>; font-weight:bold; font-size:12px;"><?php echo esc_html( $p['label'] ); ?></span>
                                            </div>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="k-form-group">
                                    <label class="k-label">Logo Toko (Header POS)</label>
                                    <div class="k-media-wrap">
                                        <div class="k-img-preview" id="logo-prev">
                                            <?php echo $logo ? '<img src="' . esc_url( $logo ) . '">' : '<span class="dashicons dashicons-format-image"></span>'; ?>
                                        </div>
                                        <div class="k-media-btns">
                                            <input type="hidden" name="kresuber_pos_logo" id="kresuber_pos_logo" value="<?php echo esc_attr( $logo ); ?>">
                                            <button type="button" class="k-btn k-btn-outline upload-btn" data-target="#kresuber_pos_logo" data-prev="#logo-prev">Pilih Gambar</button>
                                            <?php if ( $logo ): ?><button type="button" class="k-link-danger remove-btn" data-target="#kresuber_pos_logo">Hapus</button><?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Panel Hardware -->
                        <div class="k-card">
                            <div class="k-card-head"><h3>Hardware & Pembayaran</h3></div>
                            <div class="k-card-body">
                                <div class="k-row">
                                    <div class="k-col">
                                        <label class="k-label">QRIS Statis (Upload Gambar)</label>
                                        <div class="k-media-wrap">
                                            <div class="k-img-preview" id="qris-prev">
                                                <?php echo $qris ? '<img src="' . esc_url( $qris ) . '">' : '<span class="dashicons dashicons-qr"></span>'; ?>
                                            </div>
                                            <div class="k-media-btns">
                                                <input type="hidden" name="kresuber_qris_image" id="kresuber_qris_image" value="<?php echo esc_attr( $qris ); ?>">
                                                <button type="button" class="k-btn k-btn-outline upload-btn" data-target="#kresuber_qris_image" data-prev="#qris-prev">Upload QRIS</button>
                                                <?php if ( $qris ): ?><button type="button" class="k-link-danger remove-btn" data-target="#kresuber_qris_image">Hapus</button><?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="k-col">
                                        <label class="k-label">Lebar Kertas Printer</label>
                                        <p class="k-hint">Sesuaikan dengan printer thermal Bluetooth/USB Anda.</p>
                                        <select name="kresuber_printer_width" class="k-select">
                                            <option value="58mm" <?php selected( $width, '58mm' ); ?>>58mm (Mobile/Portable)</option>
                                            <option value="80mm" <?php selected( $width, '80mm' ); ?>>80mm (Desktop/Besar)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Kolom Samping -->
                    <div class="k-side-col">
                        <div class="k-card">
                            <div class="k-card-head"><h3>Manajemen Kasir</h3></div>
                            <div class="k-card-body">
                                <label class="k-label">Daftar Nama Kasir</label>
                                <p class="k-hint">Pisahkan dengan koma. Nama ini akan muncul di struk belanja.</p>
                                <?php $c_str = implode( ', ', $cashiers ); ?>
                                <textarea id="cashier_input" class="k-textarea" rows="5" placeholder="Contoh: Budi, Siti, Shift Pagi"><?php echo esc_textarea( $c_str ); ?></textarea>
                                <input type="hidden" name="kresuber_cashiers" id="kresuber_cashiers_json" value="<?php echo esc_attr( json_encode( $cashiers ) ); ?>">
                            </div>
                            <div class="k-card-foot">
                                <?php submit_button( 'Simpan Perubahan', 'primary w-full', 'submit', false ); ?>
                            </div>
                        </div>
                        
                        <div class="k-card" style="background:#f1f5f9; border:none;">
                            <div class="k-card-body" style="text-align:center;">
                                <p class="k-hint">Kresuber POS Pro v<?php echo KRESUBER_POS_PRO_VERSION; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Inline Script untuk Interaksi Admin -->
        <script>
        jQuery(document).ready(function($){
            // Media Uploader
            $('.upload-btn').click(function(e){ 
                e.preventDefault(); 
                var t = $(this).data('target'); 
                var p = $($(this).data('prev'));
                var f = wp.media({ title: 'Pilih Gambar', multiple: false }); 
                
                f.on('select', function(){ 
                    var u = f.state().get('selection').first().toJSON().url; 
                    $(t).val(u); 
                    p.html('<img src="'+u+'">'); 
                }); 
                f.open(); 
            });
            
            // Hapus Gambar
            $('.remove-btn').click(function(){ 
                $($(this).data('target')).val(''); 
                $($(this).data('prev')).html('<span class="dashicons dashicons-format-image"></span>'); 
            });
            
            // Selector Tema
            $('.k-theme-option').click(function(){ 
                $('.k-theme-option').removeClass('selected'); 
                $(this).addClass('selected'); 
            });
            
            // Konversi Textarea ke JSON untuk Kasir
            $('form').submit(function(){
                var raw = $('#cashier_input').val();
                var arr = raw.split(',').map(function(s){ return s.trim(); }).filter(function(s){ return s !== ''; });
                if(arr.length === 0) arr = ['Kasir Default'];
                $('#kresuber_cashiers_json').val(JSON.stringify(arr));
            });
        });
        </script>
        <?php
    }
}