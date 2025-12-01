<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
    <title>Kresuber POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <script src="https://unpkg.com/dexie@3.2.4/dist/dexie.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <link rel="stylesheet" href="<?php echo KRESUBER_POS_PRO_URL; ?>assets/css/pos-style.css">
    <style>
        :root { --primary: <?php global $kresuber_config; echo $kresuber_config['theme_color']; ?>; }
        .bg-theme { background-color: var(--primary); }
        .text-theme { color: var(--primary); }
        .border-theme { border-color: var(--primary); }
        .ring-theme { --tw-ring-color: var(--primary); }
        #app-loading { position: fixed; inset: 0; background: #fff; z-index: 9999; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: opacity 0.5s; }
        [v-cloak] { display: none !important; }
        #reader { width: 100%; border-radius: 8px; overflow: hidden; }
    </style>
</head>
<body class="bg-gray-100 h-screen overflow-hidden font-sans text-slate-800">
    
    <!-- Loader with Failsafe -->
    <div id="app-loading">
        <div class="mb-4 text-blue-600 animate-bounce"><i class="ri-store-3-fill text-6xl" style="color:var(--primary)"></i></div>
        <h2 class="text-xl font-bold text-slate-700">Memuat Kasir...</h2>
        <button onclick="document.getElementById('app-loading').style.display='none'" class="mt-4 text-sm text-gray-400 hover:text-red-500 underline">Paksa Buka</button>
    </div>

    <div id="app" v-cloak class="flex h-full w-full flex-col md:flex-row">
        
        <!-- Left: Main -->
        <div class="flex-1 flex flex-col h-full bg-white relative border-r border-gray-200">
            <!-- Header -->
            <div class="h-16 px-6 border-b justify-between items-center z-30 shrink-0 bg-white hidden md:flex">
                <div class="flex items-center gap-6 w-full max-w-3xl">
                    <div class="flex items-center gap-2">
                        <img v-if="config.logo" :src="config.logo" class="h-10 w-auto object-contain">
                        <span v-else class="font-bold text-2xl text-theme tracking-tight">{{config.site_name}}</span>
                    </div>
                    <!-- Search Bar with Camera Icon -->
                    <div class="relative w-full max-w-md group">
                        <i class="ri-search-line absolute left-3 top-2.5 text-gray-400"></i>
                        <input v-model="search" type="text" placeholder="Cari Produk / Scan (F3)" class="w-full pl-10 pr-10 py-2 bg-gray-100 rounded-lg outline-none focus:ring-2 ring-theme transition">
                        <button @click="openScanner" class="absolute right-2 top-1.5 p-1 text-gray-400 hover:text-theme" title="Scan Kamera"><i class="ri-qr-scan-2-line text-lg"></i></button>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button @click="sync" :class="{'animate-spin text-theme':syncing}" class="p-2 hover:bg-gray-100 rounded-full text-gray-500"><i class="ri-refresh-line text-lg"></i></button>
                    <button @click="viewMode='orders';fetchOrders()" class="flex items-center gap-2 px-3 py-2 bg-slate-50 rounded-lg font-bold text-sm hover:bg-slate-100 text-slate-700 border border-slate-200"><i class="ri-history-line"></i> Riwayat</button>
                    <a href="<?php echo esc_url(admin_url()); ?>" class="text-gray-400 hover:text-red-500 p-2"><i class="ri-logout-box-r-line text-xl"></i></a>
                </div>
            </div>

            <!-- Mobile Nav -->
            <div class="md:hidden h-14 bg-white border-b flex items-center justify-between px-4 z-50 shrink-0">
                <span class="font-bold text-lg text-theme">Kresuber</span>
                <div class="flex gap-3">
                    <button @click="openScanner" class="text-gray-600"><i class="ri-qr-scan-2-line text-xl"></i></button>
                    <button @click="showCart=!showCart" class="relative"><i class="ri-shopping-basket-fill text-2xl text-slate-700"></i><span v-if="cart.length" class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] w-4 h-4 rounded-full flex items-center justify-center">{{cartTotalQty}}</span></button>
                </div>
            </div>

            <!-- Mobile Search (Visible only on mobile) -->
            <div class="md:hidden p-3 border-b bg-white">
                <input v-model="search" type="text" placeholder="Cari..." class="w-full px-4 py-2 bg-gray-100 rounded-lg text-sm focus:ring-2 ring-theme outline-none">
            </div>

            <!-- Category Chips -->
            <div class="px-4 py-3 border-b bg-white overflow-x-auto whitespace-nowrap no-scrollbar shadow-sm z-20 shrink-0">
                <button @click="setCategory('all')" :class="curCat==='all' ? 'bg-theme text-white shadow-md border-theme' : 'bg-white text-slate-600 hover:bg-gray-50 border-gray-200'" class="px-5 py-1.5 rounded-full text-xs font-bold mr-2 transition-all border">Semua</button>
                <button v-for="c in categories" :key="c.slug" @click="setCategory(c.slug)" :class="curCat===c.slug ? 'bg-theme text-white shadow-md border-theme' : 'bg-white text-slate-600 hover:bg-gray-50 border-gray-200'" class="px-5 py-1.5 rounded-full text-xs font-bold mr-2 transition-all border">{{c.name}}</button>
            </div>

            <!-- POS Grid -->
            <div v-if="viewMode==='pos'" class="flex-1 overflow-y-auto p-4 md:p-6 bg-slate-50 custom-scrollbar">
                <div v-if="loading" class="text-center pt-20 text-gray-400">Memuat...</div>
                <div v-else-if="!products.length" class="text-center pt-20 text-gray-400"><i class="ri-inbox-line text-4xl"></i><p>Produk tidak ditemukan</p></div>
                <div v-else class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4 pb-20">
                    <div v-for="p in products" :key="p.id" @click="addToCart(p)" class="bg-white rounded-xl shadow-sm hover:shadow-md cursor-pointer overflow-hidden border border-transparent hover:border-theme flex flex-col h-64 transition group">
                        <div class="h-36 bg-gray-100 relative"><img :src="p.image" loading="lazy" class="w-full h-full object-cover"></div>
                        <div class="p-3 flex flex-col flex-1 justify-between">
                            <h3 class="font-bold text-sm text-slate-800 line-clamp-2 leading-snug">{{p.name}}</h3>
                            <div class="flex justify-between items-center mt-1"><span class="text-theme font-extrabold text-base">{{fmt(p.price)}}</span><div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center group-hover:bg-theme group-hover:text-white transition"><i class="ri-add-line"></i></div></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Orders History -->
            <div v-if="viewMode==='orders'" class="flex-1 overflow-y-auto p-6 bg-slate-50 custom-scrollbar">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="font-bold text-2xl text-slate-800">Riwayat Pesanan</h2>
                    <button @click="viewMode='pos'" class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 font-bold text-sm flex items-center gap-2 transition"><i class="ri-arrow-left-line"></i> Kembali</button>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-50 text-gray-500 border-b"><tr><th class="p-4 font-bold">ID</th><th class="p-4 font-bold">Tanggal</th><th class="p-4 font-bold">Items</th><th class="p-4 font-bold text-right">Total</th><th class="p-4 font-bold text-center">Status</th></tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="o in recentOrders" :key="o.id" class="hover:bg-blue-50 transition">
                                <td class="p-4 font-bold text-theme">#{{o.number}}</td>
                                <td class="p-4 text-gray-500">{{o.date}}</td>
                                <td class="p-4"><div v-for="(i,x) in o.items" :key="x" class="text-xs text-gray-700">{{i.qty}}x {{i.name}}</div></td>
                                <td class="p-4 font-bold text-right">{{o.total_formatted}}</td>
                                <td class="p-4 text-center"><span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-bold uppercase">{{o.status}}</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right: Cart -->
        <div :class="showCart?'translate-y-0':'translate-y-full md:translate-y-0'" class="fixed md:static inset-0 md:inset-auto z-40 w-full md:w-[400px] bg-white border-l shadow-2xl md:shadow-none flex flex-col transition-transform duration-300">
            <div class="px-5 py-4 border-b flex justify-between items-center bg-white shrink-0">
                <h2 class="font-bold text-lg flex items-center gap-2"><i class="ri-shopping-cart-2-fill text-theme"></i> Keranjang</h2>
                <div class="hidden md:flex gap-2"><button @click="clearCart" class="text-red-500 hover:bg-red-50 p-2 rounded transition"><i class="ri-delete-bin-line text-xl"></i></button></div>
                <button @click="showCart=false" class="md:hidden p-2 text-gray-400"><i class="ri-close-line text-xl"></i></button>
            </div>
            <div class="flex-1 overflow-y-auto p-4 space-y-3 bg-white custom-scrollbar">
                <div v-if="!cart.length" class="text-center text-slate-300 mt-20 flex flex-col items-center"><i class="ri-shopping-basket-line text-6xl mb-2 opacity-30"></i><p class="font-bold">Keranjang Kosong</p></div>
                <div v-for="i in cart" :key="i.id" class="flex gap-3 p-3 border border-gray-100 rounded-xl shadow-sm bg-white group">
                    <img :src="i.image" class="w-14 h-14 rounded-lg bg-gray-100 object-cover">
                    <div class="flex-1 min-w-0 flex flex-col justify-between">
                        <div class="flex justify-between items-start"><h4 class="font-bold text-sm text-slate-700 truncate leading-tight">{{i.name}}</h4><button @click="rem(i)" class="text-gray-300 hover:text-red-500"><i class="ri-close-circle-fill text-lg"></i></button></div>
                        <div class="flex justify-between items-end"><span class="text-xs text-gray-500 font-semibold">@ {{fmt(i.price)}}</span><div class="flex items-center bg-gray-50 rounded-lg border"><button @click="qty(i,-1)" class="w-8 h-7 font-bold hover:text-red-500">-</button><span class="text-sm font-bold w-6 text-center">{{i.qty}}</span><button @click="qty(i,1)" class="w-8 h-7 font-bold hover:text-theme">+</button></div></div>
                    </div>
                </div>
            </div>
            <div class="p-6 border-t bg-slate-50 shrink-0 shadow-lg z-20">
                <div class="flex justify-between items-center mb-6"><span class="font-bold text-lg text-slate-800">Total</span><span class="font-extrabold text-3xl text-theme tracking-tight">{{fmt(grandTotal)}}</span></div>
                <button @click="modal=true" :disabled="!cart.length" class="w-full py-3.5 bg-theme text-white rounded-xl font-bold shadow-lg hover:opacity-90 transition disabled:opacity-50 flex justify-center items-center gap-2"><i class="ri-secure-payment-line"></i> Bayar Sekarang</button>
            </div>
        </div>
        
        <!-- Scanner Modal -->
        <div v-if="showScanner" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/80 backdrop-blur-sm">
             <div class="bg-white w-full max-w-md rounded-2xl p-4 relative">
                 <button @click="closeScanner" class="absolute top-2 right-2 z-10 bg-white rounded-full p-1 text-red-500 shadow"><i class="ri-close-circle-fill text-3xl"></i></button>
                 <h3 class="text-center font-bold mb-4 text-lg">Scan Barcode / QR</h3>
                 <div id="reader" class="w-full rounded-lg overflow-hidden bg-black"></div>
                 <p class="text-center text-xs text-gray-500 mt-4">Arahkan kamera ke barcode produk.</p>
             </div>
        </div>

        <!-- Payment Modal -->
        <div v-if="modal" class="fixed inset-0 z-[60] flex items-end md:items-center justify-center p-0 md:p-4 bg-slate-900/70 backdrop-blur-sm transition-all">
            <div class="bg-white w-full md:max-w-md rounded-t-2xl md:rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                <div class="p-5 border-b flex justify-between items-center bg-white"><h3 class="font-bold text-lg">Pembayaran</h3><button @click="modal=false"><i class="ri-close-line text-2xl text-gray-400 hover:text-red-500"></i></button></div>
                <div class="p-6 overflow-y-auto bg-gray-50">
                    <div class="text-center mb-8"><div class="text-xs font-bold text-gray-400 uppercase tracking-wider">Total Tagihan</div><div class="text-4xl font-extrabold text-slate-800 mt-1">{{fmt(grandTotal)}}</div></div>
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <button @click="method='cash'" :class="method==='cash'?'ring-2 ring-theme bg-white shadow-md':'bg-gray-100 text-gray-500'" class="p-4 rounded-xl font-bold text-center transition border border-transparent">Tunai</button>
                        <button @click="method='qris'" :class="method==='qris'?'ring-2 ring-theme bg-white shadow-md':'bg-gray-100 text-gray-500'" class="p-4 rounded-xl font-bold text-center transition border border-transparent">QRIS</button>
                    </div>
                    <div v-if="method==='cash'" class="mb-6">
                        <div class="relative mb-3"><span class="absolute left-4 top-3.5 font-bold text-lg text-gray-400">Rp</span><input type="number" v-model="paid" ref="cashInput" class="w-full pl-12 p-3 border rounded-xl text-xl font-bold focus:ring-2 ring-theme outline-none" placeholder="0"></div>
                        <div class="flex gap-2 overflow-x-auto pb-1 no-scrollbar mb-4"><button v-for="a in quickCash" :key="a" @click="paid=a" class="px-3 py-1.5 bg-white border border-gray-200 hover:border-blue-500 rounded-lg text-xs font-bold whitespace-nowrap shadow-sm transition">{{fmt(a)}}</button></div>
                        <div class="flex justify-between font-bold p-4 bg-white rounded-xl border border-gray-200" :class="change>=0?'text-green-600':'text-red-500'"><span>Kembali</span><span>{{fmt(Math.max(0,change))}}</span></div>
                    </div>
                    <div v-if="method==='qris'" class="text-center p-6 bg-white rounded-xl border border-gray-200 mb-6"><img v-if="config.qris" :src="config.qris" class="mx-auto max-w-[200px] rounded-lg shadow-sm border"><div v-else class="text-sm text-gray-400 py-4">QRIS belum diupload di Admin.</div></div>
                </div>
                <div class="p-5 border-t bg-white"><button @click="checkout" :disabled="processing||(method==='cash'&&change<0)" class="w-full py-4 bg-theme text-white rounded-xl font-bold hover:bg-blue-700 disabled:opacity-50 shadow-lg transition flex justify-center gap-2"><i v-if="processing" class="ri-loader-4-line animate-spin text-xl"></i> {{method==='qris'?'Konfirmasi Selesai':'Bayar & Cetak'}}</button></div>
            </div>
        </div>

        <!-- Receipt -->
        <div id="receipt-print" class="hidden">
            <style>@page{margin:0}body.receipt{margin:0;padding:10px;font-family:'Courier New',monospace;font-size:12px;width:var(--print-width);line-height:1.2}.r-center{text-align:center}.r-right{text-align:right}.r-line{border-top:1px dashed #000;margin:5px 0}.r-table{width:100%;border-collapse:collapse}</style>
            <div class="receipt-body">
                <div class="r-center"><h3 style="margin:0;font-size:16px;font-weight:bold">{{config.site_name}}</h3><p style="margin:2px 0 10px;font-size:10px">POS Receipt</p></div><div class="r-line"></div>
                <div>No: #{{lastReceipt.orderNumber}}<br>Tgl: {{lastReceipt.date}}<br>Kasir: {{activeCashier}}</div>
                <div class="r-line"></div><table class="r-table"><tr v-for="i in lastReceipt.items"><td>{{i.name}}<br>{{i.qty}} x {{fmt(i.price)}}</td><td class="r-right" style="vertical-align:bottom">{{fmt(i.qty*i.price)}}</td></tr></table><div class="r-line"></div>
                <div style="display:flex;justify-content:space-between"><span>TOTAL</span> <strong>{{fmt(lastReceipt.grandTotal)}}</strong></div>
                <div v-if="lastReceipt.paymentMethod==='cash'"><div style="display:flex;justify-content:space-between"><span>Tunai</span><span>{{fmt(lastReceipt.cashReceived)}}</span></div><div style="display:flex;justify-content:space-between"><span>Kembali</span><span>{{fmt(lastReceipt.cashChange)}}</span></div></div>
                <div v-else style="text-align:center;margin-top:5px;font-style:italic;">[Lunas via {{lastReceipt.paymentMethod.toUpperCase()}}]</div>
                <div style="border-top:1px dashed #000;margin:10px 0;"></div>
                <div style="text-align:center;font-size:10px;">Terima Kasih!</div>
            </div>
        </div>
    </div>

    <script>
        globalThis.params = { api: '<?php echo esc_url_raw( rest_url( "kresuber-pos/v1" ) ); ?>', nonce: '<?php echo wp_create_nonce( "wp_rest" ); ?>', curr: '<?php echo esc_js( get_woocommerce_currency_symbol() ); ?>', conf: <?php global $kresuber_config; echo json_encode($kresuber_config); ?> };
    </script>
    <script src="<?php echo KRESUBER_POS_PRO_URL; ?>assets/js/pos-app.js"></script>
    <script>setTimeout(()=>{const l=document.getElementById('app-loading');if(l&&document.getElementById('app').innerHTML.trim().length>100)l.style.display='none'},2000);</script>
</body>
</html>
