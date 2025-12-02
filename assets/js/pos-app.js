const { createApp, ref, computed, onMounted, nextTick, watch } = Vue;

// Database Lokal (Dexie)
const db = new Dexie("KresuberDB_v1_8");
db.version(1).stores({ 
    products: "id, sku, barcode, category_slug, search_terms" 
});

createApp({
    setup() {
        // --- State ---
        const config = ref(wpData.config || {});
        const products = ref([]);
        const categories = ref([]);
        const cart = ref([]);
        
        // UI State
        const loading = ref(true);
        const loadingText = ref("Menyiapkan Aplikasi..."); // New UX
        const loadingProgress = ref(0); // New UX (0-100)
        const syncing = ref(false);
        const search = ref("");
        const curCat = ref("all");
        const showMobileCart = ref(false);
        const showPaymentModal = ref(false);
        const isProcessing = ref(false);
        
        // Payment State
        const paymentMethod = ref('cash');
        const amountPaid = ref('');
        const cashInputRef = ref(null);

        // --- Computed Logic ---
        const cartTotal = computed(() => {
            return cart.value.reduce((acc, item) => {
                const price = parseFloat(item.price) || 0;
                const qty = parseInt(item.qty) || 1;
                return acc + (price * qty);
            }, 0);
        });

        const changeAmount = computed(() => {
            const paid = parseFloat(amountPaid.value) || 0;
            return paid - cartTotal.value;
        });

        const quickCashAmounts = computed(() => {
            const total = cartTotal.value;
            const suggestions = [10000, 20000, 50000, 100000];
            return suggestions.filter(amt => amt >= total).slice(0, 3);
        });

        const cartTotalQty = computed(() => cart.value.reduce((acc, i) => acc + (parseInt(i.qty)||0), 0));

        // --- Helpers ---
        const formatRupiah = (val) => {
            const num = parseFloat(val);
            if(isNaN(num)) return "Rp 0";
            return "Rp " + new Intl.NumberFormat('id-ID').format(num);
        };

        // --- Core Functions ---

        const forceSync = async () => {
            if(confirm('Unduh ulang semua data produk dari server?')) {
                await sync();
            }
        };

        const sync = async () => {
            syncing.value = true;
            loading.value = true;
            loadingProgress.value = 5;
            loadingText.value = "Menghubungkan ke Server...";

            try {
                // Fetch dari API dengan Progress Monitor
                const res = await axios.get(`${wpData.api}/products`, {
                    headers: { 'X-WP-Nonce': wpData.nonce },
                    onDownloadProgress: (progressEvent) => {
                        const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                        // Progress 5% -> 80% (Download Phase)
                        loadingProgress.value = 5 + (percentCompleted * 0.75); 
                        loadingText.value = `Mengunduh Data Produk (${percentCompleted}%)...`;
                    }
                });

                loadingProgress.value = 80;
                loadingText.value = "Memproses Database Lokal...";
                
                // Beri sedikit jeda agar UI sempat render text baru
                await new Promise(r => setTimeout(r, 100));

                const rawData = res.data;
                const cleanData = [];
                const catMap = {};

                // Proses Data & Simpan Kategori
                rawData.forEach(p => {
                    const price = parseFloat(p.price) || 0;
                    if(p.category_slug && p.category_slug !== 'uncategorized') {
                        catMap[p.category_slug] = { slug: p.category_slug, name: p.category_name };
                    }
                    cleanData.push({
                        ...p,
                        price: price,
                        search_terms: `${p.name} ${p.sku} ${p.barcode}`.toLowerCase()
                    });
                });

                // Simpan ke DB
                await db.products.clear();
                loadingProgress.value = 90;
                loadingText.value = "Menyimpan Produk...";
                
                await db.products.bulkAdd(cleanData);
                
                // Set Kategori di Memory
                categories.value = Object.values(catMap);
                
                loadingProgress.value = 100;
                loadingText.value = "Selesai!";
                
                // Jeda sebentar di 100% sebelum hilang
                await new Promise(r => setTimeout(r, 500));
                
                // Refresh View
                await findProducts();
                
            } catch (err) {
                console.error(err);
                alert("Gagal sinkronisasi data: " + err.message);
            } finally {
                syncing.value = false;
                loading.value = false;
            }
        };

        const findProducts = async () => {
            let collection = db.products.toCollection();
            
            if (curCat.value !== 'all') {
                collection = db.products.where('category_slug').equals(curCat.value);
            }

            const q = search.value.toLowerCase().trim();
            if (q) {
                const exact = await db.products.where('sku').equals(q).or('barcode').equals(q).first();
                if (exact) {
                    add(exact);
                    search.value = "";
                    return; 
                }
                const all = await collection.toArray();
                products.value = all.filter(p => p.search_terms.includes(q));
            } else {
                products.value = await collection.limit(100).toArray();
                
                if (categories.value.length === 0) {
                    const allP = await db.products.toArray();
                    const cMap = {};
                    allP.forEach(p => {
                        if(p.category_slug && p.category_slug !== 'uncategorized') 
                             cMap[p.category_slug] = {slug:p.category_slug, name:p.category_name};
                    });
                    categories.value = Object.values(cMap);
                }
            }
        };

        const setCategory = (slug) => {
            curCat.value = slug;
            findProducts();
        };

        // --- Cart Functions ---
        const add = (product) => {
            if(product.stock_status === 'outofstock') {
                alert("Stok Habis!");
                return;
            }
            const existing = cart.value.find(i => i.id === product.id);
            if (existing) {
                existing.qty++;
            } else {
                cart.value.push({
                    id: product.id,
                    name: product.name,
                    price: parseFloat(product.price),
                    image: product.image,
                    qty: 1
                });
            }
        };

        const updateQty = (item, delta) => {
            item.qty += delta;
            if (item.qty <= 0) removeItem(item);
        };

        const removeItem = (item) => {
            cart.value = cart.value.filter(i => i.id !== item.id);
        };

        const clearCart = () => {
            if(confirm('Kosongkan keranjang?')) cart.value = [];
        };

        // --- Payment Functions ---
        const openPayment = () => {
            showPaymentModal.value = true;
            paymentMethod.value = 'cash';
            amountPaid.value = '';
        };

        const processPayment = async () => {
            isProcessing.value = true;
            try {
                const payload = {
                    items: cart.value.map(i => ({ id: i.id, qty: i.qty })),
                    payment_method: paymentMethod.value,
                    amount_tendered: paymentMethod.value === 'cash' ? amountPaid.value : cartTotal.value,
                    change: paymentMethod.value === 'cash' ? changeAmount.value : 0
                };

                const res = await axios.post(`${wpData.api}/order`, payload, {
                    headers: { 'X-WP-Nonce': wpData.nonce }
                });

                if (res.data.success) {
                    alert(`Transaksi Berhasil!\nNo Order: #${res.data.order_number}\nTotal: ${formatRupiah(res.data.total)}`);
                    cart.value = [];
                    showPaymentModal.value = false;
                    showMobileCart.value = false;
                }

            } catch (err) {
                alert("Gagal memproses transaksi: " + (err.response?.data?.message || err.message));
            } finally {
                isProcessing.value = false;
            }
        };

        // --- Lifecycle ---
        watch([search, curCat], findProducts);
        watch(showPaymentModal, (val) => {
            if(val && paymentMethod.value === 'cash') {
                nextTick(() => cashInputRef.value?.focus());
            }
        });

        onMounted(async () => {
            const count = await db.products.count();
            if (count === 0) {
                await sync();
            } else {
                loadingProgress.value = 100;
                // Simulasi loading sebentar agar transisi tidak kasar
                setTimeout(() => { loading.value = false; }, 500);
                await findProducts();
            }
            
            window.addEventListener('keydown', e => {
                if(e.key === 'F3') { e.preventDefault(); document.querySelector('input[type=text]')?.focus(); }
            });
        });

        return {
            config, products, categories, cart, 
            loading, loadingText, loadingProgress, syncing, search, curCat, showMobileCart, showPaymentModal, isProcessing,
            paymentMethod, amountPaid, cashInputRef,
            // Computed
            cartTotal, changeAmount, quickCashAmounts, cartTotalQty,
            // Methods
            formatRupiah, forceSync, setCategory, add, updateQty, removeItem, clearCart,
            openPayment, processPayment
        };
    }
}).mount('#app');