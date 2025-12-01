<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kresuber POS Pro (WCPOS Style)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo KRESUBER_POS_URL; ?>assets/css/pos-style.css">
    <!-- Dexie.js untuk Database Lokal (WCPOS Speed) -->
    <script src="https://unpkg.com/dexie/dist/dexie.js"></script>
</head>
<body class="bg-gray-100 overflow-hidden font-sans">
    
    <div id="app" v-cloak class="flex h-screen">
        
        <!-- SIDEBAR KIRI -->
        <div class="w-20 bg-slate-900 flex flex-col items-center py-4 text-white shadow-xl z-30">
            <div class="mb-6"><i class="ri-store-3-fill text-3xl text-green-400"></i></div>
            <button @click="syncProducts" :class="{'animate-spin': syncing}" class="w-10 h-10 rounded-lg mb-4 bg-slate-800 hover:bg-slate-700 text-green-400" title="Sync Data">
                <i class="ri-refresh-line text-xl"></i>
            </button>
            <div class="flex-1 w-full flex flex-col items-center gap-2 overflow-y-auto hide-scrollbar">
                <button @click="filterCategory('all')" :class="currentCategory === 'all' ? 'bg-green-600' : 'bg-slate-800'" class="w-10 h-10 rounded-lg transition"><i class="ri-apps-fill"></i></button>
                <button v-for="cat in categories" :key="cat.slug" @click="filterCategory(cat.slug)" :class="currentCategory === cat.slug ? 'bg-green-600' : 'bg-slate-800'" class="w-10 h-10 rounded-lg transition text-xs font-bold overflow-hidden" :title="cat.name">
                    {{ cat.name.substring(0,2) }}
                </button>
            </div>
             <button onclick="window.location.href='/wp-admin'" class="mt-auto mb-2 w-10 h-10 bg-red-600 rounded-lg hover:bg-red-700"><i class="ri-logout-box-line"></i></button>
        </div>

        <!-- AREA TENGAH: PRODUK -->
        <div class="flex-1 flex flex-col relative bg-gray-50">
            <!-- Header -->
            <div class="h-16 bg-white border-b flex items-center px-4 justify-between shadow-sm z-20">
                <div class="flex items-center gap-4 w-full max-w-2xl">
                    <div class="relative w-full">
                        <i class="ri-barcode-line absolute left-3 top-2.5 text-gray-400 text-lg"></i>
                        <input v-model="searchQuery" @input="searchLocal" ref="searchInput" type="text" placeholder="Scan Barcode / Cari Produk (F3)" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none transition">
                        <span v-if="searchQuery" @click="searchQuery=''; searchLocal()" class="absolute right-3 top-2.5 cursor-pointer text-gray-400 hover:text-red-500"><i class="ri-close-circle-fill"></i></span>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="text-right hidden md:block">
                        <p class="text-sm font-bold text-slate-800 leading-tight"><?php echo wp_get_current_user()->display_name; ?></p>
                        <p class="text-xs text-green-600 flex items-center justify-end gap-1"><span class="w-2 h-2 rounded-full bg-green-500"></span> Online</p>
                    </div>
                </div>
            </div>

            <!-- Grid Produk -->
            <div class="flex-1 overflow-y-auto p-4" id="product-grid">
                <div v-if="loading" class="flex justify-center pt-20"><div class="animate-spin rounded-full h-10 w-10 border-b-2 border-green-600"></div></div>
                
                <div v-else-if="products.length === 0" class="flex flex-col items-center justify-center h-full text-gray-400">
                    <i class="ri-inbox-line text-6xl mb-2"></i>
                    <p>Produk tidak ditemukan</p>
                    <button @click="syncProducts" class="mt-4 text-green-600 hover:underline">Sync Database</button>
                </div>

                <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
                    <div v-for="product in products" :key="product.id" @click="addToCart(product)" 
                         class="bg-white rounded-lg shadow-sm hover:shadow-md cursor-pointer border border-transparent hover:border-green-500 transition flex flex-col overflow-hidden h-56 group relative">
                        <!-- Badge Stok -->
                        <span class="absolute top-2 right-2 bg-black/70 text-white text-[10px] px-1.5 py-0.5 rounded backdrop-blur-sm z-10">{{ product.stock }}</span>
                        
                        <div class="h-32 bg-gray-100 overflow-hidden">
                            <img :src="product.image" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                        </div>
                        <div class="p-2 flex flex-col flex-1 justify-between">
                            <h3 class="font-medium text-gray-800 text-sm leading-snug line-clamp-2" :title="product.name">{{ product.name }}</h3>
                            <p class="text-green-600 font-bold text-sm mt-1">{{ formatPrice(product.price) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SIDEBAR KANAN: KERANJANG -->
        <div class="w-[400px] bg-white border-l shadow-2xl z-30 flex flex-col h-full flex-shrink-0">
            <!-- Customer & Action Header -->
            <div class="p-3 border-b bg-slate-50 flex justify-between items-center gap-2">
                <button class="flex-1 flex items-center gap-2 bg-white border px-3 py-2 rounded-lg text-sm hover:bg-gray-50 text-gray-700">
                    <i class="ri-user-line"></i> <span>Pelanggan Umum</span>
                </button>
                <button @click="holdOrder" class="p-2 border rounded-lg text-orange-500 hover:bg-orange-50" title="Hold Order"><i class="ri-pause-circle-line text-xl"></i></button>
                <button @click="clearCart" class="p-2 border rounded-lg text-red-500 hover:bg-red-50" title="Clear"><i class="ri-delete-bin-line text-xl"></i></button>
            </div>

            <!-- Cart Items -->
            <div class="flex-1 overflow-y-auto p-3 space-y-2 bg-gray-50">
                <div v-if="cart.length === 0" class="flex flex-col items-center justify-center h-40 text-gray-400 mt-10">
                    <i class="ri-shopping-cart-line text-4xl mb-2"></i>
                    <p class="text-sm">Keranjang Kosong</p>
                </div>

                <div v-for="(item, index) in cart" :key="index" class="bg-white p-2 rounded-lg border shadow-sm flex gap-3 relative group">
                    <img :src="item.image" class="w-12 h-12 rounded bg-gray-100 object-cover">
                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between items-start">
                            <h4 class="text-sm font-semibold text-gray-800 truncate pr-4">{{ item.name }}</h4>
                            <button @click="removeFromCart(item)" class="text-gray-300 hover:text-red-500"><i class="ri-close-line"></i></button>
                        </div>
                        <div class="flex justify-between items-end mt-1">
                            <p class="text-xs text-gray-500">{{ formatPrice(item.price) }}</p>
                            <div class="flex items-center bg-gray-100 rounded">
                                <button @click="decreaseQty(item)" class="w-6 h-6 hover:bg-gray-200 rounded-l text-gray-600 font-bold">-</button>
                                <input type="number" v-model.number="item.qty" class="w-8 h-6 bg-transparent text-center text-xs font-bold focus:outline-none p-0">
                                <button @click="increaseQty(item)" class="w-6 h-6 hover:bg-gray-200 rounded-r text-gray-600 font-bold">+</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Checkout -->
            <div class="bg-white border-t p-4 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
                <div class="space-y-1 mb-4 text-sm">
                    <div class="flex justify-between text-gray-600"><span>Subtotal</span> <span>{{ formatPrice(subTotal) }}</span></div>
                    <div class="flex justify-between text-gray-600"><span>Diskon</span> <span>Rp 0</span></div>
                    <div class="flex justify-between text-gray-600"><span>Pajak ({{ taxRate }}%)</span> <span>{{ formatPrice(taxAmount) }}</span></div>
                    <div class="flex justify-between text-lg font-bold text-slate-800 pt-2 border-t mt-2"><span>Total</span> <span>{{ formatPrice(grandTotal) }}</span></div>
                </div>

                <button @click="showPayModal = true" :disabled="cart.length === 0" 
                    class="w-full bg-green-600 text-white py-3 rounded-xl font-bold text-lg hover:bg-green-700 transition shadow-lg shadow-green-200 disabled:opacity-50 disabled:shadow-none flex justify-center items-center gap-2">
                    <span>Bayar</span> <i class="ri-arrow-right-line"></i>
                </button>
            </div>
        </div>

        <!-- MODAL PEMBAYARAN -->
        <div v-if="showPayModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center backdrop-blur-sm">
            <div class="bg-white w-[500px] rounded-2xl shadow-2xl overflow-hidden animate-fade-in-up">
                <div class="bg-slate-900 text-white p-4 flex justify-between items-center">
                    <h3 class="font-bold text-lg">Pembayaran</h3>
                    <button @click="showPayModal = false" class="hover:text-red-400"><i class="ri-close-line text-2xl"></i></button>
                </div>
                <div class="p-6">
                    <div class="text-center mb-6">
                        <p class="text-gray-500 text-sm">Total Tagihan</p>
                        <h2 class="text-4xl font-bold text-slate-800">{{ formatPrice(grandTotal) }}</h2>
                    </div>

                    <div class="grid grid-cols-2 gap-3 mb-6">
                        <button @click="paymentMethod = 'cash'" :class="paymentMethod === 'cash' ? 'ring-2 ring-green-500 bg-green-50' : 'border-gray-200'" class="border rounded-xl p-4 flex flex-col items-center hover:bg-gray-50 transition">
                            <i class="ri-money-dollar-circle-line text-3xl text-green-600 mb-2"></i>
                            <span class="font-bold text-sm">Tunai (Cash)</span>
                        </button>
                        <button @click="paymentMethod = 'qris'" :class="paymentMethod === 'qris' ? 'ring-2 ring-blue-500 bg-blue-50' : 'border-gray-200'" class="border rounded-xl p-4 flex flex-col items-center hover:bg-gray-50 transition">
                            <i class="ri-qr-code-line text-3xl text-blue-600 mb-2"></i>
                            <span class="font-bold text-sm">QRIS / Transfer</span>
                        </button>
                    </div>

                    <div v-if="paymentMethod === 'cash'" class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Uang Diterima</label>
                        <input type="number" v-model.number="cashReceived" class="w-full text-2xl p-3 border rounded-lg focus:ring-2 focus:ring-green-500 outline-none font-mono" placeholder="0">
                        
                        <!-- Quick Cash Buttons -->
                        <div class="flex gap-2 mt-2 overflow-x-auto pb-2">
                             <button @click="cashReceived = grandTotal" class="px-3 py-1 bg-gray-100 rounded-full text-xs font-bold hover:bg-gray-200">Uang Pas</button>
                             <button @click="cashReceived = 50000" class="px-3 py-1 bg-gray-100 rounded-full text-xs font-bold hover:bg-gray-200">50k</button>
                             <button @click="cashReceived = 100000" class="px-3 py-1 bg-gray-100 rounded-full text-xs font-bold hover:bg-gray-200">100k</button>
                        </div>

                        <div v-if="cashReceived > 0" class="mt-4 p-3 rounded-lg flex justify-between items-center" :class="cashChange >= 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                            <span class="font-bold">Kembalian:</span>
                            <span class="font-bold text-xl">{{ formatPrice(Math.max(0, cashChange)) }}</span>
                        </div>
                    </div>

                    <button @click="processPayment" :disabled="processing || (paymentMethod === 'cash' && cashReceived < grandTotal)" 
                        class="w-full bg-slate-900 text-white py-4 rounded-xl font-bold text-lg hover:bg-slate-800 disabled:opacity-50 flex justify-center items-center gap-2">
                        <span v-if="processing" class="animate-spin"><i class="ri-loader-4-line"></i></span>
                        <span v-else>Selesaikan & Cetak Struk</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- HIDDEN RECEIPT TEMPLATE -->
        <div id="receipt-print" class="hidden">
            <div style="width: 58mm; font-family: 'Courier New', monospace; font-size: 12px;">
                <div style="text-align: center; margin-bottom: 10px;">
                    <h2 style="font-size: 16px; margin: 0; font-weight: bold;">Kresuber Toko</h2>
                    <p style="margin: 0; font-size: 10px;">Jl. Raya WooCommerce No. 1</p>
                </div>
                <hr style="border-top: 1px dashed #000;">
                <div style="margin: 5px 0;">
                    <p style="margin:0;">No: #{{ lastOrderId }}</p>
                    <p style="margin:0;">Tgl: {{ new Date().toLocaleString('id-ID') }}</p>
                    <p style="margin:0;">Kasir: <?php echo wp_get_current_user()->display_name; ?></p>
                </div>
                <hr style="border-top: 1px dashed #000;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr v-for="item in lastOrderItems">
                        <td style="padding: 2px 0;">{{ item.name }}<br>x{{ item.qty }} @ {{ item.price }}</td>
                        <td style="text-align: right; vertical-align: top;">{{ item.qty * item.price }}</td>
                    </tr>
                </table>
                <hr style="border-top: 1px dashed #000;">
                <div style="display: flex; justify-content: space-between; font-weight: bold;">
                    <span>TOTAL</span>
                    <span>{{ formatPrice(lastOrderTotal) }}</span>
                </div>
                <div v-if="lastPaymentMethod === 'cash'" style="display: flex; justify-content: space-between;">
                    <span>Tunai</span>
                    <span>{{ formatPrice(lastCashReceived) }}</span>
                </div>
                <div v-if="lastPaymentMethod === 'cash'" style="display: flex; justify-content: space-between;">
                    <span>Kembali</span>
                    <span>{{ formatPrice(lastCashChange) }}</span>
                </div>
                <div style="text-align: center; margin-top: 15px;">
                    <p>Terima Kasih!</p>
                </div>
            </div>
        </div>

    </div>

    <!-- VARS -->
    <script>
        const kresuberParams = {
            apiUrl: '<?php echo esc_url_raw( rest_url( "kresuber-pos/v1" ) ); ?>',
            nonce: '<?php echo wp_create_nonce( "wp_rest" ); ?>',
            currency: '<?php echo get_woocommerce_currency_symbol(); ?>',
            taxRate: 11 // PPN 11% (Hardcoded for demo)
        };
    </script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="<?php echo KRESUBER_POS_URL; ?>assets/js/pos-app.js"></script>
</body>
</html>