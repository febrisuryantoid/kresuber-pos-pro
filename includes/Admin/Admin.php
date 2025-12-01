<?php
namespace Kresuber\POS_Pro\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

class Admin {

    private $plugin_name;
    private $version;

    public function __construct( $plugin_name = 'kresuber-pos-pro', $version = '2.0.1' ) {
        $this->plugin_name = $plugin_name;
        $this->version     = $version;
    }

    /**
     * Mendaftarkan setting ke WordPress agar masuk whitelist options.php
     * Dipanggil via hook 'admin_init' di Main Class.
     */
    public function register_settings() {
        register_setting( 'kresuber_pos_settings', 'kresuber_qris_image' );
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
        wp_enqueue_media(); // Wajib untuk Media Uploader
        wp_enqueue_style( 'kresuber-admin', KRESUBER_POS_PRO_URL . 'assets/css/admin.css', [], $this->version );
    }

    public function render_dashboard() {
        $pos_url = home_url( '/pos' );
        $qris_image = get_option( 'kresuber_qris_image' );
        ?>
        <div class="wrap kresuber-wrap">
            <div class="kresuber-header">
                <h1><span class="dashicons dashicons-store"></span> Kresuber POS Pro <small>v2.0.1</small></h1>
                <a href="<?php echo esc_url( $pos_url ); ?>" target="_blank" class="button-primary-glossy">
                    Buka Aplikasi Kasir <span class="dashicons dashicons-external"></span>
                </a>
            </div>

            <?php settings_errors(); // Tampilkan pesan sukses/error WP ?>

            <div class="kresuber-grid">
                <!-- Card Status -->
                <div class="kresuber-card">
                    <h2>Status Sistem</h2>
                    <p>Terminal POS aktif dan siap digunakan.</p>
                    <ul class="feature-list">
                        <li>✅ <strong>Database Lokal:</strong> Aktif (Dexie.js)</li>
                        <li>✅ <strong>Sinkronisasi:</strong> Otomatis</li>
                        <li>✅ <strong>Mode Offline:</strong> Tersedia</li>
                        <li>✅ <strong>QRIS:</strong> <?php echo $qris_image ? 'Terpasang' : 'Belum diupload'; ?></li>
                    </ul>
                </div>

                <!-- Card Settings (QRIS) -->
                <div class="kresuber-card">
                    <h2>Pengaturan Pembayaran</h2>
                    <!-- Form harus mengarah ke options.php untuk handling otomatis WP -->
                    <form method="post" action="options.php">
                        <?php 
                            // Output nonce, action, dan option_page fields
                            settings_fields( 'kresuber_pos_settings' ); 
                            do_settings_sections( 'kresuber_pos_settings' ); 
                        ?>
                        
                        <div class="form-group">
                            <label><strong>QRIS Toko (Scan Image)</strong></label>
                            <p class="description">Upload gambar QRIS statis toko Anda untuk ditampilkan di layar kasir saat pembayaran QRIS dipilih.</p>
                            
                            <div class="image-preview-wrapper">
                                <?php if ( $qris_image ) : ?>
                                    <img src="<?php echo esc_url( $qris_image ); ?>" id="qris-preview" style="max-width: 200px; border-radius: 10px; border: 2px solid #eee; margin: 10px 0;">
                                    <div id="qris-placeholder" style="display:none; width: 200px; height: 200px; background: #f0f0f1; align-items: center; justify-content: center; color: #999; border-radius: 10px; margin: 10px 0;">No Image</div>
                                <?php else: ?>
                                    <img src="" id="qris-preview" style="display:none; max-width: 200px; border-radius: 10px; border: 2px solid #eee; margin: 10px 0;">
                                    <div id="qris-placeholder" style="width: 200px; height: 200px; background: #f0f0f1; display: flex; align-items: center; justify-content: center; color: #999; border-radius: 10px; margin: 10px 0;">No Image</div>
                                <?php endif; ?>
                            </div>

                            <input type="hidden" name="kresuber_qris_image" id="kresuber_qris_image" value="<?php echo esc_attr( $qris_image ); ?>">
                            
                            <div style="margin-top: 10px;">
                                <button type="button" class="button" id="upload-qris-btn">
                                    <span class="dashicons dashicons-upload"></span> Upload QRIS
                                </button>
                                <button type="button" class="button button-link-delete" id="remove-qris-btn" style="<?php echo $qris_image ? '' : 'display:none;'; ?>">
                                    Hapus Gambar
                                </button>
                            </div>
                        </div>

                        <hr>
                        <?php submit_button( 'Simpan Pengaturan' ); ?>
                    </form>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($){
            var mediaUploader;
            
            $('#upload-qris-btn').click(function(e) {
                e.preventDefault();
                if (mediaUploader) { mediaUploader.open(); return; }
                
                mediaUploader = wp.media.frames.file_frame = wp.media({
                    title: 'Pilih Gambar QRIS',
                    button: { text: 'Gunakan Gambar Ini' },
                    multiple: false
                });
                
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#kresuber_qris_image').val(attachment.url);
                    $('#qris-preview').attr('src', attachment.url).show();
                    $('#qris-placeholder').css('display', 'none'); // Force hide with css
                    $('#remove-qris-btn').show();
                });
                
                mediaUploader.open();
            });
            
            $('#remove-qris-btn').click(function(){
                $('#kresuber_qris_image').val('');
                $('#qris-preview').hide();
                $('#qris-placeholder').css('display', 'flex'); // Force flex restore
                $(this).hide();
            });
        });
        </script>

        <style>
            .kresuber-wrap { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .kresuber-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
            .kresuber-header h1 { margin: 0; font-weight: 700; color: #2c3e50; }
            .button-primary-glossy { background: #2271b1; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 5px; font-weight: 600; transition: all 0.3s; display: inline-flex; align-items: center; gap: 5px; }
            .button-primary-glossy:hover { background: #135e96; transform: translateY(-1px); color: #fff; }
            .kresuber-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
            .kresuber-card { background: #fff; padding: 25px; border-radius: 8px; border: 1px solid #e2e4e7; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
            .kresuber-card h2 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 15px; font-size: 1.2em; }
            .feature-list { list-style: none; padding: 0; }
            .feature-list li { margin-bottom: 10px; font-size: 14px; }
            @media (max-width: 768px) { .kresuber-grid { grid-template-columns: 1fr; } }
        </style>
        <?php
    }
}