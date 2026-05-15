<?php
/**
 * AI Central Admin Tiers - HTML (Bootstrap 5.3.3)
 */
$pageTitle = 'AI Central - Tiers Management';


require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/header_ai.php';
?>

<link rel="stylesheet" href="/ai/admin/aicentral_tiers.css">

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h2 mb-0">
                    <i class="bi bi-layers text-primary"></i> Tiers Management
                </h1>
                <button class="btn btn-primary" onclick="aiCentral_showAddTierDialog()">
                    <i class="bi bi-plus-circle"></i> Add Tier
                </button>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <select id="aicentral-filter-status" class="form-select" onchange="aiCentral_filterTiers()">
                                <option value="">All Status</option>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <input type="text" id="aicentral-filter-search" class="form-control"
                                   placeholder="Search tiers by name or code..." onkeyup="aiCentral_filterTiers()">
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

    <!-- Tiers List -->
    <div class="row" id="aicentral-tiers-list">
        <div class="col-12 text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading tiers...</span>
            </div>
            <p class="mt-2 text-muted">Loading tiers...</p>
        </div>
    </div>
</div>

<!-- Add/Edit Tier Modal -->
<div class="modal fade" id="aicentral-tier-modal" tabindex="-1" aria-labelledby="aicentral-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title" id="aicentral-modal-title">
                    <i class="bi bi-layers"></i> Add Tier
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="aicentral-tier-id">

                <!-- Basic Information -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="aicentral-tier-code" class="form-label fw-semibold">
                            Tier Code <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="aicentral-tier-code" class="form-control"
                               placeholder="free" required>
                        <div class="form-text">Unique identifier (lowercase, no spaces)</div>
                    </div>

                    <div class="col-md-6">
                        <label for="aicentral-tier-name" class="form-label fw-semibold">
                            Tier Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="aicentral-tier-name" class="form-control"
                               placeholder="Free Tier" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="aicentral-tier-description" class="form-label fw-semibold">
                        Tier Description
                    </label>
                    <textarea id="aicentral-tier-description" class="form-control" rows="2"
                              placeholder="Brief description of this tier..."></textarea>
                </div>

                <!-- Request Limits -->
                <div class="mb-4">
                    <h6 class="text-primary fw-bold border-bottom pb-2 mb-3">
                        <i class="bi bi-speedometer2"></i> REQUEST LIMITS
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="aicentral-tier-daily-requests" class="form-label fw-semibold">
                                Daily Request Limit
                            </label>
                            <input type="number" id="aicentral-tier-daily-requests" class="form-control"
                                   placeholder="100" min="0">
                            <div class="form-text">Leave empty for unlimited</div>
                        </div>

                        <div class="col-md-6">
                            <label for="aicentral-tier-monthly-requests" class="form-label fw-semibold">
                                Monthly Request Limit
                            </label>
                            <input type="number" id="aicentral-tier-monthly-requests" class="form-control"
                                   placeholder="2000" min="0">
                            <div class="form-text">Leave empty for unlimited</div>
                        </div>
                    </div>
                </div>

                <!-- Token Limits -->
                <div class="mb-4">
                    <h6 class="text-primary fw-bold border-bottom pb-2 mb-3">
                        <i class="bi bi-cpu"></i> TOKEN LIMITS
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="aicentral-tier-daily-tokens" class="form-label fw-semibold">
                                Daily Token Limit
                            </label>
                            <input type="number" id="aicentral-tier-daily-tokens" class="form-control"
                                   placeholder="500000" min="0">
                            <div class="form-text">Total tokens per day</div>
                        </div>

                        <div class="col-md-6">
                            <label for="aicentral-tier-monthly-tokens" class="form-label fw-semibold">
                                Monthly Token Limit
                            </label>
                            <input type="number" id="aicentral-tier-monthly-tokens" class="form-control"
                                   placeholder="10000000" min="0">
                            <div class="form-text">Total tokens per month</div>
                        </div>
                    </div>
                </div>

                <!-- Spend Limits -->
                <div class="mb-4">
                    <h6 class="text-primary fw-bold border-bottom pb-2 mb-3">
                        <i class="bi bi-cash-coin"></i> SPEND LIMITS
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="aicentral-tier-spend-limit" class="form-label fw-semibold">
                                Monthly Spend Limit (USD)
                            </label>
                            <input type="number" id="aicentral-tier-spend-limit" class="form-control"
                                   step="0.01" min="0" placeholder="5.00">
                            <div class="form-text">Maximum monthly cost in dollars</div>
                        </div>
                    </div>
                </div>

                <!-- AI Tool Capabilities -->
                <div class="mb-4">
                    <h6 class="text-primary fw-bold border-bottom pb-2 mb-3">
                        <i class="bi bi-tools"></i> AI TOOL CAPABILITIES
                    </h6>
                    <p class="text-muted small">Configure which advanced AI capabilities are available for this tier.</p>

                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Capability</th>
                                    <th class="text-center">Enabled</th>
                                    <th>Max Uses Per Request</th>
                                    <th>Cost Info</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <strong>Web Search</strong>
                                        <i class="bi bi-question-circle text-muted"
                                           data-bs-toggle="tooltip"
                                           title="Allow AI to search the web for current information"></i>
                                    </td>
                                    <td class="text-center">
                                        <div class="form-check d-inline-block">
                                            <input type="checkbox" class="form-check-input" id="aicentral-tier-cap-websearch">
                                        </div>
                                    </td>
                                    <td>
                                        <input type="number" id="aicentral-tier-cap-websearch-max"
                                               class="form-control form-control-sm"
                                               placeholder="Unlimited" min="1" max="1000" disabled>
                                        <small class="form-text text-muted">Leave blank for unlimited</small>
                                    </td>
                                    <td><small class="text-muted">$0.01/search (Claude/OpenAI), Free (Gemini)</small></td>
                                </tr>
                                <tr>
                                    <td>
                                        <strong>Web Fetch</strong>
                                        <i class="bi bi-question-circle text-muted"
                                           data-bs-toggle="tooltip"
                                           title="Allow AI to fetch and read specific URLs"></i>
                                    </td>
                                    <td class="text-center">
                                        <div class="form-check d-inline-block">
                                            <input type="checkbox" class="form-check-input" id="aicentral-tier-cap-webfetch">
                                        </div>
                                    </td>
                                    <td>
                                        <input type="number" id="aicentral-tier-cap-webfetch-max"
                                               class="form-control form-control-sm"
                                               placeholder="Unlimited" min="1" max="1000" disabled>
                                        <small class="form-text text-muted">Leave blank for unlimited</small>
                                    </td>
                                    <td><small class="text-muted">Free (Claude)</small></td>
                                </tr>
                                <tr>
                                    <td>
                                        <strong>Vision</strong>
                                        <i class="bi bi-question-circle text-muted"
                                           data-bs-toggle="tooltip"
                                           title="Allow AI to analyze images and visual content"></i>
                                    </td>
                                    <td class="text-center">
                                        <div class="form-check d-inline-block">
                                            <input type="checkbox" class="form-check-input" id="aicentral-tier-cap-vision">
                                        </div>
                                    </td>
                                    <td>
                                        <input type="number" id="aicentral-tier-cap-vision-max"
                                               class="form-control form-control-sm"
                                               placeholder="Unlimited" min="1" max="1000" disabled>
                                        <small class="form-text text-muted">Leave blank for unlimited</small>
                                    </td>
                                    <td><small class="text-muted">Included in token costs</small></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div id="aicentral-tier-cap-warning" class="alert alert-warning d-none mt-3" role="alert">
                        <i class="bi bi-exclamation-triangle"></i>
                        <span id="aicentral-tier-cap-warning-text"></span>
                    </div>
                </div>

                <!-- Settings -->
                <div class="mb-3">
                    <h6 class="text-primary fw-bold border-bottom pb-2 mb-3">
                        <i class="bi bi-gear"></i> SETTINGS
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="aicentral-tier-sort" class="form-label fw-semibold">
                                Sort Order
                            </label>
                            <input type="number" id="aicentral-tier-sort" class="form-control"
                                   value="100" min="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold d-block">Status</label>
                            <div class="form-check form-switch">
                                <input type="checkbox" id="aicentral-tier-active" class="form-check-input" role="switch" checked>
                                <label class="form-check-label" for="aicentral-tier-active">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="aiCentral_saveTier()">
                    <i class="bi bi-check-circle"></i> Save Tier
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/footer.html'; ?>

<script src="/ai/admin/aicentral_tiers.js"></script>
