<?php
/**
 * AI Settings - User Settings Page
 * Allows users to view their AI tiers and manage API keys
 */
$pageTitle = 'AI Settings';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/header_ai.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col">
            <h1 class="h2">
                <i class="bi bi-sliders text-primary"></i> AI Settings
            </h1>
            <p class="text-muted">Manage your AI tiers, API keys, and preferences</p>
        </div>
    </div>

    <!-- User Tier Info Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-layers"></i> Your AI Tiers by Program</h5>
                </div>
                <div class="card-body" id="ai-tier-info">
                    <div class="text-center py-3">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2">Loading tier information...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- API Keys Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-key"></i> My API Keys</h5>
                    <button class="btn btn-sm btn-primary" onclick="ai_showAddKeyModal()">
                        <i class="bi bi-plus-lg"></i> Add API Key
                    </button>
                </div>
                <div class="card-body" id="ai-keys-list">
                    <div class="text-center py-3">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2">Loading API keys...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Usage Summary Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Usage Summary</h5>
                </div>
                <div class="card-body" id="ai-usage-summary">
                    <div class="text-center py-3">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2">Loading usage data...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add API Key Modal -->
<div class="modal fade" id="ai-add-key-modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add API Key</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="ai-key-provider" class="form-label">Provider</label>
                    <select id="ai-key-provider" class="form-select">
                        <option value="claude">Anthropic (Claude)</option>
                        <option value="openai">OpenAI (GPT)</option>
                        <option value="kimi">Moonshot AI (Kimi)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="ai-key-value" class="form-label">API Key</label>
                    <input type="password" id="ai-key-value" class="form-control"
                           placeholder="sk-ant-...">
                </div>
                <div class="mb-3">
                    <label for="ai-key-label" class="form-label">Label (Optional)</label>
                    <input type="text" id="ai-key-label" class="form-control"
                           placeholder="My Anthropic Key">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="ai_saveApiKey()">
                    Save API Key
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/ai/user/aiSettings.js"></script>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/footer.html'; ?>
