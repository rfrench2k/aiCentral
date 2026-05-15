/**
 * AI Central Admin - Lookups Management JavaScript
 */

let allLookups = [];
let allCategories = [];
let lookupModal = null;

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    lookupModal = new bootstrap.Modal(document.getElementById('lookup-modal'));
    aiLookups_loadData();
});

/**
 * Load all lookups and categories
 */
async function aiLookups_loadData() {
    try {
        // Load lookups
        const lookupsResponse = await fetch('/ai/admin/aicentral_lookupsCode.php?action=getLookups');
        const lookupsData = await lookupsResponse.json();

        if (lookupsData.success) {
            allLookups = lookupsData.lookups;
        }

        // Load categories
        const categoriesResponse = await fetch('/ai/admin/aicentral_lookupsCode.php?action=getLookupCategories');
        const categoriesData = await categoriesResponse.json();

        if (categoriesData.success) {
            allCategories = categoriesData.categories;
            aiLookups_populateCategoryFilter();
            aiLookups_populateCategoryList();
        }

        aiLookups_renderTable();
    } catch (error) {
        console.error('Error loading lookups:', error);
        showNotification('Error loading lookups: ' + error.message, 'error');
    }
}

/**
 * Populate category filter dropdown
 */
function aiLookups_populateCategoryFilter() {
    const filter = document.getElementById('lookup-filter-category');
    filter.innerHTML = '<option value="">All Categories</option>';

    allCategories.forEach(cat => {
        const option = document.createElement('option');
        option.value = cat.category;
        option.textContent = `${cat.category} (${cat.count})`;
        filter.appendChild(option);
    });
}

/**
 * Populate category datalist for form
 */
function aiLookups_populateCategoryList() {
    const datalist = document.getElementById('category-list');
    datalist.innerHTML = '';

    allCategories.forEach(cat => {
        const option = document.createElement('option');
        option.value = cat.category;
        datalist.appendChild(option);
    });
}

/**
 * Render lookups table
 */
function aiLookups_renderTable() {
    const tbody = document.getElementById('lookups-table-body');
    const searchTerm = document.getElementById('lookup-filter-search').value.toLowerCase();
    const categoryFilter = document.getElementById('lookup-filter-category').value;

    let filtered = allLookups;

    // Apply category filter
    if (categoryFilter) {
        filtered = filtered.filter(l => l.lookup_name === categoryFilter);
    }

    // Apply search filter
    if (searchTerm) {
        filtered = filtered.filter(l =>
            l.lookup_name.toLowerCase().includes(searchTerm) ||
            l.lookup_value.toLowerCase().includes(searchTerm) ||
            (l.lookup_desc && l.lookup_desc.toLowerCase().includes(searchTerm))
        );
    }

    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No lookups found</td></tr>';
        return;
    }

    tbody.innerHTML = filtered.map(lookup => `
        <tr>
            <td><span class="badge bg-info">${aiLookups_escapeHtml(lookup.lookup_name)}</span></td>
            <td><code>${aiLookups_escapeHtml(lookup.lookup_value)}</code></td>
            <td>${aiLookups_escapeHtml(lookup.lookup_desc || '')}</td>
            <td class="text-center">${lookup.lookup_order || '-'}</td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="aiLookups_editLookup(${lookup.id})" title="Edit">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" onclick="aiLookups_confirmDelete(${lookup.id})" title="Delete">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

/**
 * Filter lookups
 */
function aiLookups_filterLookups() {
    aiLookups_renderTable();
}

/**
 * Clear filters
 */
function aiLookups_clearFilters() {
    document.getElementById('lookup-filter-category').value = '';
    document.getElementById('lookup-filter-search').value = '';
    aiLookups_renderTable();
}

/**
 * Show add dialog
 */
function aiLookups_showAddDialog() {
    document.getElementById('lookup-modal-title').textContent = 'Add Lookup';
    document.getElementById('lookup-form').reset();
    document.getElementById('lookup-id').value = '';
    lookupModal.show();
}

/**
 * Edit lookup
 */
function aiLookups_editLookup(id) {
    const lookup = allLookups.find(l => l.id === id);
    if (!lookup) return;

    document.getElementById('lookup-modal-title').textContent = 'Edit Lookup';
    document.getElementById('lookup-id').value = lookup.id;
    document.getElementById('lookup-category').value = lookup.lookup_name;
    document.getElementById('lookup-value').value = lookup.lookup_value;
    document.getElementById('lookup-order').value = lookup.lookup_order || '';
    document.getElementById('lookup-desc').value = lookup.lookup_desc || '';

    lookupModal.show();
}

/**
 * Save lookup
 */
async function aiLookups_saveLookup() {
    const form = document.getElementById('lookup-form');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = new FormData(form);
    formData.append('action', 'saveLookup');

    try {
        const response = await fetch('/ai/admin/aicentral_lookupsCode.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            showNotification('Lookup saved successfully', 'success');
            lookupModal.hide();
            await aiLookups_loadData();
        } else {
            showNotification('Error: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Error saving lookup:', error);
        showNotification('Error saving lookup: ' + error.message, 'error');
    }
}

/**
 * Confirm delete
 */
function aiLookups_confirmDelete(id) {
    const lookup = allLookups.find(l => l.id === id);
    if (!lookup) return;

    if (confirm(`Delete lookup "${lookup.lookup_value}" from category "${lookup.lookup_name}"?`)) {
        aiLookups_deleteLookup(id);
    }
}

/**
 * Delete lookup
 */
async function aiLookups_deleteLookup(id) {
    const formData = new FormData();
    formData.append('action', 'deleteLookup');
    formData.append('id', id);

    try {
        const response = await fetch('/ai/admin/aicentral_lookupsCode.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            showNotification('Lookup deleted successfully', 'success');
            await aiLookups_loadData();
        } else {
            showNotification('Error: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Error deleting lookup:', error);
        showNotification('Error deleting lookup: ' + error.message, 'error');
    }
}

/**
 * Escape HTML
 */
function aiLookups_escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
