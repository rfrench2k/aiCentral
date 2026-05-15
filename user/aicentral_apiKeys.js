/**
 * AI Central User API Keys - Frontend JavaScript
 */

let aiCentral_apiKeysData = {
    keys: [],
    providers: []
};

let aiCentral_keyModal = null;

/**
 * Initialize API keys page
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap modal
    const modalElement = document.getElementById('keyModal');
    aiCentral_keyModal = new bootstrap.Modal(modalElement);

    aiCentral_loadProviders();
    aiCentral_loadKeys();
});

/**
 * Load providers for dropdown
 */
async function aiCentral_loadProviders() {
    try {
        const response = await fetch('/ai/user/aicentral_apiKeysCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getProviders' })
        });

        const data = await response.json();
        if (data.success) {
            aiCentral_apiKeysData.providers = data.providers;

            const dialogSelect = document.getElementById('aicentral-key-provider');
            dialogSelect.innerHTML = '<option value="">Select provider...</option>' +
                data.providers.map(p => `<option value="${p.provider_id}">${aiCentral_escapeHtml(p.provider_name)}</option>`).join('');
        }
    } catch (error) {
        console.error('Error loading providers:', error);
    }
}

/**
 * Load user's API keys
 */
async function aiCentral_loadKeys() {
    try {
        const response = await fetch('/ai/user/aicentral_apiKeysCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getKeys' })
        });

        const data = await response.json();
        if (data.success) {
            aiCentral_apiKeysData.keys = data.keys;
            aiCentral_renderKeys();
        } else {
            alert('Error: ' + data.error);
        }
    } catch (error) {
        console.error('Error loading API keys:', error);
    }
}

/**
 * Render API keys
 */
function aiCentral_renderKeys() {
    const container = document.getElementById('aicentral-keys-list');

    if (aiCentral_apiKeysData.keys.length === 0) {
        container.innerHTML = `
            <div class="alert alert-secondary text-center py-5" role="alert">
                <i class="bi bi-key fs-1 mb-3 d-block text-muted"></i>
                <h5>No API keys found</h5>
                <p class="mb-0">Click "Add API Key" to get started.</p>
            </div>
        `;
        return;
    }

    // Group by provider
    const grouped = {};
    aiCentral_apiKeysData.keys.forEach(key => {
        if (!grouped[key.provider_name]) {
            grouped[key.provider_name] = [];
        }
        grouped[key.provider_name].push(key);
    });

    let html = '';
    for (const [providerName, keys] of Object.entries(grouped)) {
        html += `
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">${aiCentral_escapeHtml(providerName)}</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        ${keys.map(key => aiCentral_renderKeyCard(key)).join('')}
                    </div>
                </div>
            </div>
        `;
    }

    container.innerHTML = html;
}

/**
 * Render single API key card
 */
function aiCentral_renderKeyCard(key) {
    const statusClass = key.last_test_status === 'success' ? 'success' :
                       key.last_test_status === 'failed' ? 'danger' : 'secondary';
    const statusIcon = key.last_test_status === 'success' ? 'check-circle-fill' :
                      key.last_test_status === 'failed' ? 'x-circle-fill' : 'question-circle-fill';

    return `
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 ${!key.is_active ? 'opacity-75' : ''}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h6 class="card-title mb-1">${aiCentral_escapeHtml(key.key_name)}</h6>
                            <small class="text-muted font-monospace">${aiCentral_escapeHtml(key.key_prefix)}...</small>
                        </div>
                        ${key.is_default ? '<span class="badge bg-warning text-dark">Default</span>' : ''}
                    </div>

                    <div class="mb-2">
                        <small class="text-muted d-block">Status:</small>
                        <span class="badge bg-${key.is_active ? 'success' : 'danger'}">
                            ${key.is_active ? 'Active' : 'Inactive'}
                        </span>
                    </div>

                    <div class="mb-2">
                        <small class="text-muted d-block">Test Status:</small>
                        <span class="badge bg-${statusClass}">
                            <i class="bi bi-${statusIcon} me-1"></i>
                            ${key.last_test_status}
                        </span>
                    </div>

                    ${key.last_used_at ? `
                    <div class="mb-2">
                        <small class="text-muted d-block">Last Used:</small>
                        <small>${new Date(key.last_used_at).toLocaleString()}</small>
                    </div>
                    ` : ''}

                    <div class="mb-3">
                        <small class="text-muted d-block">Created:</small>
                        <small>${new Date(key.created_at).toLocaleDateString()}</small>
                    </div>

                    <div class="d-flex flex-wrap gap-1">
                        <button class="btn btn-sm btn-outline-primary" onclick="aiCentral_testKey(${key.key_id})">
                            <i class="bi bi-shield-check"></i> Test
                        </button>
                        ${!key.is_default ? `
                        <button class="btn btn-sm btn-outline-secondary" onclick="aiCentral_setDefaultKey(${key.key_id})">
                            <i class="bi bi-star"></i> Default
                        </button>
                        ` : ''}
                        <button class="btn btn-sm btn-outline-${key.is_active ? 'warning' : 'success'}"
                                onclick="aiCentral_toggleKeyStatus(${key.key_id})">
                            <i class="bi bi-${key.is_active ? 'pause-circle' : 'play-circle'}"></i>
                            ${key.is_active ? 'Deactivate' : 'Activate'}
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="aiCentral_deleteKey(${key.key_id})">
                            <i class="bi bi-trash"></i> Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
}

/**
 * Show add key dialog
 */
function aiCentral_showAddKeyDialog() {
    document.getElementById('keyModalLabel').textContent = 'Add API Key';
    document.getElementById('aicentral-key-id').value = '';
    document.getElementById('aicentral-key-provider').value = '';
    document.getElementById('aicentral-key-name').value = '';
    document.getElementById('aicentral-key-value').value = '';
    document.getElementById('aicentral-key-default').checked = false;
    document.getElementById('aicentral-key-test').checked = true;

    aiCentral_keyModal.show();
}

/**
 * Close key dialog
 */
function aiCentral_closeKeyDialog() {
    aiCentral_keyModal.hide();
}

/**
 * Save API key
 */
async function aiCentral_saveKey() {
    const formData = new URLSearchParams({
        action: 'saveKey',
        key_id: document.getElementById('aicentral-key-id').value,
        provider_id: document.getElementById('aicentral-key-provider').value,
        key_name: document.getElementById('aicentral-key-name').value,
        api_key: document.getElementById('aicentral-key-value').value
    });

    if (document.getElementById('aicentral-key-default').checked) formData.append('is_default', '1');
    if (document.getElementById('aicentral-key-test').checked) formData.append('test_key', '1');

    try {
        const response = await fetch('/ai/user/aicentral_apiKeysCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData
        });

        const data = await response.json();
        if (data.success) {
            aiCentral_closeKeyDialog();
            aiCentral_loadKeys();
        } else {
            alert('Error: ' + data.error);
        }
    } catch (error) {
        console.error('Error saving API key:', error);
        alert('Error saving API key');
    }
}

/**
 * Delete API key
 */
async function aiCentral_deleteKey(keyId) {
    if (!confirm('Delete this API key? This action cannot be undone.')) return;

    try {
        const response = await fetch('/ai/user/aicentral_apiKeysCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'deleteKey',
                key_id: keyId
            })
        });

        const data = await response.json();
        if (data.success) {
            aiCentral_loadKeys();
        } else {
            alert('Error: ' + data.error);
        }
    } catch (error) {
        console.error('Error deleting API key:', error);
        alert('Error deleting API key');
    }
}

/**
 * Toggle key status
 */
async function aiCentral_toggleKeyStatus(keyId) {
    try {
        const response = await fetch('/ai/user/aicentral_apiKeysCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'toggleKeyStatus',
                key_id: keyId
            })
        });

        const data = await response.json();
        if (data.success) {
            aiCentral_loadKeys();
        } else {
            alert('Error: ' + data.error);
        }
    } catch (error) {
        console.error('Error toggling key status:', error);
        alert('Error toggling key status');
    }
}

/**
 * Set key as default
 */
async function aiCentral_setDefaultKey(keyId) {
    try {
        const response = await fetch('/ai/user/aicentral_apiKeysCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'setDefaultKey',
                key_id: keyId
            })
        });

        const data = await response.json();
        if (data.success) {
            aiCentral_loadKeys();
        } else {
            alert('Error: ' + data.error);
        }
    } catch (error) {
        console.error('Error setting default key:', error);
        alert('Error setting default key');
    }
}

/**
 * Test API key
 */
async function aiCentral_testKey(keyId) {
    try {
        const response = await fetch('/ai/user/aicentral_apiKeysCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'testKey',
                key_id: keyId
            })
        });

        const data = await response.json();
        if (data.success) {
            alert('API key test successful!');
            aiCentral_loadKeys();
        } else {
            alert('API key test failed: ' + data.error);
            aiCentral_loadKeys();
        }
    } catch (error) {
        console.error('Error testing API key:', error);
        alert('Error testing API key');
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
