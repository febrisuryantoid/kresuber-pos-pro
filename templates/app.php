<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
    <title>Kresuber POS v1.6</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <script src="https://unpkg.com/dexie@3.2.4/dist/dexie.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <style>
        :root { 
            --primary: <?php global $kresuber_config; echo $kresuber_config['theme_color']; ?>; 
            --print-width: <?php echo $kresuber_config['printer_width']; ?>;
        }
        .bg-theme { background-color: var(--primary); }
        .text-theme { color: var(--primary); }
        .border-theme { border-color: var(--primary); }
        .ring-theme { --tw-ring-color: var(--primary); }
        [v-cloak] { display: none !important; }
        #app-loading { position: fixed; inset: 0; background: #fff; z-index: 9999; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        @media print { body * { visibility: hidden; height: 0; } #receipt-print, #receipt-print * { visibility: visible; height: auto; } #receipt-print { position: absolute; left: 0; top: 0; width: 100%; } }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="bg-gray-100 h-screen overflow-hidden font-sans text-slate-800">
    <div id="app-loading">
        <div class="mb-4 text-theme animate-bounce"><i class="ri-store-3-fill text-6xl" style="color:var(--primary)"></i></div>
        <h2 class="text-xl font-bold text-slate-700">Memuat Sistem POS...</h2>
    </div>
    <div id="app" v-cloak class="flex h-full w-full flex-col md:flex-row">
        <!-- Left: Main -->
        <div class="flex-1 flex flex-col h-full bg-white relative border-r border-gray-200">
            <div class="h-16 border-b px-4 flex justify-between items-center bg-white z-30 shadow-sm">
                <div class="flex items-center gap-4 w-full max-w-3xl">
                    <div class="flex items-center gap-2">
                        <img v-if="config.logo" :src="config.logo" class="h-10 w-auto object-contain">
                        <span v-else class="font-bold text-2xl text-theme tracking-tight">{{config.site_name}}</span>
                    </div>
                    <div class="relative w-full max-w-md group hidden md:block">
                        <i class="ri-search-line absolute left-3 top-2.5 text-gray-400"></i>
                        <input v-model="search" type="text" placeholder="Cari Produk / Scan (F3)..." class="w-full pl-10 pr-10 py-2 bg-gray-100 rounded-lg focus:bg-white focus:ring-2 ring-theme outline-none transition text-sm">
                        <button v-if="search" @click="search=''" class="absolute right-2 top-2 text-gray-400 hover:text-red-500"><i class="ri-close-circle-fill"></i></button>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button @click="connectPrinter" :class="printer?'text-green-500':'text-gray-400'" class="p-2 hover:bg-gray-100 rounded-full" title="Printer"><i class="ri-bluetooth-connect-line text-xl"></i></button>
                    <button @click="sync" :class="{'animate-spin text-theme':syncing}" class="p-2 hover:bg-gray-100 rounded-full text-gray-500"><i class="ri-refresh-line text-xl"></i></button>
                    <div class="hidden lg:flex items-center gap-2 bg-gray-100 px-3 py-1 rounded-lg"><i class="ri-user-smile-line text-gray-500"></i><select v-model="activeCashier" class="bg-transparent border-none text-sm font-bold focus:ring-0 p-0 cursor-pointer"><option value="default">Admin</option><option v-for="c in config.cashiers" :key="c" :value="c">{{c}}</option></select></div>
                    <a href="<?php echo esc_url(admin_url()); ?>" class="text-gray-400 hover:text-red-500"><i class="ri-logout-box-r-line text-xl"></i></a>
                </div>
            </div>
            <div class="md:hidden p-3 border-b bg-white"><div class="relative"><input v-model="search" type="text" placeholder="Cari..." class="w-full pl-3 pr-3 py-2 bg-gray-100 rounded-lg text-sm focus:ring-2 ring-theme outline-none"></div></div>
            <div class="px-4 py-2 border-b bg-white overflow-x-auto whitespace-nowrap scrollbar-hide shadow-sm z-20">
                <button @click="setCat('all')" :class="curCat==='all'?'bg-theme text-white shadow-lg':'bg-gray-100 text-gray-600 hover:bg-gray-200'" class="px-4 py-1.5 rounded-full text-xs font-bold mr-2 transition-all">Semua</button>
                <button v-for="c in categories" :key="c.slug" @click="setCat(c.slug)" :class="curCat===c.slug?'bg-theme text-white shadow-lg':'bg-gray-100 text-gray-600 hover:bg-gray-200'" class="px-4 py-1.5 rounded-full text-xs font-bold mr-2 transition-all">{{c.name}}</button>
            </div>
            <div class="flex-1 overflow-y-auto p-4 md:p-6 bg-slate-50">
                <div v-if="loading" class="flex flex-col items-center justify-center h-full text-gray-400"><i class="ri-loader-4-line animate-spin text-4xl mb-2 text-theme"></i> Menyiapkan Data...</div>
                <div v-else-if="!products.length" class="flex flex-col items-center justify-center h-full text-gray-400"><i class="ri-inbox-line text-5xl mb-2"></i> Produk tidak ditemukan.</div>
                <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 pb-20 md:pb-0">
                    <div v-for="p in products" :key="p.id" @click="addToCart(p)" class="bg-white rounded-2xl shadow-sm hover:shadow-md cursor-pointer overflow-hidden flex flex-col border border-transparent hover:border-theme transition group h-[240px]">
                        <div class="h-32 bg-gray-100 relative"><img :src="p.image" loading="lazy" class="w-full h-full object-cover"><span v-if="p.stock_status!=='instock'" class="absolute inset-0 bg-white/80 flex items-center justify-center font-bold text-red-500 text-xs">HABIS</span><span v-else class="absolute top-2 right-2 bg-black/60 text-white text-[10px] px-2 py-0.5 rounded backdrop-blur-sm">{{p.stock}}</span></div>
                        <div class="p-3 flex flex-col flex-1 justify-between"><h3 class="font-bold text-sm text-slate-800 line-clamp-2 leading-tight">{{p.name}}</h3><div class="flex justify-between items-end mt-2"><span class="text-theme font-extrabold text-base">{{fmt(p.price)}}</span><div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 group-hover:bg-theme group-hover:text-white transition"><i class="ri-add-line"></i></div></div></div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Right: Cart -->
        <div :class="showCart?'translate-y-0':'translate-y-full md:translate-y-0'" class="fixed md:static inset-0 md:inset-auto z-40 w-full md:w-[420px] bg-white border-l shadow-2xl md:shadow-none flex flex-col transition-transform duration-300">
            <div class="md:hidden flex justify-center pt-2 pb-1" @click="showCart=false"><div class="w-12 h-1 bg-gray-300 rounded-full"></div></div>
            <div class="px-6 py-4 border-b bg-white flex justify-between items-center"><h2 class="font-bold text-lg flex items-center gap-2"><span class="bg-theme/10 text-theme p-1 rounded"><i class="ri-shopping-cart-2-fill"></i></span> Keranjang</h2><div class="flex gap-2"><button @click="toggleHold" class="p-2 rounded border hover:bg-orange-50 text-orange-500 relative"><i class="ri-pause-circle-line"></i><span v-if="heldItems.length" class="absolute top-0 right-0 w-2 h-2 bg-red-500 rounded-full"></span></button><button @click="clearCart" class="p-2 rounded border hover:bg-red-50 text-red-500"><i class="ri-delete-bin-line"></i></button></div></div>
            <div class="flex-1 overflow-y-auto p-4 space-y-3 bg-white scrollbar-hide">
                <div v-if="!cart.length" class="flex flex-col items-center justify-center h-full text-gray-300"><i class="ri-shopping-basket-line text-6xl mb-2 opacity-50"></i><p>Keranjang Kosong</p></div>
                <div v-for="i in cart" :key="i.id" class="flex gap-3 p-3 border border-gray-100 rounded-xl shadow-sm bg-white"><img :src="i.image" class="w-14 h-14 rounded-lg bg-gray-100 object-cover"><div class="flex-1 min-w-0 flex flex-col justify-between"><div class="flex justify-between"><h4 class="font-bold text-sm truncate text-slate-700">{{i.name}}</h4><button @click="rem(i)" class="text-gray-300 hover:text-red-500"><i class="ri-close-circle-fill"></i></button></div><div class="flex justify-between items-end"><span class="text-xs text-gray-500 font-semibold">@ {{fmt(i.price)}}</span><div class="flex items-center bg-gray-50 rounded-lg border"><button @click="qty(i,-1)" class="w-8 h-7 font-bold hover:text-red-500">-</button><span class="text-sm font-bold w-4 text-center">{{i.qty}}</span><button @click="qty(i,1)" class="w-8 h-7 font-bold hover:text-green-500">+</button></div></div></div></div>
            </div>
            <div class="p-6 border-t bg-slate-50 shadow-lg z-20">
                <div class="space-y-2 mb-4 text-sm"><div class="flex justify-between text-gray-500"><span>Subtotal</span><span>{{fmt(subTotal)}}</span></div><div class="flex justify-between text-gray-500"><span>Pajak ({{taxRate}}%)</span><span>{{fmt(taxAmount)}}</span></div><div class="flex justify-between text-xl font-extrabold text-slate-800 pt-2 border-t"><span>Total</span><span class="text-theme">{{fmt(grandTotal)}}</span></div></div>
                <button @click="modal=true" :disabled="!cart.length" class="w-full py-3.5 bg-theme text-white rounded-xl font-bold shadow-lg hover:opacity-90 transition disabled:opacity-50 flex justify-center items-center gap-2"><i class="ri-secure-payment-line"></i> Bayar Sekarang</button>
            </div>
        </div>
        <button @click="showCart=true" class="md:hidden fixed bottom-6 right-6 bg-theme text-white w-14 h-14 rounded-full shadow-xl flex items-center justify-center z-40"><i class="ri-shopping-cart-2-fill text-2xl"></i><span v-if="cart.length" class="absolute top-0 right-0 bg-red-500 text-[10px] w-5 h-5 flex items-center justify-center rounded-full border-2 border-white">{{cart.length}}</span></button>
        <!-- Modal -->
        <div v-if="modal" class="fixed inset-0 z-[60] flex items-end md:items-center justify-center bg-black/60 backdrop-blur-sm p-0 md:p-4">
            <div class="bg-white w-full md:max-w-md rounded-t-2xl md:rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                <div class="p-4 border-b flex justify-between items-center bg-gray-50"><h3 class="font-bold text-lg">Pembayaran</h3><button @click="modal=false"><i class="ri-close-line text-2xl text-gray-400"></i></button></div>
                <div class="p-6 overflow-y-auto">
                    <div class="text-center mb-6"><div class="text-xs font-bold text-gray-400 uppercase">Total Tagihan</div><div class="text-4xl font-extrabold text-slate-800">{{fmt(grandTotal)}}</div></div>
                    <div class="grid grid-cols-2 gap-3 mb-6"><button @click="method='cash'" :class="method==='cash'?'ring-2 ring-theme bg-blue-50 border-transparent':''" class="border p-4 rounded-xl flex flex-col items-center transition"><i class="ri-money-dollar-circle-fill text-3xl text-theme mb-1"></i><span class="font-bold text-sm">Tunai</span></button><button @click="method='qris'" :class="method==='qris'?'ring-2 ring-theme bg-blue-50 border-transparent':''" class="border p-4 rounded-xl flex flex-col items-center transition"><i class="ri-qr-code-line text-3xl text-theme mb-1"></i><span class="font-bold text-sm">QRIS</span></button></div>
                    <div v-if="method==='cash'" class="space-y-3"><div class="relative"><span class="absolute left-4 top-3.5 text-gray-400 font-bold text-lg">Rp</span><input type="number" v-model="paid" ref="cashInput" class="w-full pl-12 p-3 border rounded-xl text-xl font-bold focus:ring-2 ring-theme outline-none" placeholder="0"></div><div class="flex gap-2 overflow-x-auto pb-1 no-scrollbar"><button v-for="a in quickCash" :key="a" @click="paid=a" class="px-3 py-1.5 bg-gray-100 rounded-lg text-xs font-bold border hover:bg-gray-200 whitespace-nowrap">{{fmt(a)}}</button></div><div class="flex justify-between items-center p-4 bg-gray-50 rounded-xl"><span class="font-bold text-gray-500">Kembalian</span><span class="font-extrabold text-xl" :class="change>=0?'text-green-600':'text-red-500'">{{fmt(Math.max(0,change))}}</span></div></div>
                    <div v-if="method==='qris'" class="text-center p-6 bg-gray-50 rounded-xl border border-dashed border-gray-300"><img v-if="config.qris" :src="config.qris" class="mx-auto max-w-[200px] rounded shadow-sm"><div v-else class="text-gray-400 text-sm flex flex-col items-center"><i class="ri-image-off-line text-2xl mb-2"></i>QRIS Belum Diupload</div></div>
                </div>
                <div class="p-4 border-t"><button @click="checkout" :disabled="processing||(method==='cash'&&change<0)" class="w-full py-3.5 bg-theme text-white rounded-xl font-bold shadow hover:opacity-90 disabled:opacity-50 flex justify-center items-center gap-2"><i v-if="processing" class="ri-loader-4-line animate-spin"></i> {{method==='qris'?'Konfirmasi Pembayaran':'Bayar & Cetak'}}</button></div>
            </div>
        </div>
        <!-- Receipt -->
        <div id="receipt-print" class="hidden">
            <div style="width:var(--print-width);font-family:'Courier New',monospace;font-size:10px;text-align:center;line-height:1.2"><h3 style="margin:0;font-size:14px;font-weight:bold">{{config.site_name}}</h3><p style="margin:2px 0 5px;font-size:9px">POS Receipt</p><hr style="border-top:1px dashed #000;margin:5px 0"><div style="text-align:left">No: #{{last.order}}<br>Tgl: {{last.date}}<br>Kasir: {{activeCashier}}</div><hr style="border-top:1px dashed #000;margin:5px 0"><table style="width:100%;text-align:left"><tr v-for="i in last.items"><td style="padding-bottom:2px"><strong>{{i.name}}</strong><br>{{i.qty}} x {{fmt(i.price)}}</td><td style="text-align:right;vertical-align:top">{{fmt(i.qty*i.price)}}</td></tr></table><hr style="border-top:1px dashed #000;margin:5px 0"><div style="display:flex;justify-content:space-between"><span>TOTAL</span> <strong>{{fmt(last.grandTotal)}}</strong></div><div v-if="last.paymentMethod==='cash'"><div style="display:flex;justify-content:space-between"><span>Tunai</span><span>{{fmt(last.cashReceived)}}</span></div><div style="display:flex;justify-content:space-between"><span>Kembali</span><span>{{fmt(last.cashChange)}}</span></div></div><div v-else style="text-align:center;margin-top:5px;font-style:italic">[Lunas via {{last.paymentMethod.toUpperCase()}}]</div><hr style="border-top:1px dashed #000;margin:10px 0"><div style="text-align:center">Terima Kasih!</div></div>
        </div>
    </div>
    <script>
        globalThis.params = { api: '<?php echo esc_url_raw( rest_url( "kresuber-pos/v1" ) ); ?>', nonce: '<?php echo wp_create_nonce( "wp_rest" ); ?>', curr: '<?php echo esc_js( get_woocommerce_currency_symbol() ); ?>', conf: <?php global $kresuber_config; echo json_encode($kresuber_config); ?> };
    </script>
    <script src="<?php echo KRESUBER_POS_PRO_URL; ?>assets/js/pos-app.js"></script>
</body>
</html>
