
const { createApp, ref, computed, onMounted, watch, nextTick } = Vue;
const db = new Dexie("KresuberDB_v1_9");
db.version(1).stores({ products: "id, sku, barcode, category_slug, search_terms" });

createApp({
    setup() {
        const config = ref(wpData.config || {});
        const products = ref([]), categories = ref([]), cart = ref([]);
        const loading = ref(true), syncing = ref(false), search = ref(""), curCat = ref("all");
        const showMobileCart = ref(false), showPaymentModal = ref(false), qrisZoom = ref(false);
        const showHistory = ref(false), historyOrders = ref([]), loadingHistory = ref(false);
        const manageMode = ref(false), showProductModal = ref(false), isProcessing = ref(false), saving = ref(false);
        const showScanner = ref(false);
        let html5QrCode = null;

        // Payment
        const paymentMethod = ref('cash'), amountPaid = ref('');
        const debtForm = ref({ name: '', phone: '' });
        
        // Edit Form
        const editingProduct = ref(null);
        const form = ref({ name:'', price:'', cost_price:'', stock:'', category:'', icon:'', sku:'', barcode:'', image_url:'', image_preview:'' });

        // --- UPLOAD IMAGE LOGIC (Compress & Square) ---
        const handleImageUpload = async (e) => {
            const file = e.target.files[0];
            if (!file) return;

            // Preview local first
            form.value.image_preview = URL.createObjectURL(file);
            
            // Compress logic
            saving.value = true;
            try {
                const compressedBlob = await compressImage(file);
                const formData = new FormData();
                formData.append('file', compressedBlob, 'prod-' + Date.now() + '.jpg');
                
                const res = await axios.post(`${wpData.api}/upload`, formData, {
                    headers: { 'X-WP-Nonce': wpData.nonce, 'Content-Type': 'multipart/form-data' }
                });
                
                if (res.data.success) {
                    form.value.image_url = res.data.url;
                    form.value.image_preview = res.data.url;
                }
            } catch(err) {
                alert("Gagal upload gambar: " + err.message);
            } finally { saving.value = false; }
        };

        const compressImage = (file) => {
            return new Promise((resolve, reject) => {
                const img = new Image();
                img.src = URL.createObjectURL(file);
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    const size = 600; // Resize to 600px square
                    canvas.width = size;
                    canvas.height = size;
                    const ctx = canvas.getContext('2d');
                    ctx.fillStyle = "#FFFFFF";
                    ctx.fillRect(0, 0, size, size); // White bg

                    // Calculate crop
                    const minDim = Math.min(img.width, img.height);
                    const sx = (img.width - minDim) / 2;
                    const sy = (img.height - minDim) / 2;
                    
                    ctx.drawImage(img, sx, sy, minDim, minDim, 0, 0, size, size);
                    canvas.toBlob((blob) => resolve(blob), 'image/jpeg', 0.8);
                };
                img.onerror = reject;
            });
        };

        // --- SCANNER LOGIC ---
        const openScanner = () => {
            showScanner.value = true;
            nextTick(() => {
                html5QrCode = new Html5Qrcode("qr-reader");
                html5QrCode.start({ facingMode: "environment" }, { fps: 10, qrbox: 250 },
                    (decodedText) => {
                        // Success
                        html5QrCode.stop().then(() => {
                            showScanner.value = false;
                            handleScannedCode(decodedText);
                        });
                    },
                    (error) => {}
                );
            });
        };

        const closeScanner = () => {
            if(html5QrCode) html5QrCode.stop().then(()=>showScanner.value=false);
            else showScanner.value=false;
        };

        const handleScannedCode = async (code) => {
            // Cek di DB
            const p = await db.products.where('barcode').equals(code).or('sku').equals(code).first();
            if (p) {
                add(p);
                alert(`Produk Ditemukan: ${p.name}`);
            } else {
                if(confirm(`Kode ${code} belum terdaftar. Tambah produk baru?`)) {
                    openProductModal();
                    form.value.barcode = code;
                    form.value.sku = code;
                }
            }
        };

        // --- HISTORY ---
        const openHistory = async () => {
            showHistory.value = true;
            loadingHistory.value = true;
            try {
                const res = await axios.get(`${wpData.api}/orders`, { headers: { 'X-WP-Nonce': wpData.nonce } });
                historyOrders.value = res.data;
            } catch(e) { alert("Gagal ambil data history"); }
            finally { loadingHistory.value = false; }
        };

        // --- CRUD & ICONS ---
        const getIcon = (p) => {
            if (p.icon_override) return p.icon_override;
            const txt = (p.name + ' ' + (p.category_name||'')).toLowerCase();
            if (txt.includes('transfer') || txt.includes('dana') || txt.includes('ovo')) return 'ri-wallet-3-fill';
            if (txt.includes('pln') || txt.includes('token') || txt.includes('listrik')) return 'ri-flashlight-fill';
            if (txt.includes('kopi') || txt.includes('minum')) return 'ri-cup-fill';
            return 'ri-box-3-line';
        };

        const handleProductClick = (p) => {
            if (manageMode.value) {
                editingProduct.value = p;
                form.value = { ...p, image_preview: p.image || '', image_url: p.image || '' };
                showProductModal.value = true;
            } else add(p);
        };

        const openProductModal = () => {
            editingProduct.value = null;
            form.value = { name:'', price:'', cost_price:'', stock:'', category:'', icon:'', sku:'', barcode:'', image_url:'', image_preview:'' };
            showProductModal.value = true;
        };
        const closeProductModal = () => showProductModal.value = false;

        const saveProduct = async () => {
            if(!form.value.name || !form.value.price) return alert("Nama dan Harga wajib diisi!");
            saving.value = true;
            try {
                const payload = { ...form.value, id: editingProduct.value ? editingProduct.value.id : 0 };
                const res = await axios.post(`${wpData.api}/product`, payload, { headers: { 'X-WP-Nonce': wpData.nonce } });
                if(res.data.success) { await sync(); closeProductModal(); alert("Produk tersimpan!"); }
            } catch(e) { alert("Gagal: "+e.message); } finally { saving.value = false; }
        };

        const deleteProduct = async () => {
            if(!confirm("Hapus permanen?")) return;
            try {
                await axios.delete(`${wpData.api}/product/${editingProduct.value.id}`, { headers: { 'X-WP-Nonce': wpData.nonce } });
                await sync(); closeProductModal();
            } catch(e) { alert("Gagal"); }
        };

        const sync = async () => {
            syncing.value = true; loading.value = true;
            try {
                const res = await axios.get(`${wpData.api}/products`, { headers: { 'X-WP-Nonce': wpData.nonce } });
                const cleanData = res.data.map(p => ({
                    ...p, price: parseFloat(p.price)||0,
                    search_terms: `${p.name} ${p.sku} ${p.barcode} ${p.category_name}`.toLowerCase()
                }));
                await db.products.clear(); await db.products.bulkAdd(cleanData);
                const uniqueCats = [...new Set(cleanData.map(p => p.category_name).filter(c => c && c !== 'Lainnya'))];
                categories.value = uniqueCats.map(c => ({ name: c, slug: c.toLowerCase().replace(/\s+/g, '-') }));
                findProducts();
            } catch(e) { console.error(e); } finally { syncing.value = false; loading.value = false; }
        };

        const findProducts = async () => {
            let col = db.products.toCollection();
            if (curCat.value !== 'all') col = db.products.filter(p => p.category_name.toLowerCase().replace(/\s+/g, '-') === curCat.value);
            const q = search.value.toLowerCase().trim();
            if (q) {
                const all = await col.toArray();
                products.value = all.filter(p => p.search_terms.includes(q));
            } else products.value = await col.limit(100).toArray();
        };

        const cartTotal = computed(() => cart.value.reduce((a,i) => a + (parseFloat(i.price)*i.qty), 0));
        const changeAmount = computed(() => (parseFloat(amountPaid.value)||0) - cartTotal.value);
        const cartTotalQty = computed(() => cart.value.reduce((a,i) => a + i.qty, 0));
        const add = (p) => { 
            if(p.stock!==null && p.stock<=0) return alert("Habis!"); 
            const x = cart.value.find(i=>i.id===p.id); x?x.qty++:cart.value.push({...p, qty:1}); 
        };
        const updateQty = (i,d) => { i.qty+=d; if(i.qty<=0) cart.value = cart.value.filter(x=>x.id!==i.id); };
        const clearCart = () => confirm('Reset?')?cart.value=[]:null;

        const processPayment = async () => {
            // Validasi Hutang
            if (paymentMethod.value === 'debt') {
                if (!debtForm.value.name || !debtForm.value.phone) return alert("Nama dan No WA wajib diisi untuk hutang!");
            }
            // Konfirmasi Pembayaran
            if (paymentMethod.value !== 'debt') {
                if (!confirm("Apakah pembayaran sudah diterima dengan benar?")) return;
            }

            isProcessing.value = true;
            try {
                const payload = { 
                    items: cart.value.map(i=>({id:i.id, qty:i.qty})), 
                    payment_method: paymentMethod.value,
                    debt_name: debtForm.value.name,
                    debt_phone: debtForm.value.phone
                };
                const res = await axios.post(`${wpData.api}/order`, payload, { headers: { 'X-WP-Nonce': wpData.nonce } });
                if(res.data.success) { 
                    alert("Sukses! Ref: "+res.data.order_number); 
                    cart.value=[]; showPaymentModal.value=false; debtForm.value={name:'',phone:''};
                }
            } catch(e) { alert("Error"); } finally { isProcessing.value = false; }
        };

        const formatRupiah = (n) => 'Rp ' + new Intl.NumberFormat('id-ID').format(n);
        
        watch([search, curCat], findProducts);
        onMounted(async () => { if((await db.products.count())===0) await sync(); else await findProducts(); });

        return { config, products, categories, cart, loading, syncing, search, curCat, showMobileCart, showPaymentModal, manageMode, showProductModal, isProcessing, saving, paymentMethod, amountPaid, editingProduct, form, cartTotal, changeAmount, cartTotalQty, debtForm, qrisZoom, showScanner, showHistory, historyOrders, loadingHistory,
        getIcon, handleProductClick, openProductModal, closeProductModal, saveProduct, deleteProduct, sync, setCategory: (s)=>{curCat.value=s}, add, updateQty, removeItem:(i)=>updateQty(i, -999), clearCart, openPayment:()=>showPaymentModal.value=true, processPayment, formatRupiah, handleImageUpload, openScanner, closeScanner, openHistory };
    }
}).mount('#app');
