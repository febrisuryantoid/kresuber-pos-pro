const { createApp } = Vue;

createApp({
    data() {
        return {
            products: [],
            cart: [],
            searchQuery: '',
            currentCategory: 'all',
            loading: false,
            processing: false,
            paymentMethod: 'cod',
            currency: kresuberParams.currency
        }
    },
    computed: {
        subTotal() {
            return this.cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
        }
    },
    mounted() {
        this.fetchProducts();
    },
    methods: {
        formatPrice(value) {
            return this.currency + ' ' + new Intl.NumberFormat('id-ID').format(value);
        },
        
        async fetchProducts() {
            this.loading = true;
            try {
                const response = await axios.get(`${kresuberParams.apiUrl}/products`, {
                    params: {
                        search: this.searchQuery,
                        category: this.currentCategory
                    },
                    headers: { 'X-WP-Nonce': kresuberParams.nonce }
                });
                this.products = response.data;
            } catch (error) {
                console.error("Gagal mengambil produk", error);
                alert("Gagal koneksi ke server.");
            } finally {
                this.loading = false;
            }
        },

        debouncedSearch: _.debounce(function() {
            this.fetchProducts();
        }, 500),

        addToCart(product) {
            const existing = this.cart.find(item => item.id === product.id);
            if (existing) {
                existing.qty++;
            } else {
                this.cart.push({ ...product, qty: 1 });
            }
            this.playBeep();
        },

        increaseQty(item) {
            item.qty++;
        },

        decreaseQty(item) {
            if (item.qty > 1) {
                item.qty--;
            } else {
                this.cart = this.cart.filter(i => i.id !== item.id);
            }
        },

        clearCart() {
            if(confirm('Kosongkan keranjang?')) {
                this.cart = [];
            }
        },

        playBeep() {
            // Optional: Tambahkan suara beep sederhana
        },

        async processCheckout() {
            if (this.cart.length === 0) return;
            
            this.processing = true;
            try {
                const response = await axios.post(`${kresuberParams.apiUrl}/order`, {
                    items: this.cart,
                    payment_method: this.paymentMethod,
                    status: 'completed'
                }, {
                    headers: { 'X-WP-Nonce': kresuberParams.nonce }
                });

                if (response.data.success) {
                    alert(`Transaksi Berhasil! ID Order: #${response.data.order_id}`);
                    this.cart = [];
                    // Opsional: Print struk logic di sini
                }
            } catch (error) {
                alert("Gagal memproses transaksi: " + error.response?.data?.message || error.message);
            } finally {
                this.processing = false;
            }
        }
    }
}).mount('#app');

// Debounce helper sederhana jika lodash tidak ada
if (typeof _ === 'undefined') {
    var _ = {
        debounce: function(func, wait) {
            let timeout;
            return function(...args) {
                const context = this;
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(context, args), wait);
            };
        }
    };
}