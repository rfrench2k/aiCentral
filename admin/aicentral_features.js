/**
 * AI Central Admin Features - Frontend JavaScript
 */

let aiCentral_featuresData = {
    features: [],
    programs: [],
    providers: [],
    models: [],
    tiers: [],
    featureTypes: [],
    filteredFeatures: [],
    currentView: 'cards', // 'cards' or 'table'
    sortColumn: null,
    sortDirection: 'asc',
    currentFeature: null
};

let aiCentral_featureModal = null;

/**
 * Ensure all reference data is loaded before editing
 */
async function aiCentral_ensureReferenceDataLoaded() {
    const maxWait = 5000; // 5 seconds timeout
    const checkInterval = 100; // Check every 100ms
    const startTime = Date.now();

    while (Date.now() - startTime < maxWait) {
        if (aiCentral_featuresData.programs &&
            aiCentral_featuresData.programs.length > 0 &&
            aiCentral_featuresData.featureTypes &&
            aiCentral_featuresData.featureTypes.length > 0 &&
            aiCentral_featuresData.providers &&
            aiCentral_featuresData.providers.length > 0 &&
            aiCentral_featuresData.models &&
            aiCentral_featuresData.models.length > 0) {
            return true;
        }
        await new Promise(resolve => setTimeout(resolve, checkInterval));
    }
    return false;
}

/**
 * Initialize features page
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap modal
    const modalElement = document.getElementById('aicentral-feature-modal');
    aiCentral_featureModal = new bootstrap.Modal(modalElement);

    // Initialize Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Load saved preferences
    aiCentral_loadPreferences();

    aiCentral_loadPrograms();
    aiCentral_loadProviders();
    aiCentral_loadModels();
    aiCentral_loadTiers();
    aiCentral_loadFeatureTypes();
    aiCentral_loadCapabilities();
    aiCentral_loadFeatures();

    // Update view toggle icon
    aiCentral_updateViewToggleIcon();
});

/**
 * Load programs for dropdown
 */
async function aiCentral_loadPrograms() {
    try {
        const response = await fetch('/ai/admin/aicentral_featuresCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getPrograms' })
        });

        const data = await response.json();
        if (data.success) {
            aiCentral_featuresData.programs = data.programs;

            // Populate filter dropdown
            const filterSelect = document.getElementById('aicentral-filter-program');
            filterSelect.innerHTML = '<option value="">All Programs</option>' +
                data.programs.map(p => `<option value="${p}">${p}</option>`).join('');
        }
    } catch (error) {
        console.error('Error loading programs:', error);
    }
}

/**
 * Load providers for dropdown
 */
async function aiCentral_loadProviders() {
    try {
        const response = await fetch('/ai/admin/aicentral_featuresCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getProviders' })
        });

        const data = await response.json();
        console.log('Providers loaded:', data);
        if (data.success) {
            aiCentral_featuresData.providers = data.providers;
        } else {
            console.error('Failed to load providers:', data.error);
        }
    } catch (error) {
        console.error('Error loading providers:', error);
    }
}

/**
 * Load feature types from lookups
 */
async function aiCentral_loadFeatureTypes() {
    try {
        const response = await fetch('/ai/admin/aicentral_lookupsCode.php?action=getLookups&category=feature_type');
        const data = await response.json();

        if (data.success) {
            // Store feature types in memory
            aiCentral_featuresData.featureTypes = data.lookups;

            // Populate filter dropdown
            const filterSelect = document.getElementById('aicentral-filter-type');
            filterSelect.innerHTML = '<option value="">All Types</option>' +
                data.lookups.map(lookup =>
                    `<option value="${lookup.lookup_value}">${lookup.lookup_desc || lookup.lookup_value.replace('_', ' ')}</option>`
                ).join('');
        } else {
            console.error('Failed to load feature types:', data.error);
        }
    } catch (error) {
        console.error('Error loading feature types:', error);
    }
}

/**
 * Load models for dropdowns
 */
async function aiCentral_loadModels() {
    try {
        const response = await fetch('/ai/admin/aicentral_featuresCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getModels' })
        });

        const data = await response.json();
        console.log('Models loaded:', data);
        if (data.success) {
            aiCentral_featuresData.models = data.models;
            console.log('Total models in memory:', data.models.length);
        } else {
            console.error('Failed to load models:', data.error);
        }
    } catch (error) {
        console.error('Error loading models:', error);
    }
}

/**
 * Load capabilities from database
 */
async function aiCentral_loadCapabilities() {
    try {
        const response = await fetch('/ai/admin/aicentral_featuresCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getCapabilities' })
        });

        const data = await response.json();
        console.log('Capabilities loaded:', data);
        if (data.success) {
            aiCentral_featuresData.capabilities = data.capabilities;
        } else {
            console.error('Failed to load capabilities:', data.error);
        }
    } catch (error) {
        console.error('Error loading capabilities:', error);
    }
}

/**
 * Load tiers for tab generation
 */
async function aiCentral_loadTiers() {
    try {
        const response = await fetch('/ai/admin/aicentral_featuresCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getAllTiers' })
        });

        const data = await response.json();
        if (data.success) {
            aiCentral_featuresData.tiers = data.tiers;
            console.log('Loaded tiers:', data.tiers);
        }
    } catch (error) {
        console.error('Error loading tiers:', error);
    }
}


/**
 * Load features
 */
async function aiCentral_loadFeatures() {
    try {
        const response = await fetch('/ai/admin/aicentral_featuresCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getFeatures' })
        });

        const data = await response.json();
        if (data.success) {
            aiCentral_featuresData.features = data.features;
            aiCentral_filterFeatures();
        }
    } catch (error) {
        console.error('Error loading features:', error);
    }
}

/**
 * Filter features
 */
function aiCentral_filterFeatures() {
    const programFilter = document.getElementById('aicentral-filter-program').value;
    const typeFilter = document.getElementById('aicentral-filter-type').value;
    const statusFilter = document.getElementById('aicentral-filter-status').value;
    const searchTerm = document.getElementById('aicentral-filter-search').value.toLowerCase();

    aiCentral_featuresData.filteredFeatures = aiCentral_featuresData.features.filter(feature => {
        if (programFilter && feature.program_id !== programFilter) return false;
        if (typeFilter && feature.feature_type !== typeFilter) return false;
        if (statusFilter !== '' && feature.is_active !== (statusFilter === '1')) return false;
        if (searchTerm && !feature.feature_name.toLowerCase().includes(searchTerm) &&
            !feature.feature_code.toLowerCase().includes(searchTerm)) return false;
        return true;
    });

    aiCentral_savePreferences();
    aiCentral_renderFeatures();
}

/**
 * Render features
 */
function aiCentral_renderFeatures() {
    const container = document.getElementById('aicentral-features-list');

    if (aiCentral_featuresData.filteredFeatures.length === 0) {
        container.innerHTML = `
            <div class="text-center py-5">
                <i class="bi bi-inbox display-1 text-muted"></i>
                <p class="mt-3 text-muted">No features found</p>
            </div>
        `;
        return;
    }

    if (aiCentral_featuresData.currentView === 'table') {
        container.innerHTML = aiCentral_renderFeaturesTable();
    } else {
        container.innerHTML = aiCentral_renderFeaturesCards();
    }
}

/**
 * Render features as cards
 */
function aiCentral_renderFeaturesCards() {
    // Group by program
    const grouped = {};
    aiCentral_featuresData.filteredFeatures.forEach(feature => {
        if (!grouped[feature.program_id]) {
            grouped[feature.program_id] = [];
        }
        grouped[feature.program_id].push(feature);
    });

    let html = '';
    for (const [programId, features] of Object.entries(grouped)) {
        html += `
            <div class="mb-4">
                <h3 class="h5 mb-3 pb-2 border-bottom">
                    <i class="bi bi-folder text-primary"></i> ${aiCentral_escapeHtml(programId)}
                </h3>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                    ${features.map(feature => aiCentral_renderFeatureCard(feature)).join('')}
                </div>
            </div>
        `;
    }

    return html;
}

/**
 * Render features as table
 */
function aiCentral_renderFeaturesTable() {
    const sortIcon = (column) => {
        if (aiCentral_featuresData.sortColumn !== column) return '<i class="bi bi-arrow-down-up text-muted"></i>';
        return aiCentral_featuresData.sortDirection === 'asc' ? '<i class="bi bi-arrow-up text-primary"></i>' : '<i class="bi bi-arrow-down text-primary"></i>';
    };

    return `
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="cursor: pointer;" onclick="aiCentral_sortTable('program_id')">
                            Program ${sortIcon('program_id')}
                        </th>
                        <th style="cursor: pointer;" onclick="aiCentral_sortTable('feature_name')">
                            Feature Name ${sortIcon('feature_name')}
                        </th>
                        <th style="cursor: pointer;" onclick="aiCentral_sortTable('feature_code')">
                            Code ${sortIcon('feature_code')}
                        </th>
                        <th style="cursor: pointer;" onclick="aiCentral_sortTable('feature_type')">
                            Type ${sortIcon('feature_type')}
                        </th>
                        <th>Default Provider/Model</th>
                        <th>Capabilities</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${aiCentral_featuresData.filteredFeatures.map(feature => aiCentral_renderFeatureRow(feature)).join('')}
                </tbody>
            </table>
        </div>
    `;
}

/**
 * Render single feature table row
 */
function aiCentral_renderFeatureRow(feature) {
    return `
        <tr class="${!feature.is_active ? 'opacity-50' : ''}">
            <td><strong>${aiCentral_escapeHtml(feature.program_id)}</strong></td>
            <td>${aiCentral_escapeHtml(feature.feature_name)}</td>
            <td><code class="small">${aiCentral_escapeHtml(feature.feature_code)}</code></td>
            <td>${feature.feature_type ? `<span class="badge bg-primary">${aiCentral_escapeHtml(feature.feature_type.replace('_', ' '))}</span>` : '-'}</td>
            <td>
                ${feature.default_provider || feature.default_model ? `
                    <small>
                        ${feature.default_provider ? aiCentral_escapeHtml(feature.default_provider) : ''}
                        ${feature.default_model ? `<br><code>${aiCentral_escapeHtml(feature.default_model)}</code>` : ''}
                    </small>
                ` : '-'}
            </td>
            <td>
                ${aiCentral_renderCapabilitiesBadges(feature)}
            </td>
            <td>
                ${feature.is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>'}
            </td>
            <td class="text-end">
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary" onclick="aiCentral_editFeature(${feature.feature_id})" title="Edit">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn ${feature.is_active ? 'btn-outline-warning' : 'btn-outline-success'}"
                            onclick="aiCentral_toggleFeatureStatus(${feature.feature_id})" title="Toggle Status">
                        ${feature.is_active ? '<i class="bi bi-pause"></i>' : '<i class="bi bi-play"></i>'}
                    </button>
                </div>
            </td>
        </tr>
    `;
}

/**
 * Render single feature card
 */
function aiCentral_renderFeatureCard(feature) {
    const statusClass = feature.is_active ? 'success' : 'secondary';
    const statusIcon = feature.is_active ? 'check-circle-fill' : 'dash-circle';

    return `
        <div class="col">
            <div class="card h-100 shadow-sm ${feature.is_active ? '' : 'bg-light opacity-75'}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="flex-grow-1">
                            <h5 class="card-title mb-1">${aiCentral_escapeHtml(feature.feature_name)}</h5>
                            <p class="font-monospace text-muted small mb-0">${aiCentral_escapeHtml(feature.feature_code)}</p>
                        </div>
                        ${feature.feature_type ? `
                            <span class="badge bg-primary text-uppercase small">${aiCentral_escapeHtml(feature.feature_type.replace('_', ' '))}</span>
                        ` : ''}
                    </div>

                    ${feature.feature_description ? `
                        <p class="card-text small text-muted mb-3">${aiCentral_escapeHtml(feature.feature_description)}</p>
                    ` : ''}

                    ${feature.default_provider || feature.default_model ? `
                        <div class="bg-white border rounded p-2 mb-3 small">
                            ${feature.default_provider ? `
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Provider:</span>
                                    <span class="fw-medium">${aiCentral_escapeHtml(feature.default_provider)}</span>
                                </div>
                            ` : ''}
                            ${feature.default_model ? `
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Model:</span>
                                    <span class="font-monospace small">${aiCentral_escapeHtml(feature.default_model)}</span>
                                </div>
                            ` : ''}
                        </div>
                    ` : ''}

                    <div class="mb-3">
                        ${aiCentral_renderCapabilitiesBadges(feature)}
                    </div>
                </div>

                <div class="card-footer bg-transparent border-top">
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-secondary flex-grow-1" onclick="aiCentral_editFeature(${feature.feature_id})">
                            <i class="bi bi-pencil"></i> Edit
                        </button>
                        <button class="btn btn-sm ${feature.is_active ? 'btn-outline-warning' : 'btn-outline-success'} flex-grow-1"
                                onclick="aiCentral_toggleFeatureStatus(${feature.feature_id})">
                            <i class="bi bi-${feature.is_active ? 'pause' : 'play'}"></i> ${feature.is_active ? 'Deactivate' : 'Activate'}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
}

/**
 * Show add feature dialog (not yet implemented for new structure)
 */
function aiCentral_showAddFeatureDialog() {
    showAlert('Add feature functionality will be implemented in a future update. For now, please add features directly in the database.', 'Not Yet Implemented', 'info');
}

/**
 * Edit feature - Load full feature with tier configs
 */
async function aiCentral_editFeature(featureId) {
    try {
        // WAIT for reference data to load before proceeding
        const dataLoaded = await aiCentral_ensureReferenceDataLoaded();
        if (!dataLoaded) {
            showAlert('Reference data is still loading. Please wait a moment and try again.', 'Please Wait', 'warning');
            return;
        }

        // Load full feature details with tier configs
        const response = await fetch('/ai/admin/aicentral_featuresCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'getFeature',
                feature_id: featureId
            })
        });

        const data = await response.json();
        if (!data.success) {
            showAlert(data.error || 'Error loading feature', 'Error', 'error');
            return;
        }

        const feature = data.feature;
        aiCentral_featuresData.currentFeature = feature;

        console.log('Editing feature:', feature.feature_name);
        console.log('Programs in memory:', aiCentral_featuresData.programs);
        console.log('Feature types in memory:', aiCentral_featuresData.featureTypes);
        console.log('Providers in memory:', aiCentral_featuresData.providers);

        // Populate general settings FIRST (before building tier tabs)
        document.getElementById('aicentral-dialog-title').textContent = 'Edit Feature: ' + feature.feature_name;
        document.getElementById('aicentral-feature-id').value = feature.feature_id;
        document.getElementById('aicentral-feature-code').value = feature.feature_code;
        document.getElementById('aicentral-feature-name').value = feature.feature_name;
        document.getElementById('aicentral-feature-description').value = feature.feature_description || '';
        document.getElementById('aicentral-feature-max-input-tokens').value = feature.max_input_tokens || 128000;
        document.getElementById('aicentral-feature-sort').value = feature.sort_order;
        document.getElementById('aicentral-feature-active').checked = feature.is_active;

        // Build tier tabs (this might affect the DOM)
        aiCentral_buildTierTabs(feature.tier_configs);

        // NOW populate dropdowns AFTER tier tabs are built
        // Re-populate program dropdown
        const programSelect = document.getElementById('aicentral-feature-program');
        console.log('Program select element:', programSelect);
        if (programSelect && aiCentral_featuresData.programs && aiCentral_featuresData.programs.length > 0) {
            programSelect.innerHTML = '<option value="">Select program...</option>' +
                aiCentral_featuresData.programs.map(p => `<option value="${p}">${p}</option>`).join('');
            console.log('Program dropdown populated with', aiCentral_featuresData.programs.length, 'options');
            // Set value IMMEDIATELY after populating
            programSelect.value = feature.program_id;
            console.log('Program value set to:', feature.program_id);
        } else {
            console.error('Programs not loaded yet or empty. Programs:', aiCentral_featuresData.programs);
        }

        // Re-populate feature type dropdown
        const featureTypeSelect = document.getElementById('aicentral-feature-type');
        console.log('Feature type select element:', featureTypeSelect);
        if (featureTypeSelect && aiCentral_featuresData.featureTypes && aiCentral_featuresData.featureTypes.length > 0) {
            featureTypeSelect.innerHTML = '<option value="">Select type...</option>' +
                aiCentral_featuresData.featureTypes.map(lookup =>
                    `<option value="${lookup.lookup_value}">${lookup.lookup_desc || lookup.lookup_value.replace('_', ' ')}</option>`
                ).join('');
            console.log('Feature type dropdown populated with', aiCentral_featuresData.featureTypes.length, 'options');
            // Set value IMMEDIATELY after populating
            if (feature.feature_type) {
                featureTypeSelect.value = feature.feature_type;
                console.log('Feature type value set to:', feature.feature_type);
            }
        } else {
            console.error('Feature types not loaded yet or empty. Types:', aiCentral_featuresData.featureTypes);
        }

        // Populate "general" provider dropdown EXACTLY like tier tabs
        const generalProviderSelect = document.getElementById('provider-general');
        if (generalProviderSelect && aiCentral_featuresData.providers) {
            generalProviderSelect.innerHTML = '<option value="">Select provider...</option>' +
                aiCentral_featuresData.providers.map(p => `<option value="${aiCentral_escapeHtml(p.provider_code)}">${aiCentral_escapeHtml(p.provider_name)}</option>`).join('');
            console.log('General provider dropdown populated with', aiCentral_featuresData.providers.length, 'options');
        }

        // Set default model (fallback) - this will set provider and model dropdowns using tier pattern
        if (feature.default_model) {
            aiCentral_setTierModelFromCode('general', feature.default_model);
        }

        // Reset to General Settings tab
        const generalTab = document.getElementById('tab-general');
        const generalContent = document.getElementById('content-general');

        // Remove active class from all tabs and content
        document.querySelectorAll('#aicentral-feature-tabs .nav-link').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelectorAll('#aicentral-feature-tab-content .tab-pane').forEach(pane => {
            pane.classList.remove('show', 'active');
        });

        // Activate General Settings tab
        generalTab.classList.add('active');
        generalContent.classList.add('show', 'active');

        // Show modal
        aiCentral_featureModal.show();

    } catch (error) {
        console.error('Error loading feature:', error);
        showAlert('Error loading feature', 'Error', 'error');
    }
}

/**
 * Build tier tabs dynamically
 */
function aiCentral_buildTierTabs(tierConfigs) {
    const tabsList = document.getElementById('aicentral-feature-tabs');
    const tabContent = document.getElementById('aicentral-feature-tab-content');

    // Remove existing tier tabs (keep only General tab)
    const existingTierTabs = tabsList.querySelectorAll('li.tier-tab');
    existingTierTabs.forEach(tab => tab.remove());

    const existingTierContent = tabContent.querySelectorAll('.tier-tab-content');
    existingTierContent.forEach(content => content.remove());

    // Add tier tabs
    for (const [tierCode, tierConfig] of Object.entries(tierConfigs)) {
        // Create tab button
        const tabLi = document.createElement('li');
        tabLi.className = 'nav-item tier-tab';
        tabLi.setAttribute('role', 'presentation');

        const tabButton = document.createElement('button');
        tabButton.className = 'nav-link';
        tabButton.id = `tab-${tierCode}`;
        tabButton.setAttribute('data-bs-toggle', 'tab');
        tabButton.setAttribute('data-bs-target', `#content-${tierCode}`);
        tabButton.setAttribute('type', 'button');
        tabButton.setAttribute('role', 'tab');
        tabButton.textContent = tierConfig.tier_name;

        tabLi.appendChild(tabButton);
        tabsList.appendChild(tabLi);

        // Create tab content
        const tabPane = document.createElement('div');
        tabPane.className = 'tab-pane fade tier-tab-content';
        tabPane.id = `content-${tierCode}`;
        tabPane.setAttribute('role', 'tabpanel');
        tabPane.setAttribute('data-tier-id', tierConfig.tier_id);
        tabPane.setAttribute('data-tier-code', tierCode);

        tabPane.innerHTML = `
            <div class="row g-3 mt-2">
                <div class="col-12">
                    <h5>${tierConfig.tier_name} Configuration</h5>
                    <p class="text-muted">Configure model, token limits and capabilities for ${tierConfig.tier_name} users.</p>
                </div>

                <div class="col-md-3">
                    <label for="provider-${tierCode}" class="form-label">
                        AI Provider
                        <i class="bi bi-question-circle text-muted"
                           data-bs-toggle="tooltip"
                           data-bs-placement="top"
                           title="Select AI provider first, then choose specific model"></i>
                    </label>
                    <select id="provider-${tierCode}" class="form-select tier-provider"
                            onchange="aiCentral_updateModelsForProvider('${tierCode}')">
                        <option value="">Use feature default</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="model-code-${tierCode}" class="form-label">
                        AI Model
                        <i class="bi bi-question-circle text-muted"
                           data-bs-toggle="tooltip"
                           data-bs-placement="top"
                           title="Select specific model from chosen provider"></i>
                    </label>
                    <select id="model-code-${tierCode}" class="form-select tier-model-code"
                            onchange="aiCentral_updateTokenLimitHint('${tierCode}')">
                        <option value="">Select provider first</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="max-output-tokens-${tierCode}" class="form-label">
                        Max Output Tokens <span class="text-danger">*</span>
                        <i class="bi bi-question-circle text-muted"
                           data-bs-toggle="tooltip"
                           data-bs-placement="top"
                           title="Maximum response length in tokens - must not exceed model capability"></i>
                    </label>
                    <select id="max-output-tokens-${tierCode}"
                           class="form-select tier-max-output-tokens"
                           onchange="aiCentral_updateTokenLimitHint('${tierCode}')" required>
                        <option value="1024" ${tierConfig.max_output_tokens == 1024 ? 'selected' : ''}>1,024 tokens (short responses)</option>
                        <option value="2048" ${tierConfig.max_output_tokens == 2048 ? 'selected' : ''}>2,048 tokens (medium responses)</option>
                        <option value="4096" ${tierConfig.max_output_tokens == 4096 ? 'selected' : ''}>4,096 tokens (standard)</option>
                        <option value="8192" ${tierConfig.max_output_tokens == 8192 ? 'selected' : ''}>8,192 tokens (long responses)</option>
                        <option value="16384" ${tierConfig.max_output_tokens == 16384 ? 'selected' : ''}>16,384 tokens (very long - Claude 4.5, GPT-4o)</option>
                        <option value="32768" ${tierConfig.max_output_tokens == 32768 ? 'selected' : ''}>32,768 tokens (extra long - GPT-4.1)</option>
                        <option value="65536" ${tierConfig.max_output_tokens == 65536 ? 'selected' : ''}>65,536 tokens (massive)</option>
                        <option value="128000" ${tierConfig.max_output_tokens == 128000 ? 'selected' : ''}>128,000 tokens (extreme - GPT-5.1)</option>
                    </select>
                    <small class="form-text text-muted" id="model-max-tokens-hint-${tierCode}"></small>
                </div>

                <div class="col-12">
                    <hr class="my-3">
                    <h6 class="mb-3">AI Tool Capabilities</h6>
                    <p class="text-muted small">Enable advanced AI capabilities for this tier.</p>
                </div>

                <div class="col-12" id="capabilities-${tierCode}">
                    ${aiCentral_buildCapabilityItems(tierCode, tierConfig.capabilities)}
                </div>
            </div>
        `;

        tabContent.appendChild(tabPane);
    }

    // Reinitialize tooltips for new content
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Populate provider dropdowns in all tier tabs
    for (const [tierCode, tierConfig] of Object.entries(tierConfigs)) {
        const tierProviderSelect = document.getElementById(`provider-${tierCode}`);
        if (tierProviderSelect && aiCentral_featuresData.providers) {
            tierProviderSelect.innerHTML = '<option value="">Use feature default</option>' +
                aiCentral_featuresData.providers.map(p => `<option value="${aiCentral_escapeHtml(p.provider_code)}">${aiCentral_escapeHtml(p.provider_name)}</option>`).join('');
        }
    }

    // Populate model dropdowns for existing tier configs with model_code set
    for (const [tierCode, tierConfig] of Object.entries(tierConfigs)) {
        if (tierConfig.model_code) {
            aiCentral_setTierModelFromCode(tierCode, tierConfig.model_code);
        }
    }
}

/**
 * Update default model dropdown when default provider changes
 */
function aiCentral_updateDefaultModels() {
    const providerSelect = document.getElementById('aicentral-default-provider');
    const modelSelect = document.getElementById('aicentral-default-model');
    const providerCode = providerSelect.value;

    console.log('updateDefaultModels called with provider:', providerCode);
    console.log('Total models in memory:', aiCentral_featuresData.models.length);

    // Clear model dropdown
    modelSelect.innerHTML = '<option value="">Select model...</option>';

    if (!providerCode) {
        modelSelect.innerHTML = '<option value="">Select provider first</option>';
        modelSelect.disabled = false;
        return;
    }

    // Filter models by provider_code (from database relationship)
    const filteredModels = aiCentral_featuresData.models.filter(m => m.provider_code === providerCode);

    console.log('Filtered models for provider', providerCode, ':', filteredModels.length);

    if (filteredModels.length === 0) {
        modelSelect.innerHTML = '<option value="">No models available for this provider</option>';
        modelSelect.disabled = true;
        return;
    }

    // Populate with filtered models
    filteredModels.forEach(model => {
        const option = document.createElement('option');
        option.value = model.model_code;
        option.textContent = model.model_name || model.model_code;
        modelSelect.appendChild(option);
    });

    modelSelect.disabled = false;
}

/**
 * Update model dropdown when provider changes
 */
function aiCentral_updateModelsForProvider(tierCode) {
    const providerSelect = document.getElementById(`provider-${tierCode}`);
    const modelSelect = document.getElementById(`model-code-${tierCode}`);
    const providerCode = providerSelect.value;

    console.log('updateModelsForProvider called - tierCode:', tierCode, 'provider:', providerCode);
    console.log('Total models in memory:', aiCentral_featuresData.models.length);

    // Clear model dropdown
    modelSelect.innerHTML = '<option value="">Select model...</option>';

    if (!providerCode) {
        modelSelect.innerHTML = '<option value="">Use feature default</option>';
        modelSelect.disabled = false;
        return;
    }

    // Filter models by provider_code (from database relationship)
    const filteredModels = aiCentral_featuresData.models.filter(m => m.provider_code === providerCode);

    console.log('Filtered models for tier', tierCode, 'provider', providerCode, ':', filteredModels.length);

    if (filteredModels.length === 0) {
        modelSelect.innerHTML = '<option value="">No models available for this provider</option>';
        modelSelect.disabled = true;
        return;
    }

    // Populate with filtered models
    filteredModels.forEach(model => {
        const option = document.createElement('option');
        option.value = model.model_code;
        option.textContent = model.model_name || model.model_code;
        modelSelect.appendChild(option);
    });

    modelSelect.disabled = false;

    // Update token limit hint when model changes
    aiCentral_updateTokenLimitHint(tierCode);
}

/**
 * Update the hint text showing if selected output tokens exceed model capability
 */
function aiCentral_updateTokenLimitHint(tierCode) {
    const modelSelect = document.getElementById(`model-code-${tierCode}`);
    const outputTokensSelect = document.getElementById(`max-output-tokens-${tierCode}`);
    const hintElement = document.getElementById(`model-max-tokens-hint-${tierCode}`);

    if (!modelSelect || !outputTokensSelect || !hintElement) return;

    const selectedModelCode = modelSelect.value;
    const selectedOutputTokens = parseInt(outputTokensSelect.value);

    if (!selectedModelCode) {
        hintElement.textContent = '';
        hintElement.className = 'form-text text-muted';
        return;
    }

    // Find the selected model in our data
    const model = aiCentral_featuresData.models.find(m => m.model_code === selectedModelCode);

    if (!model || !model.max_tokens) {
        hintElement.textContent = '';
        hintElement.className = 'form-text text-muted';
        return;
    }

    const modelMaxTokens = parseInt(model.max_tokens);

    if (selectedOutputTokens > modelMaxTokens) {
        hintElement.textContent = `Warning: ${model.model_display_name} supports max ${modelMaxTokens.toLocaleString()} output tokens`;
        hintElement.className = 'form-text text-danger';
    } else {
        hintElement.textContent = `${model.model_display_name} supports up to ${modelMaxTokens.toLocaleString()} output tokens`;
        hintElement.className = 'form-text text-success';
    }
}

/**
 * Set default provider and model from combined model_code
 */
function aiCentral_setDefaultModelFromCode(modelCode) {
    if (!modelCode) return;

    const providerSelect = document.getElementById('aicentral-default-provider');
    const modelSelect = document.getElementById('aicentral-default-model');

    if (!providerSelect || !modelSelect) return;

    // Find the model in our loaded models to get its provider_code
    const model = aiCentral_featuresData.models.find(m => m.model_code === modelCode);

    if (!model) {
        console.warn('Model not found:', modelCode);
        return;
    }

    // Set provider using the provider_code from database
    providerSelect.value = model.provider_code;

    // Trigger model dropdown update
    aiCentral_updateDefaultModels();

    // Set model after a brief delay to ensure dropdown is populated
    setTimeout(() => {
        modelSelect.value = modelCode;
    }, 50);
}

/**
 * Set tier provider and model from combined model_code
 */
function aiCentral_setTierModelFromCode(tierCode, modelCode) {
    if (!modelCode) return;

    const providerSelect = document.getElementById(`provider-${tierCode}`);
    const modelSelect = document.getElementById(`model-code-${tierCode}`);

    if (!providerSelect || !modelSelect) return;

    // Find the model in our loaded models to get its provider_code
    const model = aiCentral_featuresData.models.find(m => m.model_code === modelCode);

    if (!model) {
        console.warn('Model not found for tier', tierCode, ':', modelCode);
        return;
    }

    // Set provider using the provider_code from database
    providerSelect.value = model.provider_code;

    // Trigger model dropdown update
    aiCentral_updateModelsForProvider(tierCode);

    // Set model after a brief delay to ensure dropdown is populated
    setTimeout(() => {
        modelSelect.value = modelCode;
    }, 50);
}

/**
 * Build capability items for a tier
 */
function aiCentral_buildCapabilityItems(tierCode, capabilities) {
    // Get all unique capability codes from the current feature's tier configs
    const allCapabilityCodes = new Set();

    if (aiCentral_featuresData.currentFeature && aiCentral_featuresData.currentFeature.tier_configs) {
        Object.values(aiCentral_featuresData.currentFeature.tier_configs).forEach(tierConfig => {
            if (tierConfig.capabilities) {
                tierConfig.capabilities.forEach(cap => {
                    allCapabilityCodes.add(cap.capability_code);
                });
            }
        });
    }

    // If no capabilities found in feature, use all available capabilities from database
    if (allCapabilityCodes.size === 0 && aiCentral_featuresData.capabilities) {
        aiCentral_featuresData.capabilities.forEach(cap => {
            allCapabilityCodes.add(cap.capability_code);
        });
    }

    let html = '';

    allCapabilityCodes.forEach(capCode => {
        // Get capability info from loaded capabilities (from database)
        let info = { name: capCode, description: capCode };

        if (aiCentral_featuresData.capabilities) {
            const dbCap = aiCentral_featuresData.capabilities.find(c => c.capability_code === capCode);
            if (dbCap) {
                info = {
                    name: dbCap.name,
                    description: dbCap.description
                };
            }
        }

        const tierCap = capabilities.find(c => c.capability_code === capCode);
        const isEnabled = tierCap ? tierCap.is_enabled : false;
        const maxUses = tierCap && tierCap.max_uses ? tierCap.max_uses : '';

        html += `
            <div class="capability-item ${isEnabled ? 'enabled' : ''}" data-capability="${capCode}">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <div class="form-check">
                            <input type="checkbox"
                                   class="form-check-input capability-checkbox"
                                   id="cap-${tierCode}-${capCode}"
                                   data-tier="${tierCode}"
                                   data-capability="${capCode}"
                                   ${isEnabled ? 'checked' : ''}
                                   onchange="aiCentral_toggleCapability('${tierCode}', '${capCode}', this.checked)">
                            <label class="form-check-label" for="cap-${tierCode}-${capCode}">
                                <strong>${info.name}</strong>
                            </label>
                        </div>
                        <small class="text-muted d-block mt-1">${info.description}</small>
                    </div>
                    <div class="col-md-4">
                        <label for="cap-max-${tierCode}-${capCode}" class="form-label small">Max Uses Per Request</label>
                        <input type="number"
                               id="cap-max-${tierCode}-${capCode}"
                               class="form-control form-control-sm capability-max-uses"
                               data-tier="${tierCode}"
                               data-capability="${capCode}"
                               placeholder="Unlimited"
                               min="1"
                               max="1000"
                               value="${maxUses}"
                               ${!isEnabled ? 'disabled' : ''}>
                        <small class="form-text text-muted">Leave blank for unlimited</small>
                    </div>
                </div>
            </div>
        `;
    });

    return html;
}

/**
 * Toggle capability enabled/disabled
 */
function aiCentral_toggleCapability(tierCode, capabilityCode, enabled) {
    const maxUsesInput = document.getElementById(`cap-max-${tierCode}-${capabilityCode}`);
    const capabilityItem = document.querySelector(`.capability-item[data-capability="${capabilityCode}"]`);

    maxUsesInput.disabled = !enabled;
    if (!enabled) {
        maxUsesInput.value = '';
        capabilityItem.classList.remove('enabled');
    } else {
        capabilityItem.classList.add('enabled');
    }
}

/**
 * Close feature dialog
 */
function aiCentral_closeFeatureDialog() {
    aiCentral_featureModal.hide();
}

/**
 * Save feature with all tier configurations
 */
async function aiCentral_saveFeature() {
    // Validate general settings
    const maxInputTokens = parseInt(document.getElementById('aicentral-feature-max-input-tokens').value);
    if (isNaN(maxInputTokens) || maxInputTokens < 100 || maxInputTokens > 2000000) {
        showAlert('Max Input Tokens must be between 100 and 2,000,000', 'Validation Error', 'error');
        return;
    }

    // Collect tier configurations
    const tierConfigs = {};
    const tierPanes = document.querySelectorAll('.tier-tab-content');

    for (const pane of tierPanes) {
        const tierCode = pane.getAttribute('data-tier-code');
        const tierId = parseInt(pane.getAttribute('data-tier-id'));

        // Get model_code
        const modelCodeSelect = document.getElementById(`model-code-${tierCode}`);
        const modelCode = modelCodeSelect ? modelCodeSelect.value : '';

        // Get max output tokens
        const maxOutputTokens = parseInt(document.getElementById(`max-output-tokens-${tierCode}`).value);
        if (isNaN(maxOutputTokens) || maxOutputTokens < 100 || maxOutputTokens > 200000) {
            showAlert(`Max Output Tokens for ${tierCode} must be between 100 and 200,000`, 'Validation Error', 'error');
            return;
        }

        // Get capabilities for this tier
        const capabilities = [];
        const capabilityCheckboxes = pane.querySelectorAll('.capability-checkbox');

        for (const checkbox of capabilityCheckboxes) {
            const capCode = checkbox.getAttribute('data-capability');
            const isEnabled = checkbox.checked;
            const maxUsesInput = document.getElementById(`cap-max-${tierCode}-${capCode}`);
            const maxUsesValue = maxUsesInput.value.trim();
            const maxUses = maxUsesValue === '' ? null : parseInt(maxUsesValue);

            // Validate max_uses if provided
            if (maxUses !== null && (maxUses < 1 || maxUses > 1000)) {
                showAlert(`Max Uses for ${capCode} in ${tierCode} must be between 1 and 1,000 or left blank`, 'Validation Error', 'error');
                return;
            }

            capabilities.push({
                capability_code: capCode,
                is_enabled: isEnabled,
                max_uses: maxUses
            });
        }

        tierConfigs[tierCode] = {
            tier_id: tierId,
            model_code: modelCode,
            max_output_tokens: maxOutputTokens,
            capabilities: capabilities
        };
    }

    // Get default model (fallback) - now using tier pattern
    const defaultModel = document.getElementById('model-code-general').value;

    // Build form data
    const formData = new URLSearchParams({
        action: 'saveFeature',
        feature_id: document.getElementById('aicentral-feature-id').value,
        program_id: document.getElementById('aicentral-feature-program').value,
        feature_code: document.getElementById('aicentral-feature-code').value,
        feature_name: document.getElementById('aicentral-feature-name').value,
        feature_description: document.getElementById('aicentral-feature-description').value,
        feature_type: document.getElementById('aicentral-feature-type').value,
        default_model: defaultModel,
        max_input_tokens: maxInputTokens,
        sort_order: document.getElementById('aicentral-feature-sort').value,
        tier_configs: JSON.stringify(tierConfigs)
    });

    if (document.getElementById('aicentral-feature-active').checked) {
        formData.append('is_active', '1');
    }

    try {
        const response = await fetch('/ai/admin/aicentral_featuresCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData
        });

        const data = await response.json();
        if (data.success) {
            showNotification('Feature saved successfully', 'success');
            aiCentral_closeFeatureDialog();
            aiCentral_loadFeatures();
        } else {
            showAlert(data.error || 'Error saving feature', 'Error', 'error');
        }
    } catch (error) {
        console.error('Error saving feature:', error);
        showAlert('Error saving feature', 'Error', 'error');
    }
}

/**
 * Toggle feature status
 */
async function aiCentral_toggleFeatureStatus(featureId) {
    if (!confirm('Toggle this feature\'s active status?')) return;

    try {
        const response = await fetch('/ai/admin/aicentral_featuresCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'toggleFeatureStatus',
                feature_id: featureId
            })
        });

        const data = await response.json();
        if (data.success) {
            aiCentral_loadFeatures();
        } else {
            showAlert(data.error || 'Error', 'Error', 'error');
        }
    } catch (error) {
        console.error('Error toggling feature status:', error);
        showAlert('Error toggling feature status', 'Error', 'error');
    }
}

/**
 * Render capabilities badges with color coding
 * Green = all tiers have it, Orange = some tiers have it
 */
function aiCentral_renderCapabilitiesBadges(feature) {
    // This would need to fetch actual capability data from tier_configs
    // For now, return placeholder until we add API endpoint to get capability summary
    if (!feature.tier_capability_summary) {
        return '<span class="text-muted small">-</span>';
    }

    let html = '';
    const summary = feature.tier_capability_summary;

    Object.keys(summary).forEach(capCode => {
        const tierCount = summary[capCode].enabled_count;
        const totalTiers = summary[capCode].total_tiers;
        const tierNames = summary[capCode].tier_names;

        if (tierCount === 0) return; // Skip disabled capabilities

        const badgeClass = tierCount === totalTiers ? 'bg-success' : 'bg-warning';
        const title = tierCount === totalTiers
            ? 'All tiers'
            : tierNames.join(', ') + ' only';

        html += `<span class="badge ${badgeClass} me-1" title="${title}">${capCode}</span>`;
    });

    return html || '<span class="text-muted small">No capabilities</span>';
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
    const savedView = localStorage.getItem('aicentral_features_view');
    if (savedView) {
        aiCentral_featuresData.currentView = savedView;
    }

    // Load filter values
    const savedFilters = localStorage.getItem('aicentral_features_filters');
    if (savedFilters) {
        try {
            const filters = JSON.parse(savedFilters);
            if (filters.program) document.getElementById('aicentral-filter-program').value = filters.program;
            if (filters.type) document.getElementById('aicentral-filter-type').value = filters.type;
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
    localStorage.setItem('aicentral_features_view', aiCentral_featuresData.currentView);

    const filters = {
        program: document.getElementById('aicentral-filter-program').value,
        type: document.getElementById('aicentral-filter-type').value,
        status: document.getElementById('aicentral-filter-status').value,
        search: document.getElementById('aicentral-filter-search').value
    };
    localStorage.setItem('aicentral_features_filters', JSON.stringify(filters));
}

/**
 * Toggle between card and table view
 */
function aiCentral_toggleView() {
    aiCentral_featuresData.currentView = aiCentral_featuresData.currentView === 'cards' ? 'table' : 'cards';
    aiCentral_savePreferences();
    aiCentral_updateViewToggleIcon();
    aiCentral_renderFeatures();
}

/**
 * Update view toggle button icon
 */
function aiCentral_updateViewToggleIcon() {
    const icon = document.querySelector('#aicentral-view-toggle i');
    if (aiCentral_featuresData.currentView === 'cards') {
        icon.className = 'bi bi-list-ul';
    } else {
        icon.className = 'bi bi-grid-3x3-gap';
    }
}

/**
 * Clear all filters
 */
function aiCentral_clearFilters() {
    document.getElementById('aicentral-filter-program').value = '';
    document.getElementById('aicentral-filter-type').value = '';
    document.getElementById('aicentral-filter-status').value = '';
    document.getElementById('aicentral-filter-search').value = '';
    aiCentral_savePreferences();
    aiCentral_filterFeatures();
}

/**
 * Sort table by column
 */
function aiCentral_sortTable(column) {
    if (aiCentral_featuresData.sortColumn === column) {
        aiCentral_featuresData.sortDirection = aiCentral_featuresData.sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        aiCentral_featuresData.sortColumn = column;
        aiCentral_featuresData.sortDirection = 'asc';
    }

    aiCentral_featuresData.filteredFeatures.sort((a, b) => {
        let aVal = a[column];
        let bVal = b[column];

        aVal = String(aVal || '').toLowerCase();
        bVal = String(bVal || '').toLowerCase();

        if (aVal < bVal) return aiCentral_featuresData.sortDirection === 'asc' ? -1 : 1;
        if (aVal > bVal) return aiCentral_featuresData.sortDirection === 'asc' ? 1 : -1;
        return 0;
    });

    aiCentral_renderFeatures();
}
