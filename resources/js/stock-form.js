// Item picker + line rows for the purchase, transfer and adjustment screens.
// mode: 'purchase' (qty + price), 'transfer' (qty, limited by stock) or
// 'adjust' (qty removed, limited by stock; or the counted qty when reason is 'count').
// The unsaved list is kept in the browser (draft) so a refresh or closed tab loses
// nothing. `fields` are the form's other inputs (date, store...) kept with it.
// `initialRows` (editing a saved purchase/invoice) fills the list instead of a draft.
export default ({ items, mode, draftKey, reason = null, startItem = null, fields = [], initialRows = null }) => ({
    items,
    mode,
    reason,
    query: '',
    rows: [],
    saving: false,
    saved: false,
    newUnit: 'kg',
    newCategory: '',
    createError: '',

    init() {
        // Drafts from before v2 were never cleared after saving; drop them so old lists don't come back
        try { ['purchase-draft', 'transfer-draft', 'adjust-draft', 'batch-draft'].forEach((k) => localStorage.removeItem(k)); } catch (e) {}

        if (initialRows) {
            this.rows = initialRows
                .map((row) => {
                    const item = this.items.find((i) => i.id === row.item_id);
                    if (!item) return null;
                    const r = { ...this.rowFor(item), qty: String(row.qty), price: row.price, pieces: row.pieces ?? '', description: row.description ?? '' };
                    // What this invoice already took is still "available" to it
                    if (this.mode === 'transfer') r.stock = item.stock + Number(row.qty);
                    return r;
                })
                .filter(Boolean);
            return;
        }

        try {
            let draft = JSON.parse(localStorage.getItem(draftKey) || '{}');
            if (Array.isArray(draft)) draft = { rows: draft };   // older drafts were just the rows

            // Refresh stock/price info from the server copy of each item
            this.rows = (draft.rows || [])
                .map((row) => {
                    const item = this.items.find((i) => i.id === row.item_id);
                    return item ? {
                        ...this.rowFor(item),
                        qty: row.qty,
                        selected_unit: row.selected_unit || (this.isKg(item) ? 'kg' : item.unit),
                        price: row.price,
                        pieces: row.pieces ?? '',
                        description: row.description ?? ''
                    } : null;
                })
                .filter(Boolean);

            if (this.rows.length && draft.meta) {
                for (const field of fields) {
                    const value = draft.meta[field];
                    if (value === undefined || value === null || value === '') continue;
                    this.$wire[field] = value;
                    const input = this.$root.querySelector(`[wire\\:model="${field}"]`);
                    if (input) input.value = value;
                }
            }
        } catch (e) {}

        const first = startItem && this.items.find((i) => i.id === startItem);
        if (first && !this.rows.some((r) => r.item_id === first.id)) this.add(first);

        this.$watch('rows', () => this.persist());
        // Typing in date/store/notes also updates the draft
        this.$root.addEventListener('input', () => this.persist());
        this.$root.addEventListener('change', () => this.persist());
    },

    persist() {
        if (this.saved || !draftKey) return;
        try {
            if (!this.rows.length) {
                localStorage.removeItem(draftKey);
                return;
            }
            const meta = Object.fromEntries(fields.map((f) => [f, this.$wire[f]]));
            const rows = this.rows.map((r) => ({ item_id: r.item_id, qty: r.qty, selected_unit: r.selected_unit, price: r.price, pieces: r.pieces, description: r.description }));
            localStorage.setItem(draftKey, JSON.stringify({ rows, meta }));
        } catch (e) {}
    },

    get isCount() {
        return this.mode === 'adjust' && this.reason === 'count';
    },

    // Removing stock is only possible for items that have some
    get limitedByStock() {
        return this.mode === 'transfer' || this.mode === 'batch' || (this.mode === 'adjust' && !this.isCount);
    },

    setReason(value) {
        // "Qty removed" and "qty counted" mean different things, so clear them on switch
        if ((value === 'count') !== this.isCount) this.rows.forEach((r) => (r.qty = ''));
        this.reason = value;
        this.$wire.reason = value;
    },

    unavailable(item) {
        return this.limitedByStock && item.stock <= 0;
    },

    get matches() {
        const words = this.query.trim().toLowerCase().split(/\s+/).filter(Boolean);
        if (!words.length) return [];
        return this.items.filter((i) => words.every((w) => i.name.toLowerCase().includes(w))).slice(0, 8);
    },

    get exactMatch() {
        const q = this.query.trim().toLowerCase();
        return this.items.some((i) => i.name.toLowerCase() === q);
    },

    isKg(itemOrRow) {
        if (!itemOrRow) return false;
        const u = (itemOrRow.unit || '').trim().toLowerCase();
        return u === 'kg' || u === 'kgs' || u === 'kilogram' || u === 'kilograms';
    },

    baseQty(row) {
        const n = this.num(row.qty);
        if (this.mode === 'batch' && this.isKg(row) && row.selected_unit === 'g') {
            return Math.round((n / 1000) * 100000) / 100000;
        }
        return n;
    },

    rowFor(item) {
        const isKgItem = this.isKg(item);
        return {
            key: `${item.id}-${Date.now()}-${Math.random()}`,
            item_id: item.id,
            name: item.name,
            unit: item.unit,
            selected_unit: isKgItem ? 'kg' : item.unit,
            stock: item.stock,
            cost: item.avg_cost,
            last_price: item.last_price,
            qty: '',
            // Purchase: last price paid. Transfer: last invoice rate, else average cost.
            price: this.mode === 'transfer'
                ? (item.sale_rate ?? item.last_transfer_rate ?? (Math.round(item.avg_cost * 100) / 100 || ''))
                : (item.last_price ?? ''),
            pieces: '',
            description: '',
            showDescription: false,
        };
    },

    add(item) {
        if (this.unavailable(item)) return;

        let row = this.rows.find((r) => r.item_id === item.id);
        if (!row) {
            row = this.rowFor(item);
            this.rows.push(row);
        }
        this.query = '';
        // The row list sits in an x-if, so the first row needs an extra tick to exist
        this.$nextTick(() => setTimeout(() => document.getElementById(`qty-${row.key}`)?.focus(), 50));
    },

    remove(index) {
        this.rows.splice(index, 1);
    },

    clearAll() {
        if (confirm('Clear the whole list?')) this.rows = [];
    },

    num(value) {
        const n = parseFloat(value);
        return Number.isFinite(n) ? n : 0;
    },

    // Signed stock change for an adjustment row: negative = stock goes down
    change(row) {
        if (row.qty === '') return 0;
        return this.isCount ? Math.round((this.num(row.qty) - row.stock) * 1000) / 1000 : -this.num(row.qty);
    },

    lineTotal(row) {
        if (this.mode === 'purchase') return this.num(row.qty) * this.num(row.price);
        if (this.mode === 'adjust') return this.change(row) * row.cost;
        if (this.mode === 'batch') return this.baseQty(row) * row.cost;
        // Invoices are in whole rupees
        return Math.round(this.num(row.qty) * this.num(row.price));
    },

    overStock(row) {
        return this.limitedByStock && this.baseQty(row) > row.stock;
    },

    get totalKgs() {
        return this.rows.filter((r) => r.unit === 'kg').reduce((sum, r) => sum + this.num(r.qty), 0);
    },

    get totalPieces() {
        return this.rows.reduce((sum, r) => sum + this.num(r.pieces), 0);
    },

    get total() {
        return this.rows.reduce((sum, r) => sum + this.lineTotal(r), 0);
    },

    get canSave() {
        return this.rows.length > 0 && !this.saving && !this.rows.some((r) => this.overStock(r));
    },

    money(value) {
        const v = Math.round(value * 100) / 100;
        return (v < 0 ? '-' : '') + 'Rs ' + Math.abs(v).toLocaleString('en-PK', { maximumFractionDigits: 2 });
    },

    qty(value) {
        return Number(value).toLocaleString('en-PK', { maximumFractionDigits: 3 });
    },

    async createItem() {
        this.createError = '';
        const item = await this.$wire.createItem(this.query.trim(), this.newUnit, this.newCategory ? Number(this.newCategory) : null);
        if (!item || !item.id) {
            this.createError = 'Could not add the item. Check the name.';
            return;
        }
        this.items.push(item);
        this.add(item);
    },

    async save() {
        if (!this.canSave) return;
        this.saving = true;
        const lines = this.rows.map((r) => {
            if (this.mode === 'purchase') return { item_id: r.item_id, qty: r.qty, pieces: r.pieces, unit_price: r.price };
            if (this.isCount) return { item_id: r.item_id, counted_qty: r.qty };
            if (this.mode === 'transfer') {
                return { item_id: r.item_id, qty: r.qty, pieces: r.pieces, rate: r.price, description: r.description };
            }
            if (this.mode === 'batch') {
                return { item_id: r.item_id, qty: r.qty, unit: r.selected_unit || r.unit };
            }
            return { item_id: r.item_id, qty: r.qty };
        });
        try {
            // true only when the server saved it; on a validation error the draft stays
            if ((await this.$wire.save(lines)) === true) {
                this.saved = true;
                try { if (draftKey) localStorage.removeItem(draftKey); } catch (e) {}
            }
        } finally {
            this.saving = false;
        }
    },
});
