<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Kresuber POS</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    
    <!-- Core Libraries -->
    <script src="https://unpkg.com/dexie@3.2.4/dist/dexie.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    
    <link rel="stylesheet" href="<?php echo KRESUBER_POS_PRO_URL; ?>assets/css/pos-style.css">
    
    <style>
        #app-loading { position: fixed; inset: 0; background: #fff; z-index: 9999; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: opacity 0.5s ease; }
        [v-cloak] { display: none !important; }
        :root { --print-width: <?php global $kresuber_config; echo $kresuber_config['printer_width']; ?>; }
    </style>
</head>
<body class="bg-gray-50 h-screen overflow-hidden text-slate-800 font-sans">
    
    <div id="app-loading">
        <div class="mb-4 text-blue-600 animate-bounce"><i class="ri-store-3-fill text-6xl"></i></div>
        <h2 class="text-xl font-bold">Memuat Kasir...</h2>
        <p id="loading-status" class="text-sm text-gray-400 mt-2">Menyiapkan Database...</p>
    </div>

    <div id="app" v-cloak class="flex h-full w-full flex-col md:flex-row">
        
        <!-- MOBILE NAVBAR -->
        <div class="md:hidden h-14 bg-white border-b flex items-center justify-between px-4 z-50 shrink-0">
            <div class="flex items-center gap-2">
                <img v-if="config.logo" :src="config.logo" class="h-8 max-w-[120px] object-contain">
                <span v-else class="font-bold text-lg text-blue-600">Kresuber</span>
            </div>
            <button @click="showMobileCart = !showMobileCart" class="relative p-2">
                <i class="ri-shopping-basket-fill text-2xl text-slate-700"></i>
                <span v-if="cart.length" class="absolute top-0 right-0 bg-red-500 text-white text-[10px] w-4 h-4 flex items-center justify-center rounded-full">{{ cartTotalQty }}</span>
            </button>
        </div>

        <!-- LEFT SIDE -->
        <div class="flex-1 flex flex-col h-full bg-white relative">
            
            <!-- DESKTOP HEADER -->
            <div class="hidden md:flex h-16 px-6 border-b justify-between items-center z-30 shrink-0">
                <div class="flex items-center gap-6 w-full max-w-3xl">
                    <div class="flex items-center">
                        <img v-if="config.logo" :src="config.logo" class="h-10 max-w-[150px] object-contain">
                        <div v-else class="font-bold text-2xl text-blue-600 tracking-tight">Kresuber</div>
                    </div>
                    <div class="relative w-full max-w-md group">
                        <i class="ri-search-2-line absolute left-3 top-2.5 text-gray-400"></i>
                        <input v-model="searchQuery" type="text" placeholder="Cari / Scan (F3)" 
                            class="w-full pl-10 pr-10 py-2 bg-gray-100 rounded-lg focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none transition text-sm">
                        <button v-if="searchQuery" @click="searchQuery=''" class="absolute right-2 top-2 text-gray-400 hover:text-red-500"><i class="ri-close-circle-fill"></i></button>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <button @click="syncProducts" :class="{'animate-spin text-blue-600': syncing}" class="p-2 hover:bg-gray-100 rounded-full" title="Sync"><i class="ri-refresh-line text-lg"></i></button>
                    <div class="relative">
                        <select v-model="activeCashier" class="bg-gray-50 border border-gray-200 text-gray-900 text-xs rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-1.5 pr-8 font-bold cursor-pointer">
                            <option value="default"><?php echo esc_js(wp_get_current_user()->display_name); ?></option>
                            <option v-for="c in config.cashiers" :key="c" :value="c">{{ c }}</option>
                        </select>
                        <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none text-gray-500 text-xs"><i class="ri-user-smile-line"></i></div>
                    </div>
                    <a href="<?php echo esc_url(admin_url()); ?>" class="text-gray-400 hover:text-red-500"><i class="ri-logout-box-r-line text-xl"></i></a>
                </div>
            </div>

            <!-- MOBILE SEARCH -->
            <div class="md:hidden px-4 py-2 bg-white border-b">
                <div class="relative">
                    <i class="ri-search-2-line absolute left-3 top-2.5 text-gray-400"></i>
                    <input v-model="searchQuery" type="text" placeholder="Cari Produk..." class="w-full pl-10 py-2 bg-gray-100 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
            </div>

            <!-- CHIPS -->
            <div class="px-4 md:px-6 py-2 border-b bg-white overflow-x-auto whitespace-nowrap no-scrollbar shadow-sm z-20 shrink-0">
                <button @click="setCategory('all')" :class="currentCategory==='all'?'bg-slate-800 text-white':'bg-gray-100 text-gray-600'" class="px-4 py-1.5 rounded-full text-xs font-bold mr-2 transition">Semua</button>
                <button v-for="cat in categories" :key="cat.slug" @click="setCategory(cat.slug)" :class="currentCategory===cat.slug?'bg-blue-600 text-white':'bg-gray-100 text-gray-600'" class="px-4 py-1.5 rounded-full text-xs font-bold mr-2 transition">{{ cat.name }}</button>
            </div>

            <!-- GRID -->
            <div class="flex-1 overflow-y-auto p-4 md:p-6 bg-slate-50 custom-scrollbar">
                <div v-if="loading" class="flex justify-center pt-20"><i class="ri-loader-4-line animate-spin text-4xl text-blue-600"></i></div>
                <div v-else-if="products.length===0" class="text-center pt-20 text-gray-400"><i class="ri-inbox-line text-4xl"></i><p>Produk tidak ditemukan</p></div>
                <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-3 md:gap-4 pb-20 md:pb-0">
                    <div v-for="p in products" :key="p.id" @click="addToCart(p)" class="bg-white rounded-xl shadow-sm hover:shadow-lg cursor-pointer overflow-hidden border border-transparent hover:border-blue-500 transition group flex flex-col h-[200px] md:h-[240px]">
                        <div class="h-28 md:h-36 bg-gray-100 relative">
                            <img :src="p.image" loading="lazy" class="w-full h-full object-cover">
                            <span v-if="p.stock_status==='instock'" class="absolute top-1 right-1 bg-black/60 text-white text-[10px] px-1.5 rounded">{{ p.stock }}</span>
                            <span v-else class="absolute inset-0 bg-white/80 flex items-center justify-center font-bold text-red-500 text-xs">HABIS</span>
                        </div>
                        <div class="p-2 md:p-3 flex flex-col flex-1 justify-between">
                            <h3 class="font-bold text-xs md:text-sm text-slate-800 line-clamp-2 leading-tight">{{ p.name }}</h3>
                            <div class="flex justify-between items-center mt-1">
                                <span class="text-blue-700 font-bold text-sm md:text-base">{{ formatPrice(p.price) }}</span>
                                <i class="ri-add-circle-fill text-xl text-slate-200 group-hover:text-blue-500 transition"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT SIDE: CART -->
        <div :class="{'translate-y-0': showMobileCart, 'translate-y-full md:translate-y-0': !showMobileCart}" 
             class="fixed md:static inset-0 md:inset-auto z-50 md:z-40 w-full md:w-[400px] bg-white border-l shadow-2xl md:shadow-none flex flex-col transition-transform duration-300 ease-in-out">
            
            <div class="md:hidden flex justify-center pt-2 pb-1" @click="showMobileCart = false"><div class="w-10 h-1 bg-gray-300 rounded-full"></div></div>
            <div class="px-4 md:px-6 py-3 md:py-5 border-b flex justify-between items-center bg-white shrink-0">
                <h2 class="font-bold text-lg flex items-center gap-2"><span class="bg-blue-100 text-blue-600 p-1 rounded"><i class="ri-shopping-cart-2-fill"></i></span> Keranjang</h2>
                <div class="flex gap-2">
                    <button @click="toggleHold" class="p-2 rounded border hover:bg-orange-50 text-orange-500 relative"><i class="ri-pause-circle-line"></i><span v-if="heldItems.length" class="absolute top-0 right-0 w-2 h-2 bg-red-500 rounded-full"></span></button>
                    <button @click="clearCart" class="p-2 rounded border hover:bg-red-50 text-red-500"><i class="ri-delete-bin-line"></i></button>
                    <button @click="showMobileCart = false" class="md:hidden p-2 text-gray-400"><i class="ri-close-line text-xl"></i></button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto p-4 space-y-3 custom-scrollbar bg-white">
                <div v-if="cart.length===0" class="flex flex-col items-center justify-center h-full text-slate-300"><i class="ri-shopping-basket-line text-6xl opacity-20"></i><p class="text-sm mt-2">Belum ada item</p></div>
                <div v-for="item in cart" :key="item.id" class="flex gap-3 p-2 border rounded-lg shadow-sm">
                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between"><h4 class="font-bold text-sm truncate">{{ item.name }}</h4><button @click="removeFromCart(item)" class="text-gray-400 hover:text-red-500"><i class="ri-close-circle-fill"></i></button></div>
                        <div class="flex justify-between items-end mt-1">
                            <span class="text-xs text-gray-500">@ {{ formatNumber(item.price) }}</span>
                            <div class="flex items-center bg-gray-50 rounded border p-0.5">
                                <button @click="updateQty(item,-1)" class="w-6 h-6 bg-white shadow rounded text-xs font-bold">-</button>
                                <span class="w-8 text-center text-xs font-bold">{{ item.qty }}</span>
                                <button @click="updateQty(item,1)" class="w-6 h-6 bg-blue-500 text-white shadow rounded text-xs font-bold">+</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-4 md:p-6 border-t bg-slate-50 shrink-0 shadow-lg">
                <div class="flex justify-between text-sm mb-1"><span>Subtotal</span> <span class="font-medium">{{ formatPrice(subTotal) }}</span></div>
                <div class="flex justify-between text-sm mb-3"><span>Pajak ({{ taxRate }}%)</span> <span class="font-medium">{{ formatPrice(taxAmount) }}</span></div>
                <div class="flex justify-between text-xl font-bold mb-4"><span>Total</span> <span class="text-blue-600">{{ formatPrice(grandTotal) }}</span></div>
                <button @click="openPayModal" :disabled="cart.length===0" class="w-full py-3 bg-slate-900 text-white rounded-xl font-bold hover:bg-slate-800 disabled:opacity-50 transition">Bayar Sekarang</button>
            </div>
        </div>

        <!-- PAY MODAL -->
        <div v-if="showPayModal" class="fixed inset-0 z-[60] flex items-end md:items-center justify-center p-0 md:p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-white w-full md:max-w-lg rounded-t-2xl md:rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh] animate-slide-up">
                <div class="p-4 border-b flex justify-between items-center">
                    <h3 class="font-bold text-lg">Pembayaran</h3>
                    <button @click="showPayModal=false"><i class="ri-close-line text-2xl text-gray-400"></i></button>
                </div>
                <div class="flex-1 overflow-y-auto p-6">
                    <div class="text-center mb-6"><div class="text-xs text-gray-400 font-bold uppercase">Total Tagihan</div><div class="text-4xl font-extrabold text-slate-800">{{ formatPrice(grandTotal) }}</div></div>
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <button @click="paymentMethod='cash'" :class="paymentMethod==='cash'?'ring-2 ring-blue-500 bg-blue-50':''" class="border p-4 rounded-xl font-bold text-center transition">Tunai</button>
                        <button @click="paymentMethod='qris'" :class="paymentMethod==='qris'?'ring-2 ring-blue-500 bg-blue-50':''" class="border p-4 rounded-xl font-bold text-center transition">QRIS</button>
                    </div>
                    <div v-if="paymentMethod==='cash'" class="mb-4">
                        <div class="relative"><span class="absolute left-4 top-3.5 font-bold text-lg text-gray-400">Rp</span><input type="number" v-model="cashReceived" ref="cashInput" class="w-full pl-12 p-3 border rounded-xl text-xl font-bold" placeholder="0"></div>
                        <div class="flex gap-2 mt-2 overflow-x-auto"><button v-for="a in quickCash" :key="a" @click="cashReceived=a" class="px-3 py-1 bg-gray-100 rounded text-xs font-bold border">{{ formatNumber(a) }}</button></div>
                        <div class="mt-4 flex justify-between font-bold" :class="cashChange>=0?'text-green-600':'text-red-500'"><span>Kembali</span><span>{{ formatPrice(Math.max(0,cashChange)) }}</span></div>
                    </div>
                    <div v-if="paymentMethod==='qris'" class="text-center p-4 bg-gray-50 rounded-lg">
                        <p class="mb-2 text-sm font-bold">Scan QRIS:</p>
                        <img v-if="config.qris" :src="config.qris" class="mx-auto max-w-[200px] rounded border">
                        <div v-else class="text-gray-400 text-sm">Belum ada QRIS</div>
                    </div>
                </div>
                <div class="p-4 border-t bg-gray-50">
                    <button @click="processCheckout" :disabled="processing||(paymentMethod==='cash'&&cashChange<0)" class="w-full py-3 bg-blue-600 text-white rounded-xl font-bold hover:bg-blue-700 disabled:opacity-50 transition flex justify-center gap-2">
                        <i v-if="processing" class="ri-loader-4-line animate-spin"></i> {{ paymentMethod==='qris'?'Selesai':'Bayar & Cetak' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- RECEIPT -->
        <div id="receipt-print" class="hidden">
            <style>
                @page { margin: 0; }
                body.receipt { margin: 0; padding: 5px; font-family: 'Courier New', monospace; font-size: 10px; line-height: 1.2; color: #000; width: var(--print-width); }
                .r-center { text-align: center; } .r-right { text-align: right; } .r-bold { font-weight: bold; }
                .r-line { border-top: 1px dashed #000; margin: 5px 0; }
                .r-table { width: 100%; border-collapse: collapse; }
                .r-table td { vertical-align: top; }
                .r-logo { max-width: 50%; margin: 0 auto 5px; display: block; filter: grayscale(100%); }
            </style>
            <div class="receipt-body">
                <div class="r-center">
                    <img v-if="config.logo" :src="config.logo" class="r-logo">
                    <div v-else class="r-bold" style="font-size: 12px; margin-bottom: 2px;">KRESUBER STORE</div>
                    <div><?php echo get_bloginfo('name'); ?></div>
                </div>
                <div class="r-line"></div>
                <div>No: #{{ lastReceipt.orderNumber }}</div>
                <div>Tgl: {{ lastReceipt.date }}</div>
                <div>Kasir: {{ activeCashier === 'default' ? '<?php echo esc_js(wp_get_current_user()->display_name); ?>' : activeCashier }}</div>
                <div class="r-line"></div>
                <table class="r-table">
                    <tr v-for="i in lastReceipt.items">
                        <td>{{ i.name }}<br>{{ i.qty }} x {{ formatNumber(i.price) }}</td>
                        <td class="r-right">{{ formatNumber(i.qty * i.price) }}</td>
                    </tr>
                </table>
                <div class="r-line"></div>
                <table class="r-table">
                    <tr><td>Total</td><td class="r-right r-bold">{{ formatNumber(lastReceipt.grandTotal) }}</td></tr>
                    <tr v-if="lastReceipt.paymentMethod==='cash'"><td>Tunai</td><td class="r-right">{{ formatNumber(lastReceipt.cashReceived) }}</td></tr>
                    <tr v-if="lastReceipt.paymentMethod==='cash'"><td>Kembali</td><td class="r-right">{{ formatNumber(lastReceipt.cashChange) }}</td></tr>
                </table>
                <div v-if="lastReceipt.paymentMethod!=='cash'" class="r-center" style="margin-top:5px">[Lunas via {{ lastReceipt.paymentMethod.toUpperCase() }}]</div>
                <div class="r-line"></div>
                <div class="r-center">Terima Kasih!</div>
            </div>
        </div>
    </div>

    <script>
        globalThis.kresuberParams = {
            apiUrl: '<?php echo esc_url_raw( rest_url( "kresuber-pos/v1" ) ); ?>',
            nonce: '<?php echo wp_create_nonce( "wp_rest" ); ?>',
            currencySymbol: '<?php echo esc_js( get_woocommerce_currency_symbol() ); ?>',
            taxRate: 11,
            config: <?php global $kresuber_config; echo json_encode($kresuber_config); ?>
        };
    </script>
    <script src="<?php echo KRESUBER_POS_PRO_URL; ?>assets/js/pos-app.js"></script>
</body>
</html>