<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
    <title>Kresuber POS v1.7</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <script src="https://unpkg.com/dexie@3.2.4/dist/dexie.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <link rel="stylesheet" href="<?php echo KRESUBER_POS_PRO_URL; ?>assets/css/pos-style.css">
    <style>
        :root { --primary: <?php global $kresuber_config; echo $kresuber_config['theme_color']; ?>; }
        .bg-theme { background-color: var(--primary); }
        .text-theme { color: var(--primary); }
        .border-theme { border-color: var(--primary); }
        .ring-theme { --tw-ring-color: var(--primary); }
        #app-loading { position: fixed; inset: 0; background: #fff; z-index: 9999; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        [v-cloak] { display: none; }
    </style>
</head>
<body class="bg-gray-50 h-screen overflow-hidden font-sans text-slate-800">
    
    <div id="app-loading">
        <div class="mb-4 text-theme animate-bounce"><i class="ri-store-3-fill text-6xl" style="color:var(--primary)"></i></div>
        <h2 class="text-xl font-bold">Memuat Kasir...</h2>
    </div>

    <div id="app" v-cloak class="flex h-full w-full flex-col md:flex-row">
        <!-- Left Main -->
        <div class="flex-1 flex flex-col h-full bg-white relative border-r border-gray-200">
            
            <!-- Header -->
            <div class="h-16 border-b px-4 flex justify-between items-center bg-white z-30 shadow-sm shrink-0">
                <div class="flex items-center gap-4 w-full max-w-2xl">
                    <div class="flex items-center gap-2">
                        <img v-if="config.logo" :src="config.logo" class="h-8 w-auto object-contain">
                        <span v-else class="font-bold text-xl text-theme tracking-tight">{{config.site_name}}</span>
                    </div>
                    <div class="relative w-full max-w-md hidden md:block">
                        <i class="ri-search-line absolute left-3 top-2.5 text-gray-400"></i>
                        <input v-model="search" type="text" placeholder="Cari Produk / Scan (F3)" class="w-full pl-10 pr-8 py-2 bg-gray-100 rounded-lg focus:bg-white focus:ring-2 ring-theme outline-none text-sm">
                        <button v-if="search" @click="search=''" class="absolute right-2 top-2 text-gray-400"><i class="ri-close-circle-fill"></i></button>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button @click="sync" :class="{'animate-spin text-theme':syncing}" class="p-2 hover:bg-gray-100 rounded-full text-gray-500"><i class="ri-refresh-line text-xl"></i></button>
                    <button @click="viewMode='orders';fetchOrders()" class="flex items-center gap-2 px-3 py-2 bg-gray-100 rounded-lg text-sm font-bold hover:bg-gray-200"><i class="ri-file-list-3-line"></i> <span class="hidden lg:inline">Riwayat</span></button>
                    <a href="<?php echo esc_url(admin_url()); ?>" class="text-gray-400 hover:text-red-500"><i class="ri-logout-box-r-line text-xl"></i></a>
                </div>
            </div>

            <!-- Chips -->
            <div class="px-4 py-3 border-b bg-white overflow-x-auto whitespace-nowrap scrollbar-hide shadow-sm z-20 shrink-0">
                <button @click="setCat('all')" :class="curCat==='all'?'bg-theme text-white shadow-md':'bg-gray-100 text-gray-600 hover:bg-gray-200'" class="px-4 py-1.5 rounded-full text-xs font-bold mr-2 transition-all">Semua</button>
                <button v-for="c in categories" :key="c.slug" @click="setCat(c.slug)" :class="curCat===c.slug?'bg-theme text-white shadow-md':'bg-gray-100 text-gray-600 hover:bg-gray-200'" class="px-4 py-1.5 rounded-full text-xs font-bold mr-2 transition-all">{{c.name}}</button>
            </div>

            <!-- Grid Produk -->
            <div v-if="viewMode==='pos'" class="flex-1 overflow-y-auto p-4 md:p-6 bg-slate-50 custom-scrollbar">
                <div v-if="loading" class="text-center pt-20 text-gray-400">Memuat...</div>
                <div v-else class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4 pb-20">
                    <div v-for="p in products" :key="p.id" @click="add(p)" class="bg-white rounded-xl shadow-sm hover:shadow-md cursor-pointer overflow-hidden border border-transparent hover:border-theme flex flex-col h-64 transition group">
                        <div class="h-36 bg-gray-100 relative"><img :src="p.image" loading="lazy" class="w-full h-full object-cover"></div>
                        <div class="p-3 flex flex-col flex-1 justify-between">
                            <h3 class="font-bold text-sm text-slate-800 line-clamp-2 leading-snug">{{p.name}}</h3>
                            <div class="flex justify-between items-center mt-2"><span class="text-theme font-extrabold text-base">{{fmt(p.price)}}</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Orders View -->
            <div v-if="viewMode==='orders'" class="flex-1 overflow-y-auto p-6 bg-slate-50 custom-scrollbar">
                <div class="flex justify-between mb-4"><h2 class="font-bold text-xl">Riwayat Order</h2><button @click="viewMode='pos'" class="px-4 py-2 bg-white border rounded-lg text-sm font-bold hover:bg-gray-50">Kembali</button></div>
                <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-50 border-b text-gray-500"><tr><th class="p-4">ID</th><th class="p-4">Waktu</th><th class="p-4">Items</th><th class="p-4 text-right">Total</th><th class="p-4 text-center">Status</th></tr></thead>
                        <tbody class="divide-y"><tr v-for="o in recentOrders" :key="o.id" class="hover:bg-gray-50"><td class="p-4 font-bold text-theme">#{{o.number}}</td><td class="p-4 text-gray-500">{{o.date}}</td><td class="p-4"><div v-for="i in o.items" class="text-xs text-gray-700">{{i.qty}}x {{i.name}}</div></td><td class="p-4 font-bold text-right">{{o.total_formatted}}</td><td class="p-4 text-center"><span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-bold uppercase">{{o.status}}</span></td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Cart -->
        <div :class="showCart?'translate-y-0':'translate-y-full md:translate-y-0'" class="fixed md:static inset-0 md:inset-auto z-40 w-full md:w-[400px] bg-white border-l shadow-2xl md:shadow-none flex flex-col transition-transform duration-300">
            <div class="px-5 py-4 border-b flex justify-between items-center bg-white shrink-0">
                <h2 class="font-bold text-lg flex items-center gap-2"><i class="ri-shopping-basket-2-fill text-theme"></i> Keranjang</h2>
                <div class="flex gap-2"><button @click="clearCart" class="text-red-500 p-2 rounded hover:bg-red-50"><i class="ri-delete-bin-line text-xl"></i></button><button @click="showCart=false" class="md:hidden p-2"><i class="ri-close-line text-xl"></i></button></div>
            </div>
            <div class="flex-1 overflow-y-auto p-4 space-y-3 bg-white custom-scrollbar">
                <div v-if="!cart.length" class="text-center text-slate-300 mt-20">Keranjang Kosong</div>
                <div v-for="i in cart" :key="i.id" class="flex gap-3 p-3 border rounded-xl shadow-sm bg-white group">
                    <img :src="i.image" class="w-14 h-14 rounded-lg bg-gray-100 object-cover">
                    <div class="flex-1 min-w-0 flex flex-col justify-between">
                        <div class="flex justify-between"><h4 class="font-bold text-sm truncate">{{i.name}}</h4><button @click="rem(i)" class="text-gray-300 hover:text-red-500"><i class="ri-close-circle-fill"></i></button></div>
                        <div class="flex justify-between items-end"><span class="text-xs font-bold text-gray-500">@ {{fmt(i.price)}}</span><div class="flex items-center bg-gray-50 rounded-lg border"><button @click="qty(i,-1)" class="w-8 h-7 font-bold hover:text-red-500">-</button><span class="text-sm font-bold w-6 text-center">{{i.qty}}</span><button @click="qty(i,1)" class="w-8 h-7 font-bold hover:text-theme">+</button></div></div>
                    </div>
                </div>
            </div>
            <div class="p-6 border-t bg-slate-50 shrink-0 shadow-lg z-20">
                <div class="flex justify-between items-center mb-6"><span class="font-bold text-lg text-slate-800">Total</span><span class="font-extrabold text-3xl text-theme tracking-tight">{{fmt(total)}}</span></div>
                <button @click="modal=true" :disabled="!cart.length" class="w-full py-3.5 bg-theme text-white rounded-xl font-bold shadow-lg hover:opacity-90 transition disabled:opacity-50">Bayar Sekarang</button>
            </div>
        </div>

        <!-- Payment Modal -->
        <div v-if="modal" class="fixed inset-0 z-[60] flex items-end md:items-center justify-center p-0 md:p-4 bg-slate-900/70 backdrop-blur-sm">
            <div class="bg-white w-full md:max-w-md rounded-t-2xl md:rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                <div class="p-5 border-b flex justify-between items-center bg-white"><h3 class="font-bold text-lg">Pembayaran</h3><button @click="modal=false"><i class="ri-close-line text-2xl"></i></button></div>
                <div class="p-6 overflow-y-auto bg-gray-50">
                    <div class="text-center mb-8"><div class="text-xs font-bold text-gray-400 uppercase">Tagihan</div><div class="text-4xl font-extrabold text-slate-800 mt-1">{{fmt(total)}}</div></div>
                    <div class="grid grid-cols-2 gap-4 mb-6"><button @click="method='cash'" :class="method==='cash'?'ring-2 ring-theme bg-white shadow-md':'bg-gray-100'" class="p-4 rounded-xl font-bold text-center border border-transparent transition">Tunai</button><button @click="method='qris'" :class="method==='qris'?'ring-2 ring-theme bg-white shadow-md':'bg-gray-100'" class="p-4 rounded-xl font-bold text-center border border-transparent transition">QRIS</button></div>
                    <div v-if="method==='cash'" class="mb-6"><div class="relative mb-3"><span class="absolute left-4 top-3.5 font-bold text-lg text-gray-400">Rp</span><input type="number" v-model="paid" ref="cashInput" class="w-full pl-12 p-3 border rounded-xl text-xl font-bold focus:ring-2 ring-theme outline-none" placeholder="0"></div><div class="flex gap-2 overflow-x-auto pb-1 no-scrollbar mb-4"><button v-for="a in quickCash" :key="a" @click="paid=a" class="px-3 py-1.5 bg-white border rounded-lg text-xs font-bold shadow-sm">{{fmt(a)}}</button></div><div class="flex justify-between font-bold p-4 bg-white rounded-xl border" :class="change>=0?'text-green-600':'text-red-500'"><span>Kembali</span><span>{{fmt(Math.max(0,change))}}</span></div></div>
                    <div v-if="method==='qris'" class="text-center p-6 bg-white rounded-xl border mb-6"><img v-if="config.qris" :src="config.qris" class="mx-auto max-w-[200px] rounded-lg"><div v-else class="text-sm text-gray-400 py-4">QRIS belum diupload.</div></div>
                </div>
                <div class="p-5 border-t bg-white"><button @click="checkout" :disabled="processing||(method==='cash'&&change<0)" class="w-full py-4 bg-theme text-white rounded-xl font-bold shadow-lg hover:opacity-90 disabled:opacity-50">{{processing?'Proses...':'Selesai'}}</button></div>
            </div>
        </div>
    </div>

    <script>
        globalThis.params = { api: '<?php echo esc_url_raw( rest_url( "kresuber-pos/v1" ) ); ?>', nonce: '<?php echo wp_create_nonce( "wp_rest" ); ?>', curr: '<?php echo esc_js( get_woocommerce_currency_symbol() ); ?>', conf: <?php global $kresuber_config; echo json_encode($kresuber_config); ?> };
    </script>
    <script src="<?php echo KRESUBER_POS_PRO_URL; ?>assets/js/pos-app.js"></script>
    <script>setTimeout(()=>{const l=document.getElementById('app-loading');if(l)l.style.display='none'},2000);</script>
</body>
</html>
