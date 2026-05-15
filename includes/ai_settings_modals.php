<?php
/**
 * AI Settings Modals Component
 *
 * Provides popup modals for AI Settings that can be used from any app
 * These modals are app-agnostic and don't show AI Central branding
 *
 * Usage in your app's header (after opening body tag):
 *   <?php include_once $_SERVER['DOCUMENT_ROOT'] . '/ai/includes/ai_settings_modals.php'; ?>
 *   <?php echo getAISettingsModalsHTML(); ?>
 *
 * Then in your navbar, include links that call:
 *   - openAIApiKeysModal()
 *   - openAIPreferencesModal()
 *   - openAIUsageModal()
 */

/**
 * Get all AI Settings modals HTML and JavaScript
 * @return string HTML for modals and JavaScript
 */
function getAISettingsModalsHTML() {
    static $modals_rendered = false;
    if ($modals_rendered) {
        return '';
    }
    $modals_rendered = true;

    $html = '';

    // Include the common AI JavaScript file
    $html .= '<script src="/ai/common/common_ai.js"></script>' . "\n";

    // Include the modals
    $html .= getAPIKeysModal();
    $html .= getAIPreferencesModal();
    $html .= getAIUsageModal();
    $html .= getAISettingsJS();

    return $html;
}

/**
 * API Keys Modal
 */
function getAPIKeysModal() {
    return <<<'HTML'
<!-- AI API Keys Modal -->
<div class="modal fade" id="aiApiKeysModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-key"></i> My API Keys</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Tier Information -->
                <div class="alert alert-info" id="ai-tier-info-keys">
                    <div class="text-center py-2">
                        <div class="spinner-border spinner-border-sm" role="status"></div>
                        Loading tier information...
                    </div>
                </div>

                <!-- API Keys List -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Configured API Keys</h6>
                    <button class="btn btn-sm btn-primary" onclick="showAddKeyForm()">
                        <i class="bi bi-plus-lg"></i> Add Key
                    </button>
                </div>
                <div id="ai-keys-list-modal">
                    <div class="text-center py-3">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <p class="mt-2 mb-0">Loading API keys...</p>
                    </div>
                </div>

                <!-- Add Key Form (hidden by default) -->
                <div id="add-key-form" style="display: none;" class="mt-3 p-3 border rounded bg-light">
                    <h6>Add New API Key</h6>
                    <div class="mb-3">
                        <label for="ai-key-provider-modal" class="form-label">Provider</label>
                        <select id="ai-key-provider-modal" class="form-select">
                            <option value="claude">Anthropic (Claude)</option>
                            <option value="openai">OpenAI (GPT)</option>
                            <option value="kimi">Moonshot AI (Kimi)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="ai-key-name-modal" class="form-label">Key Name (optional)</label>
                        <input type="text" id="ai-key-name-modal" class="form-control"
                               placeholder="e.g., Personal Key, Work Key">
                    </div>
                    <div class="mb-3">
                        <label for="ai-key-value-modal" class="form-label">API Key</label>
                        <input type="password" id="ai-key-value-modal" class="form-control"
                               placeholder="sk-ant-...">
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" onclick="saveAPIKey()">
                            <i class="bi bi-save"></i> Save Key
                        </button>
                        <button class="btn btn-secondary" onclick="hideAddKeyForm()">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
HTML;
}

/**
 * AI Preferences Modal
 */
function getAIPreferencesModal() {
    return <<<'HTML'
<!-- AI Preferences Modal -->
<div class="modal fade" id="aiPreferencesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-sliders"></i> AI Preferences</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Tier Information -->
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="bi bi-layers"></i> Your AI Tiers by Program</h6>
                    </div>
                    <div class="card-body" id="ai-tier-info-prefs">
                        <div class="text-center py-2">
                            <div class="spinner-border spinner-border-sm" role="status"></div>
                            Loading tier information...
                        </div>
                    </div>
                </div>

                <!-- Preferences Form -->
                <form id="aiPreferencesForm">
                    <div class="mb-3">
                        <label class="form-label">Default Temperature</label>
                        <input type="range" class="form-range" id="ai-pref-temperature"
                               min="0" max="1" step="0.1" value="0.7">
                        <small class="text-muted">Current: <span id="temp-value">0.7</span></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Default Max Tokens</label>
                        <input type="number" class="form-control" id="ai-pref-max-tokens" value="1000">
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ai-pref-save-history">
                            <label class="form-check-label" for="ai-pref-save-history">
                                Save conversation history
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="saveAIPreferences()">
                    <i class="bi bi-save"></i> Save Preferences
                </button>
            </div>
        </div>
    </div>
</div>
HTML;
}

/**
 * AI Usage Modal
 */
function getAIUsageModal() {
    return <<<'HTML'
<!-- AI Usage Modal -->
<div class="modal fade" id="aiUsageModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-bar-chart"></i> AI Usage Summary</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Usage Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h6 class="text-muted">Total Requests</h6>
                                <h3 class="mb-0" id="usage-total-requests">-</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h6 class="text-muted">Total Cost</h6>
                                <h3 class="mb-0" id="usage-total-cost">-</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h6 class="text-muted">This Month</h6>
                                <h3 class="mb-0" id="usage-month-requests">-</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h6 class="text-muted">Today</h6>
                                <h3 class="mb-0" id="usage-today-requests">-</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Usage Chart -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">Usage Over Time (Last 30 Days)</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="usage-chart" height="80"></canvas>
                    </div>
                </div>

                <!-- Recent Requests -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Recent Requests</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Program</th>
                                        <th>Feature</th>
                                        <th>Provider</th>
                                        <th>Model</th>
                                        <th>Tokens</th>
                                        <th>Cost</th>
                                    </tr>
                                </thead>
                                <tbody id="usage-recent-list">
                                    <tr>
                                        <td colspan="7" class="text-center py-3">
                                            <div class="spinner-border spinner-border-sm" role="status"></div>
                                            Loading recent requests...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
HTML;
}

/**
 * JavaScript for AI Settings modals
 */
function getAISettingsJS() {
    return <<<'HTML'
<script>
// =============================================================================
// AI Settings Modals - JavaScript Functions
// =============================================================================

/**
 * Open API Keys Modal
 */
function openAIApiKeysModal() {
    const modal = new bootstrap.Modal(document.getElementById('aiApiKeysModal'));
    modal.show();
    loadAITierInfo('ai-tier-info-keys');
    loadAPIKeys();
}

/**
 * Open AI Preferences Modal
 */
function openAIPreferencesModal() {
    const modal = new bootstrap.Modal(document.getElementById('aiPreferencesModal'));
    modal.show();
    loadAITierInfo('ai-tier-info-prefs');
    loadAIPreferences();
}

/**
 * Open AI Usage Modal
 */
function openAIUsageModal() {
    const modal = new bootstrap.Modal(document.getElementById('aiUsageModal'));
    modal.show();
    loadAIUsage();
}

/**
 * Load AI Tier Information
 */
function loadAITierInfo(elementId) {
    fetch('/ai/user/aiSettingsCode.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'getUserTierInfo' })
    })
    .then(response => response.json())
    .then(data => {
        const container = document.getElementById(elementId);
        if (data.success && data.tiers && data.tiers.length > 0) {
            let html = '<div class="row">';
            data.tiers.forEach(tier => {
                html += `
                    <div class="col-md-6 mb-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong>${tier.program_name || tier.program_id}:</strong>
                            <span class="badge bg-primary">${tier.tier_name || tier.tier_code}</span>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            container.innerHTML = html;
            container.classList.remove('alert-info');
            container.classList.add('alert-light');
        } else {
            container.innerHTML = '<p class="mb-0">No tier information available.</p>';
        }
    })
    .catch(error => {
        console.error('Error loading tier info:', error);
        document.getElementById(elementId).innerHTML =
            '<p class="mb-0 text-danger">Error loading tier information.</p>';
    });
}

/**
 * Load API Keys
 */
function loadAPIKeys() {
    fetch('/ai/user/aiSettingsCode.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'getUserKeys' })
    })
    .then(response => response.json())
    .then(data => {
        const container = document.getElementById('ai-keys-list-modal');
        if (data.success && data.keys && data.keys.length > 0) {
            let html = '<div class="list-group">';
            data.keys.forEach(key => {
                const providerName = key.provider_code === 'claude' ? 'Anthropic (Claude)' :
                                    key.provider_code === 'openai' ? 'OpenAI (GPT)' :
                                    key.provider_code === 'kimi' ? 'Moonshot AI (Kimi)' :
                                    key.provider_code;
                html += `
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${providerName}</strong>
                            ${key.key_label ? `<br><small class="text-muted">${key.key_label}</small>` : ''}
                        </div>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteAPIKey(${key.key_id})">
                            <i class="bi bi-trash"></i> Delete
                        </button>
                    </div>
                `;
            });
            html += '</div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = '<p class="text-muted">No API keys configured yet.</p>';
        }
    })
    .catch(error => {
        console.error('Error loading API keys:', error);
        document.getElementById('ai-keys-list-modal').innerHTML =
            '<p class="text-danger">Error loading API keys.</p>';
    });
}

/**
 * Show Add Key Form
 */
function showAddKeyForm() {
    document.getElementById('add-key-form').style.display = 'block';
}

/**
 * Hide Add Key Form
 */
function hideAddKeyForm() {
    document.getElementById('add-key-form').style.display = 'none';
    document.getElementById('ai-key-provider-modal').selectedIndex = 0;
    document.getElementById('ai-key-name-modal').value = '';
    document.getElementById('ai-key-value-modal').value = '';
}

/**
 * Save API Key
 */
function saveAPIKey() {
    const provider = document.getElementById('ai-key-provider-modal').value;
    const keyLabel = document.getElementById('ai-key-name-modal').value;
    const keyValue = document.getElementById('ai-key-value-modal').value;

    if (!keyValue) {
        alert('Please enter an API key');
        return;
    }

    // Prepare form data for POST
    const formData = new FormData();
    formData.append('action', 'saveUserKey');
    formData.append('provider', provider);
    formData.append('key_label', keyLabel);
    formData.append('api_key', keyValue);

    fetch('/ai/user/aiSettingsCode.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('API key saved successfully!');
            hideAddKeyForm();
            loadAPIKeys();
        } else {
            alert('Error: ' + (data.error || 'Failed to save API key'));
        }
    })
    .catch(error => {
        console.error('Error saving API key:', error);
        alert('Error saving API key');
    });
}

/**
 * Delete API Key
 */
function deleteAPIKey(keyId) {
    if (!confirm('Are you sure you want to delete this API key?')) {
        return;
    }

    // Prepare form data for POST
    const formData = new FormData();
    formData.append('action', 'deleteUserKey');
    formData.append('key_id', keyId);

    fetch('/ai/user/aiSettingsCode.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('API key deleted successfully!');
            loadAPIKeys();
        } else {
            alert('Error: ' + (data.error || 'Failed to delete API key'));
        }
    })
    .catch(error => {
        console.error('Error deleting API key:', error);
        alert('Error deleting API key');
    });
}

/**
 * Load AI Preferences
 *
 * The preferences UI is wired to the temperature slider only. Persistence
 * (a savePreferences action against /ai/user/aiSettingsCode.php) is not yet
 * implemented; the dedicated preferences page at
 * /ai/user/aicentral_preferencesHTML.php has the working backend.
 */
function loadAIPreferences() {
    // Update temperature display
    const tempSlider = document.getElementById('ai-pref-temperature');
    const tempValue = document.getElementById('temp-value');
    tempSlider.addEventListener('input', function() {
        tempValue.textContent = this.value;
    });
}

/**
 * Save AI Preferences
 *
 * Persistence is not implemented in this modal — use the full preferences
 * page at /ai/user/aicentral_preferencesHTML.php to save changes.
 */
function saveAIPreferences() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('aiPreferencesModal'));
    if (modal) {
        modal.hide();
    }
    showNotification('Use the Preferences page to save changes.', 'info');
}

/**
 * Load AI Usage
 */
let usageChart = null; // Store chart instance globally

function loadAIUsage() {
    fetch('/ai/user/aiSettingsCode.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'getUsageSummary' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update stats
            document.getElementById('usage-total-requests').textContent = data.stats.total_requests || '0';
            document.getElementById('usage-total-cost').textContent = '$' + (data.stats.total_cost || '0.00');
            document.getElementById('usage-month-requests').textContent = data.stats.month_requests || '0';
            document.getElementById('usage-today-requests').textContent = data.stats.today_requests || '0';

            // Render usage chart
            if (data.chart && data.chart.length > 0) {
                renderUsageChart(data.chart);
            } else {
                // Show message if no chart data
                const canvas = document.getElementById('usage-chart');
                const ctx = canvas.getContext('2d');
                ctx.font = '14px Arial';
                ctx.fillStyle = '#6c757d';
                ctx.textAlign = 'center';
                ctx.fillText('No usage data for the last 30 days', canvas.width / 2, canvas.height / 2);
            }

            // Update recent requests table
            if (data.recent && data.recent.length > 0) {
                let html = '';
                data.recent.forEach(req => {
                    html += `
                        <tr>
                            <td>${formatUsageDateTime(req.created_at)}</td>
                            <td>${escapeHtml(req.program_id)}</td>
                            <td>${escapeHtml(req.feature_code)}</td>
                            <td>${escapeHtml(req.provider)}</td>
                            <td>${escapeHtml(req.model)}</td>
                            <td>${req.total_tokens || '-'}</td>
                            <td>$${req.estimated_cost || '0.00'}</td>
                        </tr>
                    `;
                });
                document.getElementById('usage-recent-list').innerHTML = html;
            } else {
                document.getElementById('usage-recent-list').innerHTML =
                    '<tr><td colspan="7" class="text-center">No recent requests</td></tr>';
            }
        } else {
            // Show error message in the table
            document.getElementById('usage-recent-list').innerHTML =
                '<tr><td colspan="7" class="text-center text-danger">Error: ' + (data.error || 'Failed to load usage data') + '</td></tr>';
        }
    })
    .catch(error => {
        console.error('Error loading usage:', error);
        document.getElementById('usage-recent-list').innerHTML =
            '<tr><td colspan="7" class="text-center text-danger">Error loading usage data. Please try again.</td></tr>';
    });
}

/**
 * Render usage chart
 */
function renderUsageChart(chartData) {
    const ctx = document.getElementById('usage-chart').getContext('2d');

    // Destroy existing chart if it exists
    if (usageChart) {
        usageChart.destroy();
    }

    // Create new chart
    usageChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartData.map(item => formatChartDate(item.date)),
            datasets: [{
                label: 'Requests',
                data: chartData.map(item => item.count),
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                tension: 0.4,
                fill: true,
                borderWidth: 2,
                pointRadius: 3,
                pointHoverRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        title: function(context) {
                            return context[0].label;
                        },
                        label: function(context) {
                            return 'Requests: ' + context.parsed.y;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        precision: 0
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}

/**
 * Format date for chart labels
 */
function formatChartDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString([], {
        month: 'short',
        day: 'numeric'
    });
}

/**
 * Format datetime for usage display
 */
function formatUsageDateTime(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleString([], {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Helper function to get cookie value
 */
function getCookieValue(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
    return null;
}

</script>
HTML;
}
?>
