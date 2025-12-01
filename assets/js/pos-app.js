const { createApp } = Vue;

// 1. Setup Database Lokal (Dexie)
const db = new Dexie("KresuberPOS_DB");
db.version(1).stores({
    products: "id, name, sku, category_slug, search_keywords" // Indexing
});

createApp({
    data() {
        return {
            products: [],
            cart: [],
            categories: [],
            currentCategory: 'all',
            searchQuery: '',
            loading: false,
            syncing: false,
            
            // Payment State
            showPayModal: false,
            paymentMethod: 'cash',
            cashReceived: 0,
            processing: false,
            taxRate: kresuberParams.taxRate,

            // Receipt Data
            lastOrderId: '',
            lastOrderItems: [],
            lastOrderTotal: 0,
            lastCashReceived: 0,
            lastCashChange: 0,
            lastPaymentMethod: ''
        }
    },
    computed: {
        subTotal() { return this.cart.reduce((sum, i) => sum + (i.price * i.qty), 0); },
        taxAmount() { return Math.round(this.subTotal * (this.taxRate / 100)); },
        grandTotal() { return this.subTotal + this.taxAmount; },
        cashChange() { return this.cashReceived - this.grandTotal; }
    },
    async mounted() {
        // Init: Cek data lokal dulu, kalau kosong baru sync
        const count = await db.products.count();
        if (count === 0) {
            await this.syncProducts();
        } else {
            this.searchLocal(); // Load data dari DB
        }
        
        // Init Categories (Extract from DB in real app, here hardcoded for simplicity of demo)
        this.categories = [
            {name: 'Snack', slug: 'snack'},
            {name: 'Minuman', slug: 'minuman'},
            {name: 'Ice Cream', slug: 'ice-cream-joyday'},
            {name: 'Permen', slug: 'permen'},
            {name: 'Sembako', slug: 'sembako'}
        ];

        // Barcode Listener Global
        window.addEventListener('keydown', this.handleBarcodeScan);
        // Focus search on F3
        window.addEventListener('keydown', (e) => {
            if(e.key === 'F3') { e.preventDefault(); this.$refs.searchInput.focus(); }
        });
    },
    methods: {
        formatPrice(val) {
            return kresuberParams.currency + ' ' + new Intl.NumberFormat('id-ID').format(val);
        },

        // --- DATABASE LOGIC (WCPOS FEATURE) ---
        async syncProducts() {
            this.syncing = true;
            this.loading = true;
            try {
                // Fetch semua produk dari WP API
                const response = await axios.get(`${kresuberParams.apiUrl}/products`, {
                    headers: { 'X-WP-Nonce': kresuberParams.nonce }
                });
                
                // Siapkan data untuk Dexie (Flattening data agar searchable)
                const productsToStore = response.data.map(p => ({
                    ...p,
                    search_keywords: `${p.name} ${p.sku}`.toLowerCase(),
                    category_slug: p.category_slug || 'uncategorized' // Asumsi API return cat slug
                }));

                // Clear & Bulk Add
                await db.products.clear();
                await db.products.bulkAdd(productsToStore);
                
                this.searchLocal(); // Refresh view
                // alert('Sinkronisasi Selesai!');
            } catch (err) {
                console.error(err);
                alert('Gagal Sync!');
            } finally {
                this.syncing = false;
                this.loading = false;
            }
        },

        async searchLocal() {
            let collection = db.products.toCollection();

            // 1. Filter Category
            if (this.currentCategory !== 'all') {
                collection = db.products.where('category_slug').equals(this.currentCategory); // Butuh update API agar return slug
                // Fallback sementara untuk demo jika API belum return slug:
                // collection = db.products.filter(p => JSON.stringify(p).includes(this.currentCategory));
            }

            // 2. Filter Search (Manual filter di memory karena Dexie basic search terbatas)
            const query = this.searchQuery.toLowerCase();
            if (query) {
                // Prioritas: Exact SKU match (Barcode)
                const exactSku = await db.products.where('sku').equals(query).first();
                if (exactSku) {
                    this.addToCart(exactSku);
                    this.searchQuery = ''; // Clear after scan
                    return;
                }

                // Normal Search
                const results = await db.products.filter(p => p.search_keywords.includes(query)).toArray();
                this.products = results;
            } else {
                // Load All (Limit 50 biar ringan)
                this.products = await collection.limit(50).toArray();
            }
        },

        filterCategory(slug) {
            this.currentCategory = slug;
            this.searchLocal();
        },

        handleBarcodeScan(e) {
            // Logic scanner barcode biasanya cepat, bisa dideteksi interval
            // Di sini kita pakai cara simple: Input box search auto focus kalau scan
            if (e.target.tagName !== 'INPUT' && e.key.length === 1) {
                this.$refs.searchInput.focus();
            }
        },

        // --- CART LOGIC ---
        addToCart(product) {
            const existing = this.cart.find(i => i.id === product.id);
            if (existing) {
                existing.qty++;
            } else {
                this.cart.push({ ...product, qty: 1 });
            }
            // Play beep sound
            const audio = new Audio("data:audio/wav;base64,UklGRl9vT19XQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YU"); // Dummy short beep
            // audio.play().catch(e=>{}); 
        },
        removeFromCart(item) {
            this.cart = this.cart.filter(i => i.id !== item.id);
        },
        increaseQty(item) { item.qty++; },
        decreaseQty(item) { 
            if(item.qty > 1) item.qty--; 
            else this.removeFromCart(item);
        },
        clearCart() {
            if(confirm('Hapus semua item?')) this.cart = [];
        },
        holdOrder() {
            if(this.cart.length === 0) return;
            localStorage.setItem('kresuber_held_cart', JSON.stringify(this.cart));
            this.cart = [];
            alert('Order disimpan sementara (Hold).');
        },

        // --- CHECKOUT LOGIC ---
        async processPayment() {
            this.processing = true;
            try {
                // 1. Push ke Server WP
                const response = await axios.post(`${kresuberParams.apiUrl}/order`, {
                    items: this.cart,
                    payment_method: this.paymentMethod,
                    status: 'completed'
                }, { headers: { 'X-WP-Nonce': kresuberParams.nonce } });

                if (response.data.success) {
                    // 2. Set Data Resi
                    this.lastOrderId = response.data.order_id;
                    this.lastOrderItems = [...this.cart];
                    this.lastOrderTotal = this.grandTotal;
                    this.lastCashReceived = this.cashReceived;
                    this.lastCashChange = this.cashChange;
                    this.lastPaymentMethod = this.paymentMethod;

                    // 3. Print
                    this.printReceipt();

                    // 4. Reset
                    this.showPayModal = false;
                    this.cart = [];
                    this.cashReceived = 0;
                }
            } catch (err) {
                alert('Gagal transaksi: ' + (err.response?.data?.message || err.message));
            } finally {
                this.processing = false;
            }
        },

        printReceipt() {
            // Teknik Hidden Iframe / New Window
            setTimeout(() => {
                const content = document.getElementById('receipt-print').innerHTML;
                const win = window.open('', '', 'height=500,width=400');
                win.document.write('<html><head><title>Struk</title></head><body>');
                win.document.write(content);
                win.document.write('</body></html>');
                win.document.close();
                win.print();
                // win.close(); // Optional auto close
            }, 500);
        }
    }
}).mount('#app');