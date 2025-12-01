<?php
namespace Kresuber\POS_Pro\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

class Admin {

    private $version = '2.0.2';

    public function register_settings() {
        // Mendaftarkan opsi agar diizinkan oleh WordPress
        register_setting( 'kresuber_pos_settings', 'kresuber_qris_image' );
    }

    public function register_menu() {
        add_menu_page( 'Kresuber POS', 'Kresuber POS', 'manage_woocommerce', 'kresuber-pos', [ $this, 'render_dashboard' ], 'dashicons-store', 56 );
    }

    public function enqueue_styles() {
        wp_enqueue_media();
        wp_enqueue_style( 'kresuber-admin', KRESUBER_POS_PRO_URL . 'assets/css/admin.css', [], $this->version );
    }

    public function render_dashboard() {
        $qris_image = get_option( 'kresuber_qris_image' );
        ?>
        <div class="wrap kresuber-wrap">
            <h1>Kresuber POS Pro <small>v<?php echo $this->version; ?></small></h1>
            <br>
            <?php settings_errors(); ?>
            
            <div class="kresuber-grid">
                <div class="kresuber-card">
                    <h2>Status</h2>
                    <p>Aplikasi POS Aktif. Akses di: <strong><a href="<?php echo home_url('/pos'); ?>" target="_blank"><?php echo home_url('/pos'); ?></a></strong></p>
                </div>

                <div class="kresuber-card">
                    <h2>Pengaturan QRIS</h2>
                    <form method="post" action="options.php">
                        <?php settings_fields( 'kresuber_pos_settings' ); ?>
                        <?php do_settings_sections( 'kresuber_pos_settings' ); ?>
                        
                        <p>Upload gambar QRIS statis untuk ditampilkan di kasir.</p>
                        
                        <div style="margin-bottom:15px;">
                            <?php if($qris_image): ?>
                                <img src="<?php echo esc_url($qris_image); ?>" id="qris-preview" style="max-width:150px;border:1px solid #ddd;border-radius:5px;">
                            <?php endif; ?>
                            <div id="qris-placeholder" style="width:150px;height:150px;background:#f0f0f1;display:<?php echo $qris_image?'none':'flex';?>;align-items:center;justify-content:center;color:#999;">No Image</div>
                        </div>

                        <input type="hidden" name="kresuber_qris_image" id="kresuber_qris_image" value="<?php echo esc_attr($qris_image); ?>">
                        <button type="button" class="button" id="upload-qris-btn">Upload Gambar</button>
                        <button type="button" class="button button-link-delete" id="remove-qris-btn" style="<?php echo $qris_image?'':'display:none;';?>">Hapus</button>
                        
                        <hr>
                        <?php submit_button('Simpan'); ?>
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
                mediaUploader = wp.media.frames.file_frame = wp.media({ title: 'Pilih QRIS', button: { text: 'Gunakan' }, multiple: false });
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#kresuber_qris_image').val(attachment.url);
                    $('#qris-preview').attr('src', attachment.url).show();
                    $('#qris-placeholder').hide();
                    $('#remove-qris-btn').show();
                });
                mediaUploader.open();
            });
            $('#remove-qris-btn').click(function(){
                $('#kresuber_qris_image').val(''); $('#qris-preview').hide(); $('#qris-placeholder').css('display','flex'); $(this).hide();
            });
        });
        </script>
        <style>.kresuber-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;}.kresuber-card{background:#fff;padding:20px;border:1px solid #ccd0d4;box-shadow:0 1px 1px rgba(0,0,0,.04);}</style>
        <?php
    }
}