const { createApp, ref, computed, onMounted, nextTick, watch } = Vue;

const db = new Dexie("KresuberPOS_DB");
db.version(1).stores({
    products: "id, sku, barcode, category_slug, search_text"
});

createApp({
    setup() {
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
        
        const showPayModal = ref(false);
        const paymentMethod = ref('cash');
        const cashReceived = ref('');
        const processing = ref(false);
        const cashInput = ref(null);
        const lastReceipt = ref({});
        
        const taxRate = kresuberParams.taxRate || 0;

        // Computed
        const subTotal = computed(() => cart.value.reduce((sum, item) => sum + (item.price * item.qty), 0));
        const taxAmount = computed(() => Math.round(subTotal.value * (taxRate / 100)));
        const grandTotal = computed(() => subTotal.value + taxAmount.value);
        const cashChange = computed(() => (parseInt(cashReceived.value) || 0) - grandTotal.value);
        const quickCashOptions = computed(() => [10000, 20000, 50000, 100000].filter(amt => amt >= grandTotal.value).slice(0, 3));

        const formatPrice = (val) => kresuberParams.currencySymbol + ' ' + new Intl.NumberFormat('id-ID').format(val);
        const formatNumber = (val) => new Intl.NumberFormat('id-ID').format(val);

        // Core Logic
        const syncProducts = async () => {
            syncing.value = true; loading.value = true;
            try {
                const response = await axios.get(`${kresuberParams.apiUrl}/products`, { headers: { 'X-WP-Nonce': kresuberParams.nonce } });
                const items = response.data.map(p => ({ ...p, search_text: `${p.name} ${p.sku} ${p.barcode || ''}`.toLowerCase() }));
                
                // Extract Categories
                const cats = {};
                items.forEach(i => cats[i.category_slug] = { slug: i.category_slug, name: i.category_name });
                categories.value = Object.values(cats);

                await db.products.clear();
                await db.products.bulkAdd(items);
                productCount.value = await db.products.count();
                dbReady.value = true;
                searchLocal(); 
            } catch (error) { alert("Gagal sync: " + error.message); } 
            finally { syncing.value = false; loading.value = false; }
        };

        const searchLocal = async () => {
            let collection = db.products.toCollection();
            if (currentCategory.value !== 'all') collection = db.products.where('category_slug').equals(currentCategory.value);

            const query = searchQuery.value.toLowerCase().trim();
            if (query) {
                // Barcode Exact Match
                const exact = await db.products.where('sku').equals(query).or('barcode').equals(query).first();
                if (exact) { addToCart(exact); searchQuery.value = ''; return; }
                
                // Fuzzy
                const all = await collection.toArray();
                products.value = all.filter(p => p.search_text.includes(query)).slice(0, 50);
            } else {
                products.value = await collection.limit(50).toArray();
            }
        };

        const addToCart = (p) => {
            if(p.stock_status === 'outofstock') { alert('Stok Habis!'); return; }
            const exist = cart.value.find(i => i.id === p.id);
            exist ? exist.qty++ : cart.value.push({ ...p, qty: 1 });
        };

        const removeFromCart = (item) => cart.value = cart.value.filter(i => i.id !== item.id);
        const updateQty = (item, delta) => { item.qty += delta; if(item.qty <= 0) removeFromCart(item); };
        const clearCart = () => { if(confirm("Hapus keranjang?")) cart.value = []; };
        
        const toggleHold = () => {
            if(heldItems.value.length) {
                if(cart.value.length && !confirm("Replace current cart?")) return;
                cart.value = [...heldItems.value]; heldItems.value = [];
            } else if(cart.value.length) {
                heldItems.value = [...cart.value]; cart.value = [];
                localStorage.setItem('kresuber_held', JSON.stringify(heldItems.value));
            }
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
                    lastReceipt.value = { ...res.data, items: [...cart.value], subTotal: subTotal.value, taxAmount: taxAmount.value, grandTotal: grandTotal.value, paymentMethod: paymentMethod.value, cashReceived: payload.amount_tendered, cashChange: payload.change, cashier: kresuberParams.cashierName };
                    printReceipt();
                    cart.value = []; cashReceived.value = ''; showPayModal.value = false;
                }
            } catch(e) { alert("Error: " + e.message); }
            finally { processing.value = false; }
        };

        const printReceipt = () => {
            setTimeout(() => {
                const win = window.open('', '', 'width=300,height=600');
                win.document.write('<html><head><title>Print</title><style>body{font-family:monospace;font-size:12px;margin:0}hr{border-top:1px dashed #000}</style></head><body>' + document.getElementById('receipt-print').innerHTML + '</body></html>');
                win.document.close(); win.focus(); win.print();
            }, 300);
        };

        onMounted(async () => {
            const saved = localStorage.getItem('kresuber_held');
            if(saved) heldItems.value = JSON.parse(saved);
            
            productCount.value = await db.products.count();
            if(productCount.value === 0) await syncProducts();
            else { dbReady.value = true; searchLocal(); }

            window.addEventListener('keydown', e => {
                if(e.key === 'F3') { e.preventDefault(); document.querySelector('input[type="text"]')?.focus(); }
            });
        });

        return {
            products, categories, cart, heldItems, currentCategory, searchQuery, loading, syncing, dbReady, productCount,
            syncProducts, searchLocal, addToCart, removeFromCart, updateQty, clearCart, toggleHold,
            showPayModal, paymentMethod, cashReceived, processing, cashInput, quickCashOptions,
            subTotal, taxAmount, grandTotal, cashChange, processCheckout, lastReceipt, formatPrice, formatNumber
        };
    }
}).mount('#app');