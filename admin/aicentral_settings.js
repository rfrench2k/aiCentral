/**
 * AI Central Admin Settings - Frontend JavaScript
 */

let aiCentral_settingsData = {
    settings: []
};

/**
 * Initialize settings page
 */
document.addEventListener('DOMContentLoaded', function() {
    aiCentral_loadSettings();
});

/**
 * Load settings
 */
async function aiCentral_loadSettings() {
    try {
        const response = await fetch('/ai/admin/aicentral_settingsCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getSettings' })
        });

        const data = await response.json();
        if (data.success) {
            aiCentral_settingsData.settings = data.settings;
            aiCentral_renderSettings();
        }
    } catch (error) {
        console.error('Error loading settings:', error);
    }
}

/**
 * Render settings
 */
function aiCentral_renderSettings() {
    const container = document.getElementById('aicentral-settings-list');

    if (aiCentral_settingsData.settings.length === 0) {
        container.innerHTML = `
            <div class="alert alert-info text-center" role="alert">
                <i class="bi bi-info-circle me-2"></i>No settings found
            </div>
        `;
        return;
    }

    // Group settings by type/functionality
    const groups = {
        'Logging Settings': ['log_full_prompts', 'log_full_responses'],
        'Chatbot Settings': ['default_chatbot_retention_days'],
        'API Key Settings': ['enable_byok', 'require_key_testing'],
        'Pricing Settings': ['pricing_update_reminder_days']
    };

    let html = '';
    for (const [groupName, keys] of Object.entries(groups)) {
        const groupSettings = aiCentral_settingsData.settings.filter(s => keys.includes(s.setting_key));
        if (groupSettings.length > 0) {
            html += `
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-gear-fill me-2"></i>${groupName}
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            ${groupSettings.map(setting => aiCentral_renderSettingRow(setting)).join('')}
                        </div>
                    </div>
                </div>
            `;
        }
    }

    container.innerHTML = html;
}

/**
 * Render single setting row
 */
function aiCentral_renderSettingRow(setting) {
    const label = aiCentral_formatSettingLabel(setting.setting_key);
    const description = aiCentral_getSettingDescription(setting.setting_key);

    let inputHtml = '';
    if (setting.setting_type === 'boolean') {
        inputHtml = `
            <div class="form-check form-switch">
                <input class="form-check-input"
                       type="checkbox"
                       role="switch"
                       id="setting-${setting.setting_id}"
                       data-setting-id="${setting.setting_id}"
                       ${setting.setting_value === '1' ? 'checked' : ''}>
            </div>
        `;
    } else if (setting.setting_type === 'integer') {
        inputHtml = `
            <input type="number"
                   id="setting-${setting.setting_id}"
                   data-setting-id="${setting.setting_id}"
                   class="form-control"
                   style="width: 200px;"
                   value="${aiCentral_escapeHtml(setting.setting_value)}"
                   min="0">
        `;
    } else {
        inputHtml = `
            <input type="text"
                   id="setting-${setting.setting_id}"
                   data-setting-id="${setting.setting_id}"
                   class="form-control"
                   style="width: 200px;"
                   value="${aiCentral_escapeHtml(setting.setting_value)}">
        `;
    }

    return `
        <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-center">
                <div class="flex-grow-1 me-3">
                    <div class="fw-semibold mb-1">${label}</div>
                    <div class="text-muted small">${description}</div>
                </div>
                <div class="flex-shrink-0">
                    ${inputHtml}
                </div>
            </div>
        </div>
    `;
}

/**
 * Format setting key to readable label
 */
function aiCentral_formatSettingLabel(key) {
    return key.split('_')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

/**
 * Get setting description
 */
function aiCentral_getSettingDescription(key) {
    const descriptions = {
        'log_full_prompts': 'Store complete prompt text in usage logs',
        'log_full_responses': 'Store complete response text in usage logs',
        'default_chatbot_retention_days': 'Number of days to retain chatbot conversations',
        'enable_byok': 'Allow users to add their own API keys (BYOK)',
        'require_key_testing': 'Require API key testing before saving',
        'pricing_update_reminder_days': 'Days before reminding to update model pricing'
    };
    return descriptions[key] || 'No description available';
}

/**
 * Save all settings
 */
async function aiCentral_saveAllSettings() {
    const settings = [];

    aiCentral_settingsData.settings.forEach(setting => {
        const input = document.getElementById(`setting-${setting.setting_id}`);
        if (!input) return;

        let value;
        if (setting.setting_type === 'boolean') {
            value = input.checked ? '1' : '0';
        } else {
            value = input.value;
        }

        settings.push({
            setting_id: setting.setting_id,
            setting_value: value
        });
    });

    try {
        const response = await fetch('/ai/admin/aicentral_settingsCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'updateAllSettings',
                settings: JSON.stringify(settings)
            })
        });

        const data = await response.json();
        if (data.success) {
            // Show Bootstrap toast notification
            const toastHtml = `
                <div class="toast-container position-fixed top-0 end-0 p-3">
                    <div class="toast show bg-success text-white" role="alert">
                        <div class="toast-header bg-success text-white">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <strong class="me-auto">Success</strong>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                        </div>
                        <div class="toast-body">
                            Settings saved successfully
                        </div>
                    </div>
                </div>
            `;

            // Remove any existing toasts
            document.querySelectorAll('.toast-container').forEach(tc => tc.remove());

            // Add new toast
            document.body.insertAdjacentHTML('beforeend', toastHtml);

            // Auto-hide after 3 seconds
            setTimeout(() => {
                document.querySelectorAll('.toast-container').forEach(tc => tc.remove());
            }, 3000);

            aiCentral_loadSettings();
        } else {
            // Show error toast
            const toastHtml = `
                <div class="toast-container position-fixed top-0 end-0 p-3">
                    <div class="toast show bg-danger text-white" role="alert">
                        <div class="toast-header bg-danger text-white">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <strong class="me-auto">Error</strong>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                        </div>
                        <div class="toast-body">
                            ${data.error || 'Error saving settings'}
                        </div>
                    </div>
                </div>
            `;

            document.querySelectorAll('.toast-container').forEach(tc => tc.remove());
            document.body.insertAdjacentHTML('beforeend', toastHtml);

            setTimeout(() => {
                document.querySelectorAll('.toast-container').forEach(tc => tc.remove());
            }, 3000);
        }
    } catch (error) {
        console.error('Error saving settings:', error);

        // Show error toast
        const toastHtml = `
            <div class="toast-container position-fixed top-0 end-0 p-3">
                <div class="toast show bg-danger text-white" role="alert">
                    <div class="toast-header bg-danger text-white">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <strong class="me-auto">Error</strong>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">
                        Error saving settings
                    </div>
                </div>
            </div>
        `;

        document.querySelectorAll('.toast-container').forEach(tc => tc.remove());
        document.body.insertAdjacentHTML('beforeend', toastHtml);

        setTimeout(() => {
            document.querySelectorAll('.toast-container').forEach(tc => tc.remove());
        }, 3000);
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
