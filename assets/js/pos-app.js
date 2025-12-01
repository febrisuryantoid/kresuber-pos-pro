// Kresuber POS Pro v1.8.0 - Client Core
// Menggunakan Vue 3 + Axios + Dexie (IndexedDB)

// Cek Dependensi
if(typeof Vue === 'undefined' || typeof Dexie === 'undefined' || typeof axios === 'undefined') {
    const err = "System Error: Library kritis (Vue/Axios/Dexie) gagal dimuat. Cek koneksi internet.";
    console.error(err);
    document.getElementById('app-loading').innerHTML = '<div style="color:red;padding:20px;text-align:center;"><h3>'+err+'</h3><button onclick="location.reload()" style="margin-top:10px;padding:10px;">Refresh</button></div>';
} else {
    const { createApp, ref, computed, onMounted, nextTick, watch } = Vue;
    
    // 1. Inisialisasi Database Lokal (IndexedDB)
    const db = new Dexie("KresuberPOS_DB_v1.8");
    db.version(1).stores({ 
        products: "id, sku, barcode, cat, search", // Index untuk pencarian cepat
        settings: "key" // Store config lokal
    });

    // 2. Setup Axios Client
    const api = axios.create({
        baseURL: params.api,
        timeout: 30000, // 30 detik (per request page, bukan total)
        headers: {'X-WP-Nonce': params.nonce}
    });

    createApp({
        setup() {
            // --- STATE MANAGEMENT ---
            const config = ref(params.conf || {});
            const activeCashier = ref((config.value.cashiers?.[0]) || 'Kasir');
            const products = ref([]);
            const categories = ref([]);
            const cart = ref([]);
            const recentOrders = ref([]);
            
            // UI Flags
            const curCat = ref('all');
            const search = ref('');
            const loading = ref(true);
            const loadingText = ref('Menyiapkan sistem...');
            const syncing = ref(false);
            const viewMode = ref('pos');
            const showCart = ref(false);
            const modal = ref(false);
            const showScanner = ref(false);
            
            // Payment State
            const method = ref('cash');
            const paid = ref('');
            const processing = ref(false);
            const cashInput = ref(null);
            const lastReceipt = ref({});
            const html5QrCode = ref(null);
            
            // Error Handling
            const errorMsg = ref('');

            // --- COMPUTED PROPERTIES ---
            const subTotal = computed(() => cart.value.reduce((s,i) => s + (Number(i.price) * i.qty), 0));
            const grandTotal = computed(() => subTotal.value);
            const change = computed(() => (Number(paid.value) || 0) - grandTotal.value);
            
            // Quick Cash Buttons Logic
            const quickCash = computed(() => {
                const total = grandTotal.value;
                const base = [2000, 5000, 10000, 20000, 50000, 100000];
                // Suggest uang pas & kelipatan terdekat
                const suggestions = base.filter(f => f >= total).slice(0, 3);
                if(total > 0) suggestions.unshift(total); 
                return [...new Set(suggestions)].sort((a,b) => a-b);
            });
            
            const cartTotalQty = computed(() => cart.value.reduce((a, i) => a + i.qty, 0));
            const fmt = (v) => params.curr + ' ' + new Intl.NumberFormat('id-ID').format(v);

            // --- CORE LOGIC: SYNC BERTINGKAT (PAGINATION) ---
            const sync = async () => {
                if(syncing.value) return;
                syncing.value = true; 
                loading.value = true;
                errorMsg.value = '';
                
                try {
                    // Step 1: Kosongkan DB lokal
                    loadingText.value = "Reset database...";
                    await db.products.clear(); 
                    
                    let page = 1;
                    let totalPages = 1;
                    let totalItems = 0;

                    // Step 2: Loop Fetch per Halaman
                    do {
                        loadingText.value = `Sinkronisasi Produk... Hal ${page}`;
                        
                        const r = await api.get('/products', { 
                            params: { page: page, per_page: 50, t: Date.now() } 
                        });

                        if (!r.data || !Array.isArray(r.data.products)) throw new Error("Format Data Invalid");

                        const fetchedItems = r.data.products.map(p => ({
                            ...p,
                            // Index pencarian gabungan biar cepat
                            search: `${p.name} ${p.sku} ${p.barcode}`.toLowerCase()
                        }));

                        // Batch Insert ke IndexedDB
                        await db.products.bulkPut(fetchedItems);

                        // Update Info Pagination
                        totalPages = parseInt(r.data.pagination.total_pages);
                        totalItems = parseInt(r.data.pagination.total_items);
                        page++;

                    } while (page <= totalPages);

                    // Step 3: Bangun Ulang Kategori dari Data Lokal
                    loadingText.value = "Finalisasi...";
                    const allProds = await db.products.toArray();
                    
                    // Ekstrak Kategori Unik
                    const catsMap = {}; 
                    allProds.forEach(i => { 
                        if (!catsMap[i.category_slug]) {
                            catsMap[i.category_slug] = { slug: i.category_slug, name: i.category_name }; 
                        }
                    });
                    categories.value = Object.values(catsMap);

                    loadingText.value = `Selesai! ${totalItems} Produk.`;
                    await loadProductsFromDB();

                } catch(e) { 
                    console.error("Sync Error:", e);
                    let msg = "Gagal Sinkronisasi.";
                    if (e.code === 'ECONNABORTED') msg = "Koneksi Timeout.";
                    else if (e.response) msg = `Server Error: ${e.response.status}`;
                    else msg = e.message;
                    errorMsg.value = msg;
                } finally { 
                    syncing.value = false; 
                    loading.value = false; 
                    loadingText.value = '';
                }
            };

            // Load Produk dari Local DB ke Memory (Vue)
            const loadProductsFromDB = async () => {
                loading.value = true;
                try {
                    let collection = db.products.toCollection();
                    
                    // Filter Kategori
                    if (curCat.value !== 'all') {
                        collection = db.products.where('category_slug').equals(curCat.value);
                    }
                    
                    // Filter Search
                    const q = search.value.toLowerCase().trim();
                    if (q) {
                        // Cek Exact Match SKU/Barcode dulu (Scanner Friendly)
                        const exact = await db.products.where('sku').equals(q).or('barcode').equals(q).first();
                        if (exact) { 
                            addToCart(exact); 
                            search.value = ''; // Reset search field setelah scan sukses
                            // Jangan return, tetap tampilkan hasil search visual
                        }
                        
                        // Full text search sederhana di memory (limit 50 biar ringan)
                        const all = await collection.toArray();
                        products.value = all.filter(p => (p.search || '').includes(q)).slice(0, 50);
                    } else { 
                        // Load 50 produk pertama (Lazy Load manual jika perlu scroll)
                        products.value = await collection.limit(60).toArray();
                        
                        // Jika kategori kosong (first load), isi dari DB
                        if (!categories.value.length && products.value.length > 0) {
                             const all = await db.products.toArray(); 
                             const k = {}; 
                             all.forEach(i => k[i.category_slug] = { slug: i.category_slug, name: i.category_name }); 
                             categories.value = Object.values(k);
                        }
                    }
                } catch(e) { console.error(e); }
                loading.value = false;
            };

            // --- TRANSACTION LOGIC ---
            const checkout = async () => {
                if (method.value === 'cash' && change.value < 0) return alert('Uang pembayaran kurang!');
                
                processing.value = true;
                try {
                    const payload = { 
                        items: cart.value.map(i => ({ id: i.id, qty: i.qty })),
                        payment_method: method.value,
                        amount_tendered: method.value === 'cash' ? paid.value : 0,
                        change: method.value === 'cash' ? change.value : 0,
                        cashier: activeCashier.value
                    };

                    const r = await api.post('/order', payload);

                    if (r.data.success) {
                        // 1. Update UI Receipt
                        lastReceipt.value = { 
                            ...r.data, 
                            items: [...cart.value], 
                            grandTotal: grandTotal.value, 
                            paymentMethod: method.value, 
                            cashReceived: paid.value, 
                            cashChange: change.value
                        };
                        
                        // 2. Auto Print
                        setTimeout(printReceipt, 300);
                        
                        // 3. Kurangi Stok Lokal (Optimistic UI)
                        await updateLocalStock(cart.value);
                        
                        // 4. Reset
                        cart.value = []; paid.value = ''; modal.value = false;
                    }
                } catch(e) { 
                    alert("Transaksi Gagal: " + (e.response?.data?.message || e.message)); 
                } finally { processing.value = false; }
            };

            const updateLocalStock = async (soldItems) => {
                for (const item of soldItems) {
                    const product = await db.products.get(item.id);
                    if (product) {
                        const newStock = Math.max(0, product.stock - item.qty);
                        await db.products.update(item.id, { stock: newStock });
                        // Update tampilan jika produk terlihat
                        const inView = products.value.find(p => p.id === item.id);
                        if (inView) inView.stock = newStock;
                    }
                }
            };

            const fetchOrders = async () => {
                try { const r = await api.get('/orders'); recentOrders.value = r.data; } 
                catch(e){}
            };

            // --- UTILITIES ---
            const addToCart = (p) => { 
                if (p.stock <= 0) return alert('Stok Habis!'); 
                const i = cart.value.find(x => x.id === p.id); 
                if (i) { 
                    if(i.qty >= p.stock) return alert('Stok Kurang (Max: '+p.stock+')'); 
                    i.qty++; 
                } else { 
                    cart.value.push({...p, qty:1}); 
                } 
            };
            
            const rem = (i) => cart.value = cart.value.filter(x => x.id !== i.id);
            const qty = (i, d) => { 
                if (d > 0 && i.qty >= i.stock) return alert('Stok Mentok'); 
                i.qty += d; 
                if (i.qty <= 0) rem(i); 
            };
            
            const clearCart = () => confirm('Hapus keranjang?') ? cart.value=[] : null;
            
            const setCategory = (s) => { curCat.value=s; loadProductsFromDB(); };
            
            const printReceipt = () => {
                const w = window.open('','','width=400,height=600');
                if(!w) return alert('Pop-up terblokir! Izinkan pop-up untuk mencetak.');
                w.document.write(`<html><head><title>Print</title><style>body{margin:0;padding:0;font-family:monospace;font-size:12px}.receipt{width:${config.value.printer_width||'58mm'}}</style></head><body>${document.getElementById('receipt-print').innerHTML}</body></html>`);
                w.document.close(); w.focus(); 
                setTimeout(() => { w.print(); w.close(); }, 500);
            };

            // Scanner Implementation
            const openScanner = () => { 
                showScanner.value = true; 
                nextTick(() => { 
                    if(typeof Html5Qrcode === 'undefined') return alert('Library Scanner belum siap');
                    html5QrCode.value = new Html5Qrcode("reader");
                    html5QrCode.value.start({ facingMode: "environment" }, { fps: 10, qrbox: 250 }, 
                    (txt) => { search.value = txt; closeScanner(); }, 
                    () => {}).catch(err => { console.log(err); closeScanner(); alert("Gagal akses kamera: " + err); });
                }); 
            };
            const closeScanner = () => { 
                if(html5QrCode.value) html5QrCode.value.stop().then(() => { html5QrCode.value.clear(); showScanner.value=false; }).catch(()=>{showScanner.value=false;}); 
                else showScanner.value=false; 
            };

            // --- INITIALIZATION ---
            onMounted(async () => {
                // Hapus loader HTML statis secepat mungkin
                const loader = document.getElementById('app-loading');
                if(loader) { loader.style.opacity = '0'; setTimeout(() => loader.remove(), 500); }

                try {
                    // Cek isi DB lokal
                    const count = await db.products.count();
                    if (count === 0) {
                        await sync(); // Auto sync first run
                    } else {
                        await loadProductsFromDB();
                    }
                } catch(e) {
                    console.error("Init Error:", e);
                    errorMsg.value = "Gagal memuat data lokal.";
                }
            });

            // Watchers
            watch([search, curCat], loadProductsFromDB);
            watch(modal, (v) => { if(v && method.value==='cash') nextTick(()=>cashInput.value?.focus()); });
            
            // Global Shortcut
            window.addEventListener('keydown', e => { 
                if(e.key === 'F3'){ e.preventDefault(); document.querySelector('input[type=text]')?.focus(); }
                if(e.key === 'Escape' && modal.value) modal.value = false;
            });

            return { 
                config, products, categories, cart, recentOrders, curCat, search, loading, loadingText, syncing, 
                viewMode, activeCashier, showCart, modal, method, paid, processing, cashInput, grandTotal, change, 
                quickCash, cartTotalQty, fmt, errorMsg,
                sync, setCategory, fetchOrders, addToCart, rem, qty, clearCart, setView: (m)=>viewMode.value=m, 
                openPayModal: ()=>modal.value=true, checkout, showScanner, openScanner, closeScanner, lastReceipt 
            };
        }
    }).mount('#app');
}