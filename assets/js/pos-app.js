// Ensure libs exist
if(typeof Vue === 'undefined' || typeof Dexie === 'undefined') {
    console.error("Critical Dependencies Missing");
} else {
    const { createApp, ref, computed, onMounted, nextTick, watch } = Vue;
    const db = new Dexie("KresuberDB_V5");
    db.version(1).stores({ prod: "id, sku, barcode, cat, search" });

    createApp({
        setup() {
            // Safe config initialization
            const config = ref(params.conf || {});
            // Safe cashier initialization
            const activeCashier = ref(
                (config.value.cashiers && config.value.cashiers.length > 0) 
                ? config.value.cashiers[0] 
                : 'Default'
            );

            const products=ref([]), categories=ref([]), cart=ref([]), recentOrders=ref([]);
            const curCat=ref('all'), search=ref(''), loading=ref(true), syncing=ref(false), ordersLoading=ref(false);
            const viewMode=ref('pos'), showCart=ref(false), modal=ref(false), showScanner=ref(false);
            const method=ref('cash'), paid=ref(''), processing=ref(false), cashInput=ref(null), lastReceipt=ref({});
            const html5QrCode=ref(null);

            const subTotal = computed(() => cart.value.reduce((s,i)=>s+(Number(i.price)*i.qty),0));
            const grandTotal = computed(() => subTotal.value);
            const change = computed(() => (Number(paid.value)||0)-grandTotal.value);
            const quickCash = computed(() => [10000, 20000, 50000, 100000].filter(a => a >= grandTotal.value).slice(0, 3));
            const cartTotalQty = computed(() => cart.value.reduce((a, i) => a + i.qty, 0));
            const fmt = (v) => params.curr + ' ' + new Intl.NumberFormat('id-ID').format(v);

            const sync = async () => {
                syncing.value=true; loading.value=true;
                try {
                    const r = await axios.get(`${params.api}/products`, {headers:{'X-WP-Nonce':params.nonce}});
                    // Generate search index safely
                    const items = r.data.map(p => ({
                        ...p, 
                        search: `${p.name||''} ${p.sku||''} ${p.barcode||''}`.toLowerCase(), 
                        cat: p.category_slug || 'uncategorized'
                    }));
                    
                    const cats = {}; 
                    items.forEach(i => cats[i.cat]={slug:i.cat, name:i.category_name||'Uncategorized'});
                    categories.value = Object.values(cats);
                    
                    await db.prod.clear(); 
                    await db.prod.bulkAdd(items);
                    find();
                } catch(e){ 
                    console.error(e); 
                    alert("Gagal Sinkronisasi: " + (e.response?.data?.message || e.message)); 
                } finally { 
                    syncing.value=false; loading.value=false; 
                }
            };

            const find = async () => {
                try {
                    let c = db.prod.toCollection();
                    if(curCat.value!=='all') c = db.prod.where('cat').equals(curCat.value);
                    
                    const q = search.value.toLowerCase().trim();
                    if(q) {
                        const ex = await db.prod.where('sku').equals(q).or('barcode').equals(q).first();
                        if(ex) { add(ex); search.value=''; return; }
                        
                        const all = await c.toArray();
                        // Safe filter in case 'search' field is missing in old cache
                        products.value = all.filter(p => (p.search || '').includes(q)).slice(0, 60);
                    } else { 
                        products.value = await c.limit(60).toArray(); 
                        if(!categories.value.length && products.value.length) {
                             const all = await db.prod.toArray(); 
                             const k = {}; 
                             all.forEach(i=>k[i.cat]={slug:i.cat, name:i.category_name}); 
                             categories.value=Object.values(k);
                        }
                    }
                } catch(e) { console.error("Find Error", e); }
                loading.value = false;
            };

            const fetchOrders = async () => {
                ordersLoading.value = true;
                try { 
                    const r = await axios.get(`${params.api}/orders`, {headers:{'X-WP-Nonce':params.nonce}}); 
                    recentOrders.value = r.data; 
                } catch(e){} finally { ordersLoading.value = false; }
            };

            const add = (p) => { if(p.stock_status==='outofstock') return alert('Stok Habis!'); const i=cart.value.find(x=>x.id===p.id); i?i.qty++:cart.value.push({...p, qty:1}); };
            const rem = (i) => cart.value = cart.value.filter(x=>x.id!==i.id);
            const qty = (i,d) => { i.qty+=d; if(i.qty<=0) rem(i); };
            const clearCart = () => confirm('Hapus keranjang?') ? cart.value=[] : null;
            const setCategory = (s) => { curCat.value=s; find(); };

            const openScanner = () => {
                showScanner.value = true;
                nextTick(() => {
                    if(typeof Html5Qrcode === 'undefined') { alert('Scanner lib belum dimuat'); return; }
                    html5QrCode.value = new Html5Qrcode("reader");
                    html5QrCode.value.start({ facingMode: "environment" }, { fps: 10, qrbox: { width: 250, height: 250 } },
                    (decodedText) => { search.value = decodedText; closeScanner(); },
                    (e) => { /* ignore */ }).catch(err => { console.error(err); alert("Kamera error/blokir"); closeScanner(); });
                });
            };

            const closeScanner = () => {
                if(html5QrCode.value) { html5QrCode.value.stop().then(()=>{ html5QrCode.value.clear(); showScanner.value=false; }).catch(()=>{ showScanner.value=false; }); }
                else { showScanner.value=false; }
            };

            const checkout = async () => {
                processing.value=true;
                try {
                    const pl = { items:cart.value, payment_method:method.value, amount_tendered:paid.value, change:change.value };
                    const r = await axios.post(`${params.api}/order`, pl, {headers:{'X-WP-Nonce':params.nonce}});
                    if(r.data.success) {
                        lastReceipt.value = { ...r.data, items:[...cart.value], grandTotal:grandTotal.value, paymentMethod:method.value, cashReceived:paid.value, cashChange:change.value, cashier:activeCashier.value };
                        setTimeout(() => {
                            const width = config.value.printer_width || '58mm';
                            const w = window.open('','','width=400,height=600');
                            w.document.write(`<html><head><style>body{margin:0} .receipt{width:${width}}</style></head><body>${document.getElementById('receipt-print').innerHTML}</body></html>`);
                            w.document.close(); w.focus(); w.print();
                        }, 300);
                        cart.value=[]; paid.value=''; modal.value=false;
                    }
                } catch(e){ alert("Gagal: "+(e.response?.data?.message || e.message)); } finally { processing.value=false; }
            };

            onMounted(async () => {
                try { 
                    if((await db.prod.count())===0) await sync(); else await find(); 
                } catch(e) { console.error("Init Error", e); }
                
                // Remove loader safely
                const loader = document.getElementById('app-loading');
                if(loader) { loader.style.opacity='0'; setTimeout(()=>loader.remove(),500); }
                
                window.addEventListener('keydown', e => { if(e.key==='F3'){ e.preventDefault(); document.querySelector('input[type=text]')?.focus(); } });
            });

            watch([search, curCat], find);
            watch(modal, (v) => { if(v && method.value==='cash') nextTick(()=>cashInput.value?.focus()); });

            return { config, products, categories, cart, recentOrders, curCat, search, loading, syncing, ordersLoading, viewMode, activeCashier, showCart, modal, method, paid, processing, cashInput, grandTotal, change, quickCash, cartTotalQty, fmt, sync, setCategory, fetchOrders, add, rem, qty, clearCart, setView:(m)=>{viewMode.value=m;}, openPayModal:()=>modal.value=true, checkout, showScanner, openScanner, closeScanner };
        }
    }).mount('#app');
}