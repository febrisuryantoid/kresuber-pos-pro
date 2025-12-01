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
        
        register_setting( 'kresuber_pos_settings', 'kresuber_cashiers', [
            'sanitize_callback' => function( $input ) {
                $decoded = json_decode( $input );
                return ( json_last_error() === JSON_ERROR_NONE ) ? $input : '["Kasir 1"]';
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
        $cashiers = json_decode( get_option( 'kresuber_cashiers', '["Kasir 1"]' ) ) ?: ['Kasir 1'];
        
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
            <header class="k-header">
                <div class="k-brand">
                    <div class="k-icon-box"><span class="dashicons dashicons-store"></span></div>
                    <div class="k-brand-text">
                        <h1>Kresuber POS Pro</h1>
                        <p>Versi <?php echo KRESUBER_POS_PRO_VERSION; ?> | WooCommerce HPOS Ready</p>
                    </div>
                </div>
                <div class="k-actions">
                    <a href="<?php echo esc_url( home_url( '/pos' ) ); ?>" target="_blank" class="k-btn k-btn-primary">
                        <span class="dashicons dashicons-external"></span> Buka Aplikasi POS
                    </a>
                </div>
            </header>

            <form method="post" action="options.php" class="k-form">
                <?php settings_fields( 'kresuber_pos_settings' ); do_settings_sections( 'kresuber_pos_settings' ); ?>
                
                <div class="k-grid-layout">
                    <div class="k-main-col">
                        <!-- Info Panel -->
                        <div class="k-card" style="border-left: 4px solid #10b981;">
                            <div class="k-card-body">
                                <h3 style="margin-top:0;">✨ Update v1.8.0: Performa Super Cepat</h3>
                                <p style="font-size:13px; color:#64748b; line-height: 1.6;">
                                    Kami telah merombak sistem sinkronisasi. Kini POS menggunakan metode <strong>Batch Sync</strong> (bertahap) yang tidak akan memberatkan server, meskipun Anda memiliki ribuan produk. Stok juga tersinkronisasi secara real-time saat checkout.
                                </p>
                            </div>
                        </div>

                        <!-- Theme Panel -->
                        <div class="k-card">
                            <div class="k-card-head"><h3>Tampilan & Branding</h3></div>
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
                                    <label class="k-label">Logo Struk & Header</label>
                                    <div class="k-media-wrap">
                                        <div class="k-img-preview" id="logo-prev">
                                            <?php echo $logo ? '<img src="' . esc_url( $logo ) . '">' : '<span class="dashicons dashicons-format-image"></span>'; ?>
                                        </div>
                                        <div class="k-media-btns">
                                            <input type="hidden" name="kresuber_pos_logo" id="kresuber_pos_logo" value="<?php echo esc_attr( $logo ); ?>">
                                            <button type="button" class="k-btn k-btn-outline upload-btn" data-target="#kresuber_pos_logo" data-prev="#logo-prev">Ganti Logo</button>
                                            <?php if ( $logo ): ?><button type="button" class="k-link-danger remove-btn" data-target="#kresuber_pos_logo">Hapus</button><?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="k-side-col">
                        <div class="k-card">
                            <div class="k-card-head"><h3>Pengaturan Kasir</h3></div>
                            <div class="k-card-body">
                                <label class="k-label">Nama Kasir (Pisahkan koma)</label>
                                <?php $c_str = implode( ', ', $cashiers ); ?>
                                <textarea id="cashier_input" class="k-textarea" rows="4"><?php echo esc_textarea( $c_str ); ?></textarea>
                                <input type="hidden" name="kresuber_cashiers" id="kresuber_cashiers_json" value="<?php echo esc_attr( json_encode( $cashiers ) ); ?>">
                            </div>
                            <div class="k-card-foot">
                                <?php submit_button( 'Simpan Pengaturan', 'primary w-full', 'submit', false ); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($){
            // Media Uploader Logic
            $('.upload-btn').click(function(e){ 
                e.preventDefault(); 
                var t = $(this).data('target'), p = $($(this).data('prev'));
                var f = wp.media({ title: 'Pilih Gambar', multiple: false }); 
                f.on('select', function(){ 
                    var u = f.state().get('selection').first().toJSON().url; 
                    $(t).val(u); p.html('<img src="'+u+'">'); 
                }); 
                f.open(); 
            });
            $('.remove-btn').click(function(){ $($(this).data('target')).val(''); $($(this).data('prev')).html('<span class="dashicons dashicons-format-image"></span>'); });
            $('.k-theme-option').click(function(){ $('.k-theme-option').removeClass('selected'); $(this).addClass('selected'); });
            $('form').submit(function(){
                var raw = $('#cashier_input').val(), arr = raw.split(',').map(s=>s.trim()).filter(s=>s!=='');
                $('#kresuber_cashiers_json').val(JSON.stringify(arr.length?arr:['Kasir']));
            });
        });
        </script>
        <?php
    }
}