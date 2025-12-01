
const { createApp, ref, computed, onMounted, nextTick, watch } = Vue;
const db = new Dexie("KresuberDB");
db.version(1).stores({ prod: "id, sku, barcode, cat, search" });

createApp({
    setup() {
        const config=ref(params.conf||{}), prod=ref([]), cat=ref([]), cart=ref([]), orders=ref([]);
        const curCat=ref('all'), search=ref(''), loading=ref(false), syncing=ref(false);
        const viewMode=ref('pos'), showCart=ref(false), modal=ref(false);
        const method=ref('cash'), paid=ref(''), processing=ref(false), cashInput=ref(null), receipt=ref({});

        const total = computed(() => cart.value.reduce((s,i)=>s+(i.price*i.qty),0));
        const change = computed(() => (parseInt(paid.value)||0)-total.value);
        const fmt = (v) => params.curr + ' ' + new Intl.NumberFormat('id-ID').format(v);

        const stopLoad = () => { const l=document.getElementById('app-loading'); if(l){l.style.opacity='0'; setTimeout(()=>l.style.display='none',500);} };

        const sync = async () => {
            syncing.value=true; loading.value=true;
            try {
                const r = await axios.get(`${params.api}/products`, {headers:{'X-WP-Nonce':params.nonce}});
                const items = r.data.map(p => ({...p, search:`${p.name} ${p.sku} ${p.barcode}`.toLowerCase(), cat:p.category_slug}));
                const cats = {}; items.forEach(i => cats[i.cat]={slug:i.cat, name:i.category_name});
                cat.value = Object.values(cats);
                await db.prod.clear(); await db.prod.bulkAdd(items);
                find();
            } catch(e){ alert("Sync Gagal"); } finally { syncing.value=false; loading.value=false; }
        };

        const find = async () => {
            let c = db.prod.toCollection();
            if(curCat.value!=='all') c = db.prod.where('cat').equals(curCat.value);
            const q = search.value.toLowerCase().trim();
            if(q) {
                const ex = await db.prod.where('sku').equals(q).or('barcode').equals(q).first();
                if(ex) { add(ex); search.value=''; return; }
                const all = await c.toArray();
                prod.value = all.filter(p => p.search.includes(q)).slice(0,50);
            } else { prod.value = await c.limit(50).toArray(); }
            
            // Fallback cat load
            if(!cat.value.length && prod.value.length) {
                 const all = await db.prod.toArray(); const cats = {}; all.forEach(i => cats[i.cat]={slug:i.cat, name:i.category_name});
                 cat.value = Object.values(cats);
            }
        };

        const fetchOrders = async () => {
            loading.value=true; 
            try { const r=await axios.get(`${params.api}/orders`, {headers:{'X-WP-Nonce':params.nonce}}); orders.value=r.data; }
            catch(e){} finally{ loading.value=false; }
        };

        const add = (p) => { 
            if(p.stock_status==='outofstock') return alert('Habis');
            const i = cart.value.find(x=>x.id===p.id); i ? i.qty++ : cart.value.push({...p, qty:1}); 
        };
        const rem = (i) => cart.value = cart.value.filter(x=>x.id!==i.id);
        const qty = (i,d) => { i.qty+=d; if(i.qty<=0) rem(i); };
        const clearCart = () => confirm('Hapus?') ? cart.value=[] : null;
        const toggleHold = () => {}; // Placeholder

        const checkout = async () => {
            processing.value=true;
            try {
                const pl = { items:cart.value, payment_method:method.value, amount_tendered:paid.value, change:change.value };
                const r = await axios.post(`${params.api}/order`, pl, {headers:{'X-WP-Nonce':params.nonce}});
                if(r.data.success) {
                    receipt.value = { ...r.data, items:[...cart.value], total:total.value, paymentMethod:method.value, cashReceived:paid.value, cashChange:change.value, cashier:config.value.cashiers?.[0]||'Kasir' };
                    setTimeout(() => {
                        const w = window.open('','','width=400,height=600');
                        w.document.write(`<html><head><style>body{margin:0;font-family:monospace;font-size:12px}hr{border-top:1px dashed #000}</style></head><body>${document.getElementById('print').innerHTML}</body></html>`);
                        w.document.close(); w.focus(); w.print();
                    }, 300);
                    cart.value=[]; paid.value=''; modal.value=false;
                }
            } catch(e){ alert(e.message); } finally { processing.value=false; }
        };

        const setCategory = (s) => { curCat.value=s; find(); };

        onMounted(async () => {
            try { if((await db.prod.count())===0) await sync(); else await find(); } 
            catch(e){ console.error(e); } 
            finally { stopLoad(); }
            window.addEventListener('keydown', e => { if(e.key==='F3'){ e.preventDefault(); document.querySelector('input[type=text]')?.focus(); } });
        });

        watch([search, curCat], find);
        watch(modal, (v) => { if(v && method.value==='cash') nextTick(()=>cashInput.value?.focus()); });

        return { config, products:prod, categories:cat, cart, orders, currentCategory:curCat, searchQuery:search, loading, syncing, showCart, modal, method, paid, processing, cashInput, total, change, fmt, sync, setCategory, fetchOrders, add, rem, qty, clearCart, checkout, receipt, toggleHold };
    }
}).mount('#app');
