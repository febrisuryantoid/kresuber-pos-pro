<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
    <title>Kresuber POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet" onerror="console.error('Font Load Error')">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript" defer></script>
    <script src="https://unpkg.com/dexie@3.2.4/dist/dexie.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js" defer></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js" defer></script>
    <style>
        :root { --primary: <?php global $kresuber_config; echo esc_attr($kresuber_config['theme_color']); ?>; }
        .bg-theme { background-color: var(--primary); }
        .text-theme { color: var(--primary); }
        .border-theme { border-color: var(--primary); }
        .ring-theme { --tw-ring-color: var(--primary); }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        [v-cloak] { display: none; }
        /* Static Loader */
        #static-loader { position: fixed; inset: 0; background: #fff; z-index: 9999; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .spinner { border: 4px solid #f3f3f3; width: 40px; height: 40px; border-radius: 50%; border-top-color: var(--primary); animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        /* Progress Bar */
        .loader-bar-bg { width: 240px; height: 6px; background: #f0f0f0; border-radius: 10px; margin-top: 15px; overflow: hidden; }
        .loader-bar-fill { height: 100%; background: var(--primary); width: 0%; transition: width 0.3s ease-out; }
    </style>
</head>
<body class="bg-gray-100 h-screen w-screen overflow-hidden font-sans text-gray-800">
    
    <!-- Static Loader (Visible until Data Ready) -->
    <div id="static-loader">
        <div class="spinner mb-2"></div>
        <h2 class="font-bold text-gray-700 text-lg">Memuat Kasir...</h2>
        <div class="loader-bar-bg"><div id="loader-bar" class="loader-bar-fill"></div></div>
        <p id="loader-text" class="text-xs text-gray-400 mt-2 font-mono">Menyiapkan Aplikasi... 0%</p>
        <p class="text-[10px] text-gray-300 mt-8 absolute bottom-10">v1.9.3.2 &bull; Enterprise Edition</p>
    </div>

    <div id="app" v-cloak class="flex h-full w-full">
        <!-- LEFT: Product Grid -->
        <div class="flex-1 flex flex-col h-full relative overflow-hidden">
            <!-- Header -->
            <div class="h-16 bg-white border-b flex items-center justify-between px-4 shrink-0 z-20 gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <img v-if="config.logo" :src="config.logo" class="h-10 w-auto object-contain">
                    <h1 v-else class="font-extrabold text-xl text-theme tracking-tight truncate">{{ config.site_name }}</h1>
                </div>
                
                <div class="flex-1 max-w-lg hidden md:block">
                    <div class="relative">
                        <i class="ri-search-line absolute left-3 top-2.5 text-gray-400"></i>
                        <input v-model="search" type="text" placeholder="Cari atau Scan..." class="w-full pl-10 pr-4 py-2 bg-gray-100 rounded-lg focus:ring-2 ring-theme outline-none text-sm">
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button @click="openScanner" class="p-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200" title="Scan Barcode"><i class="ri-qr-scan-2-line text-xl"></i></button>
                    <button @click="openHistory" class="p-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200" title="Riwayat & Cicilan"><i class="ri-history-line text-xl"></i></button>
                    <button @click="openRegister" class="p-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200" title="Tutup Kasir"><i class="ri-safe-2-line text-xl"></i></button>
                    
                    <button @click="manageMode = !manageMode" :class="manageMode ? 'bg-orange-100 text-orange-600 ring-2 ring-orange-400' : 'hover:bg-gray-100 text-gray-500'" class="p-2 rounded-lg transition" title="Kelola Produk">
                        <i class="ri-edit-box-line text-xl"></i>
                    </button>
                    <button v-if="manageMode" @click="openProductModal()" class="flex items-center gap-2 px-3 py-2 bg-theme text-white rounded-lg font-bold text-sm shadow-md hover:opacity-90 transition">
                        <i class="ri-add-line"></i> <span class="hidden sm:inline">Produk</span>
                    </button>
                    
                    <button @click="sync" class="p-2 rounded-full hover:bg-gray-100 text-gray-500" title="Sync Data"><i :class="{'animate-spin': syncing}" class="ri-refresh-line text-xl"></i></button>
                    <button @click="showMobileCart=!showMobileCart" class="md:hidden p-2 rounded-full bg-theme text-white relative"><i class="ri-shopping-cart-2-fill text-xl"></i><span v-if="cartTotalQty>0" class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] px-1.5 rounded-full">{{cartTotalQty}}</span></button>
                </div>
            </div>

            <!-- Categories -->
            <div class="bg-white px-2 py-2 shadow-sm shrink-0 z-10 border-b overflow-x-auto no-scrollbar flex gap-2">
                <button @click="setCategory('all')" :class="curCat==='all'?'bg-theme text-white shadow-md':'bg-gray-50 text-gray-600 border'" class="whitespace-nowrap px-4 py-1.5 rounded-full text-xs font-bold transition">Semua</button>
                <button v-for="c in categories" :key="c.slug" @click="setCategory(c.slug)" :class="curCat===c.slug?'bg-theme text-white shadow-md':'bg-gray-50 text-gray-600 border'" class="whitespace-nowrap px-4 py-1.5 rounded-full text-xs font-bold transition">{{ c.name }}</button>
            </div>

            <!-- Manage Mode Warning -->
            <div v-if="manageMode" class="bg-orange-50 px-4 py-2 text-xs font-bold text-orange-600 flex justify-between items-center border-b border-orange-200">
                <span><i class="ri-alert-line mr-1"></i> Mode Kelola Produk Aktif. Klik produk untuk edit.</span>
                <button @click="manageMode=false" class="underline">Selesai</button>
            </div>

            <!-- Grid -->
            <div class="flex-1 overflow-y-auto bg-gray-50 p-3 pb-24 md:pb-5 custom-scrollbar relative">
                <div v-if="loading" class="flex justify-center pt-20 text-gray-400 text-sm">Sedang memuat data...</div>
                <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
                    <div v-for="p in products" :key="p.id" @click="handleProductClick(p)" 
                        class="prod-card bg-white rounded-xl shadow-sm border overflow-hidden relative group h-full flex flex-col"
                        :class="manageMode ? 'cursor-pointer hover:ring-2 ring-orange-400' : 'cursor-pointer hover:border-theme'">
                        
                        <div class="aspect-[4/3] bg-gray-100 relative flex items-center justify-center overflow-hidden">
                            <img v-if="p.image" :src="p.image" loading="lazy" class="w-full h-full object-cover">
                            <i v-else :class="getIcon(p)" class="text-5xl text-gray-300"></i>
                            
                            <!-- Stock Overlay -->
                            <div v-if="p.stock !== null && p.stock <= 0" class="absolute inset-0 bg-black/60 flex items-center justify-center text-white font-bold text-xs uppercase">Habis</div>
                            
                            <!-- Edit Overlay -->
                            <div v-if="manageMode" class="absolute inset-0 bg-white/80 flex flex-col items-center justify-center opacity-0 group-hover:opacity-100 transition duration-200">
                                <i class="ri-pencil-fill text-3xl text-orange-500 mb-1"></i>
                                <span class="text-xs font-bold text-orange-600">Edit Produk</span>
                            </div>
                        </div>

                        <div class="p-3 flex flex-col flex-1">
                            <h3 class="font-bold text-xs text-gray-800 line-clamp-2 leading-tight mb-auto">{{ p.name }}</h3>
                            <div class="mt-2 flex justify-between items-end">
                                <span class="text-theme font-black text-sm">{{ formatRupiah(p.price) }}</span>
                                <span class="text-[10px] text-gray-400 font-medium">/{{ p.unit || 'Pcs' }}</span>
                            </div>
                            <!-- Grosir Badge -->
                            <div v-if="p.wholesale && p.wholesale.length" class="mt-1 text-[10px] text-blue-600 font-bold bg-blue-50 px-1.5 py-0.5 rounded inline-block w-fit">
                                Grosir Tersedia
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT: Cart -->
        <div :class="showMobileCart?'translate-x-0':'translate-x-full md:translate-x-0'" class="fixed inset-0 z-40 md:static md:z-auto w-full md:w-[360px] bg-white shadow-2xl md:shadow-none md:border-l flex flex-col transition-transform duration-300">
            <div class="h-16 px-4 border-b flex items-center justify-between bg-white shrink-0">
                <h2 class="font-bold text-lg flex items-center gap-2"><i class="ri-shopping-basket-fill text-theme"></i> Keranjang</h2>
                <div class="flex gap-2">
                    <button v-if="cart.length" @click="clearCart" class="text-red-500 p-2 text-sm font-bold bg-red-50 rounded hover:bg-red-100">Reset</button>
                    <button class="md:hidden p-2" @click="showMobileCart=false"><i class="ri-close-large-line"></i></button>
                </div>
            </div>
            <div class="flex-1 overflow-y-auto p-3 bg-gray-50 space-y-2">
                <div v-if="!cart.length" class="text-center text-gray-400 mt-20 text-sm">Belum ada item</div>
                <div v-for="i in cart" :key="i.id" class="bg-white p-3 rounded-lg border shadow-sm flex gap-3 relative">
                    <div class="w-12 h-12 bg-gray-100 rounded flex items-center justify-center text-gray-400 shrink-0">
                        <img v-if="i.image" :src="i.image" class="w-full h-full object-cover rounded">
                        <i v-else :class="getIcon(i)" class="text-xl"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between"><h4 class="font-bold text-xs truncate">{{i.name}}</h4><button @click="removeItem(i)" class="text-gray-300 hover:text-red-500"><i class="ri-close-circle-fill"></i></button></div>
                        <div class="flex justify-between items-end mt-1">
                            <div class="flex flex-col">
                                <span class="text-xs text-gray-500 font-bold">{{ formatRupiah(getItemPrice(i)) }}</span>
                                <span v-if="isWholesaleApplied(i)" class="text-[9px] text-blue-500 font-bold">Harga Grosir!</span>
                            </div>
                            <div class="flex items-center border rounded bg-gray-50"><button @click="updateQty(i,-1)" class="w-6 h-6 font-bold hover:bg-gray-200">-</button><span class="w-8 text-center text-xs font-bold">{{i.qty}}</span><button @click="updateQty(i,1)" class="w-6 h-6 font-bold hover:bg-theme hover:text-white">+</button></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="p-4 border-t bg-white z-20 shadow-[0_-4px_10px_rgba(0,0,0,0.05)]">
                <div class="flex justify-between items-end mb-3"><span class="text-sm font-bold text-gray-500">Total</span><span class="text-2xl font-black text-theme">{{ formatRupiah(cartTotal) }}</span></div>
                <button @click="openPayment" :disabled="!cart.length" class="w-full py-3 bg-theme text-white rounded-lg font-bold shadow-lg hover:opacity-90 disabled:opacity-50">Bayar</button>
            </div>
        </div>

        <!-- PRODUCT MODAL -->
        <div v-if="showProductModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
            <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden animate-zoom-in">
                <div class="px-5 py-4 border-b flex justify-between items-center bg-gray-50">
                    <h3 class="font-bold text-lg">{{ editingProduct ? 'Edit Produk' : 'Produk Baru' }}</h3>
                    <button @click="closeProductModal" class="text-gray-400 hover:text-red-500"><i class="ri-close-fill text-2xl"></i></button>
                </div>
                <div class="p-5 max-h-[70vh] overflow-y-auto custom-scrollbar">
                    <div class="flex border-b mb-4">
                        <button @click="activeTab='info'" :class="activeTab==='info'?'border-b-2 border-theme text-theme':'text-gray-500'" class="flex-1 pb-2 text-sm font-bold">Info Produk</button>
                        <button @click="activeTab='grosir'" :class="activeTab==='grosir'?'border-b-2 border-theme text-theme':'text-gray-500'" class="flex-1 pb-2 text-sm font-bold">Harga Grosir</button>
                    </div>
                    <div v-if="activeTab==='info'" class="space-y-4">
                        <div class="flex gap-4 items-center">
                             <div class="w-20 h-20 bg-gray-100 rounded-lg flex items-center justify-center border overflow-hidden relative group cursor-pointer" @click="$refs.fileInput.click()">
                                <img v-if="form.image_preview" :src="form.image_preview" class="w-full h-full object-cover">
                                <i v-else class="ri-camera-fill text-2xl text-gray-400"></i>
                             </div>
                             <div class="flex-1">
                                <input type="file" ref="fileInput" @change="handleImageUpload" accept="image/*" class="hidden">
                                <button @click="$refs.fileInput.click()" class="text-xs bg-gray-100 px-3 py-1.5 rounded border hover:bg-gray-200">Upload Foto</button>
                             </div>
                        </div>
                        <div><label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nama</label><input v-model="form.name" type="text" class="w-full p-2 border rounded-lg focus:ring-2 ring-theme outline-none"></div>
                        <div class="grid grid-cols-2 gap-4">
                            <div><label class="block text-xs font-bold text-gray-500 uppercase mb-1">Hrg Modal</label><input v-model.number="form.cost_price" type="number" class="w-full p-2 border rounded-lg bg-yellow-50"></div>
                            <div><label class="block text-xs font-bold text-gray-500 uppercase mb-1">Hrg Jual</label><input v-model.number="form.price" type="number" class="w-full p-2 border rounded-lg font-bold"></div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div><label class="block text-xs font-bold text-gray-500 uppercase mb-1">Stok</label><input v-model="form.stock" type="number" class="w-full p-2 border rounded-lg"></div>
                            <div><label class="block text-xs font-bold text-gray-500 uppercase mb-1">Satuan</label><input v-model="form.unit" type="text" class="w-full p-2 border rounded-lg" placeholder="Pcs"></div>
                        </div>
                        <div><label class="block text-xs font-bold text-gray-500 uppercase mb-1">Kategori</label><input v-model="form.category" type="text" list="catList" class="w-full p-2 border rounded-lg"><datalist id="catList"><option v-for="c in categories" :value="c.name"></datalist></div>
                    </div>
                    <div v-if="activeTab==='grosir'" class="space-y-4">
                        <div class="bg-blue-50 p-3 rounded text-xs text-blue-700 mb-2">Contoh: Min Beli 10, Harga jadi 2800.</div>
                        <div v-for="(rule, idx) in form.wholesale" :key="idx" class="flex gap-2 items-end mb-2">
                            <div class="flex-1"><label class="text-[10px] font-bold">Min Qty</label><input v-model.number="rule.min" type="number" class="w-full p-2 border rounded"></div>
                            <div class="flex-1"><label class="text-[10px] font-bold">Harga Satuan</label><input v-model.number="rule.price" type="number" class="w-full p-2 border rounded font-bold"></div>
                            <button @click="form.wholesale.splice(idx,1)" class="p-2 text-red-500 bg-red-100 rounded mb-[1px]"><i class="ri-delete-bin-line"></i></button>
                        </div>
                        <button @click="form.wholesale.push({min:1, price:0})" class="w-full py-2 border border-dashed border-theme text-theme rounded font-bold text-xs">+ Tambah</button>
                    </div>
                </div>
                <div class="p-5 border-t bg-gray-50 flex justify-between gap-3">
                    <button v-if="editingProduct" @click="deleteProduct" class="px-4 py-2 bg-red-100 text-red-600 rounded-lg font-bold hover:bg-red-200">Hapus</button>
                    <div v-else></div>
                    <button @click="saveProduct" :disabled="saving" class="px-6 py-2 bg-theme text-white rounded-lg font-bold hover:opacity-90 flex items-center gap-2">
                        <span v-if="saving" class="spinner border-white border-2 w-4 h-4"></span> {{ saving ? 'Menyimpan...' : 'Simpan' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- PAYMENT MODAL -->
        <div v-if="showPaymentModal" class="fixed inset-0 z-50 flex items-end md:items-center justify-center bg-black/70 backdrop-blur-sm p-4 md:p-0">
            <div class="bg-white w-full md:w-[450px] rounded-t-2xl md:rounded-2xl shadow-2xl overflow-hidden animate-slide-up">
                <div v-if="paymentSuccess" class="p-8 text-center bg-white">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4 text-green-600"><i class="ri-check-line text-4xl"></i></div>
                    <h3 class="text-xl font-bold mb-1">Transaksi Berhasil!</h3>
                    <p class="text-gray-500 text-sm mb-6">Order #{{ lastOrder.number }}</p>
                    <div class="space-y-3">
                         <a :href="generateWALink(lastOrder)" target="_blank" class="block w-full py-3 bg-green-500 text-white rounded-xl font-bold shadow-lg hover:bg-green-600 flex justify-center items-center gap-2"><i class="ri-whatsapp-fill text-xl"></i> Kirim Struk WA</a>
                        <button @click="resetPayment" class="block w-full py-3 bg-gray-100 text-gray-700 rounded-xl font-bold">Transaksi Baru</button>
                    </div>
                </div>
                <div v-else>
                    <div class="p-5 border-b flex justify-between items-center bg-gray-50">
                        <h3 class="font-bold text-lg">Pembayaran</h3>
                        <button @click="showPaymentModal=false"><i class="ri-close-fill text-2xl"></i></button>
                    </div>
                    <div class="p-6 bg-white overflow-y-auto max-h-[70vh]">
                        <div class="text-center mb-6">
                            <div class="text-xs font-bold text-gray-400 uppercase tracking-widest">Total Tagihan</div>
                            <div class="text-4xl font-black text-theme mt-1">{{ formatRupiah(cartTotal) }}</div>
                        </div>
                        <div class="grid grid-cols-3 gap-2 mb-6">
                            <button @click="paymentMethod='cash'" :class="paymentMethod==='cash' ? 'border-theme bg-green-50 text-theme ring-2 ring-green-100' : 'bg-gray-50 text-gray-500'" class="p-3 rounded-xl border-2 font-bold text-xs flex flex-col items-center gap-1 transition"><i class="ri-money-dollar-circle-fill text-xl"></i> Tunai</button>
                            <button @click="paymentMethod='qris'" :class="paymentMethod==='qris' ? 'border-theme bg-green-50 text-theme ring-2 ring-green-100' : 'bg-gray-50 text-gray-500'" class="p-3 rounded-xl border-2 font-bold text-xs flex flex-col items-center gap-1 transition"><i class="ri-qr-code-line text-xl"></i> QRIS</button>
                            <button @click="paymentMethod='debt'" :class="paymentMethod==='debt' ? 'border-orange-500 bg-orange-50 text-orange-600 ring-2 ring-orange-100' : 'bg-gray-50 text-gray-500'" class="p-3 rounded-xl border-2 font-bold text-xs flex flex-col items-center gap-1 transition"><i class="ri-booklet-line text-xl"></i> Hutang</button>
                        </div>
                        <div v-if="paymentMethod==='cash'" class="animate-fade-in">
                            <label class="block text-xs font-bold text-gray-500 mb-1">Uang Diterima</label>
                            <input type="number" v-model.number="amountPaid" class="w-full p-4 border rounded-xl text-xl font-bold mb-4 focus:border-theme outline-none" placeholder="0">
                            <div class="flex justify-between p-4 bg-gray-50 rounded-xl font-bold border"><span class="text-gray-500">Kembalian</span><span class="text-xl" :class="changeAmount >= 0 ? 'text-green-600' : 'text-red-500'">{{ formatRupiah(Math.max(0, changeAmount)) }}</span></div>
                        </div>
                        <div v-else-if="paymentMethod==='qris'" class="text-center py-4 animate-fade-in bg-gray-50 rounded-xl border border-dashed">
                             <div v-if="config.qris" class="relative group cursor-zoom-in inline-block" @click="qrisZoom=true">
                                <img :src="config.qris" class="w-48 h-48 object-contain bg-white rounded-lg border p-2 mb-2">
                                <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 bg-black/30 rounded-lg text-white font-bold">Zoom</div>
                            </div>
                            <div v-else class="py-8 text-gray-400">QRIS belum diupload.</div>
                        </div>
                        <div v-else class="animate-fade-in space-y-3">
                            <div class="bg-orange-50 p-3 rounded-lg border border-orange-100 text-xs text-orange-700 font-medium">Pesanan akan berstatus "Hutang".</div>
                            <input type="text" v-model="debtForm.name" class="w-full p-3 border rounded-lg font-bold" placeholder="Nama Pelanggan">
                            <input type="tel" v-model="debtForm.phone" class="w-full p-3 border rounded-lg font-bold" placeholder="No. WhatsApp (628...)">
                        </div>
                    </div>
                    <div class="p-5 border-t bg-gray-50">
                        <button @click="processPayment" :disabled="isProcessing || (paymentMethod==='cash' && changeAmount < 0)" class="w-full py-3.5 bg-theme text-white rounded-xl font-bold shadow-lg hover:shadow-xl transition disabled:opacity-50 flex justify-center items-center gap-2">
                            <span v-if="isProcessing" class="spinner border-white border-2 w-5 h-5"></span> {{ isProcessing ? 'Memproses...' : 'Selesaikan' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- HISTORY MODAL -->
        <div v-if="showHistory" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
            <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden h-[80vh] flex flex-col animate-zoom-in">
                <div class="px-5 py-4 border-b flex justify-between items-center bg-gray-50"><h3 class="font-bold text-lg">Riwayat & Cicilan</h3><button @click="showHistory=false" class="text-gray-400 hover:text-red-500"><i class="ri-close-fill text-2xl"></i></button></div>
                <div class="flex-1 overflow-y-auto p-0 bg-gray-50">
                    <div v-if="loadingHistory" class="flex justify-center py-10"><div class="spinner border-gray-400"></div></div>
                    <table v-else class="w-full text-sm text-left">
                        <thead class="bg-gray-100 text-gray-500 uppercase text-xs"><tr><th class="p-4">Info Order</th><th class="p-4 text-right">Tagihan</th><th class="p-4 text-center">Status</th><th class="p-4">Aksi</th></tr></thead>
                        <tbody class="divide-y bg-white">
                            <tr v-for="o in historyOrders" :key="o.id">
                                <td class="p-4"><div class="font-bold text-theme">#{{o.number}}</div><div class="text-xs text-gray-500">{{o.date}}</div><div class="font-bold text-xs mt-1">{{o.customer}}</div></td>
                                <td class="p-4 text-right"><div class="font-bold">{{o.total_formatted}}</div><div v-if="o.status==='on-hold'" class="text-[10px] text-red-500">Sisa: {{ formatRupiah(o.debt_remaining) }}</div></td>
                                <td class="p-4 text-center"><span class="px-2 py-1 rounded text-[10px] font-bold uppercase" :class="o.status==='completed'?'bg-green-100 text-green-700':(o.status==='on-hold'?'bg-orange-100 text-orange-700':'bg-gray-100 text-gray-600')">{{o.status_label}}</span></td>
                                <td class="p-4"><button v-if="o.status==='on-hold'" @click="payDebt(o)" class="px-3 py-1 bg-blue-100 text-blue-600 rounded text-xs font-bold hover:bg-blue-200">Cicil</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- OTHER MODALS -->
        <div v-if="qrisZoom" class="fixed inset-0 z-[60] bg-black/90 flex items-center justify-center p-4" @click="qrisZoom=false"><img :src="config.qris" class="max-w-full max-h-full rounded-lg shadow-2xl"><button class="absolute top-4 right-4 text-white text-4xl"><i class="ri-close-line"></i></button></div>
        <div v-if="showScanner" class="fixed inset-0 z-[60] bg-black flex flex-col"><div class="p-4 flex justify-between items-center text-white bg-black/50 absolute top-0 w-full z-10"><h3 class="font-bold">Scan Barcode</h3><button @click="closeScanner" class="bg-white/20 p-2 rounded-full"><i class="ri-close-line text-2xl"></i></button></div><div id="qr-reader" class="flex-1 w-full h-full bg-black"></div></div>
        
        <!-- REGISTER MODAL -->
        <div v-if="showRegister" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
            <div class="bg-white w-full max-w-sm rounded-2xl shadow-2xl p-6 animate-zoom-in">
                <h3 class="text-lg font-bold mb-4">Laporan Tutup Kasir</h3>
                <div class="mb-4"><label class="block text-xs font-bold text-gray-500 mb-1">Hitung Uang Fisik (Laci)</label><input v-model.number="registerCash" type="number" class="w-full p-3 border rounded-lg font-bold text-xl"></div>
                <div v-if="registerResult" class="bg-gray-50 p-4 rounded-lg border mb-4 text-sm">
                    <div class="flex justify-between mb-1"><span>Sistem:</span> <span class="font-bold">{{ formatRupiah(registerResult.system) }}</span></div>
                    <div class="flex justify-between mb-1"><span>Fisik:</span> <span class="font-bold">{{ formatRupiah(registerCash) }}</span></div>
                    <div class="flex justify-between border-t pt-1 mt-1"><span>Selisih:</span> <span class="font-bold" :class="registerResult.diff >= 0 ? 'text-green-600' : 'text-red-500'">{{ formatRupiah(registerResult.diff) }}</span></div>
                </div>
                <button v-if="!registerResult" @click="closeRegister" class="w-full py-3 bg-theme text-white rounded-lg font-bold">Hitung Selisih</button>
                <button v-else @click="showRegister=false;registerResult=null" class="w-full py-3 bg-gray-200 text-gray-800 rounded-lg font-bold">Tutup</button>
            </div>
        </div>
    </div>

    <!-- ROBUST DATA INJECTION -->
    <script>
    <?php 
        global $kresuber_config;
        $json_config = wp_json_encode( $kresuber_config );
        if ( ! $json_config ) { $json_config = '{}'; } 
    ?>
    const wpData = { 
        api: '<?php echo esc_url_raw( rest_url( "kresuber-pos/v1" ) ); ?>', 
        nonce: '<?php echo wp_create_nonce( "wp_rest" ); ?>', 
        config: <?php echo $json_config; ?> 
    };
    </script>
    <script src="<?php echo KRESUBER_POS_PRO_URL; ?>assets/js/pos-app.js"></script>
</body>
</html>
