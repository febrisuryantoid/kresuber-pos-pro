// Cek dependensi kritis sebelum Vue mulai
if(typeof Vue === 'undefined' || typeof Dexie === 'undefined' || typeof axios === 'undefined') {
    const err = "Library Error: Vue, Axios, atau Dexie gagal dimuat CDN.";
    console.error(err);
    alert(err);
    document.getElementById('app-loading').innerHTML = '<p style="color:red;font-weight:bold">'+err+'</p>';
} else {
    const { createApp, ref, computed, onMounted, nextTick, watch } = Vue;
    
    // Inisialisasi Database Lokal
    const db = new Dexie("KresuberDB_V5");
    db.version(1).stores({ prod: "id, sku, barcode, cat, search" });

    // Inisialisasi Axios dengan Timeout 15 Detik (PENTING AGAR TIDAK STUCK)
    const api = axios.create({
        baseURL: params.api,
        timeout: 15000, // 15 Detik max wait
        headers: {'X-WP-Nonce': params.nonce}
    });

    createApp({
        setup() {
            // State
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
            const syncing = ref(false);
            const ordersLoading = ref(false);
            const viewMode = ref('pos');
            const showCart = ref(false);
            const modal = ref(false);
            const showScanner = ref(false);
            
            // Payment
            const method = ref('cash');
            const paid = ref('');
            const processing = ref(false);
            const cashInput = ref(null);
            const lastReceipt = ref({});
            const html5QrCode = ref(null);
            
            // Error State
            const errorMsg = ref('');

            // Computed
            const subTotal = computed(() => cart.value.reduce((s,i) => s + (Number(i.price) * i.qty), 0));
            const grandTotal = computed(() => subTotal.value);
            const change = computed(() => (Number(paid.value) || 0) - grandTotal.value);
            const quickCash = computed(() => {
                const total = grandTotal.value;
                const fractions = [2000, 5000, 10000, 20000, 50000, 100000];
                return fractions.filter(f => f >= total).slice(0, 4);
            });
            const cartTotalQty = computed(() => cart.value.reduce((a, i) => a + i.qty, 0));
            const fmt = (v) => params.curr + ' ' + new Intl.NumberFormat('id-ID').format(v);

            // --- CORE FUNCTIONALITY ---

            const forceRemoveLoader = () => {
                const loader = document.getElementById('app-loading');
                if(loader) { 
                    loader.style.opacity = '0'; 
                    setTimeout(() => loader.remove(), 500); 
                }
            };

            const sync = async () => {
                syncing.value = true; 
                loading.value = true;
                errorMsg.value = '';
                try {
                    // Gunakan instance 'api' dengan timeout
                    const r = await api.get(`/products?t=${new Date().getTime()}`);
                    
                    if (!Array.isArray(r.data)) throw new Error("Format Data Invalid (Bukan Array)");

                    const items = r.data.map(p => ({
                        id: p.id,
                        name: p.name,
                        price: p.price,
                        image: p.image,
                        stock: p.stock,
                        sku: p.sku || '',
                        barcode: p.barcode || '',
                        cat: p.category_slug || 'uncategorized',
                        category_name: p.category_name || 'Lainnya',
                        search: `${p.name} ${p.sku} ${p.barcode}`.toLowerCase()
                    }));
                    
                    // Kategori
                    const catsMap = {}; 
                    items.forEach(i => { if (!catsMap[i.cat]) catsMap[i.cat] = { slug: i.cat, name: i.category_name }; });
                    categories.value = Object.values(catsMap);
                    
                    // Simpan DB
                    await db.prod.clear(); 
                    await db.prod.bulkPut(items);
                    
                    find();
                } catch(e) { 
                    console.error("Sync Error:", e);
                    let msg = "Gagal Sinkronisasi.";
                    if (e.code === 'ECONNABORTED') msg = "Koneksi Timeout. Server lambat merespon.";
                    else if (e.response) msg = `Server Error: ${e.response.status} ${e.response.statusText}`;
                    else msg = e.message;
                    
                    errorMsg.value = msg;
                    alert(msg);
                } finally { 
                    syncing.value = false; 
                    loading.value = false; 
                }
            };

            const find = async () => {
                try {
                    let collection = db.prod.toCollection();
                    if (curCat.value !== 'all') collection = db.prod.where('cat').equals(curCat.value);
                    
                    const q = search.value.toLowerCase().trim();
                    if (q) {
                        const exact = await db.prod.where('sku').equals(q).or('barcode').equals(q).first();
                        if (exact) { addToCart(exact); search.value = ''; return; }
                        
                        const all = await collection.toArray();
                        products.value = all.filter(p => (p.search || '').includes(q)).slice(0, 50);
                    } else { 
                        products.value = await collection.limit(50).toArray();
                        if (!categories.value.length && products.value.length) {
                             const all = await db.prod.toArray(); 
                             const k = {}; 
                             all.forEach(i => k[i.cat] = { slug: i.cat, name: i.category_name }); 
                             categories.value = Object.values(k);
                        }
                    }
                } catch(e) { console.error(e); }
                loading.value = false;
            };

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
                        lastReceipt.value = { 
                            ...r.data, items: [...cart.value], grandTotal: grandTotal.value, 
                            paymentMethod: method.value, cashReceived: paid.value, 
                            cashChange: change.value, cashier: activeCashier.value 
                        };
                        setTimeout(printReceipt, 500);
                        await updateLocalStock(cart.value);
                        cart.value = []; paid.value = ''; modal.value = false;
                    }
                } catch(e) { 
                    alert("Transaksi Gagal: " + (e.response?.data?.message || e.message)); 
                } finally { processing.value = false; }
            };

            const updateLocalStock = async (soldItems) => {
                for (const item of soldItems) {
                    const product = await db.prod.get(item.id);
                    if (product) {
                        const newStock = Math.max(0, product.stock - item.qty);
                        await db.prod.update(item.id, { stock: newStock });
                        const inView = products.value.find(p => p.id === item.id);
                        if (inView) inView.stock = newStock;
                    }
                }
            };

            const fetchOrders = async () => {
                ordersLoading.value = true;
                try { const r = await api.get('/orders'); recentOrders.value = r.data; } 
                catch(e){} finally { ordersLoading.value = false; }
            };

            // Utilities
            const addToCart = (p) => { 
                if (p.stock <= 0) return alert('Stok Habis!'); 
                const i = cart.value.find(x => x.id === p.id); 
                if (i) { if(i.qty >= p.stock) return alert('Stok Kurang'); i.qty++; } 
                else cart.value.push({...p, qty:1}); 
            };
            const rem = (i) => cart.value = cart.value.filter(x => x.id !== i.id);
            const qty = (i, d) => { if (d > 0 && i.qty >= i.stock) return alert('Max Stok'); i.qty += d; if (i.qty <= 0) rem(i); };
            const clearCart = () => confirm('Hapus?') ? cart.value=[] : null;
            const setCategory = (s) => { curCat.value=s; find(); };
            
            const printReceipt = () => {
                const w = window.open('','','width=400,height=600');
                w.document.write(`<html><head><title>Print</title><style>body{margin:0;padding:0;font-family:monospace}.receipt{width:${config.value.printer_width||'58mm'}}</style></head><body>${document.getElementById('receipt-print').innerHTML}</body></html>`);
                w.document.close(); w.focus(); setTimeout(() => { w.print(); w.close(); }, 500);
            };

            // Scanner
            const openScanner = () => { showScanner.value = true; nextTick(() => { 
                if(typeof Html5Qrcode === 'undefined') return alert('Lib Scanner Error');
                html5QrCode.value = new Html5Qrcode("reader");
                html5QrCode.value.start({ facingMode: "environment" }, { fps: 10, qrbox: 250 }, 
                (txt) => { search.value = txt; closeScanner(); }, 
                () => {}).catch(err => { console.log(err); closeScanner(); alert("Gagal akses kamera"); });
            }); };
            const closeScanner = () => { 
                if(html5QrCode.value) html5QrCode.value.stop().then(() => { html5QrCode.value.clear(); showScanner.value=false; }).catch(()=>{showScanner.value=false;}); 
                else showScanner.value=false; 
            };

            // INIT
            onMounted(async () => {
                // JAMINAN: Apapun yang terjadi, loader HARUS hilang
                try {
                    const count = await db.prod.count();
                    if (count === 0) await sync();
                    else await find();
                } catch(e) {
                    console.error("Init Failed:", e);
                    errorMsg.value = "Gagal memuat data awal. Coba Sync manual.";
                } finally {
                    forceRemoveLoader(); // <-- JAMINAN ANTI STUCK
                }
            });

            // Watchers & Keys
            watch([search, curCat], find);
            watch(modal, (v) => { if(v && method.value==='cash') nextTick(()=>cashInput.value?.focus()); });
            window.addEventListener('keydown', e => { if(e.key === 'F3'){ e.preventDefault(); document.querySelector('input[type=text]')?.focus(); } });

            return { 
                config, products, categories, cart, recentOrders, curCat, search, loading, syncing, ordersLoading, 
                viewMode, activeCashier, showCart, modal, method, paid, processing, cashInput, grandTotal, change, 
                quickCash, cartTotalQty, fmt, errorMsg,
                sync, setCategory, fetchOrders, addToCart, rem, qty, clearCart, setView: (m)=>viewMode.value=m, 
                openPayModal: ()=>modal.value=true, checkout, showScanner, openScanner, closeScanner 
            };
        }
    }).mount('#app');
}