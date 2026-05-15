<?php
/**
 * AI Central Admin Features - HTML
 */
$pageTitle = 'AI Central - Features Management';


require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/header_ai.php';
?>

<link rel="stylesheet" href="/ai/admin/aicentral_features.css">

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h2 mb-0">
                    <i class="bi bi-stars text-primary"></i> Features Management
                </h1>
                <button class="btn btn-primary" onclick="aiCentral_showAddFeatureDialog()">
                    <i class="bi bi-plus-lg"></i> Add Feature
                </button>
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
                            <select id="aicentral-filter-program" class="form-select" onchange="aiCentral_filterFeatures()">
                                <option value="">All Programs</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <select id="aicentral-filter-type" class="form-select" onchange="aiCentral_filterFeatures()">
                                <option value="">All Types</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <select id="aicentral-filter-status" class="form-select" onchange="aiCentral_filterFeatures()">
                                <option value="">All Status</option>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <input type="text" id="aicentral-filter-search" class="form-control"
                                   placeholder="Search features..." onkeyup="aiCentral_filterFeatures()">
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
        </div>
    </div>

    <!-- Features List -->
    <div id="aicentral-features-list">
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading features...</span>
            </div>
            <p class="mt-3 text-muted">Loading features...</p>
        </div>
    </div>
</div>

<!-- Add/Edit Feature Modal -->
<div class="modal fade" id="aicentral-feature-modal" tabindex="-1" aria-labelledby="aicentral-dialog-title" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-custom">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="aicentral-dialog-title">Edit Feature</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="aicentral-feature-id">

                <!-- Tab Navigation -->
                <ul class="nav nav-tabs feature-config-tabs" id="aicentral-feature-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-general" data-bs-toggle="tab"
                                data-bs-target="#content-general" type="button" role="tab">
                            General Settings
                        </button>
                    </li>
                    <!-- Tier tabs will be dynamically added here -->
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="aicentral-feature-tab-content">
                    <!-- General Settings Tab -->
                    <div class="tab-pane fade show active" id="content-general" role="tabpanel">
                        <div class="row g-3 mt-2">
                            <div class="col-md-6">
                                <label for="aicentral-feature-program" class="form-label">Program ID <span class="text-danger">*</span></label>
                                <select id="aicentral-feature-program" class="form-select" required>
                                    <option value="">Select program...</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="aicentral-feature-code" class="form-label">Feature Code <span class="text-danger">*</span></label>
                                <input type="text" id="aicentral-feature-code" class="form-control"
                                       placeholder="item_analysis" required>
                            </div>

                            <div class="col-12">
                                <label for="aicentral-feature-name" class="form-label">Feature Name <span class="text-danger">*</span></label>
                                <input type="text" id="aicentral-feature-name" class="form-control"
                                       placeholder="Analyze Item with AI" required>
                            </div>

                            <div class="col-12">
                                <label for="aicentral-feature-description" class="form-label">Feature Description</label>
                                <textarea id="aicentral-feature-description" class="form-control" rows="3"
                                          placeholder="Describe what this feature does..."></textarea>
                            </div>

                            <div class="col-md-6">
                                <label for="aicentral-feature-type" class="form-label">Feature Type</label>
                                <select id="aicentral-feature-type" class="form-select">
                                    <option value="">Select type...</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="aicentral-feature-max-input-tokens" class="form-label">
                                    Max Input Tokens <span class="text-danger">*</span>
                                    <i class="bi bi-question-circle text-muted"
                                       data-bs-toggle="tooltip"
                                       data-bs-placement="top"
                                       title="Maximum input context length in tokens"></i>
                                </label>
                                <select id="aicentral-feature-max-input-tokens" class="form-select" required>
                                    <option value="4000">4,000 tokens (4K context)</option>
                                    <option value="8000">8,000 tokens (8K context)</option>
                                    <option value="16000">16,000 tokens (16K context)</option>
                                    <option value="32000">32,000 tokens (32K context)</option>
                                    <option value="64000">64,000 tokens (64K context)</option>
                                    <option value="128000" selected>128,000 tokens (128K context)</option>
                                    <option value="200000">200,000 tokens (200K context - Claude)</option>
                                    <option value="400000">400,000 tokens (400K context - GPT-5)</option>
                                    <option value="1000000">1,000,000 tokens (1M context - Gemini)</option>
                                    <option value="2000000">2,000,000 tokens (2M context - Grok)</option>
                                </select>
                                <small class="form-text text-muted">Applies to ALL tiers</small>
                            </div>

                            <div class="col-12">
                                <hr class="my-3">
                                <h6 class="mb-3">Default Fallback Model</h6>
                                <p class="text-muted small">Used when a tier doesn't have a specific model configured</p>
                            </div>

                            <div class="col-md-3">
                                <label for="provider-general" class="form-label">Default Provider</label>
                                <select id="provider-general" class="form-select"
                                        onchange="aiCentral_updateModelsForProvider('general')">
                                    <option value="">Select provider...</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label for="model-code-general" class="form-label">Default Model</label>
                                <select id="model-code-general" class="form-select">
                                    <option value="">Select provider first</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="aicentral-feature-sort" class="form-label">Sort Order</label>
                                <input type="number" id="aicentral-feature-sort" class="form-control"
                                       value="100" min="0">
                            </div>

                            <div class="col-12">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="aicentral-feature-active" checked>
                                    <label class="form-check-label" for="aicentral-feature-active">
                                        <strong>Active</strong>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tier tabs will be dynamically added here -->
                </div>
            </div>

            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="aiCentral_saveFeature()">
                    <i class="bi bi-check-lg"></i> Save Feature
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/footer.html'; ?>

<script src="/ai/admin/aicentral_features.js"></script>
