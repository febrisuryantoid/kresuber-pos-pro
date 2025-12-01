<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
    <title><?php echo esc_html( $kresuber_config['site_name'] ); ?> - POS</title>
    
    <!-- Libraries (CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    
    <!-- Core JS Engines -->
    <script src="https://unpkg.com/dexie@3.2.4/dist/dexie.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    
    <link rel="stylesheet" href="<?php echo KRESUBER_POS_PRO_URL; ?>assets/css/pos-style.css?v=<?php echo KRESUBER_POS_PRO_VERSION; ?>">
    <style>
        :root { --primary: <?php echo esc_attr( $kresuber_config['theme_color'] ); ?>; }
        .text-theme { color: var(--primary); }
        .bg-theme { background-color: var(--primary); }
        .ring-theme { --tw-ring-color: var(--primary); }
        .hover\:bg-theme:hover { background-color: var(--primary); }
        [v-cloak] { display: none !important; }
        body { background-color: #F8FAFC; font-family: 'Inter', system-ui, sans-serif; }
        
        /* Error Screen */
        #fatal-error { display:none; position:fixed; inset:0; z-index:10000; background:#fff; flex-direction:column; align-items:center; justify-content:center; text-align:center; padding:20px; }
    </style>
</head>
<body class="h-screen overflow-hidden text-slate-800">
    
    <!-- Fatal Error Handler -->
    <div id="fatal-error">
        <div class="text-red-500 text-6xl mb-4"><i class="ri-error-warning-fill"></i></div>
        <h2 class="text-2xl font-bold text-slate-800 mb-2">Gagal Memuat Sistem</h2>
        <p class="text-slate-500 mb-6 max-w-md mx-auto">Koneksi internet tidak stabil atau ada file sistem yang terblokir. Pastikan perangkat terhubung internet (CDN diperlukan).</p>
        <button onclick="window.location.reload()" class="px-6 py-3 bg-slate-800 text-white rounded-xl font-bold hover:bg-slate-700 transition">Coba Lagi</button>
        <div id="error-detail" class="mt-4 text-xs text-red-400 font-mono bg-red-50 p-2 rounded"></div>
    </div>

    <!-- Loader -->
    <div id="app-loading" class="fixed inset-0 bg-white z-[9999] flex flex-col items-center justify-center transition-opacity duration-500">
        <div class="animate-bounce mb-4"><i class="ri-store-3-fill text-6xl text-theme"></i></div>
        <h2 class="text-xl font-bold text-slate-700">Memuat Sistem...</h2>
        <p class="text-xs text-gray-400 mt-2">Menyiapkan Database & Produk</p>
    </div>

    <div id="app" v-cloak class="flex h-full w-full">
        <!-- 1. SIDEBAR NAV (Desktop Only) -->
        <aside class="hidden md:flex w-24 bg-white border-r border-gray-200 flex-col items-center py-6 z-30 shadow-[4px_0_24px_rgba(0,0,0,0.02)] shrink-0">
            <div class="mb-10 w-12 h-12 bg-theme rounded-2xl flex items-center justify-center text-white font-bold text-2xl shadow-lg shadow-blue-100">
                <i class="ri-store-line"></i>
            </div>
            
            <nav class="flex-1 flex flex-col gap-4 w-full px-3">
                <button @click="viewMode='pos'" :class="viewMode==='pos'?'bg-blue-50 text-theme':'text-gray-400 hover:text-theme hover:bg-gray-50'" class="p-3 rounded-2xl transition flex flex-col items-center gap-1 group">
                    <i class="ri-layout-grid-fill text-2xl"></i>
                    <span class="text-[10px] font-bold">Kasir</span>
                </button>
                <button @click="viewMode='orders';fetchOrders()" :class="viewMode==='orders'?'bg-blue-50 text-theme':'text-gray-400 hover:text-theme hover:bg-gray-50'" class="p-3 rounded-2xl transition flex flex-col items-center gap-1">
                    <i class="ri-history-line text-2xl"></i>
                    <span class="text-[10px] font-bold">Riwayat</span>
                </button>
                <div class="flex-1"></div>
                <button @click="sync" class="p-3 rounded-2xl text-gray-400 hover:text-green-600 hover:bg-green-50 transition flex flex-col items-center gap-1 mb-2">
                    <i class="ri-refresh-line text-2xl" :class="{'animate-spin text-green-600':syncing}"></i>
                    <span class="text-[10px] font-bold">Sync</span>
                </button>
            </nav>

            <a href="<?php echo esc_url(admin_url()); ?>" class="mb-2 text-gray-300 hover:text-red-500 p-3 transition"><i class="ri-logout-box-line text-2xl"></i></a>
        </aside>

        <!-- 2. MAIN CONTENT -->
        <main class="flex-1 flex flex-col h-full overflow-hidden relative min-w-0 bg-[#F8FAFC]">
            <!-- Mobile Header -->
            <div class="md:hidden h-16 bg-white border-b flex items-center justify-between px-4 z-40 shrink-0">
                <div class="flex items-center gap-2 font-bold text-lg text-theme"><i class="ri-store-2-fill"></i> POS</div>
                <div class="flex gap-2">
                    <button @click="openScanner" class="w-9 h-9 bg-gray-100 rounded-full text-gray-600"><i class="ri-qr-scan-2-line"></i></button>
                    <button @click="showCart=!showCart" class="relative w-9 h-9 bg-theme text-white rounded-full"><i class="ri-shopping-bag-3-fill"></i><span v-if="cartTotalQty" class="absolute -top-1 -right-1 bg-red-500 text-[10px] w-4 h-4 rounded-full flex items-center justify-center border border-white">{{cartTotalQty}}</span></button>
                </div>
            </div>

            <!-- Desktop Top Bar -->
            <header class="hidden md:flex h-20 px-8 items-center justify-between shrink-0">
                <div class="relative w-full max-w-lg group">
                    <i class="ri-search-2-line absolute left-4 top-3.5 text-gray-400 text-lg group-focus-within:text-theme transition"></i>
                    <input v-model="search" type="text" placeholder="Cari Produk, SKU atau Barcode..." class="w-full pl-12 pr-12 py-3 bg-white border-none rounded-2xl shadow-sm text-sm font-medium focus:ring-2 ring-theme outline-none transition placeholder-gray-400">
                    <button @click="openScanner" class="absolute right-3 top-2.5 p-1 text-gray-400 hover:text-theme transition" title="Scan Barcode"><i class="ri-barcode-box-line text-xl"></i></button>
                </div>
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-3 bg-white pl-4 pr-2 py-1.5 rounded-full shadow-sm border border-gray-100">
                        <div class="text-right">
                            <div class="text-xs font-bold text-slate-700">{{config.site_name}}</div>
                            <div class="text-[10px] text-gray-400">Kasir: {{activeCashier}}</div>
                        </div>
                        <div class="w-9 h-9 rounded-full bg-gray-100 flex items-center justify-center text-gray-500"><i class="ri-user-3-fill"></i></div>
                    </div>
                </div>
            </header>

            <!-- Scrollable Content -->
            <div class="flex-1 overflow-y-auto custom-scrollbar px-4 md:px-8 pb-20 md:pb-8">
                <!-- Categories -->
                <div class="mb-8 mt-2 md:mt-0">
                    <div class="flex justify-between items-end mb-4 px-1">
                        <h2 class="font-bold text-lg text-slate-800">Kategori</h2>
                    </div>
                    <div class="flex gap-3 overflow-x-auto pb-4 no-scrollbar -mx-4 px-4 md:mx-0 md:px-0">
                        <button @click="setCategory('all')" :class="curCat==='all' ? 'bg-theme text-white shadow-lg shadow-blue-200 transform -translate-y-1' : 'bg-white text-slate-600 hover:bg-white/60 border border-transparent'" class="min-w-[120px] md:min-w-[130px] p-4 rounded-2xl shadow-sm transition-all duration-300 text-left relative group">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center mb-3 text-xl transition-colors" :class="curCat==='all'?'bg-white/20 text-white':'bg-gray-50 text-gray-500'"><i class="ri-apps-fill"></i></div>
                            <div class="font-bold text-sm">Semua</div>
                            <div class="text-[10px] opacity-80 mt-0.5">Lihat Semua</div>
                        </button>
                        
                        <button v-for="c in categories" :key="c.slug" @click="setCategory(c.slug)" :class="curCat===c.slug ? 'bg-theme text-white shadow-lg shadow-blue-200 transform -translate-y-1' : 'bg-white text-slate-600 hover:bg-white/60 border border-transparent'" class="min-w-[120px] md:min-w-[130px] p-4 rounded-2xl shadow-sm transition-all duration-300 text-left relative group">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center mb-3 text-xl transition-colors" :class="curCat===c.slug?'bg-white/20 text-white':'bg-orange-50 text-orange-500'"><i class="ri-price-tag-3-fill"></i></div>
                            <div class="font-bold text-sm truncate pr-2">{{c.name}}</div>
                            <div class="text-[10px] opacity-80 mt-0.5">Kategori</div>
                        </button>
                    </div>
                </div>

                <!-- Product Grid -->
                <div v-if="viewMode==='pos'">
                    <div class="flex justify-between items-center mb-4 px-1">
                        <h2 class="font-bold text-lg text-slate-800">Daftar Produk</h2>
                    </div>

                    <div v-if="loading" class="py-20 text-center text-gray-400">
                        <i class="ri-loader-4-line animate-spin text-3xl mb-2"></i><p>Memuat produk...</p>
                    </div>
                    
                    <div v-else-if="!products.length" class="py-20 text-center text-gray-400 flex flex-col items-center">
                        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4"><i class="ri-inbox-line text-4xl opacity-50"></i></div>
                        <p class="font-medium">Produk tidak ditemukan</p>
                        <button @click="sync" class="mt-4 text-theme font-bold text-sm hover:underline">Sync Ulang</button>
                    </div>

                    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3 gap-4">
                        <div v-for="p in products" :key="p.id" @click="addToCart(p)" class="bg-white p-2.5 rounded-2xl shadow-sm hover:shadow-md border border-transparent hover:border-blue-300 cursor-pointer transition-all duration-200 flex gap-3 group h-[100px] relative overflow-hidden">
                            <div class="w-20 h-full rounded-xl bg-gray-50 shrink-0 overflow-hidden relative">
                                <img :src="p.image" loading="lazy" class="w-full h-full object-cover group-hover:scale-110 transition duration-500" onerror="this.src='https://placehold.co/100x100?text=No+Img'">
                                <div v-if="p.stock <= 5" class="absolute bottom-0 inset-x-0 bg-red-500/80 text-white text-[9px] text-center font-bold py-0.5">Sisa {{p.stock}}</div>
                            </div>
                            <div class="flex-1 flex flex-col justify-between py-0.5 min-w-0">
                                <div>
                                    <h3 class="font-bold text-slate-800 text-sm leading-tight mb-1 line-clamp-2" :title="p.name">{{p.name}}</h3>
                                    <div class="text-[10px] text-gray-400 flex items-center gap-1"><i class="ri-barcode-line"></i> {{p.sku || '-'}}</div>
                                </div>
                                <div class="flex justify-between items-end">
                                    <span class="font-extrabold text-theme text-sm">{{fmt(p.price)}}</span>
                                    <button class="w-7 h-7 rounded-lg bg-blue-50 text-theme flex items-center justify-center shadow-sm"><i class="ri-add-line font-bold"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- History View -->
                <div v-if="viewMode==='orders'" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="p-4 border-b flex justify-between items-center bg-gray-50">
                        <h3 class="font-bold text-slate-700">Riwayat Transaksi</h3>
                        <div class="text-xs text-gray-500" v-if="ordersLoading">Memuat...</div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-white text-gray-500 border-b"><tr><th class="p-4 font-bold">ID</th><th class="p-4 font-bold">Tanggal</th><th class="p-4 font-bold text-right">Total</th><th class="p-4 font-bold text-center">Status</th></tr></thead>
                            <tbody class="divide-y divide-gray-50">
                                <tr v-for="o in recentOrders" :key="o.id" class="hover:bg-blue-50">
                                    <td class="p-4 font-bold text-theme">#{{o.number}}</td>
                                    <td class="p-4 text-gray-500 text-xs">{{o.date}}</td>
                                    <td class="p-4 font-bold text-right">{{o.total_formatted}}</td>
                                    <td class="p-4 text-center"><span class="px-2 py-1 bg-green-100 text-green-700 rounded text-[10px] font-bold uppercase">{{o.status}}</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>

        <!-- 3. RIGHT CART -->
        <aside :class="showCart?'translate-x-0':'translate-x-full lg:translate-x-0'" class="fixed lg:static inset-y-0 right-0 z-50 w-full md:w-[360px] bg-white border-l border-gray-100 shadow-2xl lg:shadow-none transition-transform duration-300 flex flex-col h-full">
            <div class="p-5 flex-1 flex flex-col min-h-0 bg-white">
                <div class="flex justify-between items-center mb-6 shrink-0">
                    <div>
                        <h2 class="font-bold text-xl text-slate-800">Order Menu</h2>
                        <p class="text-xs text-gray-400 mt-1 flex items-center gap-1"><i class="ri-calendar-line"></i> <?php echo date('d M Y'); ?></p>
                    </div>
                    <div class="flex gap-2">
                         <button @click="clearCart" v-if="cart.length" class="w-8 h-8 rounded-lg bg-red-50 text-red-500 hover:bg-red-500 hover:text-white transition flex items-center justify-center"><i class="ri-delete-bin-line"></i></button>
                         <button @click="showCart=false" class="lg:hidden w-8 h-8 rounded-lg bg-gray-100 text-gray-500 flex items-center justify-center"><i class="ri-close-line text-lg"></i></button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto custom-scrollbar pr-1 -mr-2">
                    <div v-if="!cart.length" class="h-full flex flex-col items-center justify-center text-gray-300 opacity-60">
                        <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mb-3"><i class="ri-shopping-cart-2-fill text-3xl"></i></div>
                        <p class="text-sm font-medium">Keranjang Kosong</p>
                    </div>
                    
                    <div v-for="i in cart" :key="i.id" class="flex gap-3 mb-4 group relative">
                        <div class="w-14 h-14 rounded-xl bg-gray-50 overflow-hidden shrink-0 border border-gray-100">
                            <img :src="i.image" class="w-full h-full object-cover">
                        </div>
                        <div class="flex-1 min-w-0 flex flex-col justify-center">
                            <h4 class="font-bold text-sm text-slate-700 truncate mb-1">{{i.name}}</h4>
                            <div class="flex justify-between items-center">
                                <div class="text-xs text-theme font-bold">{{fmt(i.price)}}</div>
                                <div class="text-xs font-bold text-gray-400">Total: {{fmt(i.price * i.qty)}}</div>
                            </div>
                        </div>
                        <div class="absolute right-0 top-1/2 -translate-y-1/2 bg-white shadow-sm border rounded-lg flex items-center p-0.5">
                            <button @click="qty(i,-1)" class="w-6 h-6 rounded flex items-center justify-center text-gray-400 hover:bg-red-50 hover:text-red-500 transition"><i class="ri-subtract-line text-xs"></i></button>
                            <span class="w-6 text-center text-xs font-bold">{{i.qty}}</span>
                            <button @click="qty(i,1)" class="w-6 h-6 rounded flex items-center justify-center text-gray-400 hover:bg-blue-50 hover:text-blue-500 transition"><i class="ri-add-line text-xs"></i></button>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 pt-4 border-t border-dashed border-gray-200 space-y-2 shrink-0">
                    <div class="flex justify-between items-center pt-2 mt-2">
                        <span class="font-bold text-slate-800 text-sm">Total Tagihan</span>
                        <span class="font-extrabold text-xl text-theme">{{fmt(grandTotal)}}</span>
                    </div>
                </div>
            </div>

            <div class="p-5 bg-gray-50 border-t border-gray-100 shrink-0">
                <button @click="modal=true" :disabled="!cart.length" class="w-full py-3.5 bg-theme text-white rounded-xl font-bold text-sm shadow-lg shadow-blue-200 hover:bg-opacity-90 transition transform active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                    <i class="ri-secure-payment-line text-lg"></i> Proses Pembayaran
                </button>
            </div>
        </aside>

        <!-- Modals -->
        <div v-if="showScanner" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
             <div class="bg-white w-full max-w-sm rounded-2xl p-4 relative">
                 <button @click="closeScanner" class="absolute top-2 right-2 z-10 bg-gray-100 rounded-full w-8 h-8 flex items-center justify-center text-gray-600 hover:bg-red-100 hover:text-red-500"><i class="ri-close-line text-xl"></i></button>
                 <h3 class="text-center font-bold mb-4">Scan Barcode</h3>
                 <div id="reader" class="w-full rounded-xl overflow-hidden bg-black border-2 border-theme"></div>
             </div>
        </div>

        <div v-if="modal" class="fixed inset-0 z-[60] flex items-end md:items-center justify-center p-0 md:p-4 bg-slate-900/60 backdrop-blur-sm transition-all">
            <div class="bg-white w-full md:max-w-sm rounded-t-2xl md:rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh] animate-in slide-in-from-bottom-10">
                <div class="p-5 border-b flex justify-between items-center bg-white">
                    <h3 class="font-bold text-lg">Pembayaran</h3>
                    <button @click="modal=false" class="w-8 h-8 rounded-full bg-gray-50 flex items-center justify-center hover:bg-red-50 hover:text-red-500"><i class="ri-close-line text-xl"></i></button>
                </div>
                <div class="p-6 overflow-y-auto bg-white custom-scrollbar">
                    <div class="text-center mb-6">
                        <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Total Tagihan</div>
                        <div class="text-4xl font-extrabold text-slate-800 tracking-tight">{{fmt(grandTotal)}}</div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3 mb-6">
                        <button @click="method='cash'" :class="method==='cash' ? 'bg-blue-50 border-theme text-theme ring-2 ring-theme' : 'bg-gray-50 border-transparent text-gray-500'" class="py-3 px-2 rounded-xl border font-bold text-sm flex items-center justify-center gap-2 transition">
                            <i class="ri-wallet-3-fill"></i> Tunai
                        </button>
                        <button @click="method='qris'" :class="method==='qris' ? 'bg-blue-50 border-theme text-theme ring-2 ring-theme' : 'bg-gray-50 border-transparent text-gray-500'" class="py-3 px-2 rounded-xl border font-bold text-sm flex items-center justify-center gap-2 transition">
                            <i class="ri-qr-code-line"></i> QRIS
                        </button>
                    </div>

                    <div v-if="method==='cash'">
                        <div class="relative mb-3">
                            <span class="absolute left-4 top-3.5 font-bold text-lg text-gray-400">Rp</span>
                            <input type="number" v-model="paid" ref="cashInput" class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xl font-bold focus:ring-2 ring-theme focus:bg-white outline-none transition" placeholder="0">
                        </div>
                        <div class="grid grid-cols-2 gap-2 mb-6">
                            <button v-for="a in quickCash" :key="a" @click="paid=a" class="px-3 py-2 bg-white border border-gray-200 hover:border-theme hover:bg-blue-50 rounded-lg text-xs font-bold transition">{{fmt(a)}}</button>
                        </div>
                        <div class="p-4 bg-blue-50 rounded-xl border border-blue-100 flex justify-between items-center">
                            <span class="text-sm font-bold text-blue-800">Kembali</span>
                            <span class="text-lg font-extrabold" :class="change>=0?'text-green-600':'text-red-500'">{{fmt(Math.max(0,change))}}</span>
                        </div>
                    </div>

                    <div v-if="method==='qris'" class="text-center py-4">
                        <div class="p-4 bg-white rounded-xl border border-gray-200 shadow-sm inline-block mb-2">
                            <img v-if="config.qris" :src="config.qris" class="w-40 h-40 object-contain">
                            <div v-else class="w-40 h-40 flex items-center justify-center bg-gray-50 text-gray-400 text-xs px-4">QRIS belum diupload di Admin</div>
                        </div>
                        <p class="text-xs text-gray-500">Scan QRIS di atas</p>
                    </div>
                </div>

                <div class="p-5 border-t bg-white">
                    <button @click="checkout" :disabled="processing||(method==='cash'&&change<0)" class="w-full py-4 bg-theme text-white rounded-2xl font-bold shadow-lg shadow-blue-200 hover:bg-opacity-90 disabled:opacity-50 transition flex justify-center items-center gap-2">
                        <i v-if="processing" class="ri-loader-4-line animate-spin text-xl"></i> 
                        {{method==='qris'?'Konfirmasi & Cetak':'Bayar & Cetak Struk'}}
                    </button>
                </div>
            </div>
        </div>

        <!-- Receipt Template -->
        <div id="receipt-print" class="hidden">
            <style>@page{margin:0}body.receipt{margin:0;padding:10px;font-family:'Courier New',monospace;font-size:12px;width:var(--print-width);line-height:1.2}.r-center{text-align:center}.r-right{text-align:right}.r-line{border-top:1px dashed #000;margin:5px 0}.r-table{width:100%;border-collapse:collapse}</style>
            <div class="receipt-body">
                <div class="r-center"><h3 style="margin:0;font-size:16px;font-weight:bold">{{config.site_name}}</h3><p style="margin:2px 0 10px;font-size:10px">POS Receipt</p></div><div class="r-line"></div>
                <div>#{{lastReceipt.number}}<br>{{lastReceipt.date}}<br>Kasir: {{activeCashier}}</div>
                <div class="r-line"></div><table class="r-table"><tr v-for="i in lastReceipt.items"><td>{{i.name}}<br>{{i.qty}} x {{fmt(i.price)}}</td><td class="r-right" style="vertical-align:bottom">{{fmt(i.qty*i.price)}}</td></tr></table><div class="r-line"></div>
                <div style="display:flex;justify-content:space-between"><span>TOTAL</span> <strong>{{fmt(lastReceipt.grandTotal)}}</strong></div>
                <div v-if="lastReceipt.paymentMethod==='cash'"><div style="display:flex;justify-content:space-between"><span>Tunai</span><span>{{fmt(lastReceipt.cashReceived)}}</span></div><div style="display:flex;justify-content:space-between"><span>Kembali</span><span>{{fmt(lastReceipt.cashChange)}}</span></div></div>
                <div v-else style="text-align:center;margin-top:5px;font-style:italic;">[Lunas via {{lastReceipt.paymentMethod.toUpperCase()}}]</div>
                <div class="r-line"></div><div class="r-center" style="font-size:10px;">Terima Kasih!</div>
            </div>
        </div>
    </div>

    <!-- Init Params safely -->
    <script>
        // Check for dependencies immediately
        const missing = [];
        if(typeof Vue === 'undefined') missing.push('Vue.js');
        if(typeof axios === 'undefined') missing.push('Axios');
        if(typeof Dexie === 'undefined') missing.push('Dexie');
        
        if(missing.length > 0) {
            document.getElementById('app-loading').style.display = 'none';
            document.getElementById('fatal-error').style.display = 'flex';
            document.getElementById('error-detail').innerText = 'Missing Libraries: ' + missing.join(', ');
            throw new Error('System halted: Missing dependencies');
        }

        globalThis.params = { 
            api: '<?php echo esc_url_raw( rest_url( "kresuber-pos/v1" ) ); ?>', 
            nonce: '<?php echo wp_create_nonce( "wp_rest" ); ?>', 
            curr: '<?php echo esc_js( get_woocommerce_currency_symbol() ); ?>', 
            conf: <?php echo isset($kresuber_config) ? json_encode($kresuber_config) : '{}'; ?> 
        };
    </script>
    <script src="<?php echo KRESUBER_POS_PRO_URL; ?>assets/js/pos-app.js?v=<?php echo KRESUBER_POS_PRO_VERSION; ?>"></script>
</body>
</html>