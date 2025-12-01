const { createApp, ref, computed, onMounted, nextTick, watch } = Vue;

const db = new Dexie("KresuberPOS_DB");
db.version(1).stores({ products: "id, sku, barcode, category_slug, search_text" });

createApp({
    setup() {
        // State
        const viewMode = ref('pos'); // 'pos' or 'orders'
        const products = ref([]);
        const categories = ref([]);
        const cart = ref([]);
        const heldItems = ref([]);
        const recentOrders = ref([]); // New
        
        const currentCategory = ref('all');
        const searchQuery = ref('');
        const loading = ref(false);
        const syncing = ref(false);
        const productCount = ref(0);
        
        // Payment
        const showPayModal = ref(false);
        const paymentMethod = ref('cash');
        const cashReceived = ref('');
        const processing = ref(false);
        const cashInput = ref(null);
        const lastReceipt = ref({});
        
        // Config
        const taxRate = kresuberParams.taxRate || 0;
        const qrisUrl = kresuberParams.qrisUrl;

        // Computed
        const subTotal = computed(() => cart.value.reduce((sum, i) => sum + (i.price * i.qty), 0));
        const taxAmount = computed(() => Math.round(subTotal.value * (taxRate / 100)));
        const grandTotal = computed(() => subTotal.value + taxAmount.value);
        const cashChange = computed(() => (parseInt(cashReceived.value) || 0) - grandTotal.value);
        const quickCashOptions = computed(() => [10000, 20000, 50000, 100000].filter(a => a >= grandTotal.value).slice(0, 3));

        // Formatters
        const formatPrice = (v) => kresuberParams.currencySymbol + ' ' + new Intl.NumberFormat('id-ID').format(v);
        const formatNumber = (v) => new Intl.NumberFormat('id-ID').format(v);

        // --- FETCH ORDERS (New) ---
        const fetchOrders = async () => {
            loading.value = true;
            try {
                const res = await axios.get(`${kresuberParams.apiUrl}/orders`, { headers: { 'X-WP-Nonce': kresuberParams.nonce } });
                recentOrders.value = res.data;
            } catch (e) { alert("Gagal mengambil order."); }
            finally { loading.value = false; }
        };

        // --- CORE POS LOGIC ---
        const syncProducts = async () => {
            syncing.value = true; loading.value = true;
            try {
                const res = await axios.get(`${kresuberParams.apiUrl}/products`, { headers: { 'X-WP-Nonce': kresuberParams.nonce } });
                const items = res.data.map(p => ({ ...p, search_text: `${p.name} ${p.sku} ${p.barcode||''}`.toLowerCase() }));
                
                const cats = {};
                items.forEach(i => cats[i.category_slug] = { slug: i.category_slug, name: i.category_name });
                categories.value = Object.values(cats);

                await db.products.clear();
                await db.products.bulkAdd(items);
                productCount.value = await db.products.count();
                searchLocal();
            } catch (e) { alert("Sync Error: " + e.message); }
            finally { syncing.value = false; loading.value = false; }
        };

        const searchLocal = async () => {
            let col = db.products.toCollection();
            if (currentCategory.value !== 'all') col = db.products.where('category_slug').equals(currentCategory.value);

            const q = searchQuery.value.toLowerCase().trim();
            if (q) {
                const exact = await db.products.where('sku').equals(q).or('barcode').equals(q).first();
                if (exact) { addToCart(exact); searchQuery.value = ''; return; }
                const all = await col.toArray();
                products.value = all.filter(p => p.search_text.includes(q)).slice(0, 50);
            } else {
                products.value = await col.limit(60).toArray();
            }
        };

        const setCategory = (slug) => {
            currentCategory.value = slug;
            searchLocal();
        };

        // --- CART ---
        const addToCart = (p) => {
            if(p.stock_status === 'outofstock') { alert('Stok Habis!'); return; }
            const ex = cart.value.find(i => i.id === p.id);
            ex ? ex.qty++ : cart.value.push({ ...p, qty: 1 });
        };
        const removeFromCart = (i) => cart.value = cart.value.filter(item => item.id !== i.id);
        const updateQty = (i, d) => { i.qty += d; if(i.qty <= 0) removeFromCart(i); };
        const clearCart = () => confirm("Hapus keranjang?") ? cart.value = [] : null;
        
        const toggleHold = () => {
            if(heldItems.value.length) {
                if(cart.value.length && !confirm("Timpah cart?")) return;
                cart.value = [...heldItems.value]; heldItems.value = [];
            } else if(cart.value.length) {
                heldItems.value = [...cart.value]; cart.value = [];
                localStorage.setItem('kresuber_held', JSON.stringify(heldItems.value));
            }
        };

        // --- CHECKOUT ---
        const openPayModal = () => {
            showPayModal.value = true;
            nextTick(() => { if(paymentMethod.value==='cash' && cashInput.value) cashInput.value.focus(); });
        };

        const processCheckout = async () => {
            processing.value = true;
            try {
                const payload = {
                    items: cart.value, payment_method: paymentMethod.value,
                    amount_tendered: paymentMethod.value === 'cash' ? cashReceived.value : grandTotal.value,
                    change: paymentMethod.value === 'cash' ? Math.max(0, cashChange.value) : 0
                };
                const res = await axios.post(`${kresuberParams.apiUrl}/order`, payload, { headers: { 'X-WP-Nonce': kresuberParams.nonce } });
                
                if(res.data.success) {
                    lastReceipt.value = { 
                        ...res.data, items: [...cart.value], subTotal: subTotal.value, 
                        taxAmount: taxAmount.value, grandTotal: grandTotal.value, 
                        paymentMethod: paymentMethod.value, cashReceived: payload.amount_tendered, 
                        cashChange: payload.change, cashier: kresuberParams.cashierName 
                    };
                    printReceipt();
                    cart.value = []; cashReceived.value = ''; showPayModal.value = false;
                }
            } catch(e) { alert("Transaksi Gagal: " + (e.response?.data?.message || e.message)); }
            finally { processing.value = false; }
        };

        const printReceipt = () => {
            setTimeout(() => {
                const win = window.open('','','width=300,height=600');
                win.document.write('<html><head><title>Print</title><style>body{margin:0;padding:10px}</style></head><body>'+document.getElementById('receipt-print').innerHTML+'</body></html>');
                win.document.close(); win.focus(); win.print();
            }, 300);
        };

        onMounted(async () => {
            const saved = localStorage.getItem('kresuber_held');
            if(saved) heldItems.value = JSON.parse(saved);
            
            productCount.value = await db.products.count();
            if(productCount.value === 0) await syncProducts();
            else { 
                // Quick load categories
                const all = await db.products.toArray();
                const cats = {};
                all.forEach(i => cats[i.category_slug] = { slug: i.category_slug, name: i.category_name });
                categories.value = Object.values(cats);
                searchLocal(); 
            }

            window.addEventListener('keydown', e => {
                if(e.key === 'F3') { e.preventDefault(); document.querySelector('input[type="text"]')?.focus(); }
            });
        });

        watch([searchQuery, currentCategory], searchLocal);

        return {
            viewMode, products, categories, cart, heldItems, recentOrders, currentCategory, searchQuery, 
            loading, syncing, productCount, qrisUrl,
            syncProducts, searchLocal, setCategory, fetchOrders,
            addToCart, removeFromCart, updateQty, clearCart, toggleHold,
            showPayModal, openPayModal, paymentMethod, cashReceived, processing, cashInput, quickCashOptions,
            subTotal, taxAmount, grandTotal, cashChange, processCheckout, lastReceipt, formatPrice, formatNumber
        };
    }
}).mount('#app');