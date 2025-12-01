<?php
namespace Kresuber\POS_Pro\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

class Admin {
    private $version = '1.7.0';

    public function register_settings() {
        register_setting( 'kresuber_pos_settings', 'kresuber_pos_theme' ); // retail, grosir, etc
        register_setting( 'kresuber_pos_settings', 'kresuber_pos_logo' );
        register_setting( 'kresuber_pos_settings', 'kresuber_qris_image' );
        register_setting( 'kresuber_pos_settings', 'kresuber_printer_width' );
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
        $theme = get_option( 'kresuber_pos_theme', 'retail' );
        $logo = get_option( 'kresuber_pos_logo' );
        $qris = get_option( 'kresuber_qris_image' );
        $width = get_option( 'kresuber_printer_width', '58mm' );
        $cashiers = json_decode(get_option( 'kresuber_cashiers', '[]' )) ?: [];
        
        // Palette Config
        $palettes = [
            'retail'  => ['label'=>'Toko Retail', 'color'=>'#00A78E', 'bg'=>'#F0FBF8'],
            'grosir'  => ['label'=>'Toko Grosir', 'color'=>'#0B5FFF', 'bg'=>'#F1F6FF'],
            'sembako' => ['label'=>'Warung Sembako','color'=>'#F59E0B', 'bg'=>'#FFF7ED'],
            'kelontong'=>['label'=>'Warung Kelontong','color'=>'#7C4DFF', 'bg'=>'#F8F7FF'],
            'sayur'   => ['label'=>'Warung Sayur', 'color'=>'#10B981', 'bg'=>'#F0FFF4'],
            'buah'    => ['label'=>'Warung Buah',  'color'=>'#FF6B6B', 'bg'=>'#FFF5F5'],
        ];

        // Dummy stats for dashboard visualization
        $stats = [
            'sales' => 'Rp ' . number_format(rand(1000000, 5000000), 0, ',', '.'),
            'orders' => rand(10, 50),
            'products' => wp_count_posts('product')->publish
        ];
        ?>
        <div class="k-wrap">
            <header class="k-header">
                <div class="k-brand">
                    <div class="k-icon-box"><span class="dashicons dashicons-store"></span></div>
                    <div>
                        <h1>Kresuber POS Pro</h1>
                        <p>Sistem Kasir UMKM Terintegrasi</p>
                    </div>
                </div>
                <div class="k-actions">
                    <span class="k-pill">v1.7.0 Stable</span>
                    <a href="<?php echo home_url('/pos'); ?>" target="_blank" class="k-btn k-btn-primary">Buka Aplikasi POS <span class="dashicons dashicons-arrow-right-alt"></span></a>
                </div>
            </header>

            <!-- Dashboard Stats -->
            <div class="k-stats-grid">
                <div class="k-stat-card">
                    <div class="icon blue"><span class="dashicons dashicons-chart-area"></span></div>
                    <div><h3><?php echo $stats['sales']; ?></h3><p>Penjualan Hari Ini</p></div>
                </div>
                <div class="k-stat-card">
                    <div class="icon green"><span class="dashicons dashicons-cart"></span></div>
                    <div><h3><?php echo $stats['orders']; ?></h3><p>Total Pesanan</p></div>
                </div>
                <div class="k-stat-card">
                    <div class="icon purple"><span class="dashicons dashicons-products"></span></div>
                    <div><h3><?php echo $stats['products']; ?></h3><p>Total Produk</p></div>
                </div>
            </div>

            <form method="post" action="options.php" class="k-form">
                <?php settings_fields('kresuber_pos_settings'); do_settings_sections('kresuber_pos_settings'); ?>
                
                <div class="k-grid-layout">
                    <!-- LEFT COL -->
                    <div class="k-main-col">
                        <!-- THEME SELECTOR -->
                        <div class="k-card">
                            <div class="k-card-head">
                                <h3>Tema Toko</h3>
                                <p>Pilih skema warna yang sesuai dengan jenis usaha Anda.</p>
                            </div>
                            <div class="k-card-body">
                                <div class="k-theme-grid">
                                    <?php foreach($palettes as $key => $p): ?>
                                    <label class="k-theme-item <?php echo $theme === $key ? 'active' : ''; ?>">
                                        <input type="radio" name="kresuber_pos_theme" value="<?php echo $key; ?>" <?php checked($theme, $key); ?> class="hidden">
                                        <div class="k-swatch" style="background: <?php echo $p['bg']; ?>; border-color: <?php echo $p['color']; ?>;">
                                            <div class="k-swatch-accent" style="background: <?php echo $p['color']; ?>;"></div>
                                            <span style="color: <?php echo $p['color']; ?>; font-weight:bold"><?php echo $p['label']; ?></span>
                                        </div>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- HARDWARE -->
                        <div class="k-card">
                            <div class="k-card-head">
                                <h3>Hardware & Pembayaran</h3>
                            </div>
                            <div class="k-card-body">
                                <div class="k-row">
                                    <div class="k-col">
                                        <label class="k-label">Logo Struk & POS</label>
                                        <div class="k-media-wrap">
                                            <div class="k-img-preview" id="logo-prev">
                                                <?php echo $logo ? '<img src="'.esc_url($logo).'">' : '<span class="dashicons dashicons-format-image"></span>'; ?>
                                            </div>
                                            <div class="k-media-btns">
                                                <input type="hidden" name="kresuber_pos_logo" id="kresuber_pos_logo" value="<?php echo esc_attr($logo); ?>">
                                                <button type="button" class="k-btn k-btn-outline upload-btn" data-target="#kresuber_pos_logo" data-prev="#logo-prev">Upload</button>
                                                <?php if($logo): ?><button type="button" class="k-link-danger remove-btn" data-target="#kresuber_pos_logo">Hapus</button><?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="k-col">
                                        <label class="k-label">QRIS (Static)</label>
                                        <div class="k-media-wrap">
                                            <div class="k-img-preview" id="qris-prev">
                                                <?php echo $qris ? '<img src="'.esc_url($qris).'">' : '<span class="dashicons dashicons-qr"></span>'; ?>
                                            </div>
                                            <div class="k-media-btns">
                                                <input type="hidden" name="kresuber_qris_image" id="kresuber_qris_image" value="<?php echo esc_attr($qris); ?>">
                                                <button type="button" class="k-btn k-btn-outline upload-btn" data-target="#kresuber_qris_image" data-prev="#qris-prev">Upload</button>
                                                <?php if($qris): ?><button type="button" class="k-link-danger remove-btn" data-target="#kresuber_qris_image">Hapus</button><?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <hr class="k-hr">
                                <label class="k-label">Ukuran Kertas Printer</label>
                                <select name="kresuber_printer_width" class="k-select">
                                    <option value="58mm" <?php selected($width, '58mm'); ?>>58mm (Mobile/Bluetooth)</option>
                                    <option value="80mm" <?php selected($width, '80mm'); ?>>80mm (Desktop)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT COL -->
                    <div class="k-side-col">
                        <div class="k-card">
                            <div class="k-card-head"><h3>Kasir</h3></div>
                            <div class="k-card-body">
                                <label class="k-label">Nama Kasir (Pisahkan koma)</label>
                                <?php $c_str = implode(', ', $cashiers); ?>
                                <textarea id="cashier_input" class="k-textarea" rows="5" placeholder="Budi, Siti, Admin"><?php echo esc_textarea($c_str); ?></textarea>
                                <input type="hidden" name="kresuber_cashiers" id="kresuber_cashiers_json" value="<?php echo esc_attr(get_option( 'kresuber_cashiers', '[]' )); ?>">
                            </div>
                            <div class="k-card-foot">
                                <?php submit_button('Simpan Semua', 'primary w-full', 'submit', false); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <script>
        jQuery(document).ready(function($){
            // Media Uploader
            $('.upload-btn').click(function(e){ 
                e.preventDefault(); var t=$(this).data('target'); var p=$($(this).data('prev'));
                var f=wp.media({title:'Pilih Gambar',multiple:false}); 
                f.on('select',function(){ var u=f.state().get('selection').first().toJSON().url; $(t).val(u); p.html('<img src=\"'+u+'\">'); }); 
                f.open(); 
            });
            $('.remove-btn').click(function(){ $($(this).data('target')).val(''); location.reload(); });
            
            // Theme Switcher Visual
            $('.k-theme-item').click(function(){ $('.k-theme-item').removeClass('active'); $(this).addClass('active'); });

            // Cashier Array
            $('form').submit(function(){
                var arr = $('#cashier_input').val().split(',').map(s=>s.trim()).filter(s=>s);
                $('#kresuber_cashiers_json').val(JSON.stringify(arr));
            });
        });
        </script>
        <?php
    }
}
