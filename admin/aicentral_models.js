/**
 * AI Central Admin Models - Frontend JavaScript
 */

let aiCentral_modelsData = {
    models: [],
    providers: [],
    filteredModels: [],
    currentView: 'cards',
    sortColumn: null,
    sortDirection: 'asc'
};

let aiCentral_modelModal = null;

/**
 * Initialize models page
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap modal
    const modalElement = document.getElementById('aicentral-model-dialog');
    aiCentral_modelModal = new bootstrap.Modal(modalElement);

    aiCentral_loadPreferences();
    aiCentral_loadProviders();
    aiCentral_loadModels();

    // Set today's date as default for effective date
    document.getElementById('aicentral-model-effective-date').value = new Date().toISOString().split('T')[0];

    aiCentral_updateViewToggleIcon();
});

/**
 * Load providers for dropdown
 */
async function aiCentral_loadProviders() {
    try {
        const response = await fetch('/ai/admin/aicentral_modelsCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getProviders' })
        });

        const data = await response.json();
        if (data.success) {
            aiCentral_modelsData.providers = data.providers;

            // Populate filter dropdown
            const filterSelect = document.getElementById('aicentral-filter-provider');
            filterSelect.innerHTML = '<option value="">All Providers</option>' +
                data.providers.map(p => `<option value="${aiCentral_escapeHtml(p.provider_code)}">${aiCentral_escapeHtml(p.provider_name)}</option>`).join('');

            // Populate dialog dropdown
            const dialogSelect = document.getElementById('aicentral-model-provider');
            dialogSelect.innerHTML = '<option value="">Select provider...</option>' +
                data.providers.map(p => `<option value="${p.provider_id}">${aiCentral_escapeHtml(p.provider_name)}</option>`).join('');
        }
    } catch (error) {
        console.error('Error loading providers:', error);
        showAlert('Failed to load providers. Please refresh the page.', 'Network Error', 'error');
    }
}

/**
 * Load models
 */
async function aiCentral_loadModels() {
    try {
        const response = await fetch('/ai/admin/aicentral_modelsCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getModels' })
        });

        const data = await response.json();
        if (data.success) {
            aiCentral_modelsData.models = data.models;
            aiCentral_filterModels();
        }
    } catch (error) {
        console.error('Error loading models:', error);
        showAlert('Failed to load models. Please refresh the page.', 'Network Error', 'error');

        // Show error state in UI
        const container = document.getElementById('aicentral-models-list');
        container.innerHTML = `
            <div class="col-12 text-center py-5">
                <i class="bi bi-exclamation-triangle display-1 text-danger"></i>
                <p class="mt-3 text-danger fs-5">Failed to load models</p>
                <button class="btn btn-primary" onclick="aiCentral_loadModels()">
                    <i class="bi bi-arrow-clockwise"></i> Retry
                </button>
            </div>
        `;
    }
}

/**
 * Filter models
 */
function aiCentral_filterModels() {
    const providerFilter = document.getElementById('aicentral-filter-provider').value;
    const tierFilter = document.getElementById('aicentral-filter-tier').value;
    const statusFilter = document.getElementById('aicentral-filter-status').value;
    const searchTerm = document.getElementById('aicentral-filter-search').value.toLowerCase();

    aiCentral_modelsData.filteredModels = aiCentral_modelsData.models.filter(model => {
        if (providerFilter && model.provider_code !== providerFilter) return false;
        if (tierFilter && model.model_tier !== tierFilter) return false;
        if (statusFilter !== '' && model.is_active !== (statusFilter === '1')) return false;
        if (searchTerm && !model.model_display_name.toLowerCase().includes(searchTerm) &&
            !model.model_code.toLowerCase().includes(searchTerm)) return false;
        return true;
    });

    aiCentral_savePreferences();
    aiCentral_renderModels();
}

/**
 * Render models
 */
function aiCentral_renderModels() {
    const container = document.getElementById('aicentral-models-list');

    if (aiCentral_modelsData.filteredModels.length === 0) {
        container.innerHTML = '<div class="text-center text-muted py-5">No models found</div>';
        return;
    }

    if (aiCentral_modelsData.currentView === 'table') {
        container.innerHTML = aiCentral_renderModelsTable();
    } else {
        container.innerHTML = aiCentral_renderModelsCards();
    }
}

/**
 * Render models as cards
 */
function aiCentral_renderModelsCards() {
    // Group by provider
    const grouped = {};
    aiCentral_modelsData.filteredModels.forEach(model => {
        if (!grouped[model.provider_name]) {
            grouped[model.provider_name] = [];
        }
        grouped[model.provider_name].push(model);
    });

    let html = '';
    for (const [providerName, models] of Object.entries(grouped)) {
        html += `
            <div class="mb-4">
                <h4 class="text-primary mb-3">${aiCentral_escapeHtml(providerName)}</h4>
                <div class="row g-3">
                    ${models.map(model => aiCentral_renderModelCard(model)).join('')}
                </div>
            </div>
        `;
    }

    return html;
}

/**
 * Render models as table
 */
function aiCentral_renderModelsTable() {
    const sortIcon = (column) => {
        if (aiCentral_modelsData.sortColumn !== column) return '<i class="bi bi-arrow-down-up text-muted"></i>';
        return aiCentral_modelsData.sortDirection === 'asc' ? '<i class="bi bi-arrow-up text-primary"></i>' : '<i class="bi bi-arrow-down text-primary"></i>';
    };

    return `
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="cursor: pointer;" onclick="aiCentral_sortTable('provider_name')">
                            Provider ${sortIcon('provider_name')}
                        </th>
                        <th style="cursor: pointer;" onclick="aiCentral_sortTable('model_display_name')">
                            Model ${sortIcon('model_display_name')}
                        </th>
                        <th style="cursor: pointer;" onclick="aiCentral_sortTable('model_code')">
                            Code ${sortIcon('model_code')}
                        </th>
                        <th style="cursor: pointer;" onclick="aiCentral_sortTable('model_tier')">
                            Tier ${sortIcon('model_tier')}
                        </th>
                        <th style="cursor: pointer;" onclick="aiCentral_sortTable('context_window')" class="text-end">
                            Context ${sortIcon('context_window')}
                        </th>
                        <th style="cursor: pointer;" onclick="aiCentral_sortTable('input_cost_per_million')" class="text-end">
                            Input $/M ${sortIcon('input_cost_per_million')}
                        </th>
                        <th style="cursor: pointer;" onclick="aiCentral_sortTable('output_cost_per_million')" class="text-end">
                            Output $/M ${sortIcon('output_cost_per_million')}
                        </th>
                        <th>Features</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${aiCentral_modelsData.filteredModels.map(model => aiCentral_renderModelRow(model)).join('')}
                </tbody>
            </table>
        </div>
    `;
}

/**
 * Render single model table row
 */
function aiCentral_renderModelRow(model) {
    const tierColors = {
        'budget': 'secondary',
        'standard': 'info',
        'premium': 'warning',
        'flagship': 'danger'
    };
    const tierColor = tierColors[model.model_tier] || 'secondary';

    return `
        <tr class="${!model.is_active ? 'opacity-50' : ''}">
            <td>${aiCentral_escapeHtml(model.provider_name)}</td>
            <td><strong>${aiCentral_escapeHtml(model.model_display_name)}</strong></td>
            <td><code class="small">${aiCentral_escapeHtml(model.model_code)}</code></td>
            <td><span class="badge bg-${tierColor}">${model.model_tier}</span></td>
            <td class="text-end">${model.context_window.toLocaleString()}</td>
            <td class="text-end">$${model.input_cost_per_million.toFixed(2)}</td>
            <td class="text-end">$${model.output_cost_per_million.toFixed(2)}</td>
            <td>
                ${model.supports_vision ? '<span class="badge bg-primary me-1">Vision</span>' : ''}
                ${model.supports_function_calling ? '<span class="badge bg-success me-1">Functions</span>' : ''}
                ${model.supports_streaming ? '<span class="badge bg-info me-1">Streaming</span>' : ''}
            </td>
            <td>
                ${model.is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>'}
            </td>
            <td class="text-end">
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" onclick="aiCentral_editModel(${model.model_id})" title="Edit">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn ${model.is_active ? 'btn-outline-warning' : 'btn-outline-success'}"
                            onclick="aiCentral_toggleModelStatus(${model.model_id})" title="Toggle Status">
                        ${model.is_active ? '<i class="bi bi-pause"></i>' : '<i class="bi bi-play"></i>'}
                    </button>
                </div>
            </td>
        </tr>
    `;
}

/**
 * Render single model card
 */
function aiCentral_renderModelCard(model) {
    const tierColors = {
        'budget': 'secondary',
        'standard': 'info',
        'premium': 'warning',
        'flagship': 'danger'
    };
    const tierColor = tierColors[model.model_tier] || 'secondary';

    return `
        <div class="col-lg-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 ${!model.is_active ? 'opacity-75' : ''}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5 class="card-title mb-1">${aiCentral_escapeHtml(model.model_display_name)}</h5>
                            <small class="text-muted">${aiCentral_escapeHtml(model.model_code)}</small>
                        </div>
                        <span class="badge bg-${tierColor}">${model.model_tier}</span>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <small class="text-muted">Max Tokens:</small>
                            <small><strong>${model.max_tokens.toLocaleString()}</strong></small>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <small class="text-muted">Context:</small>
                            <small><strong>${model.context_window.toLocaleString()}</strong></small>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <small class="text-muted">Input Cost:</small>
                            <small><strong>$${model.input_cost_per_million}/M</strong></small>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <small class="text-muted">Output Cost:</small>
                            <small><strong>$${model.output_cost_per_million}/M</strong></small>
                        </div>
                    </div>

                    <div class="mb-3">
                        ${model.supports_vision ? '<span class="badge bg-primary me-1">Vision</span>' : ''}
                        ${model.supports_function_calling ? '<span class="badge bg-success me-1">Functions</span>' : ''}
                        ${model.supports_streaming ? '<span class="badge bg-info me-1">Streaming</span>' : ''}
                    </div>
                </div>
                <div class="card-footer bg-white border-top-0">
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-primary flex-fill" onclick="aiCentral_editModel(${model.model_id})">
                            <i class="bi bi-pencil"></i> Edit
                        </button>
                        <button class="btn btn-sm ${model.is_active ? 'btn-outline-warning' : 'btn-outline-success'}"
                                onclick="aiCentral_toggleModelStatus(${model.model_id})">
                            ${model.is_active ? '<i class="bi bi-pause"></i>' : '<i class="bi bi-play"></i>'}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
}

/**
 * Show add model dialog
 */
function aiCentral_showAddModelDialog() {
    document.getElementById('aicentral-dialog-title').textContent = 'Add Model';
    document.getElementById('aicentral-model-id').value = '';
    document.getElementById('aicentral-model-provider').value = '';
    document.getElementById('aicentral-model-code').value = '';
    document.getElementById('aicentral-model-name').value = '';
    document.getElementById('aicentral-model-tier').value = 'standard';
    document.getElementById('aicentral-model-sort').value = '100';
    document.getElementById('aicentral-model-max-tokens').value = '4096';
    document.getElementById('aicentral-model-context').value = '200000';
    document.getElementById('aicentral-model-input-cost').value = '';
    document.getElementById('aicentral-model-output-cost').value = '';
    document.getElementById('aicentral-model-thinking-cost').value = '';
    document.getElementById('aicentral-model-effective-date').value = new Date().toISOString().split('T')[0];
    document.getElementById('aicentral-model-vision').checked = false;
    document.getElementById('aicentral-model-functions').checked = false;
    document.getElementById('aicentral-model-streaming').checked = true;
    document.getElementById('aicentral-model-active').checked = true;
    document.getElementById('aicentral-model-notes').value = '';

    aiCentral_modelModal.show();
}

/**
 * Edit model
 */
function aiCentral_editModel(modelId) {
    // Convert to number to ensure type matching
    const modelIdNum = parseInt(modelId);
    const model = aiCentral_modelsData.models.find(m => parseInt(m.model_id) === modelIdNum);

    if (!model) {
        console.error('Model not found:', modelIdNum, 'Available models:', aiCentral_modelsData.models);
        showAlert('Model not found. Please refresh the page and try again.', 'Error', 'error');
        return;
    }

    document.getElementById('aicentral-dialog-title').textContent = 'Edit Model';
    document.getElementById('aicentral-model-id').value = model.model_id;
    document.getElementById('aicentral-model-provider').value = model.provider_id;
    document.getElementById('aicentral-model-code').value = model.model_code;
    document.getElementById('aicentral-model-name').value = model.model_display_name;
    document.getElementById('aicentral-model-tier').value = model.model_tier;
    document.getElementById('aicentral-model-sort').value = model.sort_order;
    document.getElementById('aicentral-model-max-tokens').value = model.max_tokens;
    document.getElementById('aicentral-model-context').value = model.context_window;
    document.getElementById('aicentral-model-input-cost').value = model.input_cost_per_million;
    document.getElementById('aicentral-model-output-cost').value = model.output_cost_per_million;
    document.getElementById('aicentral-model-thinking-cost').value = model.thinking_cost_per_million || 0;

    // Ensure date is in YYYY-MM-DD format
    let effectiveDate = model.pricing_effective_date;

    // Handle invalid date formats (e.g., just year "2025")
    if (effectiveDate) {
        if (effectiveDate.includes(' ')) {
            effectiveDate = effectiveDate.split(' ')[0]; // Remove time portion if present
        }
        // Check if date is valid YYYY-MM-DD format
        if (!/^\d{4}-\d{2}-\d{2}$/.test(effectiveDate)) {
            console.warn('Invalid date format from database:', effectiveDate, 'Using today instead');
            effectiveDate = new Date().toISOString().split('T')[0];
        }
    } else {
        effectiveDate = new Date().toISOString().split('T')[0];
    }

    document.getElementById('aicentral-model-effective-date').value = effectiveDate;

    document.getElementById('aicentral-model-vision').checked = model.supports_vision;
    document.getElementById('aicentral-model-functions').checked = model.supports_function_calling;
    document.getElementById('aicentral-model-streaming').checked = model.supports_streaming;
    document.getElementById('aicentral-model-active').checked = model.is_active;
    document.getElementById('aicentral-model-notes').value = model.notes || '';

    aiCentral_modelModal.show();
}

/**
 * Save model
 */
async function aiCentral_saveModel() {
    const effectiveDateElement = document.getElementById('aicentral-model-effective-date');
    const effectiveDate = effectiveDateElement ? effectiveDateElement.value : '';

    console.log('Date element:', effectiveDateElement);
    console.log('Date value:', effectiveDate);
    console.log('Date length:', effectiveDate.length);

    // Validate date format
    if (!effectiveDate || effectiveDate.length !== 10) {
        alert('Please enter a valid pricing effective date (YYYY-MM-DD). Current value: "' + effectiveDate + '"');
        return;
    }

    const formData = new URLSearchParams({
        action: 'saveModel',
        model_id: document.getElementById('aicentral-model-id').value,
        provider_id: document.getElementById('aicentral-model-provider').value,
        model_code: document.getElementById('aicentral-model-code').value,
        model_display_name: document.getElementById('aicentral-model-name').value,
        model_tier: document.getElementById('aicentral-model-tier').value,
        sort_order: document.getElementById('aicentral-model-sort').value,
        max_tokens: document.getElementById('aicentral-model-max-tokens').value,
        context_window: document.getElementById('aicentral-model-context').value,
        input_cost_per_million: document.getElementById('aicentral-model-input-cost').value,
        output_cost_per_million: document.getElementById('aicentral-model-output-cost').value,
        thinking_cost_per_million: document.getElementById('aicentral-model-thinking-cost').value || 0,
        pricing_effective_date: effectiveDate,
        notes: document.getElementById('aicentral-model-notes').value
    });

    if (document.getElementById('aicentral-model-vision').checked) formData.append('supports_vision', '1');
    if (document.getElementById('aicentral-model-functions').checked) formData.append('supports_function_calling', '1');
    if (document.getElementById('aicentral-model-streaming').checked) formData.append('supports_streaming', '1');
    if (document.getElementById('aicentral-model-active').checked) formData.append('is_active', '1');

    // Debug: Log what we're sending
    console.log('FormData being sent:');
    for (let [key, value] of formData.entries()) {
        console.log(`  ${key}: ${value}`);
    }

    try {
        const response = await fetch('/ai/admin/aicentral_modelsCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData
        });

        const data = await response.json();
        if (data.success) {
            aiCentral_modelModal.hide();
            aiCentral_loadModels();
        } else {
            alert('Error: ' + data.error);
        }
    } catch (error) {
        console.error('Error saving model:', error);
        alert('Error saving model');
    }
}

/**
 * Toggle model status
 */
async function aiCentral_toggleModelStatus(modelId) {
    if (!confirm('Toggle this model\'s active status?')) return;

    try {
        const response = await fetch('/ai/admin/aicentral_modelsCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'toggleModelStatus',
                model_id: modelId
            })
        });

        const data = await response.json();
        if (data.success) {
            aiCentral_loadModels();
        } else {
            alert('Error: ' + data.error);
        }
    } catch (error) {
        console.error('Error toggling model status:', error);
        alert('Error toggling model status');
    }
}

/**
 * Utility: Escape HTML
 */
function aiCentral_escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Load preferences from localStorage
 */
function aiCentral_loadPreferences() {
    const savedView = localStorage.getItem('aicentral_models_view');
    if (savedView) {
        aiCentral_modelsData.currentView = savedView;
    }

    const savedFilters = localStorage.getItem('aicentral_models_filters');
    if (savedFilters) {
        try {
            const filters = JSON.parse(savedFilters);
            if (filters.provider) document.getElementById('aicentral-filter-provider').value = filters.provider;
            if (filters.tier) document.getElementById('aicentral-filter-tier').value = filters.tier;
            if (filters.status !== undefined) document.getElementById('aicentral-filter-status').value = filters.status;
            if (filters.search) document.getElementById('aicentral-filter-search').value = filters.search;
        } catch (e) {
            console.error('Error loading filters:', e);
        }
    }
}

/**
 * Save preferences to localStorage
 */
function aiCentral_savePreferences() {
    localStorage.setItem('aicentral_models_view', aiCentral_modelsData.currentView);

    const filters = {
        provider: document.getElementById('aicentral-filter-provider').value,
        tier: document.getElementById('aicentral-filter-tier').value,
        status: document.getElementById('aicentral-filter-status').value,
        search: document.getElementById('aicentral-filter-search').value
    };
    localStorage.setItem('aicentral_models_filters', JSON.stringify(filters));
}

/**
 * Toggle between card and table view
 */
function aiCentral_toggleView() {
    aiCentral_modelsData.currentView = aiCentral_modelsData.currentView === 'cards' ? 'table' : 'cards';
    aiCentral_savePreferences();
    aiCentral_updateViewToggleIcon();
    aiCentral_renderModels();
}

/**
 * Update view toggle button icon
 */
function aiCentral_updateViewToggleIcon() {
    const icon = document.querySelector('#aicentral-view-toggle i');
    if (icon) {
        if (aiCentral_modelsData.currentView === 'cards') {
            icon.className = 'bi bi-list-ul';
        } else {
            icon.className = 'bi bi-grid-3x3-gap';
        }
    }
}

/**
 * Clear all filters
 */
function aiCentral_clearFilters() {
    document.getElementById('aicentral-filter-provider').value = '';
    document.getElementById('aicentral-filter-tier').value = '';
    document.getElementById('aicentral-filter-status').value = '1';
    document.getElementById('aicentral-filter-search').value = '';
    aiCentral_savePreferences();
    aiCentral_filterModels();
}

/**
 * Sort table by column
 */
function aiCentral_sortTable(column) {
    if (aiCentral_modelsData.sortColumn === column) {
        aiCentral_modelsData.sortDirection = aiCentral_modelsData.sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        aiCentral_modelsData.sortColumn = column;
        aiCentral_modelsData.sortDirection = 'asc';
    }

    aiCentral_modelsData.filteredModels.sort((a, b) => {
        let aVal = a[column];
        let bVal = b[column];

        if (column.includes('cost') || column.includes('tokens') || column === 'context_window' || column === 'max_tokens') {
            aVal = parseFloat(aVal) || 0;
            bVal = parseFloat(bVal) || 0;
        } else {
            aVal = String(aVal || '').toLowerCase();
            bVal = String(bVal || '').toLowerCase();
        }

        if (aVal < bVal) return aiCentral_modelsData.sortDirection === 'asc' ? -1 : 1;
        if (aVal > bVal) return aiCentral_modelsData.sortDirection === 'asc' ? 1 : -1;
        return 0;
    });

    aiCentral_renderModels();
}
