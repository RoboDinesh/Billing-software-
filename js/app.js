// ================================================
// BILLING SYSTEM - Core Module (app.js)
// Supports MySQL (Hostinger) + localStorage fallback
// ================================================

const API_ENDPOINT = 'php/api.php';
let DB_MODE = 'api'; // 'api' or 'local'

// localStorage helpers
const LS = {
    get: (k, d) => { try { return JSON.parse(localStorage.getItem('billing_'+k)) || d; } catch (e) { return d; } },
    set: (k, v) => { try { localStorage.setItem('billing_'+k, JSON.stringify(v)); } catch (e) {} }
};

// ===== DATA LAYER =====
const DB = {
    cache: {
        settings: null,
        users: [],
        customers: [],
        products: [],
        invoices: [],
        quotations: [],
        challans: [],
        labors: [],
        ledger: [],
        materials: []
    },

    async init() {
        try {
            const results = await Promise.all([
                this.fetchAPI('get_settings'),
                this.fetchAPI('get_users'),
                this.fetchAPI('get_customers'),
                this.fetchAPI('get_products'),
                this.fetchAPI('get_invoices'),
                this.fetchAPI('get_quotations'),
                this.fetchAPI('get_challans'),
                this.fetchAPI('get_labors'),
                this.fetchAPI('get_labor_ledger'),
                this.fetchAPI('get_materials')
            ]);
            
            // If any critical call failed, throw to trigger fallback
            results.forEach(res => { if (res && res.ok === false) throw new Error(res.error); });

            const [s, u, c, p, i, q, ch, l, ll, m] = results;
            if (!s || !s.company_name) {
                this.cache.settings = {
                    name: 'Your Company Name', gstin: '', address: '',
                    city: 'City', state: 'State', stateCode: 'XX',
                    phone: '', email: '', logo: '', signature: '',
                    bankName: '', bankAccount: '', bankIFSC: '', bankBranch: '',
                    termsConditions: '1. All disputes subject to local jurisdiction.\n2. Goods once sold will not be taken back.\n3. Payment due within 30 days of invoice date.'
                };
            } else {
                this.cache.settings = {
                    name: s.company_name, gstin: s.gstin, address: s.address,
                    city: s.city, state: s.state_name, stateCode: s.state_code,
                    phone: s.phone, email: s.email, logo: s.logo_url, signature: s.signature_url,
                    bankName: s.bank_name, bankAccount: s.bank_account, bankIFSC: s.bank_ifsc, bankBranch: s.bank_branch,
                    bankAddress: s.bank_address || '', reptid: s.bank_reptid || '',
                    showLogo: !!parseInt(s.show_logo), showBank: !!parseInt(s.show_bank), showSignature: !!parseInt(s.show_signature),
                    termsConditions: s.terms_conditions
                };
            }

            // Map Users
            this.cache.users = (u || []).map(x => ({ ...x, createdAt: x.created_at }));
            
            // Map Customers
            this.cache.customers = (c || []).map(x => ({ ...x, stateCode: x.state_code, createdAt: x.created_at }));
            
            // Map Products
            this.cache.products = (p || []).map(x => ({ 
                ...x, 
                hsnCode: x.hsn, 
                gstRate: parseFloat(x.gst_rate), 
                rate: parseFloat(x.price), 
                mfr: x.manufacturer || '',
                mrp: parseFloat(x.mrp || 0),
                createdAt: x.created_at 
            }));
            
            // Map Invoices
            this.cache.invoices = (i || []).map(x => {
                const sub = parseFloat(x.subtotal || 0);
                const cgst = parseFloat(x.cgst || 0);
                const sgst = parseFloat(x.sgst || 0);
                const igst = parseFloat(x.igst || 0);
                return {
                    id: x.id, invoiceNo: x.invoice_no, date: x.invoice_date, duedate: x.due_date || x.invoice_date,
                    paymentStatus: x.payment_status || 'pending', paidAmount: parseFloat(x.paid_amount || 0),
                    customerId: x.customer_id, 
                    customerName: x.customer ? x.customer.name : (x.customer_name || ''),
                    customerGSTIN: x.customer ? x.customer.gstin : (x.customer_gstin || ''), 
                    customerAddress: x.customer ? x.customer.address : (x.customer_address || ''),
                    customerStateCode: x.customer ? x.customer.stateCode : (x.customer_state_code || ''),
                    
                    // Logistics and Order info from customer_json or direct fields:
                    supplyOrderNo: x.customer ? (x.customer.supplyOrderNo || '') : (x.supplyOrderNo || ''),
                    buyerOrderNo: x.customer ? (x.customer.buyerOrderNo || '') : (x.buyerOrderNo || ''),
                    eWayBill: x.customer ? (x.customer.eWayBill || '') : (x.eWayBill || ''),
                    vehicleNo: x.customer ? (x.customer.vehicleNo || '') : (x.vehicleNo || ''),
                    destination: x.customer ? (x.customer.destination || '') : (x.destination || ''),
                    paymentMode: x.customer ? (x.customer.paymentMode || '') : (x.paymentMode || ''),
                    
                    items: x.items || [], gstType: x.gst_type || 'intra',
                    subtotal: sub, totalCGST: cgst, totalSGST: sgst, totalIGST: igst, 
                    totalGST: cgst + sgst + igst,
                    grandTotal: parseFloat(x.total || 0), notes: x.notes, termsConditions: x.terms_conditions,
                    quotationId: x.quotation_id || '', createdAt: x.created_at
                };
            });

            // Map Quotations
            this.cache.quotations = (q || []).map(x => ({
                id: x.id, quotationNo: x.quotation_no, date: x.quotation_date, validUntil: x.valid_until,
                status: x.status || 'active',
                customerId: x.customer_id, customerName: x.customer ? x.customer.name : '',
                customerGSTIN: x.customer ? x.customer.gstin : '', customerAddress: x.customer ? x.customer.address : '',
                customerStateCode: x.customer ? x.customer.stateCode : '',
                items: x.items || [], gstType: x.gst_type || 'intra',
                subtotal: parseFloat(x.subtotal), totalCGST: parseFloat(x.cgst), totalSGST: parseFloat(x.sgst),
                totalIGST: parseFloat(x.igst), totalGST: parseFloat(x.cgst)+parseFloat(x.sgst)+parseFloat(x.igst),
                grandTotal: parseFloat(x.total), notes: x.notes, termsConditions: x.terms_conditions,
                createdAt: x.created_at
            }));

            // Map Challans
            this.cache.challans = (ch || []).map(x => ({
                id: x.id, challanNo: x.challan_no, date: x.challan_date,
                status: x.status || 'active', vehicleNo: x.vehicle_no,
                customerId: x.customer_id, customerName: x.customer ? x.customer.name : '',
                customerGSTIN: x.customer ? x.customer.gstin : '', customerAddress: x.customer ? x.customer.address : '',
                customerStateCode: x.customer ? x.customer.stateCode : '',
                items: x.items || [], notes: x.notes, termsConditions: x.terms_conditions,
                createdAt: x.created_at
            }));

            // Map Labors
            this.cache.labors = (l || []).map(x => ({ ...x, joinedDate: x.joined_date, createdAt: x.created_at }));
            
            // Map Labor Ledger
            this.cache.ledger = (ll || []).map(x => ({ ...x, laborId: x.labor_id, entryDate: x.entry_date, createdAt: x.created_at, amount: parseFloat(x.amount) }));

            // Map Materials
            this.cache.materials = (m || []).map(x => ({ 
                ...x, 
                quantity: parseFloat(x.quantity), 
                rate: parseFloat(x.rate), 
                gstRate: parseFloat(x.gst_rate),
                totalAmount: parseFloat(x.total_amount),
                createdAt: x.created_at 
            }));

        } catch (e) {
            console.warn('DB API Init Failed, falling back to localStorage:', e.message);
            DB_MODE = 'local';
            // Load from localStorage
            this.cache.settings = LS.get('settings', {
                name: 'Your Company Name', gstin: '', address: '',
                city: 'City', state: 'State', stateCode: 'XX',
                phone: '', email: '', logo: '', signature: '',
                bankName: '', bankAccount: '', bankIFSC: '', bankBranch: '', bankAddress: '',
                termsConditions: '1. All disputes subject to local jurisdiction.\n2. Goods once sold will not be taken back.\n3. Payment due within 30 days.'
            });
            this.cache.users = LS.get('users', [{ id: 'U1', name: 'Admin', username: 'admin', password: 'admin123', role: 'admin', company_id: 'C001' }]);
            this.cache.customers = LS.get('customers', []);
            this.cache.products = LS.get('products', []);
            const invs = LS.get('invoices', []);
            this.cache.invoices = invs.map(x => ({
                ...x,
                totalGST: (parseFloat(x.totalCGST) || 0) + (parseFloat(x.totalSGST) || 0) + (parseFloat(x.totalIGST) || 0)
            }));
            this.cache.quotations = LS.get('quotations', []);
            this.cache.challans = LS.get('challans', []);
            this.cache.labors = LS.get('labors', []);
            this.cache.ledger = LS.get('ledger', []);
            this.cache.materials = LS.get('materials', []);
        }
    },

    async fetchAPI(action, data = null) {
        const u = Auth.getUser();
        const headers = { 'Content-Type': 'application/json' };
        if (u && u.company_id) headers['X-Company-ID'] = u.company_id;
        if (u && u.id) headers['X-User-ID'] = u.id;

        const url = `${API_ENDPOINT}?action=${action}`;
        const options = {
            method: data ? 'POST' : 'GET',
            headers: headers
        };
        if (data) options.body = JSON.stringify(data);
        try {
            const res = await fetch(url, options);
            const text = await res.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error("API Error (Not JSON):", text);
                return { ok: false, error: "Server Error: " + text.replace(/(<([^>]+)>)/gi, "").substring(0, 100) };
            }
        } catch (e) {
            console.error("Network Error:", e);
            return { ok: false, error: "Network Error: " + e.message };
        }
    },

    getSettings() { return this.cache.settings; },
    async saveSettings(d) {
        this.cache.settings = d;
        if (DB_MODE === 'local') { LS.set('settings', d); return { ok: true }; }
        return this.fetchAPI('save_settings', d);
    },

    // ----- USERS -----
    getUsers() { return this.cache.users; },
    
    getCustomers() { return this.cache.customers; },
    async addCustomer(c) {
        c.id = 'C' + Date.now();
        c.createdAt = nowISO();
        this.cache.customers.unshift(c);
        if (DB_MODE === 'local') { LS.set('customers', this.cache.customers); return c; }
        await this.fetchAPI('add_customer', c);
        return c;
    },
    async deleteCustomer(id) {
        this.cache.customers = this.cache.customers.filter(x => x.id !== id);
        if (DB_MODE === 'local') { LS.set('customers', this.cache.customers); return { ok: true }; }
        return this.fetchAPI('delete', { table: 'customers', id });
    },
    getCustomer(id) { return this.cache.customers.find(x => x.id === id); },
    async updateCustomer(id, c) {
        const idx = this.cache.customers.findIndex(x => x.id === id);
        if (idx !== -1) this.cache.customers[idx] = { ...this.cache.customers[idx], ...c };
        if (DB_MODE === 'local') { LS.set('customers', this.cache.customers); return { ok: true }; }
        c.id = id;
        return this.fetchAPI('update_customer', c);
    },

    // ----- PRODUCTS -----
    getProducts() { return this.cache.products; },
    getProduct(id) { return this.cache.products.find(x => x.id === id); },
    async addProduct(p) {
        p.id = 'P' + Date.now();
        p.createdAt = nowISO();
        this.cache.products.push(p);
        if (DB_MODE === 'local') { LS.set('products', this.cache.products); return p; }
        await this.fetchAPI('add_product', p);
        return p;
    },
    async updateProduct(id, p) {
        const idx = this.cache.products.findIndex(x => x.id === id);
        if (idx !== -1) this.cache.products[idx] = { ...this.cache.products[idx], ...p };
        if (DB_MODE === 'local') { LS.set('products', this.cache.products); return { ok: true }; }
        p.id = id;
        return this.fetchAPI('update_product', p);
    },
    async deleteProduct(id) {
        this.cache.products = this.cache.products.filter(x => x.id !== id);
        if (DB_MODE === 'local') { LS.set('products', this.cache.products); return { ok: true }; }
        return this.fetchAPI('delete', { table: 'products', id });
    },

    // ----- INVOICES -----
    getInvoices() { return this.cache.invoices; },
    async addInvoice(inv) {
        inv.id = 'I' + Date.now();
        inv.createdAt = nowISO();
        this.cache.invoices.unshift(inv);
        if (DB_MODE === 'local') { LS.set('invoices', this.cache.invoices); return inv; }
        await this.fetchAPI('add_invoice', inv);
        return inv;
    },
    async updateInvoice(id, inv) {
        const idx = this.cache.invoices.findIndex(x => x.id === id);
        if (idx !== -1) this.cache.invoices[idx] = { ...this.cache.invoices[idx], ...inv };
        if (DB_MODE === 'local') { LS.set('invoices', this.cache.invoices); return { ok: true }; }
        inv.id = id;
        return this.fetchAPI('update_invoice', inv);
    },
    async deleteInvoice(id) {
        this.cache.invoices = this.cache.invoices.filter(x => x.id !== id);
        if (DB_MODE === 'local') { LS.set('invoices', this.cache.invoices); return { ok: true }; }
        return this.fetchAPI('delete', { table: 'invoices', id });
    },
    getInvoice(id) { return this.cache.invoices.find(x => x.id === id); },
    nextInvoiceNo() {
        const fy = getFY();
        const list = this.cache.invoices.filter(x => x.invoiceNo && x.invoiceNo.includes(fy));
        return `INV/${fy}/${String(list.length + 1).padStart(3, '0')}`;
    },

    // ----- QUOTATIONS -----
    getQuotations() { return this.cache.quotations; },
    async addQuotation(q) {
        q.id = 'Q' + Date.now();
        q.createdAt = nowISO();
        this.cache.quotations.unshift(q);
        if (DB_MODE === 'local') { LS.set('quotations', this.cache.quotations); return q; }
        await this.fetchAPI('add_quotation', q);
        return q;
    },
    async updateQuotation(id, q) {
        const idx = this.cache.quotations.findIndex(x => x.id === id);
        if (idx !== -1) this.cache.quotations[idx] = { ...this.cache.quotations[idx], ...q };
        if (DB_MODE === 'local') { LS.set('quotations', this.cache.quotations); return { ok: true }; }
        q.id = id;
        return this.fetchAPI('update_quotation', q);
    },
    async deleteQuotation(id) {
        this.cache.quotations = this.cache.quotations.filter(x => x.id !== id);
        if (DB_MODE === 'local') { LS.set('quotations', this.cache.quotations); return { ok: true }; }
        return this.fetchAPI('delete', { table: 'quotations', id });
    },
    getQuotation(id) { return this.cache.quotations.find(x => x.id === id); },
    nextQuotationNo() {
        const fy = getFY();
        const list = this.cache.quotations.filter(x => x.quotationNo && x.quotationNo.includes(fy));
        return `QT/${fy}/${String(list.length + 1).padStart(3, '0')}`;
    },

    // ----- CHALLANS -----
    getChallans() { return this.cache.challans; },
    async addChallan(c) {
        c.id = 'CH' + Date.now();
        c.createdAt = nowISO();
        this.cache.challans.unshift(c);
        if (DB_MODE === 'local') { LS.set('challans', this.cache.challans); return c; }
        await this.fetchAPI('add_challan', c);
        return c;
    },
    async updateChallan(id, c) {
        const idx = this.cache.challans.findIndex(x => x.id === id);
        if (idx !== -1) this.cache.challans[idx] = { ...this.cache.challans[idx], ...c };
        if (DB_MODE === 'local') { LS.set('challans', this.cache.challans); return { ok: true }; }
        c.id = id;
        return this.fetchAPI('update_challan', c);
    },
    async deleteChallan(id) {
        this.cache.challans = this.cache.challans.filter(x => x.id !== id);
        if (DB_MODE === 'local') { LS.set('challans', this.cache.challans); return { ok: true }; }
        return this.fetchAPI('delete', { table: 'challans', id });
    },
    getChallan(id) { return this.cache.challans.find(x => x.id === id); },
    nextChallanNo() {
        const fy = getFY();
        const list = this.cache.challans.filter(x => x.challanNo && x.challanNo.includes(fy));
        return `DC/${fy}/${String(list.length + 1).padStart(3, '0')}`;
    },

    // ----- LABORS -----
    getLabors() { return this.cache.labors; },
    getLabor(id) { return this.cache.labors.find(x => x.id === id); },
    async addLabor(l) {
        l.id = 'L' + Date.now();
        l.createdAt = nowISO();
        this.cache.labors.unshift(l);
        if (DB_MODE === 'local') { LS.set('labors', this.cache.labors); return l; }
        await this.fetchAPI('add_labor', l);
        return l;
    },
    async updateLabor(id, l) {
        const idx = this.cache.labors.findIndex(x => x.id === id);
        if (idx !== -1) this.cache.labors[idx] = { ...this.cache.labors[idx], ...l };
        if (DB_MODE === 'local') { LS.set('labors', this.cache.labors); return { ok: true }; }
        l.id = id;
        return this.fetchAPI('update_labor', l);
    },
    async deleteLabor(id) {
        this.cache.labors = this.cache.labors.filter(x => x.id !== id);
        this.cache.ledger = this.cache.ledger.filter(x => x.laborId !== id);
        if (DB_MODE === 'local') { LS.set('labors', this.cache.labors); LS.set('ledger', this.cache.ledger); return { ok: true }; }
        return this.fetchAPI('delete', { table: 'labors', id });
    },

    // ----- LABOR LEDGER -----
    getLaborLedger(laborId = null) {
        if (laborId) return this.cache.ledger.filter(x => x.laborId === laborId);
        return this.cache.ledger;
    },
    async addLaborLedger(entry) {
        entry.id = 'LL' + Date.now();
        entry.createdAt = nowISO();
        this.cache.ledger.unshift(entry);
        if (DB_MODE === 'local') { LS.set('ledger', this.cache.ledger); return entry; }
        await this.fetchAPI('add_labor_ledger', entry);
        return entry;
    },
    async deleteLaborLedger(id) {
        this.cache.ledger = this.cache.ledger.filter(x => x.id !== id);
        if (DB_MODE === 'local') { LS.set('ledger', this.cache.ledger); return { ok: true }; }
        return this.fetchAPI('delete', { table: 'labor_ledger', id });
    },

    // ----- MATERIALS -----
    getMaterials() { return this.cache.materials; },
    async addMaterial(m) {
        m.id = 'M' + Date.now();
        m.createdAt = nowISO();
        this.cache.materials.unshift(m);
        if (DB_MODE === 'local') { LS.set('materials', this.cache.materials); return m; }
        await this.fetchAPI('add_material', m);
        return m;
    },
    async updateMaterial(id, m) {
        const idx = this.cache.materials.findIndex(x => x.id === id);
        if (idx !== -1) this.cache.materials[idx] = { ...this.cache.materials[idx], ...m };
        if (DB_MODE === 'local') { LS.set('materials', this.cache.materials); return { ok: true }; }
        m.id = id;
        return this.fetchAPI('update_material', m);
    },
    async deleteMaterial(id) {
        this.cache.materials = this.cache.materials.filter(x => x.id !== id);
        if (DB_MODE === 'local') { LS.set('materials', this.cache.materials); return { ok: true }; }
        return this.fetchAPI('delete', { table: 'materials', id });
    },
    async saveUsers(users) {
        this.cache.users = users;
        if (DB_MODE === 'local') { LS.set('users', users); return { ok: true }; }
        return this.fetchAPI('save_users', { users });
    },

    // ----- COMPANIES (TENANTS) -----
    async getCompanies() {
        if (DB_MODE === 'local') return LS.get('companies', [{ company_id: 'C001', company_name: 'Main Company', city: 'Local', phone: '000', created_at: nowISO() }]);
        return this.fetchAPI('get_companies');
    },
    async addCompany(data) {
        if (DB_MODE === 'local') {
            const list = await this.getCompanies();
            const newH = { ...data, company_id: 'C' + Date.now(), created_at: nowISO() };
            list.push(newH);
            LS.set('companies', list);
            return { ok: true, company_id: newH.company_id };
        }
        return this.fetchAPI('add_company', data);
    },
    async updateCompany(id, data) {
        if (DB_MODE === 'local') {
            const list = await this.getCompanies();
            const idx = list.findIndex(h => h.company_id === id);
            if (idx !== -1) list[idx] = { ...list[idx], ...data };
            LS.set('companies', list);
            return { ok: true };
        }
        return this.fetchAPI('update_company', { id, ...data });
    },

    getMode() { return DB_MODE; },

    getAllData() {
        return {
            settings: this.cache.settings,
            customers: this.cache.customers,
            products: this.cache.products,
            invoices: this.cache.invoices,
            quotations: this.cache.quotations,
            challans: this.cache.challans,
            labors: this.cache.labors,
            ledger: this.cache.ledger,
            materials: this.cache.materials,
            users: this.cache.users
        };
    }
};


// ===== AUTH =====
const Auth = {
    getUser() { return { id: 'U1', name: 'Admin', username: 'admin', role: 'admin', company_id: 'C001' }; },
    async login(username, password) { return { ok: true, user: this.getUser() }; },
    logout() { window.location.href = 'index.html'; },
    require() { return this.getUser(); }
};

// Rest of utilities (nowISO, fmtCur, etc.) remain same
function nowISO() { return new Date().toISOString(); }
function today() { return new Date().toISOString().split('T')[0]; }
function getFY() {
    const now = new Date(); const y = now.getFullYear(); const m = now.getMonth() + 1;
    return m >= 4 ? `${y}-${String(y + 1).slice(-2)}` : `${y - 1}-${String(y).slice(-2)}`;
}
function fmtDate(s) {
    if (!s) return '-'; const d = new Date(s);
    return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}
function fmtCur(n) {
    const v = parseFloat(n) || 0;
    return '₹' + v.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function numWords(amount) {
    const n = Math.round(parseFloat(amount) || 0);
    if (n === 0) return 'Zero Rupees Only';
    const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    function cvt(x) {
        if (x < 20) return ones[x];
        if (x < 100) return tens[Math.floor(x / 10)] + (x % 10 ? ' ' + ones[x % 10] : '');
        if (x < 1000) return ones[Math.floor(x / 100)] + ' Hundred' + (x % 100 ? ' ' + cvt(x % 100) : '');
        if (x < 100000) return cvt(Math.floor(x / 1000)) + ' Thousand' + (x % 1000 ? ' ' + cvt(x % 1000) : '');
        if (x < 10000000) return cvt(Math.floor(x / 100000)) + ' Lakh' + (x % 100000 ? ' ' + cvt(x % 100000) : '');
        return cvt(Math.floor(x / 10000000)) + ' Crore' + (x % 10000000 ? ' ' + cvt(x % 10000000) : '');
    }
    return cvt(n) + ' Rupees Only';
}

function toast(msg, type = 'success') {
    let c = document.getElementById('toast-container');
    if (!c) { c = document.createElement('div'); c.id = 'toast-container'; c.className = 'toast-container'; document.body.appendChild(c); }
    const t = document.createElement('div');
    t.className = `toast toast-${type}`;
    t.innerHTML = `<span>${msg}</span>`;
    c.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, 3200);
}

// Sidebar HTML
function sidebarHTML(activePage) {
    const navLinks = [
        { href: 'dashboard.html', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', label: 'Dashboard' },
        { href: 'invoice.html', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', label: 'Invoices' },
        { href: 'quotation.html', icon: 'M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2', label: 'Quotations' },
        { href: 'challan.html', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', label: 'Challans' },
        { href: 'material.html', icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', label: 'Materials' },
        { href: 'customers.html', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', label: 'Customers' },
        { href: 'products.html', icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', label: 'Inventory' },
        { href: 'labor.html', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z', label: 'Staff' },
        { href: 'settings.html', icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z', label: 'Settings' }
    ];

    const settings = DB.getSettings() || { name: 'Your Company' };
    let links = '';
    navLinks.forEach(l => {
        const active = activePage === l.href ? 'active' : '';
        links += `<a href="${l.href}" class="nav-item ${active}">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${l.icon}"/></svg>
      ${l.label}
    </a>`;
    });

    return `<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <div style="width:38px;height:38px;background:var(--accent);border-radius:8px;display:flex;align-items:center;justify-content:center;font-weight:800;color:#fff;font-size:14px">${settings.name.charAt(0)}</div>
      <div class="logo-text"><div class="company-name">${settings.name}</div></div>
    </div>
    <nav class="sidebar-nav">${links}</nav>
    <div class="sidebar-footer">
      <div class="sidebar-user" onclick="Auth.logout()">
        <div class="user-avatar" id="sidebar-avatar">A</div>
        <div class="user-info">
          <div class="user-name" id="sidebar-user-name">Admin</div>
          <div class="user-role" id="sidebar-user-role">Administrator</div>
        </div>
      </div>
    </div>
  </aside>`;
}

// ===== MODAL HELPERS =====
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// ===== DATE HELPERS =====
function addDays(n) {
    const d = new Date(); d.setDate(d.getDate() + n);
    return d.toISOString().split('T')[0];
}

// ===== SIDEBAR USER =====
function initSidebarUser() {
    const u = Auth.getUser();
    if (!u) return;
    const n = document.getElementById('sidebar-user-name');
    const r = document.getElementById('sidebar-user-role');
    const a = document.getElementById('sidebar-avatar');
    if (n) n.textContent = u.name || u.username;
    if (r) r.textContent = u.role === 'admin' ? 'Administrator' : 'Staff';
    if (a) a.textContent = (u.name || u.username).charAt(0).toUpperCase();
}

// ===== GST / STATE HELPERS =====
const INDIAN_STATES = [
    {code:'01',name:'Jammu & Kashmir'},{code:'02',name:'Himachal Pradesh'},{code:'03',name:'Punjab'},
    {code:'04',name:'Chandigarh'},{code:'05',name:'Uttarakhand'},{code:'06',name:'Haryana'},
    {code:'07',name:'Delhi'},{code:'08',name:'Rajasthan'},{code:'09',name:'Uttar Pradesh'},
    {code:'10',name:'Bihar'},{code:'11',name:'Sikkim'},{code:'12',name:'Arunachal Pradesh'},
    {code:'13',name:'Nagaland'},{code:'14',name:'Manipur'},{code:'15',name:'Mizoram'},
    {code:'16',name:'Tripura'},{code:'17',name:'Meghalaya'},{code:'18',name:'Assam'},
    {code:'19',name:'West Bengal'},{code:'20',name:'Jharkhand'},{code:'21',name:'Odisha'},
    {code:'22',name:'Chhattisgarh'},{code:'23',name:'Madhya Pradesh'},{code:'24',name:'Gujarat'},
    {code:'25',name:'Daman & Diu'},{code:'26',name:'Dadra & Nagar Haveli'},{code:'27',name:'Maharashtra'},
    {code:'28',name:'Andhra Pradesh'},{code:'29',name:'Karnataka'},{code:'30',name:'Goa'},
    {code:'31',name:'Lakshadweep'},{code:'32',name:'Kerala'},{code:'33',name:'Tamil Nadu'},
    {code:'34',name:'Puducherry'},{code:'35',name:'Andaman & Nicobar'},{code:'36',name:'Telangana'},
    {code:'37',name:'Andhra Pradesh (New)'}
];
function getStateByCode(code) { return INDIAN_STATES.find(s => s.code === code) || null; }
function stateOptions(sel) {
    sel = sel || '';
    return INDIAN_STATES.map(s => `<option value="${s.code}" ${s.code === sel ? 'selected' : ''}>${s.code} - ${s.name}</option>`).join('');
}
function gstType(sellerCode, buyerCode) {
    return (!sellerCode || !buyerCode || sellerCode === buyerCode) ? 'intra' : 'inter';
}
function calcGST(taxable, rate, type) {
    const total = taxable * (rate || 0) / 100;
    if (type === 'inter') return { cgst: 0, sgst: 0, igst: total, total };
    return { cgst: total / 2, sgst: total / 2, igst: 0, total };
}

// Close modal when clicking on overlay backdrop
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
    }
});

// ===== SIGNATURE PAD =====
function initSignaturePad(canvasId, clearBtnId) {
    const canvas = document.getElementById(canvasId);
    const clearBtn = document.getElementById(clearBtnId);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    let drawing = false;
    const getPos = (e) => {
        const rect = canvas.getBoundingClientRect();
        const src = e.touches ? e.touches[0] : e;
        return { x: src.clientX - rect.left, y: src.clientY - rect.top };
    };
    canvas.addEventListener('mousedown', e => { drawing = true; const p = getPos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); });
    canvas.addEventListener('mousemove', e => { if (!drawing) return; const p = getPos(e); ctx.lineTo(p.x, p.y); ctx.strokeStyle = '#1f2937'; ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.stroke(); });
    canvas.addEventListener('mouseup', () => drawing = false);
    canvas.addEventListener('mouseleave', () => drawing = false);
    canvas.addEventListener('touchstart', e => { e.preventDefault(); drawing = true; const p = getPos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); });
    canvas.addEventListener('touchmove', e => { e.preventDefault(); if (!drawing) return; const p = getPos(e); ctx.lineTo(p.x, p.y); ctx.strokeStyle = '#1f2937'; ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.stroke(); });
    canvas.addEventListener('touchend', () => drawing = false);
    if (clearBtn) clearBtn.addEventListener('click', () => ctx.clearRect(0, 0, canvas.width, canvas.height));
}
