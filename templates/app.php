<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Kresuber POS Pro</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <script src="https://unpkg.com/dexie/dist/dexie.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <link rel="stylesheet" href="<?php echo KRESUBER_POS_PRO_URL; ?>assets/css/pos-style.css">
    
    <style>
        #app-loading { position: fixed; inset: 0; background: #f8fafc; z-index: 9999; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        [v-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 h-screen overflow-hidden text-slate-800 font-sans">
    
    <div id="app-loading">
        <div class="mb-4 text-green-600"><i class="ri-store-3-fill text-5xl"></i></div>
        <h2 class="text-xl font-bold">Memuat Sistem POS...</h2>
        <noscript class="text-red-500 mt-2">Error: Aktifkan JavaScript.</noscript>
    </div>

    <div id="app" v-cloak class="flex h-full w-full">
        <!-- LEFT: Main Content -->
        <div class="flex-1 flex flex-col h-full border-r border-gray-200 bg-white">
            
            <!-- Header -->
            <div class="h-16 px-6 border-b flex justify-between items-center z-30">
                <div class="flex items-center gap-4 w-full max-w-2xl">
                    <div class="font-bold text-xl text-green-600 mr-2">Kresuber</div>
                    <div class="relative w-full group">
                        <i class="ri-search-2-line absolute left-3 top-2.5 text-gray-400"></i>
                        <input v-model="searchQuery" type="text" placeholder="Cari Produk / Scan Barcode (F3)..." class="w-full pl-10 pr-4 py-2 bg-gray-100 rounded-lg focus:bg-white focus:ring-2 focus:ring-green-500 outline-none transition">
                        <div class="absolute right-2 top-1.5 flex gap-1">
                            <button v-if="searchQuery" @click="searchQuery=''" class="p-1 text-gray-400 hover:text-red-500"><i class="ri-close-circle-fill"></i></button>
                            <button @click="syncProducts" :class="{'animate-spin text-green-600': syncing}" class="p-1 text-gray-400 hover:text-green-600" title="Sync"><i class="ri-refresh-line"></i></button>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button @click="viewMode='orders';fetchOrders()" class="px-3 py-1.5 bg-gray-100 rounded hover:bg-gray-200 text-sm font-bold"><i class="ri-file-list-3-line"></i> Order</button>
                    <div class="text-right leading-tight hidden lg:block">
                        <p class="text-xs font-bold"><?php echo esc_html(wp_get_current_user()->display_name); ?></p>
                        <p class="text-[10px] text-green-600 font-bold uppercase">Online</p>
                    </div>
                    <a href="<?php echo esc_url(admin_url()); ?>" class="text-gray-400 hover:text-red-500"><i class="ri-logout-box-r-line text-xl"></i></a>
                </div>
            </div>

            <!-- Categories -->
            <div class="px-6 py-2 border-b bg-white overflow-x-auto whitespace-nowrap no-scrollbar shadow-sm z-20">
                <button @click="setCategory('all')" :class="currentCategory==='all'?'bg-slate-800 text-white':'bg-gray-100 text-gray-600 hover:bg-gray-200'" class="px-4 py-1.5 rounded-full text-xs font-bold mr-2 transition">Semua</button>
                <button v-for="cat in categories" :key="cat.slug" @click="setCategory(cat.slug)" :class="currentCategory===cat.slug?'bg-green-600 text-white':'bg-gray-100 text-gray-600 hover:bg-gray-200'" class="px-4 py-1.5 rounded-full text-xs font-bold mr-2 transition">{{ cat.name }}</button>
            </div>

            <!-- View: Products -->
            <div v-if="viewMode==='pos'" class="flex-1 overflow-y-auto p-6 bg-slate-50 custom-scrollbar">
                <div v-if="loading" class="flex justify-center pt-20"><i class="ri-loader-4-line animate-spin text-4xl text-green-600"></i></div>
                <div v-else-if="products.length===0" class="text-center pt-20 text-gray-400"><i class="ri-search-eye-line text-4xl"></i><p>Produk tidak ditemukan</p></div>
                <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                    <div v-for="p in products" :key="p.id" @click="addToCart(p)" class="bg-white rounded-xl shadow-sm hover:shadow-md cursor-pointer overflow-hidden border border-transparent hover:border-green-500 transition group h-[240px] flex flex-col">
                        <div class="h-32 bg-gray-100 relative">
                            <img :src="p.image" loading="lazy" class="w-full h-full object-cover">
                            <span v-if="p.stock_status==='instock'" class="absolute top-1 right-1 bg-black/60 text-white text-[10px] px-1.5 rounded">{{ p.stock }}</span>
                            <span v-else class="absolute inset-0 bg-white/80 flex items-center justify-center font-bold text-red-500">HABIS</span>
                        </div>
                        <div class="p-3 flex flex-col flex-1 justify-between">
                            <h3 class="font-bold text-sm text-slate-800 line-clamp-2">{{ p.name }}</h3>
                            <div class="flex justify-between items-center mt-1">
                                <span class="text-green-700 font-bold">{{ formatPrice(p.price) }}</span>
                                <i class="ri-add-circle-fill text-xl text-slate-200 group-hover:text-green-500 transition"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- View: Orders -->
            <div v-if="viewMode==='orders'" class="flex-1 overflow-y-auto p-6 bg-slate-50 custom-scrollbar">
                <div class="flex justify-between mb-4"><h2 class="text-xl font-bold">Riwayat Order</h2><button @click="viewMode='pos'" class="px-3 py-1 border bg-white rounded text-sm hover:bg-gray-50">Kembali</button></div>
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-gray-500"><tr><th class="p-3">ID</th><th class="p-3">Tanggal</th><th class="p-3">Customer</th><th class="p-3">Total</th><th class="p-3">Status</th></tr></thead>
                        <tbody class="divide-y"><tr v-for="o in recentOrders" :key="o.id" class="hover:bg-gray-50"><td class="p-3 font-bold">#{{o.number}}</td><td class="p-3 text-gray-500">{{o.date}}</td><td class="p-3">{{o.customer}}</td><td class="p-3 font-bold text-green-600">{{o.total}}</td><td class="p-3"><span class="px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs uppercase">{{o.status}}</span></td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- RIGHT: Cart -->
        <div class="w-[400px] bg-white border-l shadow-xl z-40 flex flex-col h-full flex-shrink-0">
            <div class="px-6 py-4 border-b flex justify-between items-center">
                <h2 class="font-bold text-lg flex items-center gap-2"><i class="ri-shopping-basket-fill text-green-600"></i> Keranjang</h2>
                <div class="flex gap-2">
                    <button @click="toggleHold" class="w-8 h-8 rounded border hover:bg-orange-50 text-orange-500 relative"><i class="ri-pause-circle-line"></i><span v-if="heldItems.length" class="absolute top-0 right-0 w-2 h-2 bg-red-500 rounded-full"></span></button>
                    <button @click="clearCart" class="w-8 h-8 rounded border hover:bg-red-50 text-red-500"><i class="ri-delete-bin-line"></i></button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto p-4 space-y-3 custom-scrollbar">
                <div v-if="cart.length===0" class="text-center text-slate-300 mt-20"><i class="ri-shopping-cart-2-line text-5xl"></i><p class="text-sm font-bold mt-2">Keranjang Kosong</p></div>
                <div v-for="item in cart" :key="item.id" class="flex gap-3 p-2 bg-white border rounded-lg shadow-sm group">
                    <img :src="item.image" class="w-12 h-12 rounded bg-gray-100 object-cover">
                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between"><h4 class="font-bold text-sm truncate">{{ item.name }}</h4><button @click="removeFromCart(item)" class="text-gray-300 hover:text-red-500"><i class="ri-close-circle-fill"></i></button></div>
                        <div class="flex justify-between items-end mt-1">
                            <span class="text-xs text-gray-500">@ {{ formatNumber(item.price) }}</span>
                            <div class="flex items-center bg-gray-50 rounded border"><button @click="updateQty(item,-1)" class="w-6 font-bold hover:text-red-500">-</button><span class="w-6 text-center text-xs font-bold">{{ item.qty }}</span><button @click="updateQty(item,1)" class="w-6 font-bold hover:text-green-500">+</button></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-6 border-t bg-slate-50 rounded-t-3xl shadow-[0_-5px_15px_rgba(0,0,0,0.05)]">
                <div class="flex justify-between text-sm mb-1"><span>Subtotal</span> <span>{{ formatPrice(subTotal) }}</span></div>
                <div class="flex justify-between text-sm mb-3 text-gray-500"><span>Pajak ({{ taxRate }}%)</span> <span>{{ formatPrice(taxAmount) }}</span></div>
                <div class="flex justify-between text-xl font-bold mb-4"><span>Total</span> <span class="text-green-600">{{ formatPrice(grandTotal) }}</span></div>
                <button @click="openPayModal" :disabled="cart.length===0" class="w-full py-3 bg-slate-900 text-white rounded-xl font-bold hover:bg-slate-800 disabled:opacity-50 transition shadow-lg">Bayar Sekarang</button>
            </div>
        </div>

        <!-- MODAL -->
        <div v-if="showPayModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
            <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden">
                <div class="p-4 border-b flex justify-between bg-gray-50"><h3 class="font-bold">Metode Pembayaran</h3><button @click="showPayModal=false"><i class="ri-close-line text-xl"></i></button></div>
                <div class="p-6">
                    <div class="text-center mb-6"><div class="text-xs text-gray-400 font-bold uppercase">Total Tagihan</div><div class="text-4xl font-extrabold">{{ formatPrice(grandTotal) }}</div></div>
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <button @click="paymentMethod='cash'" :class="paymentMethod==='cash'?'ring-2 ring-green-500 bg-green-50':''" class="border p-4 rounded-xl font-bold text-center transition">Tunai</button>
                        <button @click="paymentMethod='qris'" :class="paymentMethod==='qris'?'ring-2 ring-blue-500 bg-blue-50':''" class="border p-4 rounded-xl font-bold text-center transition">QRIS</button>
                    </div>
                    
                    <div v-if="paymentMethod==='cash'" class="mb-4">
                        <div class="relative"><span class="absolute left-4 top-3 text-gray-400 font-bold">Rp</span><input type="number" v-model="cashReceived" ref="cashInput" class="w-full pl-10 border p-3 rounded-lg text-xl font-bold" placeholder="0"></div>
                        <div class="flex gap-2 mt-2 overflow-x-auto"><button v-for="a in quickCash" :key="a" @click="cashReceived=a" class="px-3 py-1 bg-gray-100 rounded text-xs font-bold border">{{ formatNumber(a) }}</button></div>
                        <div class="mt-4 flex justify-between font-bold" :class="cashChange>=0?'text-green-600':'text-red-500'"><span>Kembali</span><span>{{ formatPrice(Math.max(0,cashChange)) }}</span></div>
                    </div>

                    <div v-if="paymentMethod==='qris'" class="text-center mb-4 p-4 border rounded-lg bg-gray-50">
                        <div v-if="qrisUrl"><img :src="qrisUrl" class="mx-auto max-h-48 rounded"></div>
                        <div v-else class="text-gray-400 text-sm py-4">QRIS Belum diupload</div>
                    </div>

                    <button @click="processCheckout" :disabled="processing||(paymentMethod==='cash'&&cashChange<0)" class="w-full py-3 bg-green-600 text-white rounded-xl font-bold hover:bg-green-700 disabled:opacity-50 transition flex justify-center"><i v-if="processing" class="ri-loader-4-line animate-spin mr-2"></i> Proses</button>
                </div>
            </div>
        </div>

        <!-- RECEIPT -->
        <div id="receipt-print" class="hidden">
            <div style="width:58mm;text-align:center;font-family:monospace;font-size:11px;line-height:1.2;color:#000;">
                <h3 style="margin:0;font-size:14px;font-weight:bold"><?php echo esc_html(get_bloginfo('name')); ?></h3>
                <p style="margin:2px 0 5px;font-size:9px">POS Receipt</p>
                <hr style="border-top:1px dashed #000;margin:5px 0">
                <div style="text-align:left">No: #{{ lastReceipt.orderNumber }}<br>Tgl: {{ lastReceipt.date }}<br>Kasir: {{ lastReceipt.cashier }}</div>
                <hr style="border-top:1px dashed #000;margin:5px 0">
                <table style="width:100%;text-align:left"><tr v-for="i in lastReceipt.items"><td style="padding-bottom:2px"><strong>{{i.name}}</strong><br>{{i.qty}} x {{formatNumber(i.price)}}</td><td style="text-align:right;vertical-align:top">{{formatNumber(i.qty*i.price)}}</td></tr></table>
                <hr style="border-top:1px dashed #000;margin:5px 0">
                <div style="display:flex;justify-content:space-between"><span>TOTAL</span> <strong>{{ formatNumber(lastReceipt.grandTotal) }}</strong></div>
                <div v-if="lastReceipt.paymentMethod==='cash'"><div style="display:flex;justify-content:space-between"><span>Tunai</span><span>{{ formatNumber(lastReceipt.cashReceived) }}</span></div><div style="display:flex;justify-content:space-between"><span>Kembali</span><span>{{ formatNumber(lastReceipt.cashChange) }}</span></div></div>
                <div style="margin-top:10px;text-align:center">Terima Kasih!</div>
            </div>
        </div>

    </div>

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
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => { if(document.getElementById('app').innerHTML.trim() !== '') document.getElementById('app-loading').style.display = 'none'; }, 1000);
        });
    </script>
</body>
</html>