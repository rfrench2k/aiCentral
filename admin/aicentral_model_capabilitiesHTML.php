<?php
/**
 * AI Central Admin - Model Capabilities Management
 * Manage tool/capability costs per model
 */
$pageTitle = 'AI Central - Model Capabilities';

require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/header_ai.php';
?>

<link rel="stylesheet" href="/ai/admin/aicentral_model_capabilities.css">

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h2 mb-0">
                        <i class="bi bi-gear-fill text-primary"></i> Model Capabilities
                    </h1>
                    <p class="text-muted mb-0">Manage AI tool costs and configuration per model</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <select id="aicentral-filter-provider" class="form-select" onchange="aiCentral_filterCapabilities()">
                                <option value="">All Providers</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <select id="aicentral-filter-model" class="form-select" onchange="aiCentral_filterCapabilities()">
                                <option value="">All Models</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <select id="aicentral-filter-capability" class="form-select" onchange="aiCentral_filterCapabilities()">
                                <option value="">All Capabilities</option>
                                <option value="web_search">Web Search</option>
                                <option value="web_fetch">Web Fetch</option>
                                <option value="vision">Vision</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <input type="text" id="aicentral-filter-search" class="form-control"
                                   placeholder="Search..." onkeyup="aiCentral_filterCapabilities()">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Capabilities List -->
    <div id="aicentral-capabilities-list">
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading capabilities...</span>
            </div>
            <p class="mt-3 text-muted">Loading capabilities...</p>
        </div>
    </div>
</div>

<!-- Edit Capability Modal -->
<div class="modal fade" id="aicentral-capability-modal" tabindex="-1" aria-labelledby="aicentral-dialog-title" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="aicentral-dialog-title">Edit Capability</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="aicentral-capability-id">

                <!-- Model Info (read-only) -->
                <div class="alert alert-info">
                    <strong>Model:</strong> <span id="aicentral-display-model"></span><br>
                    <strong>Capability:</strong> <span id="aicentral-display-capability"></span>
                </div>

                <!-- Supported -->
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" id="aicentral-capability-supported" checked>
                        <label class="form-check-label" for="aicentral-capability-supported">
                            <strong>Capability Supported</strong>
                            <small class="text-muted d-block">Enable this if the model supports this capability</small>
                        </label>
                    </div>
                </div>

                <hr>

                <!-- Cost Structure -->
                <div class="mb-3">
                    <label class="form-label"><strong>Cost Structure</strong></label>
                    <div class="form-check">
                        <input type="radio" class="form-check-input" name="cost-type" id="cost-type-free" value="free"
                               onchange="aiCentral_updateCostFields()">
                        <label class="form-check-label" for="cost-type-free">
                            <strong>Free</strong> - No cost for this capability
                            <small class="text-muted d-block">Example: xAI free tools, Claude web_fetch</small>
                        </label>
                    </div>
                    <div class="form-check mt-2">
                        <input type="radio" class="form-check-input" name="cost-type" id="cost-type-per-use" value="per-use"
                               onchange="aiCentral_updateCostFields()">
                        <label class="form-check-label" for="cost-type-per-use">
                            <strong>Cost Per Use</strong> - Fixed cost per tool call
                            <small class="text-muted d-block">Example: $0.01 per web search</small>
                        </label>
                    </div>
                    <div class="form-check mt-2">
                        <input type="radio" class="form-check-input" name="cost-type" id="cost-type-per-1000" value="per-1000"
                               onchange="aiCentral_updateCostFields()">
                        <label class="form-check-label" for="cost-type-per-1000">
                            <strong>Cost Per 1000</strong> - Bulk pricing
                            <small class="text-muted d-block">Example: $10 per 1,000 searches</small>
                        </label>
                    </div>
                    <div class="form-check mt-2">
                        <input type="radio" class="form-check-input" name="cost-type" id="cost-type-included" value="included"
                               onchange="aiCentral_updateCostFields()">
                        <label class="form-check-label" for="cost-type-included">
                            <strong>Included in Tokens</strong> - No separate charge, cost is in token pricing
                            <small class="text-muted d-block">Example: Vision analysis</small>
                        </label>
                    </div>
                </div>

                <!-- Cost Input Fields -->
                <div id="cost-per-use-field" class="mb-3" style="display: none;">
                    <label for="aicentral-cost-per-use" class="form-label">Cost Per Use ($)</label>
                    <input type="number" id="aicentral-cost-per-use" class="form-control"
                           step="0.000001" min="0" placeholder="0.01">
                    <small class="text-muted">Enter cost in USD per single use</small>
                </div>

                <div id="cost-per-1000-field" class="mb-3" style="display: none;">
                    <label for="aicentral-cost-per-1000" class="form-label">Cost Per 1,000 Uses ($)</label>
                    <input type="number" id="aicentral-cost-per-1000" class="form-control"
                           step="0.01" min="0" placeholder="10.00">
                    <small class="text-muted">Enter cost in USD per 1,000 uses</small>
                </div>

                <hr>

                <!-- Default Max Uses -->
                <div class="mb-3">
                    <label for="aicentral-max-uses-default" class="form-label">Default Max Uses Per Request</label>
                    <input type="number" id="aicentral-max-uses-default" class="form-control"
                           min="1" max="1000" placeholder="Leave blank for no default">
                    <small class="text-muted">Recommended default limit for this capability (can be overridden per feature/tier)</small>
                </div>

                <!-- Additional Settings -->
                <div class="mb-3">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="aicentral-includes-result-tokens">
                        <label class="form-check-label" for="aicentral-includes-result-tokens">
                            <strong>Includes Result Tokens</strong>
                            <small class="text-muted d-block">Check if provider charges for both tool call AND result content (OpenAI pattern)</small>
                        </label>
                    </div>
                </div>

                <!-- Provider-Specific Info -->
                <div class="mb-3">
                    <label for="aicentral-provider-tool-name" class="form-label">Provider Tool Name (Optional)</label>
                    <input type="text" id="aicentral-provider-tool-name" class="form-control"
                           placeholder="e.g., web_search_20250305">
                    <small class="text-muted">Provider-specific tool identifier if different from capability_code</small>
                </div>

                <div class="mb-3">
                    <label for="aicentral-api-format-notes" class="form-label">API Format Notes (Optional)</label>
                    <textarea id="aicentral-api-format-notes" class="form-control" rows="2"
                              placeholder="Implementation notes for this provider/capability combination"></textarea>
                </div>
            </div>

            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="aiCentral_saveCapability()">
                    <i class="bi bi-check-lg"></i> Save Capability
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/footer.html'; ?>

<script src="/ai/admin/aicentral_model_capabilities.js"></script>
