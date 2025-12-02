<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
    <title>Kresuber POS v1.8.1</title>
    <!-- Tailwind CSS (CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <script src="https://unpkg.com/dexie@3.2.4/dist/dexie.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <style>
        :root { --primary: <?php global $kresuber_config; echo $kresuber_config['theme_color']; ?>; }
        .bg-theme { background-color: var(--primary); }
        .text-theme { color: var(--primary); }
        .border-theme { border-color: var(--primary); }
        .ring-theme { --tw-ring-color: var(--primary); }
        .ring-theme:focus { --tw-ring-color: var(--primary); box-shadow: 0 0 0 3px var(--primary); }
        
        /* Hiding Scrollbars but allowing scroll */
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        
        [v-cloak] { display: none; }
        .prod-card { transition: all 0.2s; }
        .prod-card:active { transform: scale(0.95); }
        
        /* Loading Animation */
        .spinner { border: 4px solid rgba(0,0,0,0.1); width: 40px; height: 40px; border-radius: 50%; border-left-color: var(--primary); animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        /* Progress Bar Transition */
        .progress-bar { transition: width 0.3s ease-out; }
    </style>
</head>
<body class="bg-gray-100 h-screen w-screen overflow-hidden font-sans text-gray-800">

    <div id="app" v-cloak class="flex h-full w-full relative">
        
        <!-- NEW: Reactive Loading Screen -->
        <div v-if="loading" class="fixed inset-0 z-[9999] bg-white flex flex-col items-center justify-center p-5">
            <div class="spinner mb-6"></div>
            
            <h2 class="font-bold text-xl text-gray-800 mb-2">Memuat Toko</h2>
            <p class="text-gray-500 text-sm mb-6 animate-pulse">{{ loadingText }}</p>
            
            <!-- Progress Bar Container -->
            <div class="w-full max-w-xs h-3 bg-gray-100 rounded-full overflow-hidden border border-gray-200 shadow-inner">
                <div class="h-full bg-theme progress-bar" :style="{ width: loadingProgress + '%' }"></div>
            </div>
            
            <div class="mt-2 text-xs font-bold text-theme">{{ Math.round(loadingProgress) }}%</div>
        </div>

        <!-- LEFT: MAIN CONTENT (Product Grid) -->
        <div class="flex-1 flex flex-col h-full relative overflow-hidden">
            
            <!-- 1. Header Bar -->
            <div class="h-16 bg-white border-b flex items-center justify-between px-4 shrink-0 z-20">
                <div class="flex items-center gap-3">
                    <img v-if="config.logo" :src="config.logo" class="h-10 w-auto object-contain">
                    <h1 v-else class="font-extrabold text-xl text-theme tracking-tight">{{ config.site_name }}</h1>
                </div>
                
                <!-- Search Box -->
                <div class="flex-1 max-w-lg mx-4 hidden md:block">
                    <div class="relative group">
                        <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-theme"></i>
                        <input v-model="search" type="text" placeholder="Cari barang / Scan Barcode (F3)..." 
                            class="w-full pl-10 pr-4 py-2.5 bg-gray-100 border-transparent border-2 rounded-xl focus:bg-white focus:border-theme outline-none transition-all text-sm font-medium">
                        <button v-if="search" @click="search=''" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500"><i class="ri-close-circle-fill"></i></button>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button @click="forceSync" class="p-2.5 rounded-full hover:bg-gray-100 text-gray-500 transition relative" title="Sync Data">
                        <i :class="{'animate-spin': syncing}" class="ri-refresh-line text-xl"></i>
                    </button>
                    <!-- Tombol Mobile Cart Toggle -->
                    <button @click="showMobileCart = !showMobileCart" class="md:hidden p-2.5 rounded-full bg-theme text-white shadow-lg relative">
                        <i class="ri-shopping-cart-2-fill text-xl"></i>
                        <span v-if="cartTotalQty > 0" class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">{{ cartTotalQty }}</span>
                    </button>
                </div>
            </div>

            <!-- Mobile Search Bar (Visible only on mobile) -->
            <div class="md:hidden px-4 py-2 bg-white border-b">
                 <div class="relative group">
                    <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-theme"></i>
                    <input v-model="search" type="text" placeholder="Cari barang..." 
                        class="w-full pl-10 pr-4 py-2 bg-gray-100 border-transparent border rounded-lg focus:bg-white focus:border-theme outline-none text-sm">
                </div>
            </div>

            <!-- 2. Category Scroll Bar -->
            <div class="bg-white px-2 py-2 shadow-sm shrink-0 z-10 border-b">
                <div class="flex overflow-x-auto gap-2 no-scrollbar px-2 pb-1" id="cat-scroll">
                    <button @click="setCategory('all')" 
                        class="whitespace-nowrap px-4 py-2 rounded-lg text-sm font-bold transition-all border select-none"
                        :class="curCat==='all' ? 'bg-theme text-white border-theme shadow-md' : 'bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100'">
                        <i class="ri-apps-fill mr-1"></i> Semua
                    </button>
                    <button v-for="c in categories" :key="c.slug" @click="setCategory(c.slug)"
                        class="whitespace-nowrap px-4 py-2 rounded-lg text-sm font-bold transition-all border select-none"
                        :class="curCat===c.slug ? 'bg-theme text-white border-theme shadow-md' : 'bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100'">
                        {{ c.name }}
                    </button>
                </div>
            </div>

            <!-- 3. Product Grid Area -->
            <div class="flex-1 overflow-y-auto bg-gray-50 p-3 md:p-5 custom-scrollbar relative">
                
                <!-- Empty State -->
                <div v-if="products.length === 0" class="flex flex-col items-center justify-center h-64 text-gray-400 opacity-60">
                    <i class="ri-inbox-archive-line text-6xl mb-2"></i>
                    <p>Produk tidak ditemukan</p>
                </div>

                <!-- Grid -->
                <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-3 md:gap-4 pb-24 md:pb-5">
                    <div v-for="p in products" :key="p.id" @click="add(p)" 
                        class="prod-card bg-white rounded-xl shadow-sm hover:shadow-lg border border-transparent hover:border-theme cursor-pointer flex flex-col overflow-hidden h-full">
                        <div class="aspect-square bg-gray-100 relative overflow-hidden group">
                            <img :src="p.image" loading="lazy" class="w-full h-full object-cover group-hover:scale-110 transition duration-500">
                            <div v-if="p.stock <= 0" class="absolute inset-0 bg-black/50 flex items-center justify-center text-white font-bold text-xs uppercase tracking-wider backdrop-blur-sm">Stok Habis</div>
                        </div>
                        <div class="p-3 flex flex-col flex-1">
                            <h3 class="font-semibold text-sm text-gray-800 line-clamp-2 leading-tight mb-auto">{{ p.name }}</h3>
                            <div class="mt-2 pt-2 border-t border-dashed border-gray-100">
                                <span class="block text-theme font-bold text-base">{{ formatRupiah(p.price) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT: CART SIDEBAR (Desktop Fixed / Mobile Overlay) -->
        <div class="fixed inset-0 z-40 md:static md:z-auto bg-black/50 md:bg-transparent transition-opacity"
            :class="showMobileCart ? 'opacity-100 visible' : 'opacity-0 invisible md:opacity-100 md:visible'"
            @click.self="showMobileCart = false">
            
            <div class="absolute right-0 top-0 bottom-0 w-[85%] sm:w-[380px] bg-white shadow-2xl md:shadow-none md:border-l flex flex-col transition-transform duration-300"
                :class="showMobileCart ? 'translate-x-0' : 'translate-x-full md:translate-x-0'">
                
                <!-- Cart Header -->
                <div class="h-16 px-5 border-b flex items-center justify-between bg-white shrink-0">
                    <div class="flex items-center gap-2">
                        <i class="ri-shopping-basket-fill text-theme text-xl"></i>
                        <h2 class="font-bold text-lg">Keranjang</h2>
                    </div>
                    <div class="flex items-center gap-1">
                        <button v-if="cart.length" @click="clearCart" class="text-red-500 hover:bg-red-50 p-2 rounded text-sm font-medium transition">
                           <i class="ri-delete-bin-line"></i> Reset
                        </button>
                        <button class="md:hidden p-2 text-gray-500" @click="showMobileCart = false"><i class="ri-close-large-line"></i></button>
                    </div>
                </div>

                <!-- Cart Items -->
                <div class="flex-1 overflow-y-auto p-4 bg-gray-50 space-y-3 custom-scrollbar">
                    <div v-if="cart.length === 0" class="flex flex-col items-center justify-center h-full text-gray-400">
                        <i class="ri-shopping-cart-line text-5xl mb-3 opacity-30"></i>
                        <p class="text-sm">Belum ada barang</p>
                    </div>

                    <div v-for="item in cart" :key="item.id" class="bg-white p-3 rounded-xl border shadow-sm flex gap-3 relative group">
                        <div class="w-14 h-14 bg-gray-100 rounded-lg overflow-hidden shrink-0">
                            <img :src="item.image" class="w-full h-full object-cover">
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-start mb-1">
                                <h4 class="font-bold text-sm text-gray-800 line-clamp-1 pr-5">{{ item.name }}</h4>
                                <button @click="removeItem(item)" class="text-gray-300 hover:text-red-500 absolute top-2 right-2 p-1"><i class="ri-close-circle-fill"></i></button>
                            </div>
                            <div class="flex justify-between items-end">
                                <div class="text-xs text-gray-500 font-medium">{{ formatRupiah(item.price) }} x {{ item.qty }}</div>
                                <div class="flex items-center border rounded-lg bg-gray-50 h-8">
                                    <button @click="updateQty(item, -1)" class="w-8 h-full flex items-center justify-center hover:bg-gray-200 rounded-l-lg font-bold text-gray-600">-</button>
                                    <input type="number" v-model.number="item.qty" class="w-10 h-full text-center bg-transparent text-sm font-bold outline-none appearance-none m-0">
                                    <button @click="updateQty(item, 1)" class="w-8 h-full flex items-center justify-center hover:bg-theme hover:text-white rounded-r-lg font-bold text-gray-600 transition">+</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cart Footer -->
                <div class="p-5 bg-white border-t shrink-0 shadow-[0_-5px_15px_rgba(0,0,0,0.05)] z-20">
                    <div class="flex justify-between items-end mb-4">
                        <span class="text-gray-500 font-medium text-sm">Total Bayar</span>
                        <span class="text-3xl font-extrabold text-theme tracking-tight leading-none">{{ formatRupiah(cartTotal) }}</span>
                    </div>
                    <button @click="openPayment" :disabled="cart.length === 0" 
                        class="w-full py-3.5 bg-theme text-white rounded-xl font-bold shadow-lg shadow-green-500/20 hover:opacity-90 active:scale-[0.98] transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                        <span>Bayar Sekarang</span> <i class="ri-arrow-right-line"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- PAYMENT MODAL -->
        <div v-if="showPaymentModal" class="fixed inset-0 z-50 flex items-end md:items-center justify-center bg-black/70 backdrop-blur-sm p-4 md:p-0">
            <div class="bg-white w-full md:w-[480px] rounded-t-2xl md:rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh] animate-slide-up md:animate-zoom-in">
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b flex justify-between items-center bg-gray-50">
                    <h3 class="font-bold text-lg text-gray-800">Pembayaran</h3>
                    <button @click="showPaymentModal = false" class="text-gray-400 hover:text-red-500"><i class="ri-close-fill text-2xl"></i></button>
                </div>

                <!-- Modal Body -->
                <div class="p-6 overflow-y-auto bg-white flex-1">
                    <!-- Total Display -->
                    <div class="text-center mb-8">
                        <div class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Total Tagihan</div>
                        <div class="text-4xl font-black text-theme">{{ formatRupiah(cartTotal) }}</div>
                    </div>

                    <!-- Payment Methods -->
                    <div class="grid grid-cols-2 gap-3 mb-6">
                        <button @click="paymentMethod='cash'" 
                            class="p-4 rounded-xl border-2 flex flex-col items-center gap-2 transition-all"
                            :class="paymentMethod==='cash' ? 'border-theme bg-green-50 text-theme' : 'border-gray-100 bg-gray-50 text-gray-500 hover:bg-gray-100'">
                            <i class="ri-money-dollar-circle-fill text-2xl"></i> <span class="font-bold text-sm">Tunai (Cash)</span>
                        </button>
                        <button @click="paymentMethod='qris'" 
                            class="p-4 rounded-xl border-2 flex flex-col items-center gap-2 transition-all"
                            :class="paymentMethod==='qris' ? 'border-theme bg-green-50 text-theme' : 'border-gray-100 bg-gray-50 text-gray-500 hover:bg-gray-100'">
                            <i class="ri-qr-code-line text-2xl"></i> <span class="font-bold text-sm">QRIS Scan</span>
                        </button>
                    </div>

                    <!-- Cash Input Area -->
                    <div v-if="paymentMethod==='cash'">
                        <div class="relative mb-3">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 font-bold text-gray-400">Rp</span>
                            <input ref="cashInputRef" type="number" v-model.number="amountPaid" 
                                class="w-full pl-12 pr-4 py-3 rounded-xl border-2 border-gray-200 focus:border-theme outline-none text-xl font-bold" 
                                placeholder="Masukkan nominal">
                        </div>
                        <!-- Quick Amounts -->
                        <div class="flex gap-2 mb-6 overflow-x-auto no-scrollbar pb-2">
                            <button v-for="amt in quickCashAmounts" :key="amt" @click="amountPaid = amt"
                                class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-bold whitespace-nowrap transition">
                                {{ formatRupiah(amt) }}
                            </button>
                        </div>
                        <!-- Change Display -->
                        <div class="bg-gray-50 p-4 rounded-xl border flex justify-between items-center">
                            <span class="font-bold text-gray-500 text-sm">Kembalian</span>
                            <span class="font-black text-xl" :class="changeAmount >= 0 ? 'text-green-600' : 'text-red-500'">
                                {{ formatRupiah(Math.max(0, changeAmount)) }}
                            </span>
                        </div>
                    </div>

                    <!-- QRIS Area -->
                    <div v-if="paymentMethod==='qris'" class="text-center py-4">
                        <div class="bg-white p-2 inline-block border rounded-xl shadow-sm mb-3">
                            <img v-if="config.qris" :src="config.qris" class="w-48 h-48 object-contain rounded-lg">
                            <div v-else class="w-48 h-48 flex items-center justify-center bg-gray-100 rounded-lg text-gray-400 text-sm p-4">
                                QRIS belum diupload di Admin Panel
                            </div>
                        </div>
                        <p class="text-sm text-gray-500">Scan QRIS di atas untuk membayar</p>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="p-5 border-t bg-gray-50">
                    <button @click="processPayment" :disabled="isProcessing || (paymentMethod==='cash' && changeAmount < 0)"
                        class="w-full py-3.5 bg-theme text-white rounded-xl font-bold shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition disabled:opacity-50 disabled:transform-none flex justify-center items-center gap-2">
                        <span v-if="isProcessing" class="spinner border-white border-b-transparent w-5 h-5"></span>
                        <span v-else>Selesaikan Transaksi</span>
                    </button>
                </div>
            </div>
        </div>

    </div>

    <!-- JS Logic -->
    <script>
        const wpData = {
            api: '<?php echo esc_url_raw( rest_url( "kresuber-pos/v1" ) ); ?>',
            nonce: '<?php echo wp_create_nonce( "wp_rest" ); ?>',
            config: <?php global $kresuber_config; echo json_encode($kresuber_config); ?>
        };
    </script>
    <script src="<?php echo KRESUBER_POS_PRO_URL; ?>assets/js/pos-app.js"></script>
</body>
</html>