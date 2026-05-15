/**
 * AI Central User Preferences - Frontend JavaScript
 */

let aiCentral_preferencesData = {
    preferences: {},
    models: [],
    features: []
};

/**
 * Initialize preferences page
 */
document.addEventListener('DOMContentLoaded', function() {
    aiCentral_loadModels();
    aiCentral_loadFeatures();
    aiCentral_loadPreferences();
});

/**
 * Load models for dropdowns
 */
async function aiCentral_loadModels() {
    try {
        const response = await fetch('/ai/user/aicentral_preferencesCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getModels' })
        });

        const data = await response.json();
        if (data.success) {
            aiCentral_preferencesData.models = data.models;
        }
    } catch (error) {
        console.error('Error loading models:', error);
    }
}

/**
 * Load features
 */
async function aiCentral_loadFeatures() {
    try {
        const response = await fetch('/ai/user/aicentral_preferencesCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getFeatures' })
        });

        const data = await response.json();
        if (data.success) {
            aiCentral_preferencesData.features = data.features;
        }
    } catch (error) {
        console.error('Error loading features:', error);
    }
}

/**
 * Load user preferences
 */
async function aiCentral_loadPreferences() {
    try {
        const response = await fetch('/ai/user/aicentral_preferencesCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getPreferences' })
        });

        const data = await response.json();
        if (data.success) {
            aiCentral_preferencesData.preferences = data.preferences;
            aiCentral_renderPreferences();
        } else {
            alert('Error: ' + data.error);
        }
    } catch (error) {
        console.error('Error loading preferences:', error);
    }
}

/**
 * Render preferences
 */
function aiCentral_renderPreferences() {
    const container = document.getElementById('aicentral-preferences-list');

    const prefs = aiCentral_preferencesData.preferences;

    const modelOptions = '<option value="">Use System Default</option>' +
        aiCentral_preferencesData.models.map(m =>
            `<option value="${m.model_code}">${m.provider_name} - ${m.model_display_name}</option>`
        ).join('');

    container.innerHTML = `
        <!-- Default Models Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-cpu"></i> Default Models</h5>
            </div>
            <div class="card-body">
                <!-- Preferred Chatbot Model -->
                <div class="row mb-3 p-3 bg-light rounded">
                    <div class="col-md-8">
                        <label for="pref-preferred_chatbot_model" class="form-label fw-semibold mb-1">
                            Preferred Chatbot Model
                        </label>
                        <p class="text-secondary small mb-0">Default model for chatbot conversations</p>
                    </div>
                    <div class="col-md-4">
                        <select id="pref-preferred_chatbot_model" class="form-select">
                            ${modelOptions}
                        </select>
                    </div>
                </div>

                <!-- Preferred Analysis Model -->
                <div class="row p-3 bg-light rounded">
                    <div class="col-md-8">
                        <label for="pref-preferred_analysis_model" class="form-label fw-semibold mb-1">
                            Preferred Analysis Model
                        </label>
                        <p class="text-secondary small mb-0">Default model for data analysis features</p>
                    </div>
                    <div class="col-md-4">
                        <select id="pref-preferred_analysis_model" class="form-select">
                            ${modelOptions}
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chatbot Settings Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-chat-dots"></i> Chatbot Settings</h5>
            </div>
            <div class="card-body">
                <!-- Enable Streaming -->
                <div class="row mb-3 p-3 bg-light rounded align-items-center">
                    <div class="col-md-9">
                        <label for="pref-enable_streaming" class="form-label fw-semibold mb-1">
                            Enable Streaming Responses
                        </label>
                        <p class="text-secondary small mb-0">Show responses as they are generated</p>
                    </div>
                    <div class="col-md-3 text-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="pref-enable_streaming"
                                   style="width: 3rem; height: 1.5rem; cursor: pointer;">
                        </div>
                    </div>
                </div>

                <!-- Conversation Retention Days -->
                <div class="row p-3 bg-light rounded">
                    <div class="col-md-8">
                        <label for="pref-chatbot_retention_days" class="form-label fw-semibold mb-1">
                            Conversation Retention (Days)
                        </label>
                        <p class="text-secondary small mb-0">How long to keep your chat history</p>
                    </div>
                    <div class="col-md-4">
                        <input type="number" id="pref-chatbot_retention_days" class="form-control"
                               min="1" max="365" style="max-width: 150px;">
                    </div>
                </div>
            </div>
        </div>
    `;

    // Set values
    if (prefs.preferred_chatbot_model) {
        document.getElementById('pref-preferred_chatbot_model').value = prefs.preferred_chatbot_model.value || '';
    }
    if (prefs.preferred_analysis_model) {
        document.getElementById('pref-preferred_analysis_model').value = prefs.preferred_analysis_model.value || '';
    }
    if (prefs.enable_streaming) {
        document.getElementById('pref-enable_streaming').checked = prefs.enable_streaming.value === '1';
    }
    if (prefs.chatbot_retention_days) {
        document.getElementById('pref-chatbot_retention_days').value = prefs.chatbot_retention_days.value || '90';
    }
}

/**
 * Save all preferences
 */
async function aiCentral_saveAllPreferences() {
    const preferences = {
        preferred_chatbot_model: document.getElementById('pref-preferred_chatbot_model').value,
        preferred_analysis_model: document.getElementById('pref-preferred_analysis_model').value,
        enable_streaming: document.getElementById('pref-enable_streaming').checked ? '1' : '0',
        chatbot_retention_days: document.getElementById('pref-chatbot_retention_days').value
    };

    try {
        const response = await fetch('/ai/user/aicentral_preferencesCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'updatePreferences',
                preferences: JSON.stringify(preferences)
            })
        });

        const data = await response.json();
        if (data.success) {
            alert('Preferences saved successfully');
        } else {
            alert('Error: ' + data.error);
        }
    } catch (error) {
        console.error('Error saving preferences:', error);
        alert('Error saving preferences');
    }
}
