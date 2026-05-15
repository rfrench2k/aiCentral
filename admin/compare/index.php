<?php
/**
 * AI Model Comparison Tool - Main UI (Compact Design)
 */
$pageTitle = 'AI Model Comparison';
$additionalCSS = '<link rel="stylesheet" href="/ai/admin/compare/compare.css">';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/header_ai.php';

// Authentication check
if (!in_array($user['user_level'], ['ADMIN', 'SUPERADMIN'])) {
    echo '<div class="alert alert-danger">Admin access required</div>';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/footer.html';
    exit;
}

// URL parameter detection
$usageId = (int)($_GET['usage_id'] ?? 0);
$mode = $usageId > 0 ? 'from_usage' : 'standalone';
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col">
            <h2 class="text-primary mb-1">
                <i class="bi bi-shuffle"></i> AI Model Comparison
            </h2>
        </div>
    </div>

    <!-- TWO-MODE LAYOUT: Setup Panel + Results Area -->
    <div class="row">
        <!-- LEFT: Sliding Setup Panel -->
        <div id="setup-panel" class="col-lg-4 setup-panel-expanded">
            <!-- Original Request - COMPACT -->
            <div id="original-request-section" style="display: none;">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white py-2">
                        <h6 class="mb-0 d-inline-block">
                            <i class="bi bi-file-text"></i> Original Request
                        </h6>
                        <button class="btn btn-sm btn-outline-secondary float-end py-0" id="btn-edit-prompt" onclick="compare_editPrompt()">
                            <i class="bi bi-pencil"></i> Edit
                        </button>
                    </div>
                    <div class="card-body p-2">
                        <div class="row small mb-2">
                            <div class="col-6">
                                <strong>Model:</strong> <span id="original-model" class="text-primary">-</span>
                            </div>
                            <div class="col-6">
                                <strong>Provider:</strong> <span id="original-provider">-</span>
                            </div>
                        </div>
                        <div class="row small mb-2">
                            <div class="col-3">
                                <strong>Tokens:</strong><br><span id="original-tokens">-</span>
                            </div>
                            <div class="col-3">
                                <strong>Cost:</strong><br><span id="original-cost" class="text-success">-</span>
                            </div>
                            <div class="col-3">
                                <strong>Time:</strong><br><span id="original-response-time">-</span>
                            </div>
                            <div class="col-3">
                                <strong>Tools:</strong><br><span id="original-tool-calls">-</span>
                            </div>
                        </div>
                        <!-- Tabbed Prompt/Response Display -->
                        <ul class="nav nav-tabs nav-tabs-sm mt-2" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active small" data-bs-toggle="tab" data-bs-target="#original-prompt-tab" type="button">
                                    Prompt
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link small" data-bs-toggle="tab" data-bs-target="#original-response-tab" type="button">
                                    Response
                                </button>
                            </li>
                        </ul>
                        <div class="tab-content border border-top-0 p-2">
                            <div class="tab-pane fade show active" id="original-prompt-tab">
                                <pre id="original-prompt-preview" class="compact-pre small mb-2">-</pre>
                                <button id="original-prompt-expand-btn" class="btn btn-sm btn-outline-secondary" onclick="compare_togglePromptExpand()" style="display: none;">
                                    <i class="bi bi-chevron-down"></i> Show Full Prompt
                                </button>
                                <pre id="original-prompt-full" class="compact-pre small" style="display: none;">-</pre>
                            </div>
                            <div class="tab-pane fade" id="original-response-tab">
                                <pre id="original-response" class="compact-pre small">-</pre>
                            </div>
                        </div>
                        <div id="prompt-edited-warning" class="alert alert-warning py-1 px-2 small mt-2" style="display: none;">
                            <i class="bi bi-exclamation-triangle"></i> Prompt edited - re-run all models
                        </div>
                    </div>
                </div>

                <!-- Edit Original Request Parameters (shown when Edit is clicked) -->
                <div id="edit-original-params" style="display: none;">
                    <!-- Tabbed Prompt Interface -->
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header bg-white py-2">
                            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                                <li class="nav-item">
                                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-edit-prompt" type="button">
                                        <i class="bi bi-chat-left-text"></i> Prompt
                                    </button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-edit-system" type="button">
                                        <i class="bi bi-gear"></i> System Prompt
                                    </button>
                                </li>
                            </ul>
                        </div>
                        <div class="card-body p-2">
                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="tab-edit-prompt">
                                    <textarea id="edit-prompt" class="form-control form-control-sm" rows="4"></textarea>
                                </div>
                                <div class="tab-pane fade" id="tab-edit-system">
                                    <textarea id="edit-system-prompt" class="form-control form-control-sm" rows="4" placeholder="Optional system instructions..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Parameters -->
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header bg-white py-2">
                            <h6 class="mb-0"><i class="bi bi-sliders"></i> Parameters</h6>
                        </div>
                        <div class="card-body p-2">
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label form-label-sm mb-1">Temp</label>
                                    <input type="number" id="edit-temperature" class="form-control form-control-sm" min="0" max="2" step="0.1" value="0.7">
                                </div>
                                <div class="col-6">
                                    <label class="form-label form-label-sm mb-1">Max Tokens</label>
                                    <input type="number" id="edit-max-tokens" class="form-control form-control-sm" min="100" max="32000" step="100" value="4096">
                                </div>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit-web-search">
                                <label class="form-check-label" for="edit-web-search">
                                    Web Search (max: <input type="number" id="edit-web-search-max" class="form-control form-control-sm d-inline-block" style="width: 50px;" min="1" max="20" value="5" disabled>)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Prompt Input Section (standalone mode) -->
            <div id="prompt-input-section" style="display: none;">
                <!-- Tabbed Prompt Interface -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white py-2">
                        <ul class="nav nav-tabs card-header-tabs" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-prompt" type="button">
                                    <i class="bi bi-chat-left-text"></i> Prompt
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-system" type="button">
                                    <i class="bi bi-gear"></i> System Prompt
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body p-2">
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="tab-prompt">
                                <textarea id="standalone-prompt" class="form-control form-control-sm" rows="4" placeholder="Enter your prompt here..."></textarea>
                            </div>
                            <div class="tab-pane fade" id="tab-system">
                                <textarea id="param-system-prompt" class="form-control form-control-sm" rows="4" placeholder="Optional system instructions (rarely needed)..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Compact Parameters -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white py-2">
                        <h6 class="mb-0"><i class="bi bi-sliders"></i> Parameters</h6>
                    </div>
                    <div class="card-body p-2">
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label form-label-sm mb-1">Temp</label>
                                <input type="number" id="param-temperature" class="form-control form-control-sm" min="0" max="2" step="0.1" value="0.7">
                            </div>
                            <div class="col-6">
                                <label class="form-label form-label-sm mb-1">Max Tokens</label>
                                <input type="number" id="param-max-tokens" class="form-control form-control-sm" min="100" max="32000" step="100" value="4096">
                            </div>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="cap-web-search">
                            <label class="form-check-label" for="cap-web-search">
                                Web Search (max: <input type="number" id="cap-web-search-max" class="form-control form-control-sm d-inline-block" style="width: 50px;" min="1" max="20" value="5" disabled>)
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Model Selection - COMPACT WITH INLINE COSTS -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white py-2">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <h6 class="mb-0">
                                <i class="bi bi-cpu"></i> Select Models (1-4)
                                <span id="selected-count" class="badge bg-primary ms-2">0</span>
                            </h6>
                        </div>
                        <div class="col text-end">
                            <span class="me-3" id="total-cost-display" style="display: none;">
                                <strong>Total:</strong>
                                <span id="estimated-cost-header" class="text-success fw-bold">$0.00</span>
                                <span id="cost-warning-header" class="text-danger small ms-2" style="display: none;">
                                    <i class="bi bi-exclamation-triangle"></i>
                                </span>
                            </span>
                            <button id="btn-run-comparison" class="btn btn-primary btn-sm" onclick="compare_runComparison()" disabled>
                                <i class="bi bi-play-circle"></i> Run
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-2">
                    <div id="model-selection-area" class="compact-model-grid">
                        <div class="text-center py-2">
                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                            <p class="text-muted small mb-0 mt-1">Loading...</p>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Panel Collapse Button -->
            <div class="text-center mt-2">
                <button id="collapse-panel-btn" class="btn btn-sm btn-outline-secondary" onclick="compare_togglePanel('collapse')">
                    <i class="bi bi-chevron-left"></i> Hide Panel
                </button>
            </div>
        </div>

        <!-- Collapsed Panel Strip (hidden initially) -->
        <div id="panel-strip" class="panel-strip d-none">
            <button id="expand-panel-btn" class="btn btn-sm" onclick="compare_togglePanel('expand')">
                <i class="bi bi-chevron-right"></i>
                <div class="vertical-text mt-2">EXPAND</div>
            </button>
        </div>

        <!-- RIGHT: Results Area -->
        <div id="results-area" class="col-lg-8 results-area-normal">
            <!-- Three-Tab Prompt Display (shown when results are displayed) -->
            <div id="prompt-tabs-section" style="display: none;">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white py-2">
                        <h6 class="mb-0"><i class="bi bi-chat-dots"></i> Prompt Information</h6>
                    </div>
                    <div class="card-body p-2">
                        <ul class="nav nav-tabs" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active" id="tab-user-input-btn" data-bs-toggle="tab" data-bs-target="#tab-user-input" type="button">
                                    User Input
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" id="tab-app-context-btn" data-bs-toggle="tab" data-bs-target="#tab-app-context" type="button">
                                    Application Context
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" id="tab-api-request-btn" data-bs-toggle="tab" data-bs-target="#tab-api-request" type="button">
                                    Final API Request
                                </button>
                            </li>
                        </ul>
                        <div class="tab-content border border-top-0 p-3">
                            <div class="tab-pane fade show active" id="tab-user-input">
                                <pre class="mb-0 prompt-display" id="display-user-input">-</pre>
                            </div>
                            <div class="tab-pane fade" id="tab-app-context">
                                <pre class="mb-0 prompt-display" id="display-app-context">No application context available</pre>
                            </div>
                            <div class="tab-pane fade" id="tab-api-request">
                                <pre class="mb-0 prompt-display"><code class="language-json" id="display-api-request">{}</code></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Progress -->
            <div id="progress-section" style="display: none;">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-4">
                        <div class="spinner-border text-primary mb-2" role="status"></div>
                        <h6 id="progress-message">Running comparison...</h6>
                        <p class="text-muted small mb-2" id="progress-details">Please wait</p>
                        <div class="progress" style="height: 20px;">
                            <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%">0%</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Results -->
            <div id="results-section" style="display: none;">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-2">
                        <h6 class="mb-0 d-inline-block">
                            <i class="bi bi-bar-chart"></i> Results
                        </h6>
                        <div class="btn-group btn-group-sm float-end" role="group">
                            <button type="button" class="btn btn-outline-primary active" id="btn-horizontal-layout" onclick="compare_setLayout('horizontal')">
                                <i class="bi bi-layout-split"></i> Side-by-Side
                            </button>
                            <button type="button" class="btn btn-outline-primary" id="btn-vertical-layout" onclick="compare_setLayout('vertical')">
                                <i class="bi bi-layout-three-columns"></i> Stacked
                            </button>
                        </div>
                    </div>

                    <!-- Vertical Mode Controls -->
                    <div class="card-body py-2" id="vertical-controls" style="display: none;">
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Show Records:</label>
                                <select class="form-select form-select-sm" id="record-count-selector" onchange="compare_updateRecordCount()">
                                    <option value="3">3</option>
                                    <option value="5">5</option>
                                    <option value="10" selected>10</option>
                                    <option value="15">15</option>
                                    <option value="25">25</option>
                                    <option value="all">All</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Horizontal Results -->
                    <div id="horizontal-results-container" class="card-body p-2">
                        <div class="row g-2" id="horizontal-results"></div>
                    </div>

                    <!-- Vertical Results -->
                    <div id="vertical-results-container" class="card-body p-2" style="display: none;">
                        <div id="vertical-results"></div>
                    </div>
                </div>
            </div>

            <!-- Placeholder when no results -->
            <div id="results-placeholder" class="card border-0 shadow-sm bg-light">
                <div class="card-body text-center py-5">
                    <i class="bi bi-shuffle text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3">Select models and click "Run Comparison" to see results</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/ai/admin/compare/compare.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const usageId = <?php echo $usageId; ?>;
        compare_init(usageId);
    });
</script>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/footer.html'; ?>
