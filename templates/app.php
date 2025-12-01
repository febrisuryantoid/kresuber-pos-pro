<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kresuber POS Pro</title>
    <!-- Tailwind CSS (via CDN for portability) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Remix Icon -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo KRESUBER_POS_URL; ?>assets/css/pos-style.css">
</head>
<body class="bg-gray-100 overflow-hidden">
    
    <div id="app" v-cloak class="flex h-screen">
        
        <!-- SIDEBAR KIRI: KATEGORI & MENU -->
        <div class="w-20 bg-slate-800 flex flex-col items-center py-4 text-white z-20 shadow-lg">
            <div class="mb-8">
                <i class="ri-store-2-fill text-3xl text-green-400"></i>
            </div>
            
            <button @click="filterCategory('all')" :class="{'bg-green-600': currentCategory === 'all'}" class="w-12 h-12 rounded-xl mb-4 flex items-center justify-center hover:bg-slate-700 transition">
                <i class="ri-apps-fill text-xl"></i>
            </button>
            
            <!-- Simulasi Kategori Statis (Bisa didinamiskan via API) -->
            <div class="flex-1 w-full flex flex-col items-center overflow-y-auto">
                 <button class="w-12 h-12 rounded-xl mb-4 flex items-center justify-center hover:bg-slate-700 transition text-gray-400 cursor-not-allowed" title="Kategori (Demo)">
                    <i class="ri-t-shirt-fill text-xl"></i>
                </button>
            </div>

            <button onclick="window.location.href='/wp-admin'" class="w-12 h-12 rounded-xl mb-2 flex items-center justify-center hover:bg-red-600 transition mt-auto">
                <i class="ri-logout-box-line text-xl"></i>
            </button>
        </div>

        <!-- AREA UTAMA: PRODUK -->
        <div class="flex-1 flex flex-col relative">
            <!-- Header Pencarian -->
            <div class="h-16 bg-white border-b flex items-center px-6 justify-between shadow-sm z-10">
                <h1 class="font-bold text-xl text-slate-800">Kresuber POS</h1>
                <div class="relative w-1/3">
                    <i class="ri-search-line absolute left-3 top-3 text-gray-400"></i>
                    <input v-model="searchQuery" @input="debouncedSearch" type="text" placeholder="Cari produk (Scan Barcode)..." class="w-full pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 bg-gray-50">
                </div>
                <div class="flex items-center gap-3">
                    <div class="text-right mr-2">
                        <p class="text-sm font-bold text-slate-700"><?php echo wp_get_current_user()->display_name; ?></p>
                        <p class="text-xs text-green-600">Online</p>
                    </div>
                    <img src="<?php echo get_avatar_url(get_current_user_id()); ?>" class="w-10 h-10 rounded-full border-2 border-green-500">
                </div>
            </div>

            <!-- Grid Produk -->
            <div class="flex-1 overflow-y-auto p-6">
                <div v-if="loading" class="flex justify-center items-center h-full">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-green-600"></div>
                </div>

                <div v-else-if="products.length === 0" class="flex flex-col items-center justify-center h-full text-gray-400">
                    <i class="ri-inbox-line text-6xl mb-4"></i>
                    <p>Produk tidak ditemukan</p>
                </div>

                <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                    <div v-for="product in products" :key="product.id" @click="addToCart(product)" 
                         class="bg-white rounded-xl shadow-sm hover:shadow-md transition cursor-pointer overflow-hidden border border-transparent hover:border-green-400 group h-64 flex flex-col">
                        <div class="h-32 bg-gray-200 overflow-hidden relative">
                             <img :src="product.image" class="w-full h-full object-cover group-hover:scale-110 transition duration-500">
                             <div class="absolute top-2 right-2 bg-slate-800 text-white text-xs px-2 py-1 rounded opacity-80">
                                Stok: {{ product.stock }}
                             </div>
                        </div>
                        <div class="p-3 flex flex-col flex-1">
                            <h3 class="font-semibold text-gray-800 text-sm mb-1 line-clamp-2">{{ product.name }}</h3>
                            <div class="mt-auto">
                                <p class="text-green-600 font-bold">{{ formatPrice(product.price) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SIDEBAR KANAN: KERANJANG -->
        <div class="w-96 bg-white border-l shadow-2xl z-20 flex flex-col h-full">
            <div class="p-4 border-b bg-slate-50 flex justify-between items-center">
                <h2 class="font-bold text-lg text-slate-800"><i class="ri-shopping-cart-2-line mr-2"></i>Order Saat Ini</h2>
                <button @click="clearCart" class="text-red-500 hover:bg-red-50 p-2 rounded-full transition" title="Hapus Semua">
                    <i class="ri-delete-bin-line"></i>
                </button>
            </div>

            <!-- List Item -->
            <div class="flex-1 overflow-y-auto p-4 space-y-3">
                <div v-if="cart.length === 0" class="text-center text-gray-400 mt-20">
                    <i class="ri-shopping-basket-line text-5xl mb-2 block"></i>
                    <p>Keranjang Kosong</p>
                </div>

                <div v-for="(item, index) in cart" :key="index" class="flex items-center gap-3 bg-white p-2 rounded-lg border border-gray-100 shadow-sm">
                    <img :src="item.image" class="w-12 h-12 rounded object-cover bg-gray-100">
                    <div class="flex-1">
                        <h4 class="text-sm font-semibold text-gray-800 line-clamp-1">{{ item.name }}</h4>
                        <p class="text-xs text-green-600 font-medium">{{ formatPrice(item.price) }}</p>
                    </div>
                    <div class="flex items-center bg-gray-100 rounded-lg">
                        <button @click="decreaseQty(item)" class="px-2 py-1 hover:bg-gray-200 rounded-l text-gray-600">-</button>
                        <span class="px-2 text-sm font-bold w-6 text-center">{{ item.qty }}</span>
                        <button @click="increaseQty(item)" class="px-2 py-1 hover:bg-gray-200 rounded-r text-gray-600">+</button>
                    </div>
                </div>
            </div>

            <!-- Footer Total & Checkout -->
            <div class="p-5 bg-slate-50 border-t">
                <div class="flex justify-between mb-2 text-sm text-gray-600">
                    <span>Subtotal</span>
                    <span>{{ formatPrice(subTotal) }}</span>
                </div>
                <div class="flex justify-between mb-4 text-sm text-gray-600">
                    <span>Pajak (0%)</span> <!-- Placeholder -->
                    <span>Rp 0</span>
                </div>
                <div class="flex justify-between mb-6 text-xl font-bold text-slate-800 border-t pt-2 border-gray-200">
                    <span>Total Bayar</span>
                    <span>{{ formatPrice(subTotal) }}</span>
                </div>

                <div class="grid grid-cols-2 gap-2 mb-3">
                    <button @click="paymentMethod = 'cod'" :class="{'ring-2 ring-green-500 bg-green-50 border-green-500': paymentMethod === 'cod'}" class="border rounded p-2 text-center text-sm font-medium hover:bg-gray-50">
                        <i class="ri-money-dollar-circle-line block text-lg mb-1"></i> Tunai
                    </button>
                    <button @click="paymentMethod = 'bacs'" :class="{'ring-2 ring-green-500 bg-green-50 border-green-500': paymentMethod === 'bacs'}" class="border rounded p-2 text-center text-sm font-medium hover:bg-gray-50">
                        <i class="ri-bank-card-line block text-lg mb-1"></i> Transfer
                    </button>
                </div>

                <button @click="processCheckout" :disabled="cart.length === 0 || processing" 
                    class="w-full bg-slate-900 text-white py-4 rounded-xl font-bold text-lg hover:bg-slate-800 transition shadow-lg disabled:opacity-50 disabled:cursor-not-allowed flex justify-center items-center">
                    <span v-if="processing" class="animate-spin mr-2"><i class="ri-loader-4-line"></i></span>
                    <span v-else>Bayar Sekarang</span>
                </button>
            </div>
        </div>

    </div>

    <!-- Data Injection dari WordPress -->
    <script>
        const kresuberParams = {
            apiUrl: '<?php echo esc_url_raw( rest_url( "kresuber-pos/v1" ) ); ?>',
            nonce: '<?php echo wp_create_nonce( "wp_rest" ); ?>',
            currency: '<?php echo get_woocommerce_currency_symbol(); ?>'
        };
    </script>

    <!-- Vue.js 3 -->
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <!-- Axios -->
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <!-- App Logic -->
    <script src="<?php echo KRESUBER_POS_URL; ?>assets/js/pos-app.js"></script>
</body>
</html>