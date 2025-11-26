/* RIM Admin JS */
function RIMMaterialsApp() {
    return {
        showModal: false,
        isEditing: false,
        submitting: false,
        units: rimAdminData.settings.units || [],
        form: {
            id: null,
            name: '',
            unit_type: 'pcs',
            quantity: 0,
            warning_quantity: 0,
            supplier: '',
            price: ''
        },
        table: null,

        init() {
            this.initTable();

            // Listen for edit/delete events from DataTable
            jQuery('#rim-materials-table').on('click', '.rim-edit-btn', (e) => {
                const id = jQuery(e.currentTarget).data('id');
                this.editMaterial(id);
            });

            jQuery('#rim-materials-table').on('click', '.rim-delete-btn', (e) => {
                const id = jQuery(e.currentTarget).data('id');
                this.deleteMaterial(id);
            });
        },

        initTable() {
            if (jQuery.fn.DataTable.isDataTable('#rim-materials-table')) {
                jQuery('#rim-materials-table').DataTable().destroy();
            }

            this.table = jQuery('#rim-materials-table').DataTable({
                processing: true,
                serverSide: false, // Client-side for simplicity unless large data
                ajax: {
                    url: rimAdminData.ajaxUrl,
                    data: {
                        action: 'rim_list_materials',
                        nonce: rimAdminData.nonce
                    },
                    dataSrc: function (json) {
                        return json.success ? json.data.items : [];
                    }
                },
                columns: [
                    { data: 'name' },
                    { data: 'unit_type' },
                    {
                        data: 'quantity',
                        render: function (data, type, row) {
                            return parseFloat(data).toFixed(2);
                        }
                    },
                    { data: 'warning_quantity' },
                    { data: 'supplier' },
                    {
                        data: 'price',
                        render: function (data) {
                            return data ? parseFloat(data).toFixed(2) : '-';
                        }
                    },
                    { data: 'last_updated' },
                    { data: 'last_edited_by_name' }, // This matches PHP format_material
                    {
                        data: 'id',
                        render: function (data) {
                            return `
                                <button class="button button-small rim-edit-btn" data-id="${data}">${rimAdminData.i18n.edit}</button>
                                <button class="button button-small button-link-delete rim-delete-btn" data-id="${data}">${rimAdminData.i18n.delete}</button>
                            `;
                        }
                    }
                ]
            });
        },

        openCreateModal() {
            this.isEditing = false;
            this.form = {
                id: null,
                name: '',
                unit_type: this.units[0] || 'pcs',
                quantity: 0,
                warning_quantity: 0,
                supplier: '',
                price: ''
            };
            this.showModal = true;
        },

        closeModal() {
            this.showModal = false;
        },

        editMaterial(id) {
            // Find row data
            const data = this.table.row((idx, data) => data.id == id).data();
            if (data) {
                this.isEditing = true;
                this.form = {
                    id: data.id,
                    name: data.name,
                    unit_type: data.unit_type,
                    quantity: parseFloat(data.quantity),
                    warning_quantity: parseFloat(data.warning_quantity),
                    supplier: data.supplier,
                    price: data.price
                };
                this.showModal = true;
            }
        },

        submitMaterial() {
            this.submitting = true;
            const action = this.isEditing ? 'rim_update_material' : 'rim_add_material';

            jQuery.post(rimAdminData.ajaxUrl, {
                action: action,
                nonce: rimAdminData.nonce,
                ...this.form
            }, (response) => {
                this.submitting = false;
                if (response.success) {
                    this.closeModal();
                    this.table.ajax.reload();
                    alert(rimAdminData.i18n.success);
                } else {
                    alert(response.data.message || rimAdminData.i18n.error);
                }
            }).fail(() => {
                this.submitting = false;
                alert(rimAdminData.i18n.error);
            });
        },

        deleteMaterial(id) {
            if (!confirm(rimAdminData.i18n.confirmDelete)) return;

            jQuery.post(rimAdminData.ajaxUrl, {
                action: 'rim_delete_material',
                nonce: rimAdminData.nonce,
                id: id
            }, (response) => {
                if (response.success) {
                    this.table.ajax.reload();
                } else {
                    alert(response.data.message || rimAdminData.i18n.error);
                }
            });
        }
    };
}

function RIMTransactionsApp() {
    return {
        materials: [],
        submitting: false,
        exporting: false,
        form: {
            material_id: '',
            type: 'add',
            quantity: '',
            price: '',
            supplier: '',
            reason: '',
            transaction_date: new Date().toISOString().slice(0, 16)
        },
        filters: {
            material: '',
            type: '',
            date_start: '',
            date_end: ''
        },
        table: null,

        init() {
            this.fetchMaterials();
            this.initTable();
        },

        fetchMaterials() {
            jQuery.get(rimAdminData.ajaxUrl, {
                action: 'rim_list_materials',
                nonce: rimAdminData.nonce,
                per_page: -1 // Get all
            }, (response) => {
                if (response.success) {
                    this.materials = response.data.items;
                }
            });
        },

        initTable() {
            if (jQuery.fn.DataTable.isDataTable('#rim-transactions-table')) {
                jQuery('#rim-transactions-table').DataTable().destroy();
            }

            this.table = jQuery('#rim-transactions-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: rimAdminData.ajaxUrl,
                    data: (d) => {
                        d.action = 'rim_list_transactions';
                        d.nonce = rimAdminData.nonce;
                        d.material = this.filters.material;
                        d.type = this.filters.type;
                        d.date_start = this.filters.date_start;
                        d.date_end = this.filters.date_end;
                        d.page = (d.start / d.length) + 1;
                        d.per_page = d.length;
                    },
                    dataSrc: function (json) {
                        return json.success ? json.data.items : [];
                    }
                },
                columns: [
                    { data: 'material_name' },
                    {
                        data: 'type',
                        render: function (data) {
                            return data === 'add' ? rimAdminData.i18n.add : rimAdminData.i18n.use;
                        }
                    },
                    {
                        data: 'quantity',
                        render: function (data, type, row) {
                            return parseFloat(data).toFixed(3) + ' ' + row.unit;
                        }
                    },
                    {
                        data: 'price',
                        render: function (data) {
                            return data ? parseFloat(data).toFixed(2) : '-';
                        }
                    },
                    { data: 'supplier' },
                    { data: 'reason' },
                    { data: 'transaction_date' },
                    { data: 'created_by' }
                ]
            });
        },

        reloadTable() {
            this.table.ajax.reload();
        },

        resetFilters() {
            this.filters = {
                material: '',
                type: '',
                date_start: '',
                date_end: ''
            };
            this.reloadTable();
        },

        handleMaterialChange() {
            // Optional: Update unit label or price hint
        },

        submitTransaction() {
            this.submitting = true;

            jQuery.post(rimAdminData.ajaxUrl, {
                action: 'rim_create_transaction',
                nonce: rimAdminData.nonce,
                ...this.form
            }, (response) => {
                this.submitting = false;
                if (response.success) {
                    alert(rimAdminData.i18n.success);
                    this.form = {
                        material_id: '',
                        type: 'add',
                        quantity: '',
                        price: '',
                        supplier: '',
                        reason: '',
                        transaction_date: new Date().toISOString().slice(0, 16)
                    };
                    this.reloadTable();
                } else {
                    alert(response.data.message || rimAdminData.i18n.error);
                }
            }).fail(() => {
                this.submitting = false;
                alert(rimAdminData.i18n.error);
            });
        },

        exportCsv() {
            this.exporting = true;
            const params = jQuery.param({
                action: 'rim_export_transactions_csv',
                nonce: rimAdminData.nonce,
                ...this.filters
            });

            // Use window.open or hidden iframe to download
            // But since the AJAX returns base64, we need to handle it differently as per handle_export_transactions_csv

            jQuery.post(rimAdminData.ajaxUrl, params, (response) => {
                this.exporting = false;
                if (response.success) {
                    const link = document.createElement('a');
                    link.href = 'data:text/csv;base64,' + response.data.payload;
                    link.download = response.data.filename;
                    link.click();
                } else {
                    alert(response.data.message || rimAdminData.i18n.error);
                }
            }).fail(() => {
                this.exporting = false;
                alert(rimAdminData.i18n.error);
            });
        }
    };
}

function RIMReportsApp() {
    return {
        filters: {
            date_start: '',
            date_end: ''
        },
        summary: {
            purchases_quantity: 0,
            purchases_value: 0,
            usage_quantity: 0
        },
        purchases: [],
        usage: [],
        exportingPurchases: false,
        exportingUsage: false,

        init() {
            this.resetFilters(); // Sets default range
        },

        resetFilters() {
            const end = new Date();
            const start = new Date();
            start.setDate(start.getDate() - 7);

            this.filters.date_end = end.toISOString().slice(0, 10);
            this.filters.date_start = start.toISOString().slice(0, 10);

            this.reloadAll();
        },

        reloadAll() {
            this.fetchSummary();
            this.fetchPurchases();
            this.fetchUsage();
        },

        fetchSummary() {
            jQuery.get(rimAdminData.ajaxUrl, {
                action: 'rim_report_summary',
                nonce: rimAdminData.nonce,
                ...this.filters
            }, (response) => {
                if (response.success) {
                    this.summary = response.data;
                }
            });
        },

        fetchPurchases() {
            jQuery.get(rimAdminData.ajaxUrl, {
                action: 'rim_report_purchases',
                nonce: rimAdminData.nonce,
                ...this.filters
            }, (response) => {
                if (response.success) {
                    this.purchases = response.data.items;
                }
            });
        },

        fetchUsage() {
            jQuery.get(rimAdminData.ajaxUrl, {
                action: 'rim_report_usage',
                nonce: rimAdminData.nonce,
                ...this.filters
            }, (response) => {
                if (response.success) {
                    this.usage = response.data.items;
                }
            });
        },

        exportPurchases() {
            // Implement export logic similar to transactions if needed
            // For now just alert or log
            console.log('Export purchases not implemented in this snippet');
        },

        exportUsage() {
            console.log('Export usage not implemented in this snippet');
        }
    };
}

function RIMSettingsApp() {
    return {
        saving: false,
        form: {
            alerts_enabled: false,
            alert_email: '',
            units_list: []
        },
        unitsInput: '',

        init(settings) {
            if (settings) {
                this.form.alerts_enabled = settings.alerts_enabled;
                this.form.alert_email = settings.alert_email;
                this.form.units_list = settings.units_list;
                this.unitsInput = settings.units_list.join(', ');
            }
        },

        save() {
            this.saving = true;
            this.form.units_list = this.unitsInput.split(',').map(u => u.trim()).filter(u => u);

            jQuery.post(rimAdminData.ajaxUrl, {
                action: 'rim_save_settings',
                nonce: rimAdminData.nonce,
                ...this.form
            }, (response) => {
                this.saving = false;
                if (response.success) {
                    alert(rimAdminData.i18n.success);
                } else {
                    alert(response.data.message || rimAdminData.i18n.error);
                }
            }).fail(() => {
                this.saving = false;
                alert(rimAdminData.i18n.error);
            });
        }
    };
}
