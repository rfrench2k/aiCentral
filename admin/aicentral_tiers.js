/**
 * AI Central Admin Tiers - Frontend JavaScript (Bootstrap 5.3.3)
 */

let aiCentral_tiersData = {
    tiers: [],
    filteredTiers: [],
    currentView: 'cards', // 'cards' or 'table'
    sortColumn: null,
    sortDirection: 'asc'
};

let aiCentral_tierModal = null;

/**
 * Initialize tiers page
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap modal
    const modalElement = document.getElementById('aicentral-tier-modal');
    aiCentral_tierModal = new bootstrap.Modal(modalElement);

    // Initialize Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Load saved preferences
    aiCentral_loadPreferences();

    aiCentral_loadTiers();

    // Update view toggle icon
    aiCentral_updateViewToggleIcon();

    // Add checkbox event listeners for capability max uses
    document.getElementById('aicentral-tier-cap-websearch').addEventListener('change', function() {
        aiCentral_toggleTierCapabilityMaxUses('websearch', this.checked);
    });
    document.getElementById('aicentral-tier-cap-webfetch').addEventListener('change', function() {
        aiCentral_toggleTierCapabilityMaxUses('webfetch', this.checked);
    });
    document.getElementById('aicentral-tier-cap-vision').addEventListener('change', function() {
        aiCentral_toggleTierCapabilityMaxUses('vision', this.checked);
    });
});

/**
 * Load tiers
 */
async function aiCentral_loadTiers() {
    try {
        const response = await fetch('/ai/admin/aicentral_tiersCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getTiers' })
        });

        const data = await response.json();
        if (data.success) {
            aiCentral_tiersData.tiers = data.tiers;
            aiCentral_filterTiers();
        }
    } catch (error) {
        console.error('Error loading tiers:', error);
        showAlert('Failed to load tiers. Please refresh the page.', 'Network Error', 'error');

        // Show error state in UI
        const container = document.getElementById('aicentral-tiers-list');
        container.innerHTML = `
            <div class="col-12 text-center py-5">
                <i class="bi bi-exclamation-triangle display-1 text-danger"></i>
                <p class="mt-3 text-danger fs-5">Failed to load tiers</p>
                <button class="btn btn-primary" onclick="aiCentral_loadTiers()">
                    <i class="bi bi-arrow-clockwise"></i> Retry
                </button>
            </div>
        `;
    }
}

/**
 * Filter tiers
 */
function aiCentral_filterTiers() {
    const statusFilter = document.getElementById('aicentral-filter-status').value;
    const searchTerm = document.getElementById('aicentral-filter-search').value.toLowerCase();

    aiCentral_tiersData.filteredTiers = aiCentral_tiersData.tiers.filter(tier => {
        if (statusFilter !== '' && tier.is_active !== (statusFilter === '1')) return false;
        if (searchTerm && !tier.tier_name.toLowerCase().includes(searchTerm) &&
            !tier.tier_code.toLowerCase().includes(searchTerm)) return false;
        return true;
    });

    aiCentral_savePreferences();
    aiCentral_renderTiers();
}

/**
 * Render tiers
 */
function aiCentral_renderTiers() {
    const container = document.getElementById('aicentral-tiers-list');

    if (aiCentral_tiersData.filteredTiers.length === 0) {
        container.innerHTML = `
            <div class="col-12 text-center py-5">
                <i class="bi bi-inbox display-1 text-muted"></i>
                <p class="mt-3 text-muted fs-5">No tiers found</p>
            </div>
        `;
        return;
    }

    if (aiCentral_tiersData.currentView === 'table') {
        container.className = '';
        container.innerHTML = aiCentral_renderTiersTable();
    } else {
        container.className = 'row';
        container.innerHTML = aiCentral_tiersData.filteredTiers.map(tier => aiCentral_renderTierCard(tier)).join('');
    }
}

/**
 * Render tiers as table
 */
function aiCentral_renderTiersTable() {
    const sortIcon = (column) => {
        if (aiCentral_tiersData.sortColumn !== column) return '<i class="bi bi-arrow-down-up text-muted"></i>';
        return aiCentral_tiersData.sortDirection === 'asc' ? '<i class="bi bi-arrow-up text-primary"></i>' : '<i class="bi bi-arrow-down text-primary"></i>';
    };

    return `
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="cursor: pointer;" onclick="aiCentral_sortTable('tier_name')">
                            Tier ${sortIcon('tier_name')}
                        </th>
                        <th style="cursor: pointer;" onclick="aiCentral_sortTable('tier_code')">
                            Code ${sortIcon('tier_code')}
                        </th>
                        <th style="cursor: pointer;" onclick="aiCentral_sortTable('daily_request_limit')" class="text-end">
                            Daily Requests ${sortIcon('daily_request_limit')}
                        </th>
                        <th style="cursor: pointer;" onclick="aiCentral_sortTable('monthly_request_limit')" class="text-end">
                            Monthly Requests ${sortIcon('monthly_request_limit')}
                        </th>
                        <th style="cursor: pointer;" onclick="aiCentral_sortTable('daily_token_limit')" class="text-end">
                            Daily Tokens ${sortIcon('daily_token_limit')}
                        </th>
                        <th style="cursor: pointer;" onclick="aiCentral_sortTable('monthly_token_limit')" class="text-end">
                            Monthly Tokens ${sortIcon('monthly_token_limit')}
                        </th>
                        <th style="cursor: pointer;" onclick="aiCentral_sortTable('monthly_spend_limit_usd')" class="text-end">
                            Spend Limit ${sortIcon('monthly_spend_limit_usd')}
                        </th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${aiCentral_tiersData.filteredTiers.map(tier => aiCentral_renderTierRow(tier)).join('')}
                </tbody>
            </table>
        </div>
    `;
}

/**
 * Render single tier table row
 */
function aiCentral_renderTierRow(tier) {
    return `
        <tr class="${!tier.is_active ? 'opacity-50' : ''}">
            <td><strong>${aiCentral_escapeHtml(tier.tier_name)}</strong></td>
            <td><code>${aiCentral_escapeHtml(tier.tier_code).toUpperCase()}</code></td>
            <td class="text-end">${tier.daily_request_limit ? tier.daily_request_limit.toLocaleString() : '<span class="text-muted">Unlimited</span>'}</td>
            <td class="text-end">${tier.monthly_request_limit ? tier.monthly_request_limit.toLocaleString() : '<span class="text-muted">Unlimited</span>'}</td>
            <td class="text-end">${tier.daily_token_limit ? tier.daily_token_limit.toLocaleString() : '<span class="text-muted">Unlimited</span>'}</td>
            <td class="text-end">${tier.monthly_token_limit ? tier.monthly_token_limit.toLocaleString() : '<span class="text-muted">Unlimited</span>'}</td>
            <td class="text-end text-success fw-bold">${tier.monthly_spend_limit_usd ? `$${tier.monthly_spend_limit_usd.toFixed(2)}` : '<span class="text-muted">Unlimited</span>'}</td>
            <td>
                ${tier.is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>'}
            </td>
            <td class="text-end">
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" onclick="aiCentral_editTier(${tier.tier_id})" title="Edit">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn ${tier.is_active ? 'btn-warning' : 'btn-success'}"
                            onclick="aiCentral_toggleTierStatus(${tier.tier_id})" title="Toggle Status">
                        ${tier.is_active ? '<i class="bi bi-pause-circle"></i>' : '<i class="bi bi-play-circle"></i>'}
                    </button>
                </div>
            </td>
        </tr>
    `;
}

/**
 * Render single tier card
 */
function aiCentral_renderTierCard(tier) {
    const statusBadge = tier.is_active
        ? '<span class="badge bg-success">Active</span>'
        : '<span class="badge bg-secondary">Inactive</span>';

    return `
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card h-100 border-0 shadow-sm ${tier.is_active ? '' : 'opacity-75'}">
                <div class="card-header bg-primary text-white border-0">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5 class="card-title mb-1">${aiCentral_escapeHtml(tier.tier_name)}</h5>
                            <small class="font-monospace text-white-50">${aiCentral_escapeHtml(tier.tier_code).toUpperCase()}</small>
                        </div>
                        ${statusBadge}
                    </div>
                </div>

                <div class="card-body">
                    ${tier.tier_description ? `<p class="text-muted small mb-3">${aiCentral_escapeHtml(tier.tier_description)}</p>` : ''}

                    <!-- Request Limits -->
                    <div class="mb-3">
                        <h6 class="text-primary fw-bold small mb-2">
                            <i class="bi bi-speedometer2"></i> REQUEST LIMITS
                        </h6>
                        <div class="bg-light rounded p-2">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="small text-muted">Daily:</span>
                                <span class="small fw-semibold">${tier.daily_request_limit ? tier.daily_request_limit.toLocaleString() : 'Unlimited'}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="small text-muted">Monthly:</span>
                                <span class="small fw-semibold">${tier.monthly_request_limit ? tier.monthly_request_limit.toLocaleString() : 'Unlimited'}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Token Limits -->
                    <div class="mb-3">
                        <h6 class="text-primary fw-bold small mb-2">
                            <i class="bi bi-cpu"></i> TOKEN LIMITS
                        </h6>
                        <div class="bg-light rounded p-2">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="small text-muted">Daily:</span>
                                <span class="small fw-semibold">${tier.daily_token_limit ? tier.daily_token_limit.toLocaleString() : 'Unlimited'}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="small text-muted">Monthly:</span>
                                <span class="small fw-semibold">${tier.monthly_token_limit ? tier.monthly_token_limit.toLocaleString() : 'Unlimited'}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Spend Limit -->
                    <div class="mb-3">
                        <h6 class="text-primary fw-bold small mb-2">
                            <i class="bi bi-cash-coin"></i> SPEND LIMIT
                        </h6>
                        <div class="bg-light rounded p-2 text-center">
                            <div class="text-success fw-bold fs-5">
                                ${tier.monthly_spend_limit_usd ? `$${tier.monthly_spend_limit_usd.toFixed(2)}/month` : 'Unlimited'}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-light border-0">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary btn-sm" onclick="aiCentral_editTier(${tier.tier_id})">
                            <i class="bi bi-pencil"></i> Edit
                        </button>
                        <button class="btn ${tier.is_active ? 'btn-warning' : 'btn-success'} btn-sm"
                                onclick="aiCentral_toggleTierStatus(${tier.tier_id})">
                            <i class="bi ${tier.is_active ? 'bi-pause-circle' : 'bi-play-circle'}"></i>
                            ${tier.is_active ? 'Deactivate' : 'Activate'}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
}

/**
 * Toggle tier capability max uses input
 */
function aiCentral_toggleTierCapabilityMaxUses(capability, enabled) {
    const input = document.getElementById(`aicentral-tier-cap-${capability}-max`);
    input.disabled = !enabled;
    if (!enabled) {
        input.value = '';
    }
}

/**
 * Load tier capabilities
 */
async function aiCentral_loadTierCapabilities(tierId) {
    try {
        const response = await fetch('/ai/admin/aicentral_tiersCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'getTierCapabilities',
                tier_id: tierId
            })
        });

        const data = await response.json();
        if (data.success) {
            // Reset all capabilities first
            document.getElementById('aicentral-tier-cap-websearch').checked = false;
            document.getElementById('aicentral-tier-cap-webfetch').checked = false;
            document.getElementById('aicentral-tier-cap-vision').checked = false;
            document.getElementById('aicentral-tier-cap-websearch-max').value = '';
            document.getElementById('aicentral-tier-cap-webfetch-max').value = '';
            document.getElementById('aicentral-tier-cap-vision-max').value = '';
            document.getElementById('aicentral-tier-cap-websearch-max').disabled = true;
            document.getElementById('aicentral-tier-cap-webfetch-max').disabled = true;
            document.getElementById('aicentral-tier-cap-vision-max').disabled = true;

            // Set capabilities from database
            if (data.capabilities) {
                data.capabilities.forEach(cap => {
                    const checkbox = document.getElementById(`aicentral-tier-cap-${cap.capability_code}`);
                    const maxInput = document.getElementById(`aicentral-tier-cap-${cap.capability_code}-max`);
                    if (checkbox) {
                        checkbox.checked = cap.is_enabled;
                        if (maxInput) {
                            maxInput.disabled = !cap.is_enabled;
                            if (cap.max_uses) {
                                maxInput.value = cap.max_uses;
                            }
                        }
                    }
                });
            }
        }
    } catch (error) {
        console.error('Error loading tier capabilities:', error);
    }
}

/**
 * Show add tier dialog
 */
function aiCentral_showAddTierDialog() {
    document.getElementById('aicentral-modal-title').innerHTML = '<i class="bi bi-layers"></i> Add Tier';
    document.getElementById('aicentral-tier-id').value = '';
    document.getElementById('aicentral-tier-code').value = '';
    document.getElementById('aicentral-tier-name').value = '';
    document.getElementById('aicentral-tier-description').value = '';
    document.getElementById('aicentral-tier-daily-requests').value = '';
    document.getElementById('aicentral-tier-monthly-requests').value = '';
    document.getElementById('aicentral-tier-daily-tokens').value = '';
    document.getElementById('aicentral-tier-monthly-tokens').value = '';
    document.getElementById('aicentral-tier-spend-limit').value = '';
    document.getElementById('aicentral-tier-sort').value = '100';
    document.getElementById('aicentral-tier-active').checked = true;

    // Reset capabilities
    document.getElementById('aicentral-tier-cap-websearch').checked = false;
    document.getElementById('aicentral-tier-cap-webfetch').checked = false;
    document.getElementById('aicentral-tier-cap-vision').checked = false;
    document.getElementById('aicentral-tier-cap-websearch-max').value = '';
    document.getElementById('aicentral-tier-cap-webfetch-max').value = '';
    document.getElementById('aicentral-tier-cap-vision-max').value = '';
    document.getElementById('aicentral-tier-cap-websearch-max').disabled = true;
    document.getElementById('aicentral-tier-cap-webfetch-max').disabled = true;
    document.getElementById('aicentral-tier-cap-vision-max').disabled = true;

    // Hide warning
    document.getElementById('aicentral-tier-cap-warning').classList.add('d-none');

    aiCentral_tierModal.show();
}

/**
 * Edit tier
 */
async function aiCentral_editTier(tierId) {
    // Convert to number to ensure type matching
    const tierIdNum = parseInt(tierId);
    const tier = aiCentral_tiersData.tiers.find(t => parseInt(t.tier_id) === tierIdNum);

    if (!tier) {
        console.error('Tier not found:', tierIdNum, 'Available tiers:', aiCentral_tiersData.tiers);
        showAlert('Tier not found. Please refresh the page and try again.', 'Error', 'error');
        return;
    }

    document.getElementById('aicentral-modal-title').innerHTML = '<i class="bi bi-pencil"></i> Edit Tier';
    document.getElementById('aicentral-tier-id').value = tier.tier_id;
    document.getElementById('aicentral-tier-code').value = tier.tier_code;
    document.getElementById('aicentral-tier-name').value = tier.tier_name;
    document.getElementById('aicentral-tier-description').value = tier.tier_description || '';
    document.getElementById('aicentral-tier-daily-requests').value = tier.daily_request_limit || '';
    document.getElementById('aicentral-tier-monthly-requests').value = tier.monthly_request_limit || '';
    document.getElementById('aicentral-tier-daily-tokens').value = tier.daily_token_limit || '';
    document.getElementById('aicentral-tier-monthly-tokens').value = tier.monthly_token_limit || '';
    document.getElementById('aicentral-tier-spend-limit').value = tier.monthly_spend_limit_usd || '';
    document.getElementById('aicentral-tier-sort').value = tier.sort_order;
    document.getElementById('aicentral-tier-active').checked = tier.is_active;

    // Hide warning
    document.getElementById('aicentral-tier-cap-warning').classList.add('d-none');

    // Load capabilities for this tier
    await aiCentral_loadTierCapabilities(tierId);

    aiCentral_tierModal.show();
}

/**
 * Close tier dialog
 */
function aiCentral_closeTierDialog() {
    aiCentral_tierModal.hide();
}

/**
 * Save tier
 */
async function aiCentral_saveTier() {
    // Build capabilities array
    const capabilities = [];

    // Web Search
    if (document.getElementById('aicentral-tier-cap-websearch').checked) {
        const maxUses = document.getElementById('aicentral-tier-cap-websearch-max').value;
        capabilities.push({
            capability_code: 'web_search',
            is_enabled: 1,
            max_uses: maxUses ? parseInt(maxUses) : null
        });
    } else {
        capabilities.push({
            capability_code: 'web_search',
            is_enabled: 0,
            max_uses: null
        });
    }

    // Web Fetch
    if (document.getElementById('aicentral-tier-cap-webfetch').checked) {
        const maxUses = document.getElementById('aicentral-tier-cap-webfetch-max').value;
        capabilities.push({
            capability_code: 'web_fetch',
            is_enabled: 1,
            max_uses: maxUses ? parseInt(maxUses) : null
        });
    } else {
        capabilities.push({
            capability_code: 'web_fetch',
            is_enabled: 0,
            max_uses: null
        });
    }

    // Vision
    if (document.getElementById('aicentral-tier-cap-vision').checked) {
        const maxUses = document.getElementById('aicentral-tier-cap-vision-max').value;
        capabilities.push({
            capability_code: 'vision',
            is_enabled: 1,
            max_uses: maxUses ? parseInt(maxUses) : null
        });
    } else {
        capabilities.push({
            capability_code: 'vision',
            is_enabled: 0,
            max_uses: null
        });
    }

    // Validate max_uses values
    for (const cap of capabilities) {
        if (cap.is_enabled && cap.max_uses !== null && (cap.max_uses < 1 || cap.max_uses > 1000)) {
            showAlert('Max Uses must be between 1 and 1000 or left blank for unlimited', 'Validation Error', 'error');
            return;
        }
    }

    const formData = new URLSearchParams({
        action: 'saveTier',
        tier_id: document.getElementById('aicentral-tier-id').value,
        tier_code: document.getElementById('aicentral-tier-code').value,
        tier_name: document.getElementById('aicentral-tier-name').value,
        tier_description: document.getElementById('aicentral-tier-description').value,
        daily_request_limit: document.getElementById('aicentral-tier-daily-requests').value,
        monthly_request_limit: document.getElementById('aicentral-tier-monthly-requests').value,
        daily_token_limit: document.getElementById('aicentral-tier-daily-tokens').value,
        monthly_token_limit: document.getElementById('aicentral-tier-monthly-tokens').value,
        monthly_spend_limit_usd: document.getElementById('aicentral-tier-spend-limit').value,
        sort_order: document.getElementById('aicentral-tier-sort').value,
        capabilities: JSON.stringify(capabilities)
    });

    if (document.getElementById('aicentral-tier-active').checked) formData.append('is_active', '1');

    try {
        const response = await fetch('/ai/admin/aicentral_tiersCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData
        });

        const data = await response.json();
        if (data.success) {
            showNotification('Tier saved successfully', 'success');
            aiCentral_closeTierDialog();
            aiCentral_loadTiers();
        } else {
            showAlert(data.error || 'Error', 'Error', 'error');
        }
    } catch (error) {
        console.error('Error saving tier:', error);
        showAlert('Error saving tier', 'Error', 'error');
    }
}

/**
 * Toggle tier status
 */
async function aiCentral_toggleTierStatus(tierId) {
    if (!confirm('Toggle this tier\'s active status?')) return;

    try {
        const response = await fetch('/ai/admin/aicentral_tiersCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'toggleTierStatus',
                tier_id: tierId
            })
        });

        const data = await response.json();
        if (data.success) {
            aiCentral_loadTiers();
        } else {
            showAlert(data.error || 'Error', 'Error', 'error');
        }
    } catch (error) {
        console.error('Error toggling tier status:', error);
        showAlert('Error toggling tier status', 'Error', 'error');
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
    const savedView = localStorage.getItem('aicentral_tiers_view');
    if (savedView) {
        aiCentral_tiersData.currentView = savedView;
    }

    // Load filter values
    const savedFilters = localStorage.getItem('aicentral_tiers_filters');
    if (savedFilters) {
        try {
            const filters = JSON.parse(savedFilters);
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
    localStorage.setItem('aicentral_tiers_view', aiCentral_tiersData.currentView);

    const filters = {
        status: document.getElementById('aicentral-filter-status').value,
        search: document.getElementById('aicentral-filter-search').value
    };
    localStorage.setItem('aicentral_tiers_filters', JSON.stringify(filters));
}

/**
 * Toggle between card and table view
 */
function aiCentral_toggleView() {
    aiCentral_tiersData.currentView = aiCentral_tiersData.currentView === 'cards' ? 'table' : 'cards';
    aiCentral_savePreferences();
    aiCentral_updateViewToggleIcon();
    aiCentral_renderTiers();
}

/**
 * Update view toggle button icon
 */
function aiCentral_updateViewToggleIcon() {
    const icon = document.querySelector('#aicentral-view-toggle i');
    if (aiCentral_tiersData.currentView === 'cards') {
        icon.className = 'bi bi-list-ul';
    } else {
        icon.className = 'bi bi-grid-3x3-gap';
    }
}

/**
 * Clear all filters
 */
function aiCentral_clearFilters() {
    document.getElementById('aicentral-filter-status').value = '';
    document.getElementById('aicentral-filter-search').value = '';
    aiCentral_savePreferences();
    aiCentral_filterTiers();
}

/**
 * Sort table by column
 */
function aiCentral_sortTable(column) {
    if (aiCentral_tiersData.sortColumn === column) {
        aiCentral_tiersData.sortDirection = aiCentral_tiersData.sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        aiCentral_tiersData.sortColumn = column;
        aiCentral_tiersData.sortDirection = 'asc';
    }

    aiCentral_tiersData.filteredTiers.sort((a, b) => {
        let aVal = a[column];
        let bVal = b[column];

        // Handle numeric columns
        if (column.includes('limit') || column.includes('tokens') || column === 'sort_order') {
            aVal = parseFloat(aVal) || 0;
            bVal = parseFloat(bVal) || 0;
        } else {
            aVal = String(aVal || '').toLowerCase();
            bVal = String(bVal || '').toLowerCase();
        }

        if (aVal < bVal) return aiCentral_tiersData.sortDirection === 'asc' ? -1 : 1;
        if (aVal > bVal) return aiCentral_tiersData.sortDirection === 'asc' ? 1 : -1;
        return 0;
    });

    aiCentral_renderTiers();
}
