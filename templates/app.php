<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
    <title>Kresuber POS v3.0.1</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <script src="https://unpkg.com/dexie@3.2.4/dist/dexie.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <link rel="stylesheet" href="<?php echo KRESUBER_POS_PRO_URL; ?>assets/css/pos-style.css">
    <style>
        #app-loading { position: fixed; inset: 0; background: #f8fafc; z-index: 9999; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: opacity 0.5s; }
        [v-cloak] { display: none !important; }
        :root { --print-width: <?php global $kresuber_config; echo $kresuber_config['printer_width']; ?>; }
    </style>
</head>
<body class="bg-gray-50 h-screen overflow-hidden text-slate-800 font-sans">
    
    <!-- LOADING SCREEN (FAILSAFE) -->
    <div id="app-loading">
        <div class="mb-4 text-blue-600 animate-bounce"><i class="ri-store-3-fill text-6xl"></i></div>
        <h2 class="text-xl font-bold text-slate-700">Memuat Kasir...</h2>
        <p id="loading-msg" class="text-sm text-gray-400 mt-2">Menyiapkan Database...</p>
        
        <!-- AUTO-ERROR MESSAGE IF STUCK -->
        <div id="loading-error" class="hidden mt-4 text-red-500 text-sm text-center">
            <i class="ri-error-warning-line text-2xl"></i><br>
            Gagal memuat aplikasi.<br>Silakan Refresh halaman.
        </div>
    </div>

    <div id="app" v-cloak class="flex h-full w-full flex-col md:flex-row">
        <!-- Sidebar -->
        <div class="md:hidden h-14 bg-white border-b flex items-center justify-between px-4 z-50 shrink-0">
            <div class="flex items-center gap-2">
                <img v-if="config.logo" :src="config.logo" class="h-8 max-w-[120px] object-contain">
                <span v-else class="font-bold text-lg text-blue-600">Kresuber</span>
            </div>
            <button @click="showCart=!showCart" class="relative p-2"><i class="ri-shopping-basket-fill text-2xl text-slate-700"></i><span v-if="cart.length" class="absolute top-0 right-0 bg-red-500 text-white text-[10px] w-4 h-4 rounded-full flex items-center justify-center">{{cart.length}}</span></button>
        </div>
        <div class="flex-1 flex flex-col h-full bg-white relative">
            <!-- Header -->
            <div class="hidden md:flex h-16 px-6 border-b justify-between items-center z-30 shrink-0">
                <div class="flex items-center gap-6 w-full max-w-3xl">
                    <img v-if="config.logo" :src="config.logo" class="h-10 max-w-[150px] object-contain">
                    <div v-else class="font-bold text-2xl text-blue-600">Kresuber</div>
                    <div class="relative w-full max-w-md">
                        <input v-model="search" type="text" placeholder="Cari / Scan Barcode (F3)" class="w-full pl-10 pr-4 py-2 bg-gray-100 rounded-lg outline-none focus:ring-2 focus:ring-blue-500">
                        <i class="ri-search-2-line absolute left-3 top-2.5 text-gray-400"></i>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <button @click="sync" :class="{'animate-spin':syncing}" class="p-2 hover:bg-gray-100 rounded-full"><i class="ri-refresh-line text-lg"></i></button>
                    <button @click="viewMode='orders';fetchOrders()" class="px-3 py-1 bg-gray-100 rounded font-bold hover:bg-gray-200">Orders</button>
                    <a href="<?php echo esc_url(admin_url()); ?>" class="text-gray-400 hover:text-red-500"><i class="ri-logout-box-r-line text-xl"></i></a>
                </div>
            </div>
            
            <!-- Chips -->
            <div class="px-4 py-2 border-b bg-white overflow-x-auto whitespace-nowrap no-scrollbar shadow-sm z-20 shrink-0">
                <button @click="setCategory('all')" :class="currentCategory==='all'?'bg-slate-800 text-white':'bg-gray-100'" class="px-4 py-1.5 rounded-full text-xs font-bold mr-2 transition">Semua</button>
                <button v-for="c in categories" :key="c.slug" @click="setCategory(c.slug)" :class="currentCategory===c.slug?'bg-blue-600 text-white':'bg-gray-100'" class="px-4 py-1.5 rounded-full text-xs font-bold mr-2 transition">{{c.name}}</button>
            </div>

            <!-- Product Grid -->
            <div v-if="viewMode==='pos'" class="flex-1 overflow-y-auto p-4 bg-slate-50">
                <div v-if="loading" class="text-center pt-20">Loading...</div>
                <div v-else class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4">
                    <div v-for="p in products" :key="p.id" @click="add(p)" class="bg-white rounded-xl shadow-sm hover:shadow-md cursor-pointer overflow-hidden border border-transparent hover:border-blue-500 flex flex-col h-60">
                        <div class="h-36 bg-gray-100 relative"><img :src="p.image" loading="lazy" class="w-full h-full object-cover"></div>
                        <div class="p-3 flex flex-col flex-1 justify-between">
                            <h3 class="font-bold text-xs line-clamp-2">{{p.name}}</h3>
                            <div class="flex justify-between mt-1"><span class="text-blue-700 font-bold">{{fmt(p.price)}}</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Orders View -->
            <div v-if="viewMode==='orders'" class="flex-1 overflow-y-auto p-6 bg-slate-50">
                <div class="flex justify-between mb-4"><h2 class="font-bold text-xl">History</h2><button @click="viewMode='pos'" class="px-3 py-1 bg-white border rounded">Back</button></div>
                <div class="bg-white rounded shadow overflow-hidden"><table class="w-full text-sm text-left"><thead class="bg-gray-50"><tr><th class="p-3">ID</th><th class="p-3">Total</th><th class="p-3">Status</th></tr></thead><tbody><tr v-for="o in orders" class="border-t hover:bg-gray-50"><td class="p-3">#{{o.number}}</td><td class="p-3 font-bold">{{o.total}}</td><td class="p-3">{{o.status}}</td></tr></tbody></table></div>
            </div>
        </div>

        <!-- Cart -->
        <div :class="{'translate-y-0':showCart,'translate-y-full md:translate-y-0':!showCart}" class="fixed md:static inset-0 md:inset-auto z-50 md:z-40 w-full md:w-[400px] bg-white border-l shadow-2xl md:shadow-none flex flex-col transition-transform duration-300">
            <div class="px-4 py-3 border-b flex justify-between items-center bg-white">
                <h2 class="font-bold text-lg">Keranjang</h2>
                <button @click="showCart=false" class="md:hidden p-2"><i class="ri-close-line"></i></button>
            </div>
            <div class="flex-1 overflow-y-auto p-4 space-y-3 bg-white">
                <div v-if="!cart.length" class="text-center text-gray-300 mt-10">Kosong</div>
                <div v-for="i in cart" :key="i.id" class="flex gap-3 p-2 border rounded shadow-sm">
                    <div class="flex-1"><div class="flex justify-between"><h4 class="font-bold text-sm truncate">{{i.name}}</h4><button @click="rem(i)" class="text-red-500"><i class="ri-close-circle-fill"></i></button></div>
                    <div class="flex justify-between items-end mt-1"><span class="text-xs">@ {{fmt(i.price)}}</span><div class="flex items-center bg-gray-50 rounded border"><button @click="qty(i,-1)" class="w-6 font-bold">-</button><span class="w-6 text-center text-xs">{{i.qty}}</span><button @click="qty(i,1)" class="w-6 font-bold">+</button></div></div></div>
                </div>
            </div>
            <div class="p-4 border-t bg-slate-50">
                <div class="flex justify-between mb-2 font-bold text-xl"><span>Total</span><span class="text-blue-600">{{fmt(total)}}</span></div>
                <button @click="modal=true" :disabled="!cart.length" class="w-full py-3 bg-slate-900 text-white rounded-xl font-bold disabled:opacity-50">Bayar</button>
            </div>
        </div>

        <!-- Modal -->
        <div v-if="modal" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden p-6">
                <div class="flex justify-between mb-6"><h3 class="font-bold text-lg">Pembayaran</h3><button @click="modal=false"><i class="ri-close-line text-xl"></i></button></div>
                <div class="text-center mb-6 text-4xl font-extrabold">{{fmt(total)}}</div>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <button @click="method='cash'" :class="method==='cash'?'ring-2 ring-blue-500':''" class="border p-3 rounded-xl font-bold">Tunai</button>
                    <button @click="method='qris'" :class="method==='qris'?'ring-2 ring-blue-500':''" class="border p-3 rounded-xl font-bold">QRIS</button>
                </div>
                <div v-if="method==='cash'" class="mb-4">
                    <input type="number" v-model="paid" ref="cashInput" class="w-full pl-4 p-3 border rounded-xl text-xl font-bold" placeholder="Diterima">
                    <div class="mt-2 flex justify-between font-bold" :class="change>=0?'text-green-600':'text-red-500'"><span>Kembali</span><span>{{fmt(Math.max(0,change))}}</span></div>
                </div>
                <div v-if="method==='qris'" class="text-center p-4 bg-gray-50 rounded border mb-4">
                     <img v-if="config.qris" :src="config.qris" class="mx-auto max-w-[200px] rounded">
                     <div v-else class="text-sm">QRIS belum diupload.</div>
                </div>
                <button @click="checkout" :disabled="processing||(method==='cash'&&change<0)" class="w-full py-3 bg-blue-600 text-white rounded-xl font-bold disabled:opacity-50">{{processing?'Proses...':'Selesai'}}</button>
            </div>
        </div>

        <!-- Print -->
        <div id="print" class="hidden">
            <div style="width:58mm;text-align:center;font-family:monospace;font-size:11px;">
                <h3 style="margin:0"><?php echo get_bloginfo('name'); ?></h3>
                <p style="margin:0;font-size:9px">Struk #{{receipt.order}}</p>
                <hr style="border-top:1px dashed #000">
                <table style="width:100%;text-align:left"><tr v-for="i in receipt.items"><td>{{i.name}}<br>{{i.qty}} x {{fmt(i.price)}}</td><td style="text-align:right">{{fmt(i.qty*i.price)}}</td></tr></table>
                <hr style="border-top:1px dashed #000">
                <div style="display:flex;justify-content:space-between"><span>TOTAL</span><b>{{fmt(receipt.total)}}</b></div>
                <div style="text-align:center;margin-top:10px">Terima Kasih</div>
            </div>
        </div>
    </div>

    <script>
        globalThis.params = {
            api: '<?php echo esc_url_raw( rest_url( "kresuber-pos/v1" ) ); ?>',
            nonce: '<?php echo wp_create_nonce( "wp_rest" ); ?>',
            curr: '<?php echo esc_js( get_woocommerce_currency_symbol() ); ?>',
            conf: <?php global $kresuber_config; echo json_encode($kresuber_config); ?>
        };
    </script>
    <script src="<?php echo KRESUBER_POS_PRO_URL; ?>assets/js/pos-app.js"></script>
    
    <!-- FAILSAFE: Force Remove Loader if JS Hangs -->
    <script>
        setTimeout(function() {
            var loader = document.getElementById('app-loading');
            var app = document.getElementById('app');
            if (loader && loader.style.display !== 'none') {
                // If app is ready but loader still there
                if (app.innerHTML.trim() !== '') {
                    loader.style.display = 'none';
                } else {
                    document.getElementById('loading-error').classList.remove('hidden');
                }
            }
        }, 5000); // 5 seconds timeout
    </script>
</body>
</html>
