<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Kresuber POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <script src="https://unpkg.com/dexie/dist/dexie.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <link rel="stylesheet" href="<?php echo KRESUBER_POS_PRO_URL; ?>assets/css/pos-style.css">
</head>
<body class="bg-gray-100 h-screen w-screen overflow-hidden text-slate-800">
    <div id="app" v-cloak class="flex h-full w-full">
        <!-- Sidebar -->
        <div class="w-20 bg-slate-900 flex flex-col items-center py-4 z-50 text-white flex-shrink-0">
            <div class="mb-6 text-green-400"><i class="ri-store-3-fill text-3xl"></i></div>
            <button @click="syncProducts" :class="{'animate-spin': syncing}" class="w-12 h-12 rounded-xl mb-4 bg-slate-800 hover:bg-slate-700 text-green-400 flex items-center justify-center"><i class="ri-refresh-line text-xl"></i></button>
            <div class="flex-1 w-full flex flex-col items-center gap-2 overflow-y-auto no-scrollbar">
                <button @click="currentCategory='all'" :class="currentCategory==='all'?'bg-green-600':'bg-slate-800'" class="w-12 h-12 rounded-xl flex items-center justify-center"><i class="ri-apps-2-line"></i></button>
                <button v-for="c in categories" :key="c.slug" @click="currentCategory=c.slug" :class="currentCategory===c.slug?'bg-green-600':'bg-slate-800'" class="w-12 h-12 rounded-xl text-[10px] font-bold p-1 text-center flex items-center justify-center">{{ c.name.substring(0,4) }}</button>
            </div>
            <a href="<?php echo admin_url(); ?>" class="mt-4 w-12 h-12 bg-red-600/20 text-red-500 hover:bg-red-600 hover:text-white rounded-xl flex items-center justify-center"><i class="ri-logout-box-line text-xl"></i></a>
        </div>

        <!-- Main -->
        <div class="flex-1 flex flex-col bg-slate-50 min-w-0">
            <div class="h-16 bg-white border-b px-6 flex justify-between items-center z-30">
                <div class="relative w-full max-w-xl group">
                    <i class="ri-barcode-line absolute left-3 top-2.5 text-gray-400"></i>
                    <input v-model="searchQuery" type="text" placeholder="Scan Barcode / Cari (F3)" class="w-full pl-10 pr-4 py-2 bg-gray-100 rounded-lg focus:bg-white focus:ring-2 focus:ring-green-500 outline-none">
                </div>
                <div class="flex items-center gap-3">
                    <div class="text-right hidden md:block">
                        <span class="block text-xs font-bold text-gray-500">KASIR</span>
                        <span class="block text-sm font-bold"><?php echo wp_get_current_user()->display_name; ?></span>
                    </div>
                    <img src="<?php echo get_avatar_url(get_current_user_id()); ?>" class="w-10 h-10 rounded-full border">
                </div>
            </div>

            <div class="flex-1 overflow-y-auto p-6 custom-scrollbar">
                <div v-if="loading" class="flex justify-center pt-20"><i class="ri-loader-4-line animate-spin text-3xl text-green-600"></i></div>
                <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                    <div v-for="p in products" :key="p.id" @click="addToCart(p)" class="bg-white rounded-xl shadow-sm hover:shadow-md cursor-pointer overflow-hidden flex flex-col border border-transparent hover:border-green-500 transition h-60 product-card">
                        <div class="h-32 bg-gray-200 relative">
                            <img :src="p.image" loading="lazy" class="w-full h-full object-cover">
                            <span class="absolute top-1 right-1 bg-black/70 text-white text-[10px] px-1.5 rounded">{{ p.stock }}</span>
                        </div>
                        <div class="p-3 flex flex-col flex-1 justify-between">
                            <h3 class="text-xs font-bold text-slate-800 line-clamp-2">{{ p.name }}</h3>
                            <div class="flex justify-between items-end">
                                <span class="text-green-600 font-bold">{{ formatPrice(p.price) }}</span>
                                <i class="ri-add-circle-fill text-2xl text-slate-200 hover:text-green-500 transition"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cart -->
        <div class="w-[400px] bg-white border-l shadow-xl z-40 flex flex-col flex-shrink-0">
            <div class="px-5 py-4 border-b flex justify-between items-center bg-white">
                <h2 class="font-bold text-lg">Keranjang</h2>
                <div class="flex gap-2">
                    <button @click="toggleHold" class="p-2 border rounded hover:bg-orange-50 text-orange-500 relative" title="Hold"><i class="ri-pause-circle-line"></i><span v-if="heldItems.length" class="absolute top-0 right-0 w-2 h-2 bg-red-500 rounded-full"></span></button>
                    <button @click="clearCart" class="p-2 border rounded hover:bg-red-50 text-red-500"><i class="ri-delete-bin-line"></i></button>
                </div>
            </div>
            
            <div class="flex-1 overflow-y-auto p-4 bg-slate-50 custom-scrollbar">
                <div v-if="cart.length===0" class="text-center text-gray-400 mt-10">Kosong</div>
                <div v-for="i in cart" :key="i.id" class="flex gap-3 mb-3 bg-white p-2 rounded shadow-sm">
                    <img :src="i.image" class="w-12 h-12 rounded bg-gray-200 object-cover">
                    <div class="flex-1">
                        <div class="flex justify-between"><span class="text-xs font-bold line-clamp-1">{{ i.name }}</span> <button @click="removeFromCart(i)" class="text-gray-400 hover:text-red-500"><i class="ri-close-circle-fill"></i></button></div>
                        <div class="flex justify-between items-end mt-1">
                            <span class="text-xs text-gray-500">{{ formatPrice(i.price) }}</span>
                            <div class="flex items-center bg-gray-100 rounded"><button @click="updateQty(i,-1)" class="px-2 font-bold">-</button><span class="text-xs font-bold w-6 text-center">{{ i.qty }}</span><button @click="updateQty(i,1)" class="px-2 font-bold">+</button></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-5 border-t bg-white shadow-[0_-5px_10px_rgba(0,0,0,0.05)]">
                <div class="flex justify-between text-sm mb-1"><span>Subtotal</span> <span>{{ formatPrice(subTotal) }}</span></div>
                <div class="flex justify-between text-sm mb-3"><span>Pajak ({{ taxRate }}%)</span> <span>{{ formatPrice(taxAmount) }}</span></div>
                <div class="flex justify-between text-xl font-bold mb-4"><span>Total</span> <span>{{ formatPrice(grandTotal) }}</span></div>
                <button @click="showPayModal=true" :disabled="cart.length===0" class="w-full bg-slate-900 text-white py-3 rounded-lg font-bold hover:bg-slate-800 disabled:opacity-50 flex justify-center items-center gap-2"><i class="ri-wallet-3-line"></i> Bayar</button>
            </div>
        </div>

        <!-- Pay Modal -->
        <div v-if="showPayModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 backdrop-blur-sm">
            <div class="bg-white w-[400px] rounded-xl shadow-2xl overflow-hidden p-6">
                <div class="flex justify-between mb-6"><h3 class="font-bold text-lg">Pembayaran</h3> <button @click="showPayModal=false"><i class="ri-close-line text-xl"></i></button></div>
                <div class="text-center mb-6"><div class="text-xs text-gray-500">TOTAL TAGIHAN</div><div class="text-4xl font-extrabold">{{ formatPrice(grandTotal) }}</div></div>
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <button @click="paymentMethod='cash'" :class="paymentMethod==='cash'?'ring-2 ring-green-500 bg-green-50':''" class="border p-3 rounded font-bold text-center">Tunai</button>
                    <button @click="paymentMethod='qris'" :class="paymentMethod==='qris'?'ring-2 ring-blue-500 bg-blue-50':''" class="border p-3 rounded font-bold text-center">QRIS</button>
                </div>
                <div v-if="paymentMethod==='cash'" class="mb-4">
                    <input type="number" v-model="cashReceived" ref="cashInput" class="w-full border p-3 rounded text-xl font-bold mb-2" placeholder="Uang diterima...">
                    <div class="flex gap-2 mb-2 overflow-x-auto"><button v-for="q in quickCashOptions" :key="q" @click="cashReceived=q" class="px-2 py-1 bg-gray-100 rounded text-xs font-bold">{{ formatNumber(q) }}</button></div>
                    <div class="flex justify-between font-bold" :class="cashChange>=0?'text-green-600':'text-red-500'"><span>Kembali:</span> <span>{{ formatPrice(Math.max(0,cashChange)) }}</span></div>
                </div>
                <button @click="processCheckout" :disabled="processing || (paymentMethod==='cash' && cashChange<0)" class="w-full bg-green-600 text-white py-3 rounded font-bold disabled:opacity-50 hover:bg-green-700">Selesaikan</button>
            </div>
        </div>

        <!-- Receipt -->
        <div id="receipt-print" class="hidden">
            <div style="width:58mm;text-align:center;font-family:monospace;font-size:11px;line-height:1.2;">
                <h3 style="margin:0"><?php echo get_bloginfo('name'); ?></h3>
                <p style="margin:0;font-size:10px">Struk Pembelian</p>
                <hr>
                <div style="text-align:left">No: #{{ lastReceipt.orderNumber }}<br>Tgl: {{ lastReceipt.date }}<br>Kasir: {{ lastReceipt.cashier }}</div>
                <hr>
                <table style="width:100%;text-align:left">
                    <tr v-for="i in lastReceipt.items"><td>{{ i.name }}<br>{{ i.qty }}x {{ formatNumber(i.price) }}</td><td style="text-align:right;vertical-align:top">{{ formatNumber(i.qty*i.price) }}</td></tr>
                </table>
                <hr>
                <div style="display:flex;justify-content:space-between"><span>TOTAL</span> <b>{{ formatNumber(lastReceipt.grandTotal) }}</b></div>
                <div v-if="lastReceipt.paymentMethod==='cash'">
                    <div style="display:flex;justify-content:space-between"><span>Tunai</span> <span>{{ formatNumber(lastReceipt.cashReceived) }}</span></div>
                    <div style="display:flex;justify-content:space-between"><span>Kembali</span> <span>{{ formatNumber(lastReceipt.cashChange) }}</span></div>
                </div>
                <div v-else style="text-align:center;margin-top:5px">[Non-Tunai]</div>
                <hr><div style="text-align:center;font-size:10px">Terima Kasih!</div>
            </div>
        </div>
    </div>

    <script>
        const kresuberParams = {
            apiUrl: '<?php echo esc_url_raw( rest_url( "kresuber-pos/v1" ) ); ?>',
            nonce: '<?php echo wp_create_nonce( "wp_rest" ); ?>',
            currencySymbol: '<?php echo get_woocommerce_currency_symbol(); ?>',
            taxRate: 11,
            cashierName: '<?php echo wp_get_current_user()->display_name; ?>'
        };
    </script>
    <script src="<?php echo KRESUBER_POS_PRO_URL; ?>assets/js/pos-app.js"></script>
</body>
</html>