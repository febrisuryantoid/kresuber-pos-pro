<?php
namespace Kresuber\POS_Pro\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

class Admin {

    private $version = '3.0.0';

    public function register_settings() {
        // General & Branding
        register_setting( 'kresuber_pos_settings', 'kresuber_pos_logo' );
        register_setting( 'kresuber_pos_settings', 'kresuber_qris_image' );
        
        // Printer Config
        register_setting( 'kresuber_pos_settings', 'kresuber_printer_width' ); // 58mm or 80mm
        register_setting( 'kresuber_pos_settings', 'kresuber_printer_conn' ); // browser, bluetooth, wifi
        
        // Cashier Management (Saved as JSON array)
        register_setting( 'kresuber_pos_settings', 'kresuber_cashiers' );
    }

    public function register_menu() {
        add_menu_page( 'Kresuber POS', 'Kresuber POS', 'manage_woocommerce', 'kresuber-pos', [ $this, 'render_dashboard' ], 'dashicons-store', 56 );
    }

    public function enqueue_styles() {
        wp_enqueue_media();
        wp_enqueue_style( 'kresuber-admin', KRESUBER_POS_PRO_URL . 'assets/css/admin.css', [], $this->version );
    }

    public function render_dashboard() {
        // Get Options
        $logo = get_option( 'kresuber_pos_logo' );
        $qris = get_option( 'kresuber_qris_image' );
        $p_width = get_option( 'kresuber_printer_width', '58mm' );
        $p_conn = get_option( 'kresuber_printer_conn', 'browser' );
        $cashiers = get_option( 'kresuber_cashiers', '[]' ); // JSON String
        
        // Ensure valid JSON
        if(empty($cashiers) || !json_decode($cashiers)) $cashiers = '[]';
        ?>
        <div class="wrap kresuber-admin-wrapper">
            <!-- Header -->
            <div class="kresuber-header">
                <div class="k-title">
                    <h1>Kresuber POS Pro <span class="version-tag">v3.0</span></h1>
                    <p>Sistem Kasir Modern & Responsif</p>
                </div>
                <div class="k-actions">
                    <a href="<?php echo home_url('/pos'); ?>" target="_blank" class="k-btn k-btn-primary">
                        <span class="dashicons dashicons-external"></span> Buka Aplikasi POS
                    </a>
                </div>
            </div>

            <?php settings_errors(); ?>

            <form method="post" action="options.php" id="kresuber-form">
                <?php settings_fields( 'kresuber_pos_settings' ); ?>
                <?php do_settings_sections( 'kresuber_pos_settings' ); ?>

                <!-- Tabs Navigation -->
                <div class="k-tabs">
                    <button type="button" class="k-tab active" data-target="#tab-branding">Branding & Tampilan</button>
                    <button type="button" class="k-tab" data-target="#tab-printer">Printer & Hardware</button>
                    <button type="button" class="k-tab" data-target="#tab-cashier">Manajemen Kasir</button>
                </div>

                <!-- TAB 1: BRANDING -->
                <div id="tab-branding" class="k-tab-content active">
                    <div class="k-card">
                        <h3>Logo Toko</h3>
                        <p class="desc">Logo ini akan menggantikan teks "Kresuber" di pojok kiri atas aplikasi POS.</p>
                        <div class="k-upload-box">
                            <div class="preview-area" id="logo-preview-area">
                                <?php if($logo): ?>
                                    <img src="<?php echo esc_url($logo); ?>" class="img-preview">
                                <?php else: ?>
                                    <span class="dashicons dashicons-format-image"></span>
                                <?php endif; ?>
                            </div>
                            <input type="hidden" name="kresuber_pos_logo" id="kresuber_pos_logo" value="<?php echo esc_attr($logo); ?>">
                            <div class="btn-group">
                                <button type="button" class="k-btn k-btn-secondary upload-btn" data-target="#kresuber_pos_logo">Pilih Logo</button>
                                <button type="button" class="k-btn k-btn-danger remove-btn" data-target="#kresuber_pos_logo" <?php echo $logo?'':'style="display:none"';?>>Hapus</button>
                            </div>
                        </div>
                    </div>

                    <div class="k-card">
                        <h3>QRIS Pembayaran</h3>
                        <p class="desc">Gambar QRIS statis yang akan muncul saat metode pembayaran QRIS dipilih.</p>
                        <div class="k-upload-box">
                            <div class="preview-area" id="qris-preview-area">
                                <?php if($qris): ?>
                                    <img src="<?php echo esc_url($qris); ?>" class="img-preview">
                                <?php else: ?>
                                    <span class="dashicons dashicons-qr"></span>
                                <?php endif; ?>
                            </div>
                            <input type="hidden" name="kresuber_qris_image" id="kresuber_qris_image" value="<?php echo esc_attr($qris); ?>">
                            <div class="btn-group">
                                <button type="button" class="k-btn k-btn-secondary upload-btn" data-target="#kresuber_qris_image">Upload QRIS</button>
                                <button type="button" class="k-btn k-btn-danger remove-btn" data-target="#kresuber_qris_image" <?php echo $qris?'':'style="display:none"';?>>Hapus</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: PRINTER -->
                <div id="tab-printer" class="k-tab-content">
                    <div class="k-card">
                        <h3>Konfigurasi Printer Thermal</h3>
                        <div class="form-row">
                            <label>Ukuran Kertas</label>
                            <select name="kresuber_printer_width" class="k-select">
                                <option value="58mm" <?php selected($p_width, '58mm'); ?>>58mm (Standar Kecil)</option>
                                <option value="80mm" <?php selected($p_width, '80mm'); ?>>80mm (Lebar)</option>
                            </select>
                            <p class="desc">Pilih ukuran sesuai kertas printer Anda. 58mm adalah yang paling umum untuk printer portable.</p>
                        </div>
                        
                        <div class="form-row">
                            <label>Metode Koneksi</label>
                            <select name="kresuber_printer_conn" class="k-select">
                                <option value="browser" <?php selected($p_conn, 'browser'); ?>>Browser Print (Default)</option>
                                <option value="bluetooth" <?php selected($p_conn, 'bluetooth'); ?>>Bluetooth (Experimental)</option>
                                <option value="wifi" <?php selected($p_conn, 'wifi'); ?>>WiFi / LAN (via IP)</option>
                            </select>
                            <p class="desc">
                                <strong>Browser Print:</strong> Menggunakan dialog print bawaan browser (Paling stabil).<br>
                                <strong>Bluetooth:</strong> Membutuhkan browser Chrome & HTTPS.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- TAB 3: KASIR -->
                <div id="tab-cashier" class="k-tab-content">
                    <div class="k-card">
                        <h3>Daftar Kasir</h3>
                        <p class="desc">Kelola nama-nama kasir yang bisa dipilih saat shift dimulai.</p>
                        
                        <div id="cashier-manager">
                            <div class="cashier-input-group">
                                <input type="text" id="new-cashier-name" placeholder="Nama Kasir Baru..." class="k-input">
                                <button type="button" id="add-cashier-btn" class="k-btn k-btn-primary">Tambah</button>
                            </div>
                            
                            <ul id="cashier-list"></ul>
                            <!-- Hidden input store JSON -->
                            <input type="hidden" name="kresuber_cashiers" id="kresuber_cashiers" value="<?php echo esc_attr($cashiers); ?>">
                        </div>
                    </div>
                </div>

                <div class="footer-actions">
                    <?php submit_button( 'Simpan Semua Perubahan' ); ?>
                </div>
            </form>
        </div>

        <!-- JS for Admin UI -->
        <script>
        jQuery(document).ready(function($){
            // Tabs
            $('.k-tab').click(function(){
                $('.k-tab').removeClass('active');
                $(this).addClass('active');
                $('.k-tab-content').removeClass('active');
                $($(this).data('target')).addClass('active');
            });

            // Media Uploader
            var mediaUploader;
            $('.upload-btn').click(function(e) {
                e.preventDefault();
                var targetId = $(this).data('target');
                if (mediaUploader) { mediaUploader.open(); return; }
                mediaUploader = wp.media.frames.file_frame = wp.media({ title: 'Pilih Gambar', button: { text: 'Gunakan' }, multiple: false });
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $(targetId).val(attachment.url);
                    $(targetId).parent().find('.img-preview').remove();
                    $(targetId).parent().find('.preview-area').html('<img src=\"'+attachment.url+'\" class=\"img-preview\">');
                    $(targetId).parent().find('.remove-btn').show();
                });
                mediaUploader.open();
            });
            $('.remove-btn').click(function(){
                var targetId = $(this).data('target');
                $(targetId).val('');
                $(targetId).parent().find('.img-preview').remove();
                $(targetId).parent().find('.preview-area').html('<span class=\"dashicons dashicons-format-image\"></span>');
                $(this).hide();
            });

            // Cashier Manager
            let cashiers = JSON.parse($('#kresuber_cashiers').val() || '[]');
            
            function renderCashiers() {
                $('#cashier-list').html('');
                cashiers.forEach((name, index) => {
                    $('#cashier-list').append(`
                        <li>
                            <span class="c-name">${name}</span>
                            <button type="button" class="del-cashier" data-index="${index}"><span class="dashicons dashicons-trash"></span></button>
                        </li>
                    `);
                });
                $('#kresuber_cashiers').val(JSON.stringify(cashiers));
            }

            $('#add-cashier-btn').click(function(){
                var name = $('#new-cashier-name').val().trim();
                if(name) {
                    cashiers.push(name);
                    $('#new-cashier-name').val('');
                    renderCashiers();
                }
            });

            $(document).on('click', '.del-cashier', function(){
                var idx = $(this).data('index');
                cashiers.splice(idx, 1);
                renderCashiers();
            });

            renderCashiers();
        });
        </script>

        <style>
            /* Modern Admin CSS */
            .kresuber-admin-wrapper { background: #f0f0f1; padding: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .kresuber-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; background: #fff; padding: 20px 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
            .k-title h1 { margin: 0; font-size: 24px; color: #1e293b; display: flex; align-items: center; gap: 10px; }
            .version-tag { background: #e0f2fe; color: #0284c7; font-size: 12px; padding: 2px 8px; border-radius: 99px; font-weight: bold; }
            .k-btn { padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s; }
            .k-btn-primary { background: #2563eb; color: #fff; } .k-btn-primary:hover { background: #1d4ed8; color: #fff; }
            .k-btn-secondary { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; } .k-btn-secondary:hover { background: #e2e8f0; }
            .k-btn-danger { background: #fee2e2; color: #dc2626; } .k-btn-danger:hover { background: #fecaca; }
            
            /* Tabs */
            .k-tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 2px; }
            .k-tab { background: none; border: none; padding: 10px 20px; font-size: 14px; font-weight: 600; color: #64748b; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -4px; transition: 0.3s; }
            .k-tab.active { color: #2563eb; border-bottom-color: #2563eb; }
            .k-tab-content { display: none; animation: fadeIn 0.3s; }
            .k-tab-content.active { display: block; }

            .k-card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 20px; }
            .k-card h3 { margin-top: 0; font-size: 18px; color: #334155; margin-bottom: 10px; }
            .desc { color: #64748b; font-size: 13px; margin-bottom: 15px; }
            
            .k-upload-box { display: flex; align-items: center; gap: 20px; background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px dashed #cbd5e1; }
            .preview-area { width: 80px; height: 80px; display: flex; align-items: center; justify-content: center; background: #fff; border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden; }
            .img-preview { width: 100%; height: 100%; object-fit: contain; }
            .dashicons-format-image, .dashicons-qr { font-size: 32px; color: #94a3b8; height: 32px; width: 32px; }

            .form-row { margin-bottom: 20px; }
            .k-select, .k-input { width: 100%; max-width: 400px; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; }
            
            /* Cashier List */
            #cashier-manager { max-width: 500px; }
            .cashier-input-group { display: flex; gap: 10px; margin-bottom: 15px; }
            #cashier-list { list-style: none; padding: 0; margin: 0; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
            #cashier-list li { display: flex; justify-content: space-between; align-items: center; padding: 10px 15px; background: #fff; border-bottom: 1px solid #f1f5f9; }
            #cashier-list li:last-child { border-bottom: none; }
            .del-cashier { background: none; border: none; color: #ef4444; cursor: pointer; padding: 5px; }
            .del-cashier:hover { background: #fee2e2; border-radius: 4px; }

            @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
        </style>
        <?php
    }
}