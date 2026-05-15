/**
 * AI Central Admin - Model Capabilities Frontend JavaScript
 */

let aiCentral_capabilitiesData = {
    capabilities: [],
    providers: [],
    models: [],
    filteredCapabilities: [],
    currentCapability: null
};

let aiCentral_capabilityModal = null;

/**
 * Initialize page
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap modal
    const modalElement = document.getElementById('aicentral-capability-modal');
    aiCentral_capabilityModal = new bootstrap.Modal(modalElement);

    aiCentral_loadProviders();
    aiCentral_loadModels();
    aiCentral_loadCapabilities();
});

/**
 * Load providers for filter
 */
async function aiCentral_loadProviders() {
    try {
        const response = await fetch('/ai/admin/aicentral_model_capabilitiesCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getProviders' })
        });

        const data = await response.json();
        if (data.success) {
            aiCentral_capabilitiesData.providers = data.providers;

            // Populate filter dropdown
            const filterSelect = document.getElementById('aicentral-filter-provider');
            filterSelect.innerHTML = '<option value="">All Providers</option>' +
                data.providers.map(p => `<option value="${aiCentral_escapeHtml(p.provider_code)}">${aiCentral_escapeHtml(p.provider_name)}</option>`).join('');
        }
    } catch (error) {
        console.error('Error loading providers:', error);
    }
}

/**
 * Load models for filter
 */
async function aiCentral_loadModels() {
    try {
        const response = await fetch('/ai/admin/aicentral_model_capabilitiesCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getModels' })
        });

        const data = await response.json();
        if (data.success) {
            aiCentral_capabilitiesData.models = data.models;

            // Populate filter dropdown
            const filterSelect = document.getElementById('aicentral-filter-model');
            filterSelect.innerHTML = '<option value="">All Models</option>' +
                data.models.map(m => `<option value="${m.model_id}">${aiCentral_escapeHtml(m.provider_name)} - ${aiCentral_escapeHtml(m.model_display_name)}</option>`).join('');
        }
    } catch (error) {
        console.error('Error loading models:', error);
    }
}

/**
 * Load capabilities
 */
async function aiCentral_loadCapabilities() {
    try {
        const response = await fetch('/ai/admin/aicentral_model_capabilitiesCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getCapabilities' })
        });

        const data = await response.json();
        if (data.success) {
            aiCentral_capabilitiesData.capabilities = data.capabilities;
            aiCentral_filterCapabilities();
        }
    } catch (error) {
        console.error('Error loading capabilities:', error);
        showAlert('Failed to load capabilities. Please refresh the page.', 'Error', 'error');
    }
}

/**
 * Filter capabilities
 */
function aiCentral_filterCapabilities() {
    const providerFilter = document.getElementById('aicentral-filter-provider').value;
    const modelFilter = document.getElementById('aicentral-filter-model').value;
    const capabilityFilter = document.getElementById('aicentral-filter-capability').value;
    const searchTerm = document.getElementById('aicentral-filter-search').value.toLowerCase();

    aiCentral_capabilitiesData.filteredCapabilities = aiCentral_capabilitiesData.capabilities.filter(cap => {
        if (providerFilter && cap.provider_code !== providerFilter) return false;
        if (modelFilter && cap.model_id !== parseInt(modelFilter)) return false;
        if (capabilityFilter && cap.capability_code !== capabilityFilter) return false;
        if (searchTerm && !cap.model_display_name.toLowerCase().includes(searchTerm) &&
            !cap.capability_name.toLowerCase().includes(searchTerm)) return false;
        return true;
    });

    aiCentral_renderCapabilities();
}

/**
 * Render capabilities
 */
function aiCentral_renderCapabilities() {
    const container = document.getElementById('aicentral-capabilities-list');

    if (aiCentral_capabilitiesData.filteredCapabilities.length === 0) {
        container.innerHTML = `
            <div class="text-center py-5">
                <i class="bi bi-inbox display-1 text-muted"></i>
                <p class="mt-3 text-muted">No capabilities found</p>
            </div>
        `;
        return;
    }

    // Group by model
    const grouped = {};
    aiCentral_capabilitiesData.filteredCapabilities.forEach(cap => {
        const key = `${cap.provider_name} - ${cap.model_display_name}`;
        if (!grouped[key]) {
            grouped[key] = {
                model_id: cap.model_id,
                model_display_name: cap.model_display_name,
                provider_name: cap.provider_name,
                capabilities: []
            };
        }
        grouped[key].capabilities.push(cap);
    });

    let html = '';
    for (const [modelKey, modelData] of Object.entries(grouped)) {
        html += `
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="bi bi-cpu"></i> ${aiCentral_escapeHtml(modelKey)}
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 25%;">Capability</th>
                                    <th style="width: 15%;">Status</th>
                                    <th style="width: 20%;">Cost Structure</th>
                                    <th style="width: 15%;">Default Max Uses</th>
                                    <th style="width: 15%;">Special Flags</th>
                                    <th style="width: 10%;" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${modelData.capabilities.map(cap => aiCentral_renderCapabilityRow(cap)).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
    }

    container.innerHTML = html;
}

/**
 * Render single capability row
 */
function aiCentral_renderCapabilityRow(cap) {
    // Determine cost structure display
    let costDisplay = '';
    if (cap.is_free) {
        costDisplay = '<span class="badge bg-success"><i class="bi bi-gift"></i> Free</span>';
    } else if (cap.cost_per_use !== null && cap.cost_per_use > 0) {
        costDisplay = `<span class="badge bg-warning text-dark">$${cap.cost_per_use.toFixed(6)}/use</span>`;
    } else if (cap.cost_per_1000 !== null && cap.cost_per_1000 > 0) {
        costDisplay = `<span class="badge bg-info">$${cap.cost_per_1000.toFixed(2)}/1000</span>`;
    } else {
        costDisplay = '<span class="badge bg-secondary">Included in tokens</span>';
    }

    // Status
    const statusBadge = cap.is_supported
        ? '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Supported</span>'
        : '<span class="badge bg-danger"><i class="bi bi-x-circle"></i> Not Supported</span>';

    // Special flags
    let flags = [];
    if (cap.includes_result_tokens) {
        flags.push('<span class="badge bg-info" title="Provider charges for result tokens"><i class="bi bi-arrow-left-right"></i> Incl. Results</span>');
    }
    const flagsDisplay = flags.length > 0 ? flags.join(' ') : '<span class="text-muted">-</span>';

    // Max uses default
    const maxUsesDisplay = cap.max_uses_default
        ? `<span class="badge bg-primary">${cap.max_uses_default} max</span>`
        : '<span class="text-muted">No default</span>';

    return `
        <tr class="${!cap.is_supported ? 'table-secondary' : ''}">
            <td>
                <strong>${aiCentral_escapeHtml(cap.capability_name)}</strong><br>
                <small class="text-muted font-monospace">${aiCentral_escapeHtml(cap.capability_code)}</small>
            </td>
            <td>${statusBadge}</td>
            <td>${costDisplay}</td>
            <td>${maxUsesDisplay}</td>
            <td>${flagsDisplay}</td>
            <td class="text-end">
                <button class="btn btn-sm btn-outline-primary" onclick="aiCentral_editCapability(${cap.capability_id})" title="Edit">
                    <i class="bi bi-pencil"></i>
                </button>
            </td>
        </tr>
    `;
}

/**
 * Edit capability
 */
async function aiCentral_editCapability(capabilityId) {
    try {
        const response = await fetch('/ai/admin/aicentral_model_capabilitiesCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'getCapability',
                capability_id: capabilityId
            })
        });

        const data = await response.json();
        if (!data.success) {
            showAlert(data.error || 'Error loading capability', 'Error', 'error');
            return;
        }

        const cap = data.capability;
        aiCentral_capabilitiesData.currentCapability = cap;

        // Populate form
        document.getElementById('aicentral-capability-id').value = cap.capability_id;
        document.getElementById('aicentral-display-model').textContent = `${cap.provider_name} - ${cap.model_display_name}`;
        document.getElementById('aicentral-display-capability').textContent = cap.capability_name;
        document.getElementById('aicentral-capability-supported').checked = cap.is_supported;
        document.getElementById('aicentral-cost-per-use').value = cap.cost_per_use || '';
        document.getElementById('aicentral-cost-per-1000').value = cap.cost_per_1000 || '';
        document.getElementById('aicentral-max-uses-default').value = cap.max_uses_default || '';
        document.getElementById('aicentral-includes-result-tokens').checked = cap.includes_result_tokens;
        document.getElementById('aicentral-provider-tool-name').value = cap.provider_tool_name || '';
        document.getElementById('aicentral-api-format-notes').value = cap.api_format_notes || '';

        // Determine cost type
        if (cap.is_free) {
            document.getElementById('cost-type-free').checked = true;
        } else if (cap.cost_per_use !== null && cap.cost_per_use > 0) {
            document.getElementById('cost-type-per-use').checked = true;
        } else if (cap.cost_per_1000 !== null && cap.cost_per_1000 > 0) {
            document.getElementById('cost-type-per-1000').checked = true;
        } else {
            document.getElementById('cost-type-included').checked = true;
        }

        aiCentral_updateCostFields();

        // Show modal
        aiCentral_capabilityModal.show();

    } catch (error) {
        console.error('Error loading capability:', error);
        showAlert('Error loading capability', 'Error', 'error');
    }
}

/**
 * Update cost fields visibility based on selected cost type
 */
function aiCentral_updateCostFields() {
    const costType = document.querySelector('input[name="cost-type"]:checked')?.value;

    document.getElementById('cost-per-use-field').style.display = costType === 'per-use' ? 'block' : 'none';
    document.getElementById('cost-per-1000-field').style.display = costType === 'per-1000' ? 'block' : 'none';
}

/**
 * Save capability
 */
async function aiCentral_saveCapability() {
    const capabilityId = document.getElementById('aicentral-capability-id').value;
    const costType = document.querySelector('input[name="cost-type"]:checked')?.value;

    // Build form data
    const formData = new URLSearchParams({
        action: 'saveCapability',
        capability_id: capabilityId,
        max_uses_default: document.getElementById('aicentral-max-uses-default').value,
        provider_tool_name: document.getElementById('aicentral-provider-tool-name').value,
        api_format_notes: document.getElementById('aicentral-api-format-notes').value
    });

    if (document.getElementById('aicentral-capability-supported').checked) {
        formData.append('is_supported', '1');
    }

    if (document.getElementById('aicentral-includes-result-tokens').checked) {
        formData.append('includes_result_tokens', '1');
    }

    // Set cost fields based on cost type
    if (costType === 'free') {
        formData.append('is_free', '1');
    } else if (costType === 'per-use') {
        const costPerUse = document.getElementById('aicentral-cost-per-use').value;
        if (costPerUse) {
            formData.append('cost_per_use', costPerUse);
        }
    } else if (costType === 'per-1000') {
        const costPer1000 = document.getElementById('aicentral-cost-per-1000').value;
        if (costPer1000) {
            formData.append('cost_per_1000', costPer1000);
        }
    }
    // 'included' type has no additional fields

    try {
        const response = await fetch('/ai/admin/aicentral_model_capabilitiesCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData
        });

        const data = await response.json();
        if (data.success) {
            showNotification('Capability saved successfully', 'success');
            aiCentral_capabilityModal.hide();
            aiCentral_loadCapabilities();
        } else {
            showAlert(data.error || 'Error saving capability', 'Error', 'error');
        }
    } catch (error) {
        console.error('Error saving capability:', error);
        showAlert('Error saving capability', 'Error', 'error');
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
