// Ensure libs exist
if(typeof Vue === 'undefined' || typeof Dexie === 'undefined') {
    console.error("Critical Dependencies Missing: Vue or Dexie");
} else {
    const { createApp, ref, computed, onMounted, nextTick, watch } = Vue;
    
    // Inisialisasi Database Lokal (IndexedDB)
    const db = new Dexie("KresuberDB_V5");
    db.version(1).stores({ prod: "id, sku, barcode, cat, search" });

    createApp({
        setup() {
            // Konfigurasi & State
            const config = ref(params.conf || {});
            const activeCashier = ref(
                (config.value.cashiers && config.value.cashiers.length > 0) 
                ? config.value.cashiers[0] 
                : 'Kasir'
            );

            // Reactive Data
            const products = ref([]);
            const categories = ref([]);
            const cart = ref([]);
            const recentOrders = ref([]);
            
            // UI State
            const curCat = ref('all');
            const search = ref('');
            const loading = ref(true);
            const syncing = ref(false);
            const ordersLoading = ref(false);
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

            // Computed Logic
            const subTotal = computed(() => cart.value.reduce((s,i) => s + (Number(i.price) * i.qty), 0));
            const grandTotal = computed(() => subTotal.value);
            const change = computed(() => (Number(paid.value) || 0) - grandTotal.value);
            
            // Quick Cash Buttons (Dinamis berdasarkan total)
            const quickCash = computed(() => {
                const total = grandTotal.value;
                const fractions = [2000, 5000, 10000, 20000, 50000, 100000];
                // Ambil pecahan yang lebih besar dari total, max 4 opsi
                return fractions.filter(f => f >= total).slice(0, 4);
            });

            const cartTotalQty = computed(() => cart.value.reduce((a, i) => a + i.qty, 0));
            
            // Formatter Rupiah
            const fmt = (v) => params.curr + ' ' + new Intl.NumberFormat('id-ID').format(v);

            // --- CORE LOGIC START ---

            // 1. Sinkronisasi Data Produk dari Server ke Browser
            const sync = async () => {
                syncing.value = true; 
                loading.value = true;
                try {
                    // Tambahkan timestamp untuk bypass browser cache pada request API
                    const r = await axios.get(`${params.api}/products?t=${new Date().getTime()}`, {
                        headers: {'X-WP-Nonce': params.nonce}
                    });
                    
                    // Mapping Data API ke Struktur Database Lokal
                    const items = r.data.map(p => ({
                        id: p.id,
                        name: p.name,
                        price: p.price,
                        image: p.image,
                        stock: p.stock,
                        sku: p.sku || '',
                        barcode: p.barcode || '',
                        cat: p.category_slug || 'uncategorized',
                        category_name: p.category_name || 'Lainnya', // Simpan nama kategori
                        // Index pencarian gabungan
                        search: `${p.name} ${p.sku} ${p.barcode}`.toLowerCase()
                    }));
                    
                    // Ekstrak Kategori Unik dari Produk
                    const catsMap = {}; 
                    items.forEach(i => {
                        if (!catsMap[i.cat]) {
                            catsMap[i.cat] = { slug: i.cat, name: i.category_name };
                        }
                    });
                    categories.value = Object.values(catsMap);
                    
                    // Simpan ke IndexedDB (Bulk Put lebih cepat dari Add)
                    await db.prod.clear(); 
                    await db.prod.bulkPut(items);
                    
                    // Refresh Grid
                    find();
                } catch(e) { 
                    console.error("Sync Failed", e); 
                    alert("Gagal Sinkronisasi: " + (e.response?.data?.message || "Koneksi Bermasalah")); 
                } finally { 
                    syncing.value = false; 
                    loading.value = false; 
                }
            };

            // 2. Pencarian & Filter Produk (Lokal)
            const find = async () => {
                try {
                    let collection = db.prod.toCollection();
                    
                    // Filter Kategori
                    if (curCat.value !== 'all') {
                        collection = db.prod.where('cat').equals(curCat.value);
                    }
                    
                    const q = search.value.toLowerCase().trim();
                    if (q) {
                        // Prioritas 1: Cari Barcode/SKU Exact Match (Cepat untuk Scanner)
                        const exactMatch = await db.prod.where('sku').equals(q).or('barcode').equals(q).first();
                        
                        if (exactMatch) { 
                            addToCart(exactMatch); 
                            search.value = ''; 
                            return; // Langsung masuk keranjang, tidak perlu render grid
                        }
                        
                        // Prioritas 2: Cari Nama/Keyword Partial
                        const allDocs = await collection.toArray(); // Ambil semua di kategori ini dulu
                        products.value = allDocs.filter(p => (p.search || '').includes(q)).slice(0, 50);
                    } else { 
                        // Tampilkan 50 produk pertama jika tidak cari (Lazy load simulation)
                        products.value = await collection.limit(50).toArray();
                        
                        // Fallback: Jika kategori kosong di RAM tapi ada di DB (saat reload page)
                        if (!categories.value.length && products.value.length) {
                             const all = await db.prod.toArray(); 
                             const k = {}; 
                             all.forEach(i => k[i.cat] = { slug: i.cat, name: i.category_name }); 
                             categories.value = Object.values(k);
                        }
                    }
                } catch(e) { 
                    console.error("Find Error", e); 
                }
                loading.value = false;
            };

            // 3. Checkout Logic (Kirim Order ke Server)
            const checkout = async () => {
                if (method.value === 'cash' && change.value < 0) {
                    alert('Uang pembayaran kurang!');
                    return;
                }

                processing.value = true;
                try {
                    // Payload disesuaikan dengan API create_order baru
                    const payload = { 
                        items: cart.value.map(i => ({ id: i.id, qty: i.qty })), // Kirim ID & Qty saja agar ringan
                        payment_method: method.value,
                        amount_tendered: method.value === 'cash' ? paid.value : 0,
                        change: method.value === 'cash' ? change.value : 0,
                        cashier: activeCashier.value // Kirim nama kasir
                    };

                    const r = await axios.post(`${params.api}/order`, payload, {
                        headers: {'X-WP-Nonce': params.nonce}
                    });

                    if (r.data.success) {
                        // Persiapkan Data Struk
                        lastReceipt.value = { 
                            ...r.data, // order_number, date, etc
                            items: [...cart.value], 
                            grandTotal: grandTotal.value, 
                            paymentMethod: method.value, 
                            cashReceived: paid.value, 
                            cashChange: change.value, 
                            cashier: activeCashier.value 
                        };

                        // Cetak Struk
                        setTimeout(() => {
                            printReceipt();
                        }, 500);

                        // Optimasi UX: Kurangi stok lokal tanpa perlu sync ulang
                        await updateLocalStock(cart.value);

                        // Reset
                        cart.value = []; 
                        paid.value = ''; 
                        modal.value = false;
                    }
                } catch(e) { 
                    alert("Transaksi Gagal: " + (e.response?.data?.message || e.message)); 
                } finally { 
                    processing.value = false; 
                }
            };

            // Helper: Kurangi stok di DB Lokal agar UI update realtime
            const updateLocalStock = async (soldItems) => {
                for (const item of soldItems) {
                    const product = await db.prod.get(item.id);
                    if (product) {
                        const newStock = Math.max(0, product.stock - item.qty);
                        await db.prod.update(item.id, { stock: newStock });
                        
                        // Update juga di view grid saat ini jika ada
                        const inView = products.value.find(p => p.id === item.id);
                        if (inView) inView.stock = newStock;
                    }
                }
            };

            // --- UTILITIES ---

            const fetchOrders = async () => {
                ordersLoading.value = true;
                try { 
                    const r = await axios.get(`${params.api}/orders`, {headers:{'X-WP-Nonce':params.nonce}}); 
                    recentOrders.value = r.data; 
                } catch(e){} finally { ordersLoading.value = false; }
            };

            const addToCart = (p) => { 
                if (p.stock <= 0) return alert('Stok Habis!'); 
                const i = cart.value.find(x => x.id === p.id); 
                if (i) {
                    if (i.qty >= p.stock) return alert('Stok tidak mencukupi!');
                    i.qty++;
                } else {
                    cart.value.push({...p, qty:1}); 
                }
            };
            
            const rem = (i) => cart.value = cart.value.filter(x => x.id !== i.id);
            const qty = (i, d) => { 
                // Cek stok saat tambah qty
                if (d > 0 && i.qty >= i.stock) {
                    return alert('Mencapai batas stok!');
                }
                i.qty += d; 
                if (i.qty <= 0) rem(i); 
            };
            
            const clearCart = () => confirm('Hapus keranjang?') ? cart.value=[] : null;
            const setCategory = (s) => { curCat.value=s; find(); };

            const printReceipt = () => {
                const width = config.value.printer_width || '58mm';
                const w = window.open('','','width=400,height=600');
                const content = document.getElementById('receipt-print').innerHTML;
                w.document.write(`<html><head><title>Print</title><style>body{margin:0;padding:0}.receipt{width:${width}}</style></head><body>${content}</body></html>`);
                w.document.close(); 
                w.focus(); 
                setTimeout(() => { w.print(); w.close(); }, 500);
            };

            // Scanner Logic
            const openScanner = () => {
                showScanner.value = true;
                nextTick(() => {
                    if (typeof Html5Qrcode === 'undefined') { alert('Library Scanner belum dimuat'); return; }
                    
                    html5QrCode.value = new Html5Qrcode("reader");
                    const configQr = { fps: 10, qrbox: { width: 250, height: 250 } };
                    
                    html5QrCode.value.start({ facingMode: "environment" }, configQr,
                        (decodedText) => { 
                            search.value = decodedText; // Trigger watch(search) -> find() -> addToCart()
                            closeScanner(); 
                        },
                        (e) => { /* ignore errors during scanning */ }
                    ).catch(err => { 
                        console.error(err); 
                        alert("Gagal akses kamera. Pastikan izin diberikan."); 
                        closeScanner(); 
                    });
                });
            };

            const closeScanner = () => {
                if (html5QrCode.value) { 
                    html5QrCode.value.stop().then(() => { 
                        html5QrCode.value.clear(); 
                        showScanner.value = false; 
                    }).catch(() => { showScanner.value = false; }); 
                } else { 
                    showScanner.value = false; 
                }
            };

            // Lifecycle Hooks
            onMounted(async () => {
                try { 
                    // Auto sync jika DB kosong
                    if ((await db.prod.count()) === 0) {
                        await sync(); 
                    } else {
                        await find(); 
                    }
                } catch(e) { console.error("Init Error", e); }
                
                // Remove loader UI
                const loader = document.getElementById('app-loading');
                if(loader) { loader.style.opacity='0'; setTimeout(()=>loader.remove(), 500); }
                
                // Keyboard Shortcut (F3 = Search)
                window.addEventListener('keydown', e => { 
                    if(e.key === 'F3'){ e.preventDefault(); document.querySelector('input[type=text]')?.focus(); } 
                });
            });

            // Watchers
            watch([search, curCat], find);
            watch(modal, (v) => { if(v && method.value==='cash') nextTick(()=>cashInput.value?.focus()); });

            return { 
                config, products, categories, cart, recentOrders, curCat, search, 
                loading, syncing, ordersLoading, viewMode, activeCashier, showCart, 
                modal, method, paid, processing, cashInput, grandTotal, change, 
                quickCash, cartTotalQty, fmt, 
                sync, setCategory, fetchOrders, addToCart, rem, qty, clearCart, 
                setView: (m) => { viewMode.value = m; }, 
                openPayModal: () => modal.value = true, 
                checkout, showScanner, openScanner, closeScanner 
            };
        }
    }).mount('#app');
}