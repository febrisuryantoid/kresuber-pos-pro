const { createApp, ref, computed, onMounted, nextTick } = Vue;

// --- 1. INISIALISASI DEXIE DB (IndexedDB Wrapper) ---
// Ini yang bikin aplikasi cepat & offline-first seperti WCPOS
const db = new Dexie("KresuberPOS_DB");
db.version(1).stores({
    products: "id, sku, barcode, category_slug, search_text" // Indexing fields
});

createApp({
    setup() {
        // --- State ---
        const products = ref([]);
        const categories = ref([]);
        const cart = ref([]);
        const heldItems = ref([]);
        const currentCategory = ref('all');
        const searchQuery = ref('');
        const loading = ref(false);
        const syncing = ref(false);
        const dbReady = ref(false);
        const productCount = ref(0);
        
        // Payment
        const showPayModal = ref(false);
        const paymentMethod = ref('cash');
        const cashReceived = ref('');
        const processing = ref(false);
        const cashInput = ref(null);
        
        // Receipt Data
        const lastReceipt = ref({});
        
        // Config
        const taxRate = kresuberParams.taxRate;

        // --- Computed ---
        const subTotal = computed(() => cart.value.reduce((sum, item) => sum + (item.price * item.qty), 0));
        const taxAmount = computed(() => Math.round(subTotal.value * (taxRate / 100)));
        const grandTotal = computed(() => subTotal.value + taxAmount.value);
        const cashChange = computed(() => (cashReceived.value || 0) - grandTotal.value);
        
        const quickCash = computed(() => {
            const total = grandTotal.value;
            return [50000, 100000].filter(amt => amt > total);
        });

        // --- Methods ---
        
        const formatPrice = (val) => kresuberParams.currencySymbol + ' ' + new Intl.NumberFormat('id-ID').format(val);
        const formatNumber = (val) => new Intl.NumberFormat('id-ID').format(val);

        // 1. SYNC DATA DARI WP API KE DEXIE
        const syncProducts = async () => {
            syncing.value = true;
            loading.value = true;
            try {
                // Ambil semua data
                const response = await axios.get(`${kresuberParams.apiUrl}/products`, {
                    headers: { 'X-WP-Nonce': kresuberParams.nonce }
                });
                
                // Siapkan data untuk indexing
                const items = response.data.map(p => ({
                    ...p,
                    // Buat field gabungan untuk search super cepat
                    search_text: `${p.name} ${p.sku} ${p.barcode || ''}`.toLowerCase() 
                }));

                // Extract Categories (Unik)
                const cats = new Set(items.map(i => i.category_slug));
                categories.value = Array.from(cats).map(slug => ({
                    slug, 
                    name: slug === 'uncategorized' ? 'Lainnya' : slug.replace(/-/g, ' ').toUpperCase()
                }));

                // Simpan ke Dexie
                await db.products.clear();
                await db.products.bulkAdd(items);
                
                productCount.value = await db.products.count();
                dbReady.value = true;
                
                // Load ulang tampilan
                searchLocal(); 
                
            } catch (error) {
                console.error("Sync Error", error);
                alert("Gagal sinkronisasi. Cek koneksi internet.");
            } finally {
                syncing.value = false;
                loading.value = false;
            }
        };

        // 2. SEARCH LOKAL (Instant)
        const searchLocal = async () => {
            let collection = db.products.toCollection();

            // Filter Kategori
            if (currentCategory.value !== 'all') {
                collection = db.products.where('category_slug').equals(currentCategory.value);
            }

            const query = searchQuery.value.toLowerCase().trim();
            
            if (query) {
                // LOGIKA SCANNER BARCODE PRIORITY
                // Jika input cocok persis dengan SKU atau Barcode -> Langsung Add to Cart
                const exactMatch = await db.products.where('sku').equals(query)
                    .or('barcode').equals(query).first();

                if (exactMatch) {
                    addToCart(exactMatch);
                    searchQuery.value = ''; // Reset search setelah scan
                    return;
                }

                // Jika bukan barcode exact, lakukan search text fuzzy
                // Menggunakan filter JS di memori karena 'contains' Dexie terbatas
                const allInCat = await collection.toArray();
                products.value = allInCat.filter(p => p.search_text.includes(query)).slice(0, 50);
            } else {
                // Default view (limit 50 biar ringan)
                products.value = await collection.limit(50).toArray();
            }
        };

        const clearSearch = () => {
            searchQuery.value = '';
            searchLocal();
        };

        const filterCategory = (slug) => {
            currentCategory.value = slug;
            searchLocal();
        };

        // 3. CART ACTIONS
        const addToCart = (product) => {
            const existing = cart.value.find(i => i.id === product.id);
            if (existing) {
                existing.qty++;
            } else {
                cart.value.push({ ...product, qty: 1 });
            }
            // Play Sound effect (Optional)
            // new Audio(beepUrl).play(); 
        };

        const removeFromCart = (item) => {
            cart.value = cart.value.filter(i => i.id !== item.id);
        };

        const increaseQty = (item) => item.qty++;
        
        const decreaseQty = (item) => {
            if (item.qty > 1) item.qty--;
            else removeFromCart(item);
        };

        const clearCart = () => {
            if (confirm("Kosongkan keranjang?")) cart.value = [];
        };

        // 4. HOLD ORDER (Fitur Pro)
        const toggleHold = () => {
            if (heldItems.value.length > 0) {
                // Restore
                if (cart.value.length > 0 && !confirm("Timpah keranjang saat ini dengan order yang di-hold?")) return;
                cart.value = [...heldItems.value];
                heldItems.value = [];
            } else {
                // Hold
                if (cart.value.length === 0) return;
                heldItems.value = [...cart.value];
                cart.value = [];
                // Simpan ke LocalStorage agar persist kalau refresh
                localStorage.setItem('pos_held_items', JSON.stringify(heldItems.value));
            }
        };

        // 5. CHECKOUT & PRINT
        const openPayModal = () => {
            showPayModal.value = true;
            nextTick(() => {
                if (paymentMethod.value === 'cash' && cashInput.value) {
                    cashInput.value.focus();
                }
            });
        };

        const processCheckout = async () => {
            processing.value = true;
            try {
                // Kirim ke WP API
                const orderData = {
                    items: cart.value,
                    payment_method: paymentMethod.value,
                    amount_tendered: paymentMethod.value === 'cash' ? cashReceived.value : grandTotal.value,
                    change: paymentMethod.value === 'cash' ? Math.max(0, cashChange.value) : 0
                };

                const response = await axios.post(`${kresuberParams.apiUrl}/order`, orderData, {
                    headers: { 'X-WP-Nonce': kresuberParams.nonce }
                });

                if (response.data.success) {
                    // Siapkan Data Struk
                    lastReceipt.value = {
                        orderNumber: response.data.order_number,
                        date: new Date().toLocaleString('id-ID'),
                        cashier: kresuberParams.cashierName,
                        items: [...cart.value],
                        subTotal: subTotal.value,
                        taxAmount: taxAmount.value,
                        grandTotal: grandTotal.value,
                        paymentMethod: paymentMethod.value,
                        cashReceived: cashReceived.value,
                        cashChange: Math.max(0, cashChange.value)
                    };

                    // Print Struk
                    printReceipt();

                    // Reset
                    cart.value = [];
                    cashReceived.value = '';
                    showPayModal.value = false;
                }

            } catch (error) {
                alert("Gagal memproses transaksi: " + error.message);
            } finally {
                processing.value = false;
            }
        };

        const printReceipt = () => {
            setTimeout(() => {
                const content = document.getElementById('receipt-print').innerHTML;
                const win = window.open('', '', 'width=300,height=600');
                win.document.write('<html><head><title>Print Struk</title></head><body style="margin:0;">');
                win.document.write(content);
                win.document.write('</body></html>');
                win.document.close();
                win.focus();
                win.print();
                // win.close(); // Uncomment untuk auto close
            }, 300);
        };

        // --- Lifecycle ---
        onMounted(async () => {
            // Restore held items
            const savedHold = localStorage.getItem('pos_held_items');
            if (savedHold) heldItems.value = JSON.parse(savedHold);

            // Cek Database Lokal
            productCount.value = await db.products.count();
            if (productCount.value === 0) {
                await syncProducts();
            } else {
                dbReady.value = true;
                searchLocal();
            }

            // Global Keyboard Listener untuk Scanner & F3
            window.addEventListener('keydown', (e) => {
                if (e.key === 'F3') {
                    e.preventDefault();
                    // Fokus ke search bar
                    const input = document.querySelector('input[type="text"]');
                    if(input) input.focus();
                }
            });
        });

        return {
            products, categories, cart, heldItems, currentCategory, searchQuery, 
            loading, syncing, dbReady, productCount,
            syncProducts, searchLocal, clearSearch, filterCategory,
            addToCart, removeFromCart, increaseQty, decreaseQty, clearCart, toggleHold,
            
            showPayModal, openPayModal, paymentMethod, cashReceived, processing, cashInput, quickCash,
            subTotal, taxAmount, grandTotal, cashChange, taxRate,
            processCheckout, lastReceipt, formatPrice, formatNumber
        };
    }
}).mount('#app');