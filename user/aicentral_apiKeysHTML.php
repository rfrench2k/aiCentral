<?php
/**
 * AI Central User API Keys - HTML
 */
$pageTitle = "My API Keys - AI Central";
require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/header_ai.php';
?>

<div class="container py-4">
    <!-- Page Header -->
    <div class="mb-4">
        <h1 class="display-6 fw-bold mb-2">My API Keys</h1>
        <p class="text-muted">Manage your personal API keys to use premium models and bypass quotas</p>
    </div>

    <!-- Info Alert -->
    <div class="alert alert-info d-flex align-items-start mb-4" role="alert">
        <i class="bi bi-info-circle-fill fs-4 me-3 flex-shrink-0"></i>
        <div>
            <strong>Bring Your Own Key (BYOK):</strong> Add your own API keys from providers like OpenAI, Anthropic, or Google to access premium models without quota limits. Your keys are encrypted and stored securely.
        </div>
    </div>

    <!-- Add Key Button -->
    <div class="mb-4">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#keyModal" onclick="aiCentral_showAddKeyDialog()">
            <i class="bi bi-plus-lg me-1"></i> Add API Key
        </button>
    </div>

    <!-- API Keys List -->
    <div id="aicentral-keys-list">
        <div class="text-center py-5 text-muted">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3">Loading your API keys...</p>
        </div>
    </div>
</div>

<!-- Add/Edit API Key Modal -->
<div class="modal fade" id="keyModal" tabindex="-1" aria-labelledby="keyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="keyModalLabel">Add API Key</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="aicentral-key-id">

                <div class="mb-3">
                    <label for="aicentral-key-provider" class="form-label">Provider <span class="text-danger">*</span></label>
                    <select id="aicentral-key-provider" class="form-select" required>
                        <option value="">Select provider...</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="aicentral-key-name" class="form-label">Key Name <span class="text-danger">*</span></label>
                    <input type="text" id="aicentral-key-name" class="form-control"
                           placeholder="My OpenAI Key" required>
                    <small class="form-text text-muted">A friendly name to identify this key</small>
                </div>

                <div class="mb-3">
                    <label for="aicentral-key-value" class="form-label">API Key <span class="text-danger">*</span></label>
                    <input type="password" id="aicentral-key-value" class="form-control"
                           placeholder="sk-..." required>
                    <small class="form-text text-muted">Your API key will be encrypted before storage</small>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="aicentral-key-default">
                    <label class="form-check-label" for="aicentral-key-default">
                        Set as default for this provider
                    </label>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="aicentral-key-test" checked>
                    <label class="form-check-label" for="aicentral-key-test">
                        Test key before saving
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="aiCentral_saveKey()">Save Key</button>
            </div>
        </div>
    </div>
</div>

<script src="/ai/user/aicentral_apiKeys.js"></script>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/footer.html'; ?>
