<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Kresuber POS Pro</title>
    
    <!-- Libraries -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <script src="https://unpkg.com/dexie/dist/dexie.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    
    <link rel="stylesheet" href="<?php echo KRESUBER_POS_PRO_URL; ?>assets/css/pos-style.css">
    
    <style>
        /* Fallback Loading jika JS Error */
        #app-loading { 
            position: fixed; inset: 0; background: #f8fafc; z-index: 9999; 
            display: flex; flex-direction: column; align-items: center; justify-content: center;
        }
        [v-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 h-screen w-screen overflow-hidden text-slate-800 font-sans">
    
    <!-- LOADING SCREEN -->
    <div id="app-loading">
        <div class="mb-4 text-green-600"><i class="ri-store-3-fill text-5xl"></i></div>
        <h2 class="text-xl font-bold text-slate-700">Memuat Sistem POS...</h2>
        <p class="text-slate-400 text-sm mt-2">Pastikan koneksi internet stabil.</p>
        <noscript>
            <p class="text-red-500 mt-4 font-bold">Error: JavaScript harus diaktifkan!</p>
        </noscript>
    </div>

    <div id="app" v-cloak class="flex h-full w-full">
        
        <!-- SIDEBAR UTAMA (Left) -->
        <div class="flex-1 flex flex-col h-full relative border-r border-gray-200 bg-white">
            
            <!-- HEADER -->
            <div class="h-20 px-6 border-b border-gray-100 flex items-center justify-between bg-white z-30">
                <div class="flex items-center gap-4 w-full max-w-3xl">
                    <div class="flex items-center gap-2 mr-4 text-green-600">
                        <i class="ri-store-3-fill text-3xl"></i>
                        <span class="font-bold text-xl tracking-tight hidden md:block">Kresuber</span>
                    </div>

                    <div class="relative w-full group">
                        <div class="absolute left-4 top-3.5 text-gray-400 group-focus-within:text-green-600 transition">
                            <i class="ri-search-2-line text-lg"></i>
                        </div>
                        <input v-model="searchQuery" type="text" placeholder="Cari Produk atau Scan Barcode (F3)..." 
                            class="w-full pl-12 pr-4 py-3 bg-gray-100 border-none rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 transition shadow-inner font-medium text-sm">
                        
                        <div class="absolute right-2 top-2 flex gap-1">
                            <button v-if="searchQuery" @click="searchQuery=''" class="p-1.5 text-gray-400 hover:text-red-500"><i class="ri-close-circle-fill"></i></button>
                            <button @click="syncProducts" :class="{'animate-spin text-green-600': syncing}" class="p-1.5 text-gray-400 hover:text-green-600" title="Sync"><i class="ri-refresh-line"></i></button>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button @click="viewMode = 'orders'; fetchOrders()" class="flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-sm transition">
                        <i class="ri-file-list-3-line"></i> <span class="hidden lg:inline">Riwayat Order</span>
                    </button>
                    
                    <div class="h-8 w-[1px] bg-gray-200 mx-1"></div>
                    
                    <div class="flex items-center gap-2">
                        <div class="text-right hidden lg:block leading-tight">
                            <p class="text-xs font-bold text-slate-800"><?php echo esc_html(wp_get_current_user()->display_name); ?></p>
                            <p class="text-[10px] text-green-600 font-bold uppercase">Online</p>
                        </div>
                        <img src="<?php echo esc_url(get_avatar_url(get_current_user_id())); ?>" class="w-10 h-10 rounded-full border-2 border-green-100">
                    </div>
                    
                    <a href="<?php echo esc_url(admin_url()); ?>" class="p-2 text-gray-400 hover:text-red-500 transition" title="Exit"><i class="ri-logout-box-r-line text-xl"></i></a>
                </div>
            </div>

            <!-- CHIPS KATEGORI -->
            <div class="py-3 px-6 border-b border-gray-100 bg-white overflow-x-auto no-scrollbar whitespace-nowrap z-20 shadow-sm">
                <button @click="setCategory('all')" 
                    :class="currentCategory === 'all' ? 'bg-slate-900 text-white shadow-lg' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                    class="px-5 py-2 rounded-full text-sm font-bold mr-2 transition-all duration-200 inline-flex items-center gap-2 border border-transparent">
                    <i class="ri-apps-fill"></i> Semua
                </button>
                
                <button v-for="cat in categories" :key="cat.slug" @click="setCategory(cat.slug)" 
                    :class="currentCategory === cat.slug ? 'bg-green-600 text-white shadow-lg shadow-green-200' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                    class="px-5 py-2 rounded-full text-sm font-bold mr-2 transition-all duration-200 border border-transparent">
                    {{ cat.name }}
                </button>
            </div>

            <!-- KONTEN UTAMA -->
            <div v-if="viewMode === 'pos'" class="flex-1 overflow-y-auto p-6 bg-slate-50 custom-scrollbar">
                <div v-if="loading" class="flex flex-col items-center justify-center h-full text-slate-400">
                    <i class="ri-loader-4-line text-4xl animate-spin mb-3 text-green-600"></i>
                    <p class="font-medium animate-pulse">Memuat Produk...</p>
                </div>

                <div v-else-if="products.length === 0" class="flex flex-col items-center justify-center h-full text-slate-400">
                    <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center shadow-sm mb-4"><i class="ri-search-eye-line text-4xl text-slate-300"></i></div>
                    <p class="font-medium">Tidak ada produk ditemukan</p>
                </div>

                <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-4 pb-20">
                    <div v-for="product in products" :key="product.id" @click="addToCart(product)" 
                         class="bg-white rounded-2xl shadow-sm hover:shadow-lg hover:-translate-y-1 cursor-pointer overflow-hidden flex flex-col border border-transparent hover:border-green-500 transition-all duration-300 group h-[260px]">
                        
                        <div class="h-40 bg-gray-100 relative overflow-hidden">
                            <img :src="product.image" loading="lazy" class="w-full h-full object-cover group-hover:scale-110 transition duration-500">
                            <span v-if="product.stock_status==='instock'" class="absolute top-2 right-2 bg-slate-900/80 backdrop-blur text-white text-[10px] px-2 py-1 rounded-lg font-bold shadow">
                                Stok: {{ product.stock }}
                            </span>
                            <span v-else class="absolute inset-0 bg-white/80 flex items-center justify-center font-bold text-red-500">HABIS</span>
                        </div>
                        
                        <div class="p-4 flex flex-col flex-1 justify-between">
                            <div>
                                <h3 class="font-bold text-slate-800 text-sm leading-snug line-clamp-2 mb-1 group-hover:text-green-600 transition">{{ product.name }}</h3>
                                <p class="text-[10px] text-gray-400 font-mono tracking-wide">{{ product.sku }}</p>
                            </div>
                            <div class="flex justify-between items-center mt-2">
                                <span class="text-green-700 font-extrabold text-base">{{ formatPrice(product.price) }}</span>
                                <button class="w-8 h-8 rounded-full bg-slate-100 text-slate-400 group-hover:bg-green-600 group-hover:text-white flex items-center justify-center transition shadow-sm">
                                    <i class="ri-add-line text-lg"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- VIEW: RIWAYAT ORDER -->
            <div v-if="viewMode === 'orders'" class="flex-1 overflow-y-auto p-6 bg-slate-50 custom-scrollbar animate-fade-in">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-slate-800">Riwayat Pesanan</h2>
                    <button @click="viewMode = 'pos'" class="px-4 py-2 bg-white border rounded-lg hover:bg-gray-50 text-sm font-bold shadow-sm">
                        <i class="ri-arrow-left-line mr-1"></i> Kembali
                    </button>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50 text-slate-500 text-xs uppercase font-bold">
                            <tr>
                                <th class="p-4">ID</th>
                                <th class="p-4">Waktu</th>
                                <th class="p-4">Customer</th>
                                <th class="p-4">Produk</th>
                                <th class="p-4">Total</th>
                                <th class="p-4">Status</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-slate-700 divide-y divide-gray-100">
                            <tr v-if="recentOrders.length === 0"><td colspan="6" class="p-8 text-center text-gray-400">Belum ada data.</td></tr>
                            <tr v-for="order in recentOrders" :key="order.id" class="hover:bg-gray-50 transition">
                                <td class="p-4 font-bold">#{{ order.number }}</td>
                                <td class="p-4 text-gray-500">{{ order.date }}</td>
                                <td class="p-4">{{ order.customer }}</td>
                                <td class="p-4 text-gray-500 truncate max-w-xs">{{ order.items_summary }}</td>
                                <td class="p-4 font-bold text-green-600">{{ order.total }}</td>
                                <td class="p-4"><span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-bold uppercase">{{ order.status }}</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- SIDEBAR KANAN (CART) -->
        <div class="w-[420px] bg-white border-l shadow-2xl z-40 flex flex-col flex-shrink-0 h-full">
            <div class="px-6 py-5 border-b border-gray-100 flex justify-between items-center bg-white">
                <h2 class="font-bold text-xl text-slate-800 flex items-center gap-2">
                    <span class="bg-green-100 text-green-600 p-1 rounded-lg"><i class="ri-shopping-basket-2-fill"></i></span> Keranjang
                </h2>
                <div class="flex gap-2">
                    <button @click="toggleHold" class="w-10 h-10 rounded-xl border border-gray-200 text-gray-400 hover:text-orange-500 hover:bg-orange-50 transition relative" title="Hold">
                        <i class="ri-pause-circle-line text-xl"></i>
                        <span v-if="heldItems.length" class="absolute top-0 right-0 w-3 h-3 bg-red-500 border-2 border-white rounded-full"></span>
                    </button>
                    <button @click="clearCart" class="w-10 h-10 rounded-xl border border-gray-200 text-gray-400 hover:text-red-500 hover:bg-red-50 transition" title="Hapus"><i class="ri-delete-bin-line text-xl"></i></button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto p-4 custom-scrollbar bg-white space-y-3">
                <div v-if="cart.length === 0" class="flex flex-col items-center justify-center h-full text-slate-300">
                    <i class="ri-shopping-cart-2-line text-6xl mb-3 opacity-20"></i>
                    <p class="text-sm font-bold text-gray-400">Keranjang Kosong</p>
                </div>

                <div v-for="item in cart" :key="item.id" class="flex gap-4 p-3 bg-white rounded-2xl border border-gray-100 shadow-sm hover:border-green-200 transition group">
                    <div class="w-16 h-16 rounded-xl bg-gray-100 overflow-hidden flex-shrink-0">
                        <img :src="item.image" class="w-full h-full object-cover">
                    </div>
                    <div class="flex-1 min-w-0 flex flex-col justify-between py-0.5">
                        <div class="flex justify-between items-start gap-1">
                            <h4 class="font-bold text-slate-700 text-sm leading-snug line-clamp-2">{{ item.name }}</h4>
                            <button @click="removeFromCart(item)" class="text-gray-300 hover:text-red-500 -mt-1"><i class="ri-close-circle-fill text-lg"></i></button>
                        </div>
                        <div class="flex justify-between items-end">
                            <span class="text-xs font-semibold text-gray-400">@ {{ formatNumber(item.price) }}</span>
                            <div class="flex items-center bg-gray-50 rounded-lg p-1 border border-gray-100">
                                <button @click="updateQty(item, -1)" class="w-7 h-7 rounded-md bg-white text-slate-500 hover:text-red-500 font-bold shadow-sm flex items-center justify-center"><i class="ri-subtract-line"></i></button>
                                <span class="w-8 text-center text-sm font-bold text-slate-800">{{ item.qty }}</span>
                                <button @click="updateQty(item, 1)" class="w-7 h-7 rounded-md bg-green-500 text-white hover:bg-green-600 font-bold shadow-sm flex items-center justify-center"><i class="ri-add-line"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-6 bg-white border-t border-gray-100 shadow-[0_-10px_40px_rgba(0,0,0,0.05)] z-20 rounded-t-3xl">
                <div class="space-y-3 mb-6">
                    <div class="flex justify-between text-sm text-slate-500"><span>Subtotal</span> <span class="font-medium text-slate-800">{{ formatPrice(subTotal) }}</span></div>
                    <div class="flex justify-between text-sm text-slate-500"><span>Pajak ({{ taxRate }}%)</span> <span class="font-medium text-slate-800">{{ formatPrice(taxAmount) }}</span></div>
                    <div class="border-t border-dashed border-gray-200 my-1"></div>
                    <div class="flex justify-between items-center">
                        <span class="font-bold text-lg text-slate-800">Total Tagihan</span>
                        <span class="font-extrabold text-3xl text-green-600 tracking-tight">{{ formatPrice(grandTotal) }}</span>
                    </div>
                </div>
                <button @click="openPayModal" :disabled="cart.length === 0" class="w-full bg-slate-900 text-white py-4 rounded-xl font-bold text-lg hover:bg-slate-800 transition-all active:scale-[0.98] shadow-xl shadow-slate-300 disabled:opacity-50 disabled:shadow-none flex items-center justify-center gap-3">
                    Bayar Sekarang
                </button>
            </div>
        </div>

        <!-- PAYMENT MODAL -->
        <div v-if="showPayModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/70 backdrop-blur-sm" @click="showPayModal = false"></div>
            <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl relative z-10 overflow-hidden flex flex-col max-h-[90vh]">
                <div class="p-5 border-b bg-gray-50 flex justify-between items-center shrink-0">
                    <h3 class="font-bold text-lg text-slate-800">Metode Pembayaran</h3>
                    <button @click="showPayModal = false" class="w-8 h-8 rounded-full bg-white text-gray-400 hover:text-red-500 shadow-sm flex items-center justify-center"><i class="ri-close-line text-xl"></i></button>
                </div>
                <div class="flex-1 overflow-y-auto p-6 custom-scrollbar">
                    <div class="text-center mb-8">
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Total</p>
                        <h2 class="text-5xl font-extrabold text-slate-900 tracking-tighter">{{ formatPrice(grandTotal) }}</h2>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <button @click="paymentMethod='cash'" :class="paymentMethod === 'cash' ? 'ring-2 ring-green-500 bg-green-50 border-transparent' : 'border-gray-200 hover:border-green-300'" class="border p-5 rounded-2xl flex flex-col items-center justify-center transition"><i class="ri-money-dollar-circle-fill text-3xl text-green-600 mb-2"></i><span class="font-bold text-slate-700">Tunai</span></button>
                        <button @click="paymentMethod='qris'" :class="paymentMethod === 'qris' ? 'ring-2 ring-blue-500 bg-blue-50 border-transparent' : 'border-gray-200 hover:border-blue-300'" class="border p-5 rounded-2xl flex flex-col items-center justify-center transition"><i class="ri-qr-code-line text-3xl text-blue-600 mb-2"></i><span class="font-bold text-slate-700">QRIS</span></button>
                    </div>
                    <div v-if="paymentMethod === 'cash'" class="space-y-4">
                        <div class="relative"><span class="absolute left-4 top-4 text-gray-400 font-bold text-lg">Rp</span><input type="number" v-model="cashReceived" ref="cashInput" placeholder="0" class="w-full pl-12 pr-4 py-4 text-2xl font-bold border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 outline-none"></div>
                        <div class="flex gap-2 overflow-x-auto no-scrollbar pb-1">
                            <button @click="cashReceived=grandTotal" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-xs font-bold border border-gray-200 whitespace-nowrap">Uang Pas</button>
                            <button v-for="amt in quickCashOptions" :key="amt" @click="cashReceived=amt" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-xs font-bold border border-gray-200 whitespace-nowrap">{{ formatNumber(amt) }}</button>
                        </div>
                        <div class="flex justify-between items-center p-4 bg-gray-50 rounded-xl border border-gray-100">
                            <span class="font-bold text-slate-500">Kembalian</span>
                            <span class="font-extrabold text-2xl" :class="cashChange >= 0 ? 'text-green-600' : 'text-red-500'">{{ formatPrice(Math.max(0, cashChange)) }}</span>
                        </div>
                    </div>
                    <div v-if="paymentMethod === 'qris'" class="text-center bg-white p-4 border rounded-xl">
                        <p class="text-sm font-bold text-slate-500 mb-4">Scan QRIS:</p>
                        <div v-if="qrisUrl" class="inline-block p-2 border-2 border-slate-900 rounded-xl"><img :src="qrisUrl" class="max-w-[200px] h-auto rounded-lg"></div>
                        <div v-else class="p-8 bg-gray-100 rounded-lg text-gray-400 text-sm"><i class="ri-image-off-line text-2xl mb-2 block"></i>Belum ada gambar QRIS.<br>Upload di Dashboard Admin.</div>
                    </div>
                </div>
                <div class="p-5 border-t bg-gray-50 shrink-0">
                    <button @click="processCheckout" :disabled="processing || (paymentMethod === 'cash' && cashChange < 0)" class="w-full bg-green-600 text-white py-4 rounded-xl font-bold text-lg hover:bg-green-700 disabled:opacity-50 transition flex items-center justify-center gap-2">
                        <i v-if="processing" class="ri-loader-4-line animate-spin text-xl"></i>
                        <span v-else>{{ paymentMethod === 'qris' ? 'Konfirmasi Selesai' : 'Bayar & Cetak Struk' }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- HIDDEN RECEIPT -->
        <div id="receipt-print" class="hidden">
            <div style="width:58mm;text-align:center;font-family:'Courier New',monospace;font-size:11px;line-height:1.2;color:#000;">
                <h3 style="margin:0;font-weight:bold;font-size:14px;"><?php echo esc_html(get_bloginfo('name')); ?></h3>
                <p style="margin:2px 0 5px;font-size:9px;">POS Receipt</p>
                <div style="border-top:1px dashed #000;margin:5px 0;"></div>
                <div style="text-align:left;">No: #{{ lastReceipt.orderNumber }}<br>Tgl: {{ lastReceipt.date }}<br>Kasir: {{ lastReceipt.cashier }}</div>
                <div style="border-top:1px dashed #000;margin:5px 0;"></div>
                <table style="width:100%;text-align:left;border-collapse:collapse;"><tr v-for="item in lastReceipt.items"><td style="padding-bottom:2px;"><strong>{{ item.name }}</strong><br>{{ item.qty }} x {{ formatNumber(item.price) }}</td><td style="text-align:right;vertical-align:top;">{{ formatNumber(item.qty * item.price) }}</td></tr></table>
                <div style="border-top:1px dashed #000;margin:5px 0;"></div>
                <div style="display:flex;justify-content:space-between;"><span>TOTAL</span> <strong>{{ formatNumber(lastReceipt.grandTotal) }}</strong></div>
                <div v-if="lastReceipt.paymentMethod==='cash'"><div style="display:flex;justify-content:space-between;"><span>Tunai</span> <span>{{ formatNumber(lastReceipt.cashReceived) }}</span></div><div style="display:flex;justify-content:space-between;"><span>Kembali</span> <span>{{ formatNumber(lastReceipt.cashChange) }}</span></div></div>
                <div v-else style="text-align:center;margin-top:5px;font-style:italic;">[Lunas via {{ lastReceipt.paymentMethod.toUpperCase() }}]</div>
                <div style="border-top:1px dashed #000;margin:10px 0;"></div>
                <div style="text-align:center;font-size:10px;">Terima Kasih!</div>
            </div>
        </div>

    </div>

    <!-- CONFIG JS SAFE MODE -->
    <script>
        globalThis.kresuberParams = {
            apiUrl: '<?php echo esc_url_raw( rest_url( "kresuber-pos/v1" ) ); ?>',
            nonce: '<?php echo wp_create_nonce( "wp_rest" ); ?>',
            currencySymbol: '<?php echo esc_js( get_woocommerce_currency_symbol() ); ?>',
            taxRate: 11,
            cashierName: '<?php echo esc_js( wp_get_current_user()->display_name ); ?>',
            qrisUrl: '<?php global $kresuber_qris_url; echo esc_url( $kresuber_qris_url ); ?>'
        };
    </script>
    <script src="<?php echo KRESUBER_POS_PRO_URL; ?>assets/js/pos-app.js"></script>
    <script>
        // Hapus Loading Screen jika Vue berhasil mount
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                const appContent = document.getElementById('app');
                if (appContent && appContent.innerHTML.trim() !== '') {
                    document.getElementById('app-loading').style.display = 'none';
                }
            }, 1000); // Timeout safety
        });
    </script>
</body>
</html>