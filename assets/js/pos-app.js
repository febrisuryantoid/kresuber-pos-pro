
const { createApp, ref, computed, onMounted, nextTick, watch } = Vue;
const db = new Dexie("KresuberDB_V2");
db.version(1).stores({ prod: "id, sku, barcode, cat, search" });

createApp({
    setup() {
        // State
        const config = ref(params.conf || {});
        const products = ref([]); const categories = ref([]); const cart = ref([]); const heldItems = ref([]);
        const curCat = ref('all'); const search = ref(''); const loading = ref(false); const syncing = ref(false);
        const activeCashier = ref(config.value.cashiers?.[0] || 'Default');
        const showMobileCart = ref(false); const showCart = ref(false); // Desktop/Mobile toggle
        const modal = ref(false); const method = ref('cash'); const paid = ref(''); const processing = ref(false); const cashInput = ref(null);
        const last = ref({});
        
        // Configs
        const taxRate = 11; // Default Tax
        const printerDevice = ref(null); // Bluetooth device

        // Computed
        const subTotal = computed(() => cart.value.reduce((s, i) => s + (i.price * i.qty), 0));
        const taxAmount = computed(() => Math.round(subTotal.value * (taxRate / 100)));
        const grandTotal = computed(() => subTotal.value + taxAmount.value);
        const change = computed(() => (parseInt(paid.value) || 0) - grandTotal.value);
        const quickCash = computed(() => [10000, 20000, 50000, 100000].filter(a => a >= grandTotal.value).slice(0, 3));
        const fmt = (v) => params.curr + ' ' + new Intl.NumberFormat('id-ID').format(v);

        // --- Core Functions ---
        const stopLoad = () => {
            const l = document.getElementById('app-loading');
            if(l) { l.style.opacity='0'; setTimeout(() => l.style.display='none', 500); }
        };

        const sync = async () => {
            syncing.value = true; loading.value = true;
            try {
                const r = await axios.get(`${params.api}/products`, { headers: { 'X-WP-Nonce': params.nonce } });
                const items = r.data.map(p => ({ ...p, search: `${p.name} ${p.sku} ${p.barcode}`.toLowerCase(), cat: p.category_slug }));
                
                const cats = {}; 
                items.forEach(i => cats[i.cat] = { slug: i.cat, name: i.category_name });
                categories.value = Object.values(cats);

                await db.prod.clear(); await db.prod.bulkAdd(items);
                find();
            } catch(e) { alert("Sync Gagal: " + e.message); } 
            finally { syncing.value = false; loading.value = false; }
        };

        const find = async () => {
            let c = db.prod.toCollection();
            if (curCat.value !== 'all') c = db.prod.where('cat').equals(curCat.value);
            const q = search.value.toLowerCase().trim();
            if (q) {
                const ex = await db.prod.where('sku').equals(q).or('barcode').equals(q).first();
                if (ex) { add(ex); search.value = ''; return; }
                const all = await c.toArray();
                products.value = all.filter(p => p.search.includes(q)).slice(0, 60);
            } else { 
                products.value = await c.limit(60).toArray(); 
                // Fix empty cats on reload
                if(!categories.value.length && products.value.length) {
                    const all = await db.prod.toArray(); const k = {}; all.forEach(i=>k[i.cat]={slug:i.cat,name:i.category_name}); categories.value=Object.values(k);
                }
            }
        };

        const add = (p) => {
            if (p.stock_status === 'outofstock') return alert('Stok Habis!');
            const i = cart.value.find(x => x.id === p.id); i ? i.qty++ : cart.value.push({ ...p, qty: 1 });
        };
        const rem = (i) => cart.value = cart.value.filter(x => x.id !== i.id);
        const qty = (i, d) => { i.qty += d; if (i.qty <= 0) rem(i); };
        const clearCart = () => confirm('Hapus keranjang?') ? cart.value = [] : null;
        
        const toggleHold = () => {
            if (heldItems.value.length) { if(cart.value.length && !confirm("Timpah keranjang?")) return; cart.value = [...heldItems.value]; heldItems.value = []; }
            else if (cart.value.length) { heldItems.value = [...cart.value]; cart.value = []; localStorage.setItem('kresuber_held', JSON.stringify(heldItems.value)); }
        };

        // --- Bluetooth Print Logic (Simple ESC/POS) ---
        const connectPrinter = async () => {
            try {
                printerDevice.value = await navigator.bluetooth.requestDevice({ filters: [{ services: ['000018f0-0000-1000-8000-00805f9b34fb'] }] });
                alert("Printer Connected: " + printerDevice.value.name);
            } catch(e) { console.log(e); alert("Bluetooth tidak didukung/gagal."); }
        };

        const checkout = async () => {
            processing.value = true;
            try {
                const pl = { items: cart.value, payment_method: method.value, amount_tendered: paid.value, change: change.value, cashier: activeCashier.value };
                const r = await axios.post(`${params.api}/order`, pl, { headers: { 'X-WP-Nonce': params.nonce } });
                if (r.data.success) {
                    last.value = { 
                        ...r.data, items: [...cart.value], total: total.value, grandTotal: grandTotal.value,
                        paymentMethod: method.value, cashReceived: paid.value, cashChange: change.value 
                    };
                    
                    // Print Handling
                    setTimeout(() => {
                        const w = window.open('', '', 'width=400,height=600');
                        w.document.write(`<html><head><style>body{margin:0} .receipt{width:${config.value.printer_width}}</style></head><body>${document.getElementById('receipt-print').innerHTML}</body></html>`);
                        w.document.close(); w.focus(); w.print();
                    }, 300);

                    cart.value = []; paid.value = ''; modal.value = false; showMobileCart.value = false;
                }
            } catch(e) { alert("Gagal: " + (e.response?.data?.message || e.message)); } 
            finally { processing.value = false; }
        };

        const setCat = (s) => { curCat.value = s; find(); };

        onMounted(async () => {
            try {
                const saved = localStorage.getItem('kresuber_held'); if (saved) heldItems.value = JSON.parse(saved);
                const c = await db.prod.count();
                if (c === 0) await sync(); else await find();
            } catch (e) { console.error(e); } 
            finally { stopLoad(); }
            window.addEventListener('keydown', e => { if (e.key === 'F3') { e.preventDefault(); document.querySelector('input[type=text]')?.focus(); } });
        });

        watch([search, curCat], find);
        watch(modal, (v) => { if (v && method.value === 'cash') nextTick(() => cashInput.value?.focus()); });

        return { 
            config, products, categories, cart, heldItems, curCat, search, loading, syncing, 
            showMobileCart, showCart, modal, method, paid, processing, cashInput, last, activeCashier,
            total, change, grandTotal, taxAmount, quickCash, cartTotalQty, fmt,
            sync, setCat, add, rem, qty, clearCart, toggleHold, checkout, connectPrinter
        };
    }
}).mount('#app');
