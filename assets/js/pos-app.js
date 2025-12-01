
const { createApp, ref, computed, onMounted, nextTick, watch } = Vue;
const db = new Dexie("KresuberDB_V4");
db.version(1).stores({ prod: "id, sku, barcode, cat, search" });

createApp({
    setup() {
        const config=ref(params.conf||{}), products=ref([]), categories=ref([]), cart=ref([]), recentOrders=ref([]);
        const curCat=ref('all'), search=ref(''), loading=ref(true), syncing=ref(false), ordersLoading=ref(false);
        const viewMode=ref('pos'), showMobileCart=ref(false), showCart=ref(false), modal=ref(false), showScanner=ref(false);
        const method=ref('cash'), paid=ref(''), processing=ref(false), cashInput=ref(null), html5QrCode=ref(null);

        // FIX: Ensure paid.value defaults to 0 string if empty to prevent NaN
        const subTotal = computed(() => cart.value.reduce((s,i)=>s+(i.price*i.qty),0));
        const grandTotal = computed(() => subTotal.value); // No Tax
        const change = computed(() => {
            const p = parseInt(paid.value) || 0;
            return p - grandTotal.value;
        });
        const quickCash = computed(() => [10000, 20000, 50000, 100000].filter(a => a >= grandTotal.value).slice(0, 3));
        const cartTotalQty = computed(() => cart.value.reduce((a, i) => a + i.qty, 0));
        const fmt = (v) => params.curr + ' ' + new Intl.NumberFormat('id-ID').format(v);

        const sync = async () => {
            syncing.value=true; loading.value=true;
            try {
                const r = await axios.get(`${params.api}/products`, {headers:{'X-WP-Nonce':params.nonce}});
                const items = r.data.map(p => ({...p, search:`${p.name} ${p.sku} ${p.barcode}`.toLowerCase(), cat:p.category_slug}));
                // Rebuild categories based on incoming data
                const cats = {}; items.forEach(i => cats[i.cat]={slug:i.cat, name:i.category_name});
                categories.value = Object.values(cats);
                await db.prod.clear(); await db.prod.bulkAdd(items);
                find();
            } catch(e){ alert("Sync Gagal"); } finally { syncing.value=false; loading.value=false; }
        };

        const find = async () => {
            let c = db.prod.toCollection();
            // FIX: Ensure curCat works correctly
            if(curCat.value!=='all') c = db.prod.where('cat').equals(curCat.value);
            const q = search.value.toLowerCase().trim();
            if(q) {
                const ex = await db.prod.where('sku').equals(q).or('barcode').equals(q).first();
                if(ex) { add(ex); search.value=''; return; }
                const all = await c.toArray();
                products.value = all.filter(p => p.search.includes(q)).slice(0, 60);
            } else { 
                products.value = await c.limit(60).toArray();
                if(!categories.value.length && products.value.length) {
                     const all = await db.prod.toArray(); const k = {}; all.forEach(i=>k[i.cat]={slug:i.cat,name:i.category_name}); categories.value=Object.values(k);
                }
            }
        };

        const fetchOrders = async () => {
            ordersLoading.value = true;
            try { const r = await axios.get(`${params.api}/orders`, {headers:{'X-WP-Nonce':params.nonce}}); recentOrders.value = r.data; }
            catch(e){} finally { ordersLoading.value = false; }
        };

        const add = (p) => { if(p.stock_status==='outofstock') return alert('Habis!'); const i=cart.value.find(x=>x.id===p.id); i?i.qty++:cart.value.push({...p, qty:1}); };
        const rem = (i) => cart.value = cart.value.filter(x=>x.id!==i.id);
        const qty = (i,d) => { i.qty+=d; if(i.qty<=0) rem(i); };
        const clearCart = () => confirm('Hapus keranjang?') ? cart.value=[] : null;

        // --- CAMERA SCANNER LOGIC ---
        const openScanner = () => {
            showScanner.value = true;
            nextTick(() => {
                html5QrCode.value = new Html5Qrcode("reader");
                html5QrCode.value.start({ facingMode: "environment" }, { fps: 10, qrbox: { width: 250, height: 250 } },
                (decodedText) => {
                    // Success callback
                    search.value = decodedText; // Will trigger find() and auto-add
                    closeScanner();
                },
                (errorMessage) => { /* ignore failures */ })
                .catch(err => console.error(err));
            });
        };

        const closeScanner = () => {
            if(html5QrCode.value) { html5QrCode.value.stop().then(()=>{ html5QrCode.value.clear(); showScanner.value=false; }); }
            else { showScanner.value=false; }
        };

        const checkout = async () => {
            processing.value=true;
            try {
                const pl = { items:cart.value, payment_method:method.value, amount_tendered:paid.value, change:change.value };
                const r = await axios.post(`${params.api}/order`, pl, {headers:{'X-WP-Nonce':params.nonce}});
                if(r.data.success) {
                    alert("Transaksi Sukses #" + r.data.order_number);
                    cart.value=[]; paid.value=''; modal.value=false;
                }
            } catch(e){ alert("Gagal: "+e.message); } finally { processing.value=false; }
        };

        const setCategory = (s) => { curCat.value = s; find(); };

        onMounted(async () => {
            try { if((await db.prod.count())===0) await sync(); else await find(); } catch(e) { console.error(e); }
            document.getElementById('app-loading').style.display='none';
            window.addEventListener('keydown', e => { if(e.key==='F3'){ e.preventDefault(); document.querySelector('input[type=text]')?.focus(); } });
        });

        watch([search, curCat], find);
        watch(modal, (v) => { if(v && method.value==='cash') nextTick(()=>cashInput.value?.focus()); });

        return { config, products, categories, cart, recentOrders, curCat, search, loading, syncing, ordersLoading, viewMode, showMobileCart, showCart, modal, method, paid, processing, cashInput, grandTotal, change, quickCash, cartTotalQty, fmt, sync, setCategory, fetchOrders, add, rem, qty, clearCart, setView:(m)=>{viewMode.value=m;}, openPayModal:()=>modal.value=true, checkout, showScanner, openScanner, closeScanner };
    }
}).mount('#app');
