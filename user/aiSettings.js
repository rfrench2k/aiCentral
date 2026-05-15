/**
 * AI Settings - User Frontend JavaScript
 */

let ai_settingsData = {
    tiers: [],
    keys: [],
    usage: null
};

let ai_addKeyModal = null;

/**
 * Initialize page
 */
document.addEventListener('DOMContentLoaded', function() {
    const modalElement = document.getElementById('ai-add-key-modal');
    ai_addKeyModal = new bootstrap.Modal(modalElement);

    ai_loadTierInfo();
    ai_loadApiKeys();
    ai_loadUsageSummary();
});

/**
 * Load user's tier information
 */
async function ai_loadTierInfo() {
    try {
        const response = await fetch('/ai/user/aiSettingsCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getUserTierInfo' })
        });

        const data = await response.json();
        if (data.success) {
            ai_settingsData.tiers = data.tiers;
            ai_displayTierInfo(data.tiers);
        } else {
            document.getElementById('ai-tier-info').innerHTML =
                '<div class="alert alert-danger">Error loading tier info: ' + data.error + '</div>';
        }
    } catch (error) {
        console.error('Error loading tier info:', error);
        document.getElementById('ai-tier-info').innerHTML =
            '<div class="alert alert-danger">Error loading tier information</div>';
    }
}

/**
 * Display tier information
 */
function ai_displayTierInfo(tiers) {
    if (tiers.length === 0) {
        document.getElementById('ai-tier-info').innerHTML =
            '<p class="text-muted">No tier assignments found. Contact support.</p>';
        return;
    }

    const html = `
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Program</th>
                        <th>Tier</th>
                        <th>Daily Requests</th>
                        <th>Monthly Requests</th>
                        <th>Monthly Tokens</th>
                    </tr>
                </thead>
                <tbody>
                    ${tiers.map(t => `
                        <tr>
                            <td><strong>${t.program_id || 'Global'}</strong></td>
                            <td><span class="badge bg-primary">${t.tier_name}</span></td>
                            <td>${t.daily_request_limit || 'Unlimited'}</td>
                            <td>${t.monthly_request_limit || 'Unlimited'}</td>
                            <td>${t.monthly_token_limit ? (t.monthly_token_limit / 1000000).toFixed(0) + 'M' : 'Unlimited'}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;

    document.getElementById('ai-tier-info').innerHTML = html;
}

/**
 * Load user's API keys
 */
async function ai_loadApiKeys() {
    try {
        const response = await fetch('/ai/user/aiSettingsCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getUserKeys' })
        });

        const data = await response.json();
        if (data.success) {
            ai_settingsData.keys = data.keys;
            ai_displayApiKeys(data.keys);
        } else {
            document.getElementById('ai-keys-list').innerHTML =
                '<div class="alert alert-danger">Error loading API keys: ' + data.error + '</div>';
        }
    } catch (error) {
        console.error('Error loading API keys:', error);
        document.getElementById('ai-keys-list').innerHTML =
            '<div class="alert alert-danger">Error loading API keys</div>';
    }
}

/**
 * Display API keys
 */
function ai_displayApiKeys(keys) {
    if (keys.length === 0) {
        document.getElementById('ai-keys-list').innerHTML =
            '<p class="text-muted">No API keys configured. Add your own API keys to bypass tier limits.</p>';
        return;
    }

    const html = `
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Provider</th>
                        <th>Label</th>
                        <th>Last Used</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${keys.map(k => `
                        <tr>
                            <td>${k.provider_name}</td>
                            <td>${k.key_label || 'Unlabeled'}</td>
                            <td>${k.last_used_at ? new Date(k.last_used_at).toLocaleDateString() : 'Never'}</td>
                            <td>
                                <button class="btn btn-sm btn-outline-danger" onclick="ai_deleteApiKey(${k.key_id})">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;

    document.getElementById('ai-keys-list').innerHTML = html;
}

/**
 * Show add key modal
 */
function ai_showAddKeyModal() {
    document.getElementById('ai-key-provider').value = 'claude';
    document.getElementById('ai-key-value').value = '';
    document.getElementById('ai-key-label').value = '';
    ai_addKeyModal.show();
}

/**
 * Save API key
 */
async function ai_saveApiKey() {
    const provider = document.getElementById('ai-key-provider').value;
    const keyValue = document.getElementById('ai-key-value').value;
    const label = document.getElementById('ai-key-label').value;

    if (!keyValue) {
        alert('Please enter an API key');
        return;
    }

    try {
        const response = await fetch('/ai/user/aiSettingsCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'saveUserKey',
                provider: provider,
                api_key: keyValue,
                key_label: label
            })
        });

        const data = await response.json();
        if (data.success) {
            ai_addKeyModal.hide();
            ai_loadApiKeys();
        } else {
            alert('Error saving API key: ' + data.error);
        }
    } catch (error) {
        console.error('Error saving API key:', error);
        alert('Error saving API key');
    }
}

/**
 * Delete API key
 */
async function ai_deleteApiKey(keyId) {
    if (!confirm('Are you sure you want to delete this API key?')) {
        return;
    }

    try {
        const response = await fetch('/ai/user/aiSettingsCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'deleteUserKey',
                key_id: keyId
            })
        });

        const data = await response.json();
        if (data.success) {
            ai_loadApiKeys();
        } else {
            alert('Error deleting API key: ' + data.error);
        }
    } catch (error) {
        console.error('Error deleting API key:', error);
        alert('Error deleting API key');
    }
}

/**
 * Load usage summary
 */
async function ai_loadUsageSummary() {
    try {
        const response = await fetch('/ai/user/aiSettingsCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getUserUsage' })
        });

        const data = await response.json();
        if (data.success) {
            ai_settingsData.usage = data.usage;
            ai_displayUsageSummary(data.usage);
        } else {
            document.getElementById('ai-usage-summary').innerHTML =
                '<div class="alert alert-danger">Error loading usage: ' + data.error + '</div>';
        }
    } catch (error) {
        console.error('Error loading usage:', error);
        document.getElementById('ai-usage-summary').innerHTML =
            '<div class="alert alert-danger">Error loading usage data</div>';
    }
}

/**
 * Display usage summary
 */
function ai_displayUsageSummary(usage) {
    const html = `
        <div class="row">
            <div class="col-md-3">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <h3 class="text-primary">${usage.requests_today || 0}</h3>
                        <p class="text-muted mb-0">Requests Today</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <h3 class="text-success">${usage.requests_month || 0}</h3>
                        <p class="text-muted mb-0">Requests This Month</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <h3 class="text-warning">${usage.tokens_month ? (usage.tokens_month / 1000000).toFixed(1) + 'M' : 0}</h3>
                        <p class="text-muted mb-0">Tokens This Month</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <h3 class="text-danger">$${usage.cost_month ? parseFloat(usage.cost_month).toFixed(2) : '0.00'}</h3>
                        <p class="text-muted mb-0">Cost This Month</p>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.getElementById('ai-usage-summary').innerHTML = html;
}
