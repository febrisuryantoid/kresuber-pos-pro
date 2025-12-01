<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kresuber POS Pro</title>
    
    <!-- CSS Framework & Icons -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo KRESUBER_POS_URL; ?>assets/css/pos-style.css">
    
    <!-- DATABASE LOKAL (Kunci kecepatan WCPOS) -->
    <script src="https://unpkg.com/dexie/dist/dexie.js"></script>
    
    <!-- Utilities -->
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
</head>
<body class="bg-gray-100 overflow-hidden font-sans select-none">
    
    <div id="app" v-cloak class="flex h-screen">
        
        <!-- SIDEBAR NAVIGASI -->
        <div class="w-20 bg-slate-900 flex flex-col items-center py-4 text-white shadow-xl z-40">
            <div class="mb-6"><i class="ri-store-3-fill text-3xl text-green-400"></i></div>
            
            <button @click="syncProducts" :class="{'animate-spin text-white': syncing, 'text-green-400': !syncing}" class="w-12 h-12 rounded-xl mb-4 bg-slate-800 hover:bg-slate-700 transition flex items-center justify-center relative group">
                <i class="ri-refresh-line text-xl"></i>
                <span class="absolute left-14 bg-black text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition whitespace-nowrap pointer-events-none">Sync Data</span>
            </button>

            <!-- Kategori Dinamis -->
            <div class="flex-1 w-full flex flex-col items-center gap-3 overflow-y-auto no-scrollbar px-2">
                <button @click="filterCategory('all')" :class="currentCategory === 'all' ? 'bg-green-600 shadow-lg shadow-green-900/50' : 'bg-slate-800 hover:bg-slate-700'" class="w-12 h-12 rounded-xl transition flex items-center justify-center">
                    <i class="ri-apps-fill text-lg"></i>
                </button>
                <button v-for="cat in categories" :key="cat.slug" @click="filterCategory(cat.slug)" 
                    :class="currentCategory === cat.slug ? 'bg-green-600 shadow-lg shadow-green-900/50' : 'bg-slate-800 hover:bg-slate-700'" 
                    class="w-12 h-12 rounded-xl transition flex flex-col items-center justify-center text-[10px] font-bold overflow-hidden p-1 leading-tight text-center break-words">
                    {{ cat.name }}
                </button>
            </div>
            
            <a href="<?php echo admin_url(); ?>" class="mt-4 w-12 h-12 bg-red-600/80 hover:bg-red-600 rounded-xl flex items-center justify-center transition text-white">
                <i class="ri-logout-box-line text-xl"></i>
            </a>
        </div>

        <!-- AREA PRODUK -->
        <div class="flex-1 flex flex-col relative bg-slate-50">
            <!-- Header Search -->
            <div class="h-16 bg-white border-b flex items-center px-4 justify-between shadow-sm z-20">
                <div class="flex items-center gap-3 w-full max-w-xl">
                    <div class="relative w-full group">
                        <div class="absolute left-3 top-2.5 text-gray-400 group-focus-within:text-green-600 transition">
                            <i class="ri-barcode-line text-lg"></i>
                        </div>
                        <input v-model="searchQuery" @input="searchLocal" ref="searchInput" type="text" 
                            placeholder="Scan Barcode / Cari Nama / SKU (Tekan F3)" 
                            class="w-full pl-10 pr-10 py-2.5 border border-gray-200 bg-gray-50 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none transition text-sm font-medium">
                        <button v-if="searchQuery" @click="clearSearch" class="absolute right-3 top-2.5 text-gray-400 hover:text-red-500">
                            <i class="ri-close-circle-fill"></i>
                        </button>
                    </div>
                </div>
                
                <div class="flex items-center gap-4">
                     <!-- Indikator Status Database -->
                    <div class="flex items-center gap-2 px-3 py-1.5 bg-gray-100 rounded-full border border-gray-200">
                        <div :class="dbReady ? 'bg-green-500' : 'bg-red-500'" class="w-2.5 h-2.5 rounded-full animate-pulse"></div>
                        <span class="text-xs font-bold text-gray-600">{{ productCount }} Produk</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <img src="<?php echo get_avatar_url(get_current_user_id()); ?>" class="w-9 h-9 rounded-full border border-gray-300">
                        <div class="hidden lg:block leading-tight">
                            <p class="text-xs font-bold text-slate-800"><?php echo wp_get_current_user()->display_name; ?></p>
                            <p class="text-[10px] text-green-600 font-bold uppercase">Kasir</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grid Produk -->
            <div class="flex-1 overflow-y-auto p-4 content-start" id="product-grid">
                <div v-if="loading" class="flex flex-col justify-center items-center h-full text-gray-400">
                    <i class="ri-loader-4-line text-4xl animate-spin mb-2 text-green-600"></i>
                    <p class="text-sm font-medium">Memuat Database...</p>
                </div>
                
                <div v-else-if="products.length === 0" class="flex flex-col items-center justify-center h-full text-gray-400">
                    <div class="bg-white p-6 rounded-full shadow-sm mb-4"><i class="ri-inbox-2-line text-4xl text-gray-300"></i></div>
                    <p class="font-medium">Produk tidak ditemukan</p>
                    <p class="text-xs mt-1">Coba kata kunci lain atau sync ulang.</p>
                </div>

                <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-3 content-start">
                    <div v-for="product in products" :key="product.id" @click="addToCart(product)" 
                         class="bg-white rounded-xl shadow-sm hover:shadow-lg cursor-pointer border border-transparent hover:border-green-500 transition-all duration-200 flex flex-col overflow-hidden group h-[220px] active:scale-95">
                        
                        <!-- Image Container -->
                        <div class="h-32 bg-gray-100 overflow-hidden relative">
                            <img :src="product.image" loading="lazy" class="w-full h-full object-cover group-hover:scale-110 transition duration-500">
                            <!-- Stock Badge -->
                            <div class="absolute top-2 right-2 bg-slate-900/80 backdrop-blur text-white text-[10px] px-2 py-0.5 rounded-md font-bold shadow-sm border border-white/10">
                                {{ product.stock }}
                            </div>
                        </div>

                        <!-- Info -->
                        <div class="p-3 flex flex-col flex-1 justify-between bg-white">
                            <div>
                                <h3 class="font-bold text-slate-700 text-xs leading-snug line-clamp-2 mb-1">{{ product.name }}</h3>
                                <p class="text-[10px] text-gray-400">{{ product.sku }}</p>
                            </div>
                            <div class="flex justify-between items-end mt-2">
                                <p class="text-green-600 font-bold text-sm">{{ formatPrice(product.price) }}</p>
                                <button class="w-6 h-6 rounded-full bg-green-50 text-green-600 hover:bg-green-500 hover:text-white flex items-center justify-center transition">
                                    <i class="ri-add-line"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SIDEBAR KERANJANG (Cart) -->
        <div class="w-[420px] bg-white border-l shadow-2xl z-30 flex flex-col h-full flex-shrink-0">
            <!-- Cart Header -->
            <div class="px-4 py-3 border-b bg-white flex justify-between items-center shadow-sm z-10">
                <h2 class="font-bold text-slate-800 flex items-center gap-2">
                    <i class="ri-shopping-bag-3-fill text-green-600"></i> Keranjang
                </h2>
                <div class="flex gap-2">
                    <!-- Tombol Hold -->
                    <button @click="toggleHold" :class="heldItems.length ? 'text-orange-500 bg-orange-50 border-orange-200' : 'text-gray-400 border-transparent'" class="w-9 h-9 border rounded-lg hover:bg-orange-100 transition flex items-center justify-center relative" title="Hold / Restore Order">
                        <i class="ri-pause-circle-line text-lg"></i>
                        <span v-if="heldItems.length" class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full border-2 border-white"></span>
                    </button>
                    <!-- Tombol Clear -->
                    <button @click="clearCart" class="w-9 h-9 border border-transparent text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition flex items-center justify-center" title="Hapus Semua">
                        <i class="ri-delete-bin-line text-lg"></i>
                    </button>
                </div>
            </div>

            <!-- Cart Items List -->
            <div class="flex-1 overflow-y-auto p-3 space-y-2 bg-slate-50">
                <div v-if="cart.length === 0" class="flex flex-col items-center justify-center h-[60%] text-gray-300">
                    <i class="ri-shopping-cart-line text-6xl mb-3 opacity-50"></i>
                    <p class="text-sm font-medium">Keranjang Kosong</p>
                    <p class="text-xs text-center px-10 mt-2">Scan barcode atau klik produk untuk menambahkan.</p>
                </div>

                <div v-for="(item, index) in cart" :key="index" class="bg-white p-3 rounded-xl border border-gray-100 shadow-sm flex gap-3 relative group transition hover:border-green-200">
                    <div class="w-14 h-14 rounded-lg bg-gray-100 overflow-hidden flex-shrink-0">
                        <img :src="item.image" class="w-full h-full object-cover">
                    </div>
                    
                    <div class="flex-1 min-w-0 flex flex-col justify-between">
                        <div class="flex justify-between items-start gap-2">
                            <h4 class="text-sm font-bold text-slate-700 leading-tight line-clamp-2">{{ item.name }}</h4>
                            <button @click="removeFromCart(item)" class="text-gray-300 hover:text-red-500 transition"><i class="ri-close-circle-fill text-lg"></i></button>
                        </div>
                        
                        <div class="flex justify-between items-end mt-1">
                            <p class="text-xs font-semibold text-gray-500">{{ formatPrice(item.price) }}</p>
                            
                            <!-- Qty Control -->
                            <div class="flex items-center bg-gray-100 rounded-lg p-0.5 border border-gray-200">
                                <button @click="decreaseQty(item)" class="w-7 h-7 hover:bg-white hover:shadow rounded-md text-gray-600 font-bold transition flex items-center justify-center">-</button>
                                <input type="number" v-model.number="item.qty" class="w-8 h-7 bg-transparent text-center text-sm font-bold focus:outline-none p-0 text-slate-800">
                                <button @click="increaseQty(item)" class="w-7 h-7 hover:bg-white hover:shadow rounded-md text-green-600 font-bold transition flex items-center justify-center">+</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cart Summary & Checkout -->
            <div class="bg-white border-t p-5 shadow-[0_-5px_20px_-5px_rgba(0,0,0,0.1)] z-20">
                <div class="space-y-2 mb-5">
                    <div class="flex justify-between text-sm text-gray-500">
                        <span>Subtotal</span>
                        <span class="font-medium text-slate-700">{{ formatPrice(subTotal) }}</span>
                    </div>
                    <div class="flex justify-between text-sm text-gray-500">
                        <span>Pajak ({{ taxRate }}%)</span>
                        <span class="font-medium text-slate-700">{{ formatPrice(taxAmount) }}</span>
                    </div>
                    <div class="border-t border-dashed my-2"></div>
                    <div class="flex justify-between items-center">
                        <span class="text-base font-bold text-slate-800">Total Bayar</span>
                        <span class="text-2xl font-bold text-green-600">{{ formatPrice(grandTotal) }}</span>
                    </div>
                </div>

                <button @click="openPayModal" :disabled="cart.length === 0" 
                    class="w-full bg-slate-900 text-white py-4 rounded-xl font-bold text-lg hover:bg-slate-800 transition active:scale-[0.98] shadow-lg shadow-slate-300 disabled:opacity-50 disabled:cursor-not-allowed flex justify-center items-center gap-3">
                    <i class="ri-secure-payment-line text-xl"></i>
                    <span>Bayar Sekarang</span>
                </button>
            </div>
        </div>

        <!-- MODAL PEMBAYARAN -->
        <div v-if="showPayModal" class="fixed inset-0 z-50 flex items-center justify-center">
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showPayModal = false"></div>
            
            <!-- Content -->
            <div class="bg-white w-[500px] rounded-2xl shadow-2xl relative z-10 overflow-hidden animate-fade-in">
                <!-- Header -->
                <div class="bg-slate-50 border-b p-4 flex justify-between items-center">
                    <h3 class="font-bold text-slate-800 text-lg">Pembayaran</h3>
                    <button @click="showPayModal = false" class="w-8 h-8 rounded-full hover:bg-gray-200 flex items-center justify-center transition"><i class="ri-close-line text-xl"></i></button>
                </div>

                <div class="p-6">
                    <!-- Total Display -->
                    <div class="text-center mb-8">
                        <p class="text-sm text-gray-500 uppercase tracking-wide font-bold mb-1">Total Tagihan</p>
                        <h2 class="text-5xl font-extrabold text-slate-800 tracking-tight">{{ formatPrice(grandTotal) }}</h2>
                    </div>

                    <!-- Metode Pembayaran -->
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <button @click="paymentMethod = 'cash'" :class="paymentMethod === 'cash' ? 'ring-2 ring-green-500 bg-green-50 border-transparent' : 'border-gray-200 hover:border-green-300'" class="border rounded-xl p-4 flex flex-col items-center transition h-24 justify-center">
                            <i class="ri-money-dollar-circle-fill text-3xl text-green-600 mb-1"></i>
                            <span class="font-bold text-sm text-slate-700">Tunai</span>
                        </button>
                        <button @click="paymentMethod = 'qris'" :class="paymentMethod === 'qris' ? 'ring-2 ring-blue-500 bg-blue-50 border-transparent' : 'border-gray-200 hover:border-blue-300'" class="border rounded-xl p-4 flex flex-col items-center transition h-24 justify-center">
                            <i class="ri-qr-code-line text-3xl text-blue-600 mb-1"></i>
                            <span class="font-bold text-sm text-slate-700">QRIS / Transfer</span>
                        </button>
                    </div>

                    <!-- Input Tunai -->
                    <div v-if="paymentMethod === 'cash'" class="mb-6 space-y-3 animate-slide-down">
                        <div class="relative">
                            <span class="absolute left-4 top-3.5 text-gray-400 font-bold">Rp</span>
                            <input type="number" v-model.number="cashReceived" ref="cashInput" class="w-full pl-12 pr-4 py-3 text-xl font-bold border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none" placeholder="0">
                        </div>
                        
                        <!-- Uang Pas Suggestions -->
                        <div class="flex gap-2 overflow-x-auto pb-2 no-scrollbar">
                            <button @click="cashReceived = grandTotal" class="px-4 py-2 bg-gray-100 border border-gray-200 rounded-lg text-xs font-bold hover:bg-gray-200 transition whitespace-nowrap">Uang Pas</button>
                            <button v-for="amt in quickCash" :key="amt" @click="cashReceived = amt" class="px-4 py-2 bg-gray-100 border border-gray-200 rounded-lg text-xs font-bold hover:bg-gray-200 transition whitespace-nowrap">{{ formatNumber(amt) }}</button>
                        </div>

                        <!-- Kembalian -->
                        <div class="p-4 rounded-xl flex justify-between items-center transition-colors duration-300" :class="cashChange >= 0 ? 'bg-green-100 text-green-900' : 'bg-red-50 text-red-800'">
                            <span class="font-bold text-sm">Kembalian</span>
                            <span class="font-extrabold text-xl">{{ formatPrice(Math.max(0, cashChange)) }}</span>
                        </div>
                    </div>

                    <!-- Submit -->
                    <button @click="processCheckout" :disabled="processing || (paymentMethod === 'cash' && cashChange < 0)" 
                        class="w-full bg-green-600 text-white py-4 rounded-xl font-bold text-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed flex justify-center items-center gap-2 shadow-lg shadow-green-200 transition">
                        <i v-if="processing" class="ri-loader-4-line animate-spin text-xl"></i>
                        <span v-else>Bayar & Cetak</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- HIDDEN RECEIPT TEMPLATE (Thermal 58mm) -->
        <div id="receipt-print" class="hidden">
            <div style="width: 58mm; font-family: 'Courier New', monospace; font-size: 11px; color: #000; line-height: 1.2;">
                <div style="text-align: center; margin-bottom: 8px;">
                    <h2 style="font-size: 14px; margin: 0; font-weight: bold; text-transform: uppercase;"><?php echo get_bloginfo('name'); ?></h2>
                    <p style="margin: 2px 0; font-size: 10px;">Jl. Contoh WooCommerce No. 1</p>
                </div>
                
                <div style="border-bottom: 1px dashed #000; margin-bottom: 5px;"></div>
                
                <div style="margin-bottom: 5px;">
                    <div>No: #{{ lastReceipt.orderNumber }}</div>
                    <div>Tgl: {{ lastReceipt.date }}</div>
                    <div>Kasir: {{ lastReceipt.cashier }}</div>
                </div>

                <div style="border-bottom: 1px dashed #000; margin-bottom: 5px;"></div>

                <table style="width: 100%; border-collapse: collapse; margin-bottom: 5px;">
                    <tr v-for="item in lastReceipt.items" style="vertical-align: top;">
                        <td style="padding-bottom: 4px;">
                            <div style="font-weight: bold;">{{ item.name }}</div>
                            <div style="display: flex; justify-content: space-between;">
                                <span>{{ item.qty }} x {{ formatNumber(item.price) }}</span>
                                <span>{{ formatNumber(item.qty * item.price) }}</span>
                            </div>
                        </td>
                    </tr>
                </table>

                <div style="border-bottom: 1px dashed #000; margin-bottom: 5px;"></div>

                <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                    <span>Subtotal</span>
                    <span>{{ formatNumber(lastReceipt.subTotal) }}</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span>Pajak ({{ taxRate }}%)</span>
                    <span>{{ formatNumber(lastReceipt.taxAmount) }}</span>
                </div>
                
                <div style="border-bottom: 1px dashed #000; margin-bottom: 5px;"></div>

                <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 14px; margin-bottom: 5px;">
                    <span>TOTAL</span>
                    <span>{{ formatNumber(lastReceipt.grandTotal) }}</span>
                </div>

                <div v-if="lastReceipt.paymentMethod === 'cash'">
                    <div style="display: flex; justify-content: space-between;">
                        <span>Tunai</span>
                        <span>{{ formatNumber(lastReceipt.cashReceived) }}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>Kembali</span>
                        <span>{{ formatNumber(lastReceipt.cashChange) }}</span>
                    </div>
                </div>
                <div v-else style="text-align: center; font-style: italic; margin-top: 5px;">
                    (Pembayaran Non-Tunai)
                </div>

                <div style="border-bottom: 1px dashed #000; margin-bottom: 10px; margin-top: 5px;"></div>
                
                <div style="text-align: center;">
                    <p style="margin: 0;">Terima Kasih!</p>
                    <p style="margin: 0;">Barang yang sudah dibeli</p>
                    <p style="margin: 0;">tidak dapat ditukar/dikembalikan.</p>
                </div>
            </div>
        </div>

    </div>

    <!-- CONFIG -->
    <script>
        const kresuberParams = {
            apiUrl: '<?php echo esc_url_raw( rest_url( "kresuber-pos/v1" ) ); ?>',
            nonce: '<?php echo wp_create_nonce( "wp_rest" ); ?>',
            currencySymbol: '<?php echo get_woocommerce_currency_symbol(); ?>',
            taxRate: 11, // Configurable later
            siteName: '<?php echo get_bloginfo("name"); ?>',
            cashierName: '<?php echo wp_get_current_user()->display_name; ?>'
        };
    </script>
    <script src="<?php echo KRESUBER_POS_URL; ?>assets/js/pos-app.js"></script>
</body>
</html>