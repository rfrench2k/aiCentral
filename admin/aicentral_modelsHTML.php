<?php
/**
 * AI Central Admin Models - HTML
 */
$pageTitle = 'AI Central - Models Management';

require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/header_ai.php';
?>

<div class="row mb-4">
    <div class="col">
        <h1 class="text-primary">
            <i class="bi bi-cpu"></i> Models Management
        </h1>
        <p class="text-muted">Manage AI models, pricing, and capabilities</p>
    </div>
    <div class="col-auto">
        <button class="btn btn-primary" onclick="aiCentral_showAddModelDialog()">
            <i class="bi bi-plus-circle"></i> Add Model
        </button>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <select id="aicentral-filter-provider" class="form-select" onchange="aiCentral_filterModels()">
                    <option value="">All Providers</option>
                </select>
            </div>
            <div class="col-md-2">
                <select id="aicentral-filter-tier" class="form-select" onchange="aiCentral_filterModels()">
                    <option value="">All Tiers</option>
                    <option value="budget">Budget</option>
                    <option value="standard">Standard</option>
                    <option value="premium">Premium</option>
                    <option value="flagship">Flagship</option>
                </select>
            </div>
            <div class="col-md-2">
                <select id="aicentral-filter-status" class="form-select" onchange="aiCentral_filterModels()">
                    <option value="">All Status</option>
                    <option value="1" selected>Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            <div class="col-md-4">
                <input type="text" id="aicentral-filter-search" class="form-control"
                       placeholder="Search models..." onkeyup="aiCentral_filterModels()">
            </div>
            <div class="col-md-1">
                <div class="btn-group w-100" role="group">
                    <button type="button" class="btn btn-outline-secondary" onclick="aiCentral_toggleView()"
                            id="aicentral-view-toggle" title="Toggle View">
                        <i class="bi bi-list-ul"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="aiCentral_clearFilters()"
                            title="Clear Filters">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Models List -->
<div id="aicentral-models-list">
    <div class="text-center py-5">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
</div>

<!-- Add/Edit Model Modal -->
<div class="modal fade" id="aicentral-model-dialog" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="aicentral-dialog-title">Add Model</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="aicentral-model-id">

                <div class="mb-3">
                    <label class="form-label">Provider *</label>
                    <select id="aicentral-model-provider" class="form-select" required>
                        <option value="">Select provider...</option>
                    </select>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Model Code *</label>
                        <input type="text" id="aicentral-model-code" class="form-control"
                               placeholder="claude-sonnet-4-5-20250929" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Display Name *</label>
                        <input type="text" id="aicentral-model-name" class="form-control"
                               placeholder="Claude 4.5 Sonnet" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Model Tier *</label>
                        <select id="aicentral-model-tier" class="form-select" required>
                            <option value="budget">Budget</option>
                            <option value="standard">Standard</option>
                            <option value="premium">Premium</option>
                            <option value="flagship">Flagship</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Sort Order</label>
                        <input type="number" id="aicentral-model-sort" class="form-control"
                               value="100" min="0">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Max Tokens</label>
                        <input type="number" id="aicentral-model-max-tokens" class="form-control"
                               value="4096" min="1">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Context Window</label>
                        <input type="number" id="aicentral-model-context" class="form-control"
                               value="200000" min="1">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Input Cost per Million Tokens ($) *</label>
                        <input type="number" id="aicentral-model-input-cost" class="form-control"
                               step="0.01" min="0" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Output Cost per Million Tokens ($) *</label>
                        <input type="number" id="aicentral-model-output-cost" class="form-control"
                               step="0.01" min="0" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Thinking Cost per Million Tokens ($)</label>
                        <input type="number" id="aicentral-model-thinking-cost" class="form-control"
                               step="0.01" min="0" placeholder="0.00">
                        <small class="text-muted">For models with extended thinking (e.g., Claude Opus 4.1)</small>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Pricing Effective Date *</label>
                    <input type="date" id="aicentral-model-effective-date" class="form-control" required>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input type="checkbox" id="aicentral-model-vision" class="form-check-input">
                        <label class="form-check-label" for="aicentral-model-vision">Supports Vision</label>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input type="checkbox" id="aicentral-model-functions" class="form-check-input">
                        <label class="form-check-label" for="aicentral-model-functions">Supports Function Calling</label>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input type="checkbox" id="aicentral-model-streaming" class="form-check-input" checked>
                        <label class="form-check-label" for="aicentral-model-streaming">Supports Streaming</label>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input type="checkbox" id="aicentral-model-active" class="form-check-input" checked>
                        <label class="form-check-label" for="aicentral-model-active">Active</label>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <textarea id="aicentral-model-notes" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="aiCentral_saveModel()">Save Model</button>
            </div>
        </div>
    </div>
</div>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/footer.html'; ?>

<script src="/ai/admin/aicentral_models.js"></script>
