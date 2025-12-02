
// Safety Check
if (typeof Vue === 'undefined') {
    document.getElementById('static-loader').innerHTML = '<h3 class="text-red-500 text-center font-bold p-10">Gagal memuat VueJS. Cek Koneksi Internet.</h3>';
    throw new Error("Vue not loaded");
}

const { createApp, ref, computed, onMounted, watch, nextTick } = Vue;
const db = new Dexie("KresuberDB_v1_9_3");
db.version(1).stores({ products: "id, sku, barcode, category_slug, search_terms" });

// Loader Helper
const updateLoader = (pct, text) => {
    const elBar = document.getElementById('loader-bar');
    const elText = document.getElementById('loader-text');
    if(elBar) elBar.style.width = pct + '%';
    if(elText && text) elText.innerText = text + ' ' + pct + '%';
};

createApp({
    setup() {
        const config = ref(wpData.config || {});
        const products = ref([]), categories = ref([]), cart = ref([]);
        const loading = ref(true), syncing = ref(false), search = ref(""), curCat = ref("all");
        
        // Modals & UI
        const showMobileCart = ref(false), showPaymentModal = ref(false), qrisZoom = ref(false);
        const showHistory = ref(false), showRegister = ref(false), showScanner = ref(false);
        const manageMode = ref(false), showProductModal = ref(false);
        const isProcessing = ref(false), saving = ref(false), activeTab = ref('info');
        
        // Transaction Data
        const historyOrders = ref([]), loadingHistory = ref(false);
        const registerCash = ref(0), registerResult = ref(null);
        let html5QrCode = null;

        // Forms
        const paymentMethod = ref('cash'), amountPaid = ref('');
        const debtForm = ref({ name: '', phone: '' });
        const paymentSuccess = ref(false), lastOrder = ref({});
        const editingProduct = ref(null);
        const form = ref({ name:'', price:'', cost_price:'', stock:'', category:'', icon:'', sku:'', barcode:'', unit:'Pcs', image_url:'', image_preview:'', wholesale:[] });

        // --- INIT DATA WITH PROGRESS ---
        onMounted(async () => {
            updateLoader(10, "Menyiapkan Database...");
            await new Promise(r => setTimeout(r, 300)); // Smooth start
            
            try { 
                if((await db.products.count()) === 0) {
                    await sync(); 
                } else {
                    updateLoader(80, "Memuat Data Lokal...");
                    await findProducts();
                    updateLoader(100, "Selesai!");
                }
            } catch(e) { console.error(e); }
            
            setTimeout(() => {
                const loader = document.getElementById('static-loader');
                if(loader) {
                    loader.style.transition = 'opacity 0.5s';
                    loader.style.opacity = '0';
                    setTimeout(() => loader.style.display = 'none', 500);
                }
            }, 800);
        });

        // --- SYNC WITH PROGRESS BAR ---
        const sync = async () => {
            syncing.value = true; loading.value = true;
            updateLoader(20, "Menghubungkan Server...");
            
            try {
                const res = await axios.get(`${wpData.api}/products`, { 
                    headers: { 'X-WP-Nonce': wpData.nonce },
                    onDownloadProgress: (progressEvent) => {
                        const total = progressEvent.total || (progressEvent.loaded * 1.2); // Fallback estimate
                        let percent = Math.round((progressEvent.loaded * 100) / total);
                        // Scale download progress to 20%-80% range
                        let scaled = 20 + Math.round(percent * 0.6);
                        updateLoader(scaled, "Mengunduh Produk...");
                    }
                });

                updateLoader(85, "Menyimpan Database...");
                
                const cleanData = res.data.map(p => ({ 
                    ...p, 
                    price: parseFloat(p.price)||0, 
                    search_terms: `${p.name} ${p.sku} ${p.barcode} ${p.category_name}`.toLowerCase() 
                }));
                
                await db.products.clear(); 
                await db.products.bulkAdd(cleanData);
                
                updateLoader(95, "Finalisasi...");
                
                const uniqueCats = [...new Set(cleanData.map(p => p.category_name).filter(c => c && c !== 'Lainnya'))];
                categories.value = uniqueCats.map(c => ({ name: c, slug: c.toLowerCase().replace(/\s+/g, '-') }));
                
                findProducts();
                updateLoader(100, "Siap!");
                
            } catch(e) { 
                alert("Gagal Sync: " + e.message); 
            } finally { 
                syncing.value = false; 
                loading.value = false; 
            }
        };

        const findProducts = async () => {
            let col = db.products.toCollection();
            if (curCat.value !== 'all') col = db.products.filter(p => p.category_name.toLowerCase().replace(/\s+/g, '-') === curCat.value);
            const q = search.value.toLowerCase().trim();
            if (q) { const all = await col.toArray(); products.value = all.filter(p => p.search_terms.includes(q)); } else products.value = await col.limit(100).toArray();
        };

        // --- CORE FUNCTIONS ---
        const getIcon = (p) => {
            if (p.icon_override) return p.icon_override;
            const txt = (p.name + ' ' + (p.category_name||'')).toLowerCase();
            if (txt.includes('kopi')) return 'ri-cup-fill';
            if (txt.includes('mie')) return 'ri-restaurant-fill';
            if (txt.includes('top up') || txt.includes('dana')) return 'ri-smartphone-fill';
            return 'ri-box-3-line';
        };

        const getItemPrice = (item) => {
            if(!item.wholesale || item.wholesale.length === 0) return item.price;
            const applicableRules = item.wholesale.filter(rule => item.qty >= rule.min);
            if(applicableRules.length > 0) {
                applicableRules.sort((a,b) => b.min - a.min);
                return applicableRules[0].price;
            }
            return item.price;
        };

        const isWholesaleApplied = (item) => getItemPrice(item) < item.price;
        const cartTotal = computed(() => cart.value.reduce((acc, item) => acc + (getItemPrice(item) * item.qty), 0));
        const cartTotalQty = computed(() => cart.value.reduce((a,i) => a + i.qty, 0));
        const changeAmount = computed(() => (parseFloat(amountPaid.value)||0) - cartTotal.value);

        // --- CART ---
        const add = (p) => { 
            if(p.stock!==null && p.stock<=0) return alert("Stok Habis!"); 
            const x = cart.value.find(i=>i.id===p.id); 
            if(x) x.qty++; else cart.value.push({...p, qty:1}); 
        };
        const updateQty = (i,d) => { i.qty+=d; if(i.qty<=0) cart.value = cart.value.filter(x=>x.id!==i.id); };
        const removeItem = (i) => updateQty(i, -999);
        const clearCart = () => confirm('Reset keranjang?')?cart.value=[]:null;

        // --- PAYMENT ---
        const openPayment = () => { paymentSuccess.value=false; showPaymentModal.value=true; };
        const processPayment = async () => {
            if (paymentMethod.value === 'debt' && (!debtForm.value.name || !debtForm.value.phone)) return alert("Isi Nama & WA!");
            isProcessing.value = true;
            try {
                const itemsPayload = cart.value.map(i => ({ id: i.id, qty: i.qty, price: getItemPrice(i) }));
                const payload = { items: itemsPayload, payment_method: paymentMethod.value, debt_name: debtForm.value.name, debt_phone: debtForm.value.phone };
                const res = await axios.post(`${wpData.api}/order`, payload, { headers: { 'X-WP-Nonce': wpData.nonce } });
                if(res.data.success) { lastOrder.value = res.data; paymentSuccess.value = true; cart.value=[]; debtForm.value={name:'',phone:''}; }
            } catch(e) { alert("Gagal Transaksi: " + e.message); } finally { isProcessing.value = false; }
        };
        const resetPayment = () => { showPaymentModal.value = false; paymentSuccess.value = false; };
        const generateWALink = (order) => {
            if(!order || !order.total) return '#';
            let text = `*STRUK BELANJA ${config.value.site_name}*%0A` + `No: #${order.order_number}%0ATotal: *${formatRupiah(order.total)}*%0A` + `Terima Kasih!`;
            let phone = debtForm.value.phone || ''; if(phone.startsWith('0')) phone = '62' + phone.slice(1);
            return `https://wa.me/${phone}?text=${text}`;
        };

        // --- REGISTER ---
        const openRegister = () => { registerCash.value=0; registerResult.value=null; showRegister.value=true; };
        const closeRegister = async () => {
            try { const res = await axios.post(`${wpData.api}/close-register`, { actual_cash: registerCash.value }, { headers: { 'X-WP-Nonce': wpData.nonce } }); registerResult.value = res.data; } catch(e) { alert("Error"); }
        };

        // --- HISTORY ---
        const openHistory = async () => {
            showHistory.value = true; loadingHistory.value = true;
            try { const res = await axios.get(`${wpData.api}/orders`, { headers: { 'X-WP-Nonce': wpData.nonce } }); historyOrders.value = res.data; } catch(e) {} finally { loadingHistory.value = false; }
        };
        const payDebt = async (order) => {
            const amount = prompt(`Sisa Hutang: ${formatRupiah(order.debt_remaining)}\nMasukkan jumlah bayar:`);
            if(!amount) return;
            try { const res = await axios.post(`${wpData.api}/pay-debt`, { order_id: order.id, amount: amount }, { headers: { 'X-WP-Nonce': wpData.nonce } }); if(res.data.success) { alert("Lunas/Terbayar!"); openHistory(); } } catch(e) { alert("Gagal"); }
        };

        // --- CRUD ---
        const handleImageUpload = async (e) => {
            const file = e.target.files[0]; if (!file) return;
            form.value.image_preview = URL.createObjectURL(file); saving.value = true;
            try {
                const formData = new FormData(); formData.append('file', file);
                const res = await axios.post(`${wpData.api}/upload`, formData, { headers: { 'X-WP-Nonce': wpData.nonce, 'Content-Type': 'multipart/form-data' } });
                if(res.data.success) { form.value.image_url = res.data.url; form.value.image_preview = res.data.url; }
            } catch(e) { alert("Upload fail"); } finally { saving.value=false; }
        };
        const openProductModal = () => { editingProduct.value = null; activeTab.value='info'; form.value = { name:'', price:'', cost_price:'', stock:'', category:'', icon:'', sku:'', barcode:'', unit:'Pcs', wholesale:[] }; showProductModal.value = true; };
        const handleProductClick = (p) => { if(manageMode.value) { editingProduct.value = p; form.value = { ...p, image_preview: p.image||'', wholesale: p.wholesale || [] }; showProductModal.value = true; } else add(p); };
        const saveProduct = async () => {
            saving.value = true;
            try {
                const payload = { ...form.value, id: editingProduct.value ? editingProduct.value.id : 0 };
                const res = await axios.post(`${wpData.api}/product`, payload, { headers: { 'X-WP-Nonce': wpData.nonce } });
                if(res.data.success) { await sync(); showProductModal.value=false; }
            } catch(e) { alert("Err"); } finally { saving.value=false; }
        };
        const deleteProduct = async () => { if(!confirm("Hapus?")) return; try { await axios.delete(`${wpData.api}/product/${editingProduct.value.id}`, { headers: { 'X-WP-Nonce': wpData.nonce } }); await sync(); showProductModal.value=false; } catch(e) { alert("Gagal"); } };

        // --- SCANNER ---
        const openScanner = () => { showScanner.value=true; nextTick(() => { html5QrCode = new Html5Qrcode("qr-reader"); html5QrCode.start({ facingMode: "environment" }, { fps: 10, qrbox: 250 }, (txt) => { html5QrCode.stop(); showScanner.value=false; handleScannedCode(txt); }, (e)=>{}); }); };
        const closeScanner = () => { if(html5QrCode) html5QrCode.stop(); showScanner.value=false; };
        const handleScannedCode = async (code) => { const p = await db.products.where('barcode').equals(code).or('sku').equals(code).first(); if(p) { add(p); alert("Found: "+p.name); } else if(confirm("Kode baru. Tambah?")) { openProductModal(); form.value.barcode=code; form.value.sku=code; } };
        const formatRupiah = (n) => 'Rp ' + new Intl.NumberFormat('id-ID').format(n || 0);

        watch([search, curCat], findProducts);

        return { config, products, categories, cart, loading, syncing, search, curCat, showMobileCart, showPaymentModal, manageMode, showProductModal, isProcessing, saving, paymentMethod, amountPaid, editingProduct, form, cartTotal, changeAmount, cartTotalQty, debtForm, qrisZoom, showScanner, showHistory, historyOrders, loadingHistory, registerCash, showRegister, registerResult, paymentSuccess, lastOrder, activeTab,
        getIcon, handleProductClick, openProductModal, closeProductModal as closeProductModal, saveProduct, sync, setCategory: (s)=>{curCat.value=s}, add, updateQty, removeItem, clearCart, openPayment, processPayment, formatRupiah, handleImageUpload, openScanner, closeScanner, openHistory, payDebt, openRegister, closeRegister, resetPayment, generateWALink, getItemPrice, isWholesaleApplied, deleteProduct };
    }
}).mount('#app');
