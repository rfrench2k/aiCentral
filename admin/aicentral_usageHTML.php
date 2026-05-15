<?php
/**
 * AI Central Admin - Usage Analysis
 * Comprehensive usage tracking and analysis
 */
$pageTitle = 'AI Central - Usage Analysis';

$additionalCSS = '<link rel="stylesheet" href="/ai/admin/aicentral_usage.css">';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/header_ai.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/aPRIV_DB_AI.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/common_ai.php';

$programOptions = [];
$conn = ai_getDBConnection();
if ($conn) {
    $result = $conn->query("SELECT program_id, program_name FROM programs WHERE is_active = 1 ORDER BY program_name");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $programOptions[] = $row;
        }
    }
}
?>

<div class="row mb-4">
    <div class="col">
        <h1 class="text-primary">
            <i class="bi bi-bar-chart-line"></i> Usage Analysis
        </h1>
        <p class="text-muted">Detailed AI usage tracking and analytics</p>
    </div>
    <div class="col-auto">
        <button class="btn btn-success" onclick="aiUsage_exportData()">
            <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
        </button>
        <button class="btn btn-primary" onclick="aiUsage_refreshData()">
            <i class="bi bi-arrow-clockwise"></i> Refresh
        </button>
    </div>
</div>

<!-- Filters Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0">
            <i class="bi bi-funnel"></i> Filters
        </h5>
    </div>
    <div class="card-body">
        <!-- Primary Filters Row -->
        <div class="row g-3 mb-3">
            <!-- User -->
            <div class="col-md-2">
                <label class="form-label small fw-bold">User</label>
                <select class="form-select form-select-sm" id="filter-user">
                    <option value="">All Users</option>
                </select>
            </div>

            <!-- Program -->
            <div class="col-md-2">
                <label class="form-label small fw-bold">Program</label>
                <select class="form-select form-select-sm" id="filter-program">
                    <option value="">All Programs</option>
                    <?php foreach ($programOptions as $prog): ?>
                        <option value="<?= htmlspecialchars($prog['program_id']) ?>"><?= htmlspecialchars($prog['program_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Date Range -->
            <div class="col-md-2">
                <label class="form-label small fw-bold">Date Range</label>
                <select class="form-select form-select-sm" id="filter-daterange">
                    <option value="today">Today</option>
                    <option value="yesterday">Yesterday</option>
                    <option value="last7days" selected>Last 7 Days</option>
                    <option value="last30days">Last 30 Days</option>
                    <option value="thismonth">This Month</option>
                    <option value="lastmonth">Last Month</option>
                    <option value="custom">Custom Range</option>
                </select>
            </div>

            <!-- Custom Date Range (hidden by default) -->
            <div class="col-md-2" id="custom-date-range" style="display: none;">
                <label class="form-label small fw-bold">From</label>
                <input type="date" class="form-control form-control-sm" id="filter-date-from">
            </div>
            <div class="col-md-2" id="custom-date-range-to" style="display: none;">
                <label class="form-label small fw-bold">To</label>
                <input type="date" class="form-control form-control-sm" id="filter-date-to">
            </div>

            <!-- Feature -->
            <div class="col-md-3">
                <label class="form-label small fw-bold">Feature</label>
                <select class="form-select form-select-sm" id="filter-feature">
                    <option value="">All Features</option>
                </select>
            </div>

            <!-- Action Buttons -->
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button class="btn btn-primary btn-sm" onclick="aiUsage_applyFilters()" title="Search">
                    <i class="bi bi-search"></i>
                </button>
                <button class="btn btn-sm btn-outline-secondary" onclick="aiUsage_resetFilters()" title="Reset Filters">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </button>
                <button class="btn btn-sm btn-outline-info" onclick="aiUsage_toggleAdvancedFilters()" id="btn-toggle-filters">
                    <i class="bi bi-chevron-down"></i> More Filters
                </button>
            </div>
        </div>

        <!-- Advanced Filters (Hidden by Default) -->
        <div id="advanced-filters" style="display: none;">
            <hr class="my-3">
            <div class="row g-3">
                <!-- Provider -->
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Provider</label>
                    <select class="form-select form-select-sm" id="filter-provider">
                        <option value="">All Providers</option>
                    </select>
                </div>

                <!-- Model -->
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Model</label>
                    <select class="form-select form-select-sm" id="filter-model">
                        <option value="">All Models</option>
                    </select>
                </div>

                <!-- Status -->
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Status</label>
                    <select class="form-select form-select-sm" id="filter-status">
                        <option value="">All</option>
                        <option value="success" selected>Success</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>

                <!-- Cost Range -->
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Cost Range (USD)</label>
                    <select class="form-select form-select-sm" id="filter-cost">
                        <option value="">Any Cost</option>
                        <option value="0-0.01">$0.00 - $0.01</option>
                        <option value="0.01-0.10">$0.01 - $0.10</option>
                        <option value="0.10-1.00">$0.10 - $1.00</option>
                        <option value="1.00-10.00">$1.00 - $10.00</option>
                        <option value="10.00-999">$10.00+</option>
                    </select>
                </div>

                <!-- Token Range -->
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Token Range</label>
                    <select class="form-select form-select-sm" id="filter-tokens">
                        <option value="">Any Tokens</option>
                        <option value="0-1000">0 - 1K</option>
                        <option value="1000-10000">1K - 10K</option>
                        <option value="10000-100000">10K - 100K</option>
                        <option value="100000-999999999">100K+</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs Navigation -->
<ul class="nav nav-tabs mb-4" id="usage-tabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview-tab-pane" type="button" role="tab" aria-controls="overview-tab-pane" aria-selected="true">
            <i class="bi bi-graph-up"></i> Overview
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="by-feature-tab" data-bs-toggle="tab" data-bs-target="#by-feature-tab-pane" type="button" role="tab" aria-controls="by-feature-tab-pane" aria-selected="false">
            <i class="bi bi-grid"></i> By Feature
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="by-user-tab" data-bs-toggle="tab" data-bs-target="#by-user-tab-pane" type="button" role="tab" aria-controls="by-user-tab-pane" aria-selected="false">
            <i class="bi bi-people"></i> By User
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="by-model-tab" data-bs-toggle="tab" data-bs-target="#by-model-tab-pane" type="button" role="tab" aria-controls="by-model-tab-pane" aria-selected="false">
            <i class="bi bi-cpu"></i> By Model
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="by-program-tab" data-bs-toggle="tab" data-bs-target="#by-program-tab-pane" type="button" role="tab" aria-controls="by-program-tab-pane" aria-selected="false">
            <i class="bi bi-app"></i> By Program
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="outliers-tab" data-bs-toggle="tab" data-bs-target="#outliers-tab-pane" type="button" role="tab" aria-controls="outliers-tab-pane" aria-selected="false">
            <i class="bi bi-exclamation-triangle"></i> Outliers
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="trends-tab" data-bs-toggle="tab" data-bs-target="#trends-tab-pane" type="button" role="tab" aria-controls="trends-tab-pane" aria-selected="false">
            <i class="bi bi-graph-up-arrow"></i> Trends
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="details-tab" data-bs-toggle="tab" data-bs-target="#details-tab-pane" type="button" role="tab" aria-controls="details-tab-pane" aria-selected="false">
            <i class="bi bi-table"></i> Usage Details
        </button>
    </li>
</ul>

<!-- Tab Content -->
<div class="tab-content" id="usage-tab-content">
    <!-- Overview Tab -->
    <div class="tab-pane fade show active" id="overview-tab-pane" role="tabpanel" aria-labelledby="overview-tab" tabindex="0">
        <!-- Summary Stats -->
        <div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-primary text-white rounded-3 p-3">
                            <i class="bi bi-bar-chart-fill fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted small mb-1">Total Requests</h6>
                        <h3 class="mb-0" id="summary-total-requests">---</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-success text-white rounded-3 p-3">
                            <i class="bi bi-cash-coin fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted small mb-1">Total Cost</h6>
                        <h3 class="mb-0" id="summary-total-cost">---</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-info text-white rounded-3 p-3">
                            <i class="bi bi-lightning-fill fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted small mb-1">Total Tokens</h6>
                        <h3 class="mb-0" id="summary-total-tokens">---</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-warning text-dark rounded-3 p-3">
                            <i class="bi bi-clock-fill fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted small mb-1">Avg Response</h6>
                        <h3 class="mb-0" id="summary-avg-response">---</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-graph-up"></i> Usage Over Time</h5>
            </div>
            <div class="card-body">
                <canvas id="chart-usage-overtime" style="max-height: 250px;"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-pie-chart-fill"></i> Cost by Provider</h5>
            </div>
            <div class="card-body">
                <canvas id="chart-cost-provider" style="max-height: 250px;"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-person-fill"></i> Top Users</h5>
            </div>
            <div class="card-body">
                <canvas id="chart-top-users" style="max-height: 250px;"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-app-indicator"></i> Top Programs</h5>
            </div>
            <div class="card-body">
                <canvas id="chart-top-programs" style="max-height: 250px;"></canvas>
            </div>
        </div>
    </div>
</div>
    </div>
    <!-- End Overview Tab -->

    <!-- Usage Details Tab -->
    <div class="tab-pane fade" id="details-tab-pane" role="tabpanel" aria-labelledby="details-tab" tabindex="0">
        <!-- Usage Data Table -->
        <div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0">
            <i class="bi bi-table"></i> Usage Details
            <span class="badge bg-primary ms-2" id="usage-count">0</span>
        </h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="usage-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 140px;">Time</th>
                        <th>User</th>
                        <th>Program</th>
                        <th>Feature</th>
                        <th>Provider</th>
                        <th>Model</th>
                        <th class="text-end">Tokens</th>
                        <th class="text-end">Tools</th>
                        <th class="text-end">Cost</th>
                        <th class="text-end">Response</th>
                        <th>Status</th>
                        <th style="width: 100px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="usage-table-body">
                    <tr>
                        <td colspan="11" class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <span class="text-muted small">Showing <span id="showing-count">0</span> results</span>
            </div>
            <div>
                <nav aria-label="Usage pagination">
                    <ul class="pagination pagination-sm mb-0" id="pagination">
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>
    </div>
    <!-- End Usage Details Tab -->

    <!-- By Feature Tab -->
    <div class="tab-pane fade" id="by-feature-tab-pane" role="tabpanel" aria-labelledby="by-feature-tab" tabindex="0">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">
                    <i class="bi bi-grid"></i> Performance by Feature
                    <span class="badge bg-primary ms-2" id="by-feature-count">0</span>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="by-feature-table">
                        <thead class="table-light">
                            <tr>
                                <th>Feature</th>
                                <th>Program</th>
                                <th class="text-end">Total Requests</th>
                                <th class="text-end">Avg Tokens</th>
                                <th class="text-end">Avg Cost</th>
                                <th class="text-end">Avg Response</th>
                                <th class="text-end">Total Cost</th>
                            </tr>
                        </thead>
                        <tbody id="by-feature-table-body">
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!-- End By Feature Tab -->

    <!-- By User Tab -->
    <div class="tab-pane fade" id="by-user-tab-pane" role="tabpanel" aria-labelledby="by-user-tab" tabindex="0">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">
                    <i class="bi bi-people"></i> Performance by User
                    <span class="badge bg-primary ms-2" id="by-user-count">0</span>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="by-user-table">
                        <thead class="table-light">
                            <tr>
                                <th>User</th>
                                <th class="text-end">Total Requests</th>
                                <th class="text-end">Avg Tokens</th>
                                <th class="text-end">Avg Cost</th>
                                <th class="text-end">Avg Response</th>
                                <th class="text-end">Total Cost</th>
                                <th class="text-end">Total Tokens</th>
                            </tr>
                        </thead>
                        <tbody id="by-user-table-body">
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!-- End By User Tab -->

    <!-- By Model Tab -->
    <div class="tab-pane fade" id="by-model-tab-pane" role="tabpanel" aria-labelledby="by-model-tab" tabindex="0">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">
                    <i class="bi bi-cpu"></i> Performance by Model
                    <span class="badge bg-primary ms-2" id="by-model-count">0</span>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="by-model-table">
                        <thead class="table-light">
                            <tr>
                                <th>Model</th>
                                <th>Provider</th>
                                <th class="text-end">Total Requests</th>
                                <th class="text-end">Avg Tokens</th>
                                <th class="text-end">Avg Cost</th>
                                <th class="text-end">Avg Response</th>
                                <th class="text-end">Total Cost</th>
                            </tr>
                        </thead>
                        <tbody id="by-model-table-body">
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!-- End By Model Tab -->

    <!-- By Program Tab -->
    <div class="tab-pane fade" id="by-program-tab-pane" role="tabpanel" aria-labelledby="by-program-tab" tabindex="0">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">
                    <i class="bi bi-app"></i> Performance by Program
                    <span class="badge bg-primary ms-2" id="by-program-count">0</span>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="by-program-table">
                        <thead class="table-light">
                            <tr>
                                <th>Program</th>
                                <th class="text-end">Total Requests</th>
                                <th class="text-end">Avg Tokens</th>
                                <th class="text-end">Avg Cost</th>
                                <th class="text-end">Avg Response</th>
                                <th class="text-end">Total Cost</th>
                                <th class="text-end">Total Tokens</th>
                            </tr>
                        </thead>
                        <tbody id="by-program-table-body">
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!-- End By Program Tab -->

    <!-- Outliers Tab -->
    <div class="tab-pane fade" id="outliers-tab-pane" role="tabpanel" aria-labelledby="outliers-tab" tabindex="0">
        <div class="row g-4">
            <!-- High Token Users -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="bi bi-lightning-fill"></i> Highest Avg Tokens (Users)</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>User</th>
                                        <th class="text-end">Avg Tokens</th>
                                        <th class="text-end">Requests</th>
                                    </tr>
                                </thead>
                                <tbody id="outliers-high-tokens-users"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- High Cost Users -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="bi bi-cash-coin"></i> Highest Avg Cost (Users)</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>User</th>
                                        <th class="text-end">Avg Cost</th>
                                        <th class="text-end">Requests</th>
                                    </tr>
                                </thead>
                                <tbody id="outliers-high-cost-users"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Slow Response Users -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="bi bi-clock-fill"></i> Slowest Avg Response (Users)</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>User</th>
                                        <th class="text-end">Avg Response</th>
                                        <th class="text-end">Requests</th>
                                    </tr>
                                </thead>
                                <tbody id="outliers-slow-users"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- High Token Requests -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="bi bi-lightning"></i> Highest Token Requests</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>User</th>
                                        <th>Feature</th>
                                        <th class="text-end">Tokens</th>
                                    </tr>
                                </thead>
                                <tbody id="outliers-high-token-requests"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Most Expensive Requests -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="bi bi-currency-dollar"></i> Most Expensive Requests</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>User</th>
                                        <th>Feature</th>
                                        <th class="text-end">Cost</th>
                                    </tr>
                                </thead>
                                <tbody id="outliers-expensive-requests"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Slowest Requests -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="bi bi-hourglass-split"></i> Slowest Requests</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>User</th>
                                        <th>Feature</th>
                                        <th class="text-end">Response Time</th>
                                    </tr>
                                </thead>
                                <tbody id="outliers-slow-requests"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- End Outliers Tab -->

    <!-- Trends Tab -->
    <div class="tab-pane fade" id="trends-tab-pane" role="tabpanel" aria-labelledby="trends-tab" tabindex="0">
        <div class="row g-4">
            <!-- Average Tokens Trend -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="bi bi-graph-up"></i> Average Tokens Trend</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="chart-trend-tokens" style="max-height: 250px;"></canvas>
                    </div>
                </div>
            </div>

            <!-- Average Cost Trend -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="bi bi-graph-up"></i> Average Cost Trend</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="chart-trend-cost" style="max-height: 250px;"></canvas>
                    </div>
                </div>
            </div>

            <!-- Average Response Time Trend -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="bi bi-graph-up"></i> Average Response Time Trend</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="chart-trend-response" style="max-height: 250px;"></canvas>
                    </div>
                </div>
            </div>

            <!-- Request Volume Trend -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="bi bi-graph-up"></i> Request Volume Trend</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="chart-trend-volume" style="max-height: 250px;"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- End Trends Tab -->

</div>
<!-- End Tab Content -->

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-info-circle"></i> Request Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="text-muted">Request Information</h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="text-muted" style="width: 150px;">Request ID:</td>
                                <td id="detail-usage-id" class="fw-bold">-</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Timestamp:</td>
                                <td id="detail-timestamp">-</td>
                            </tr>
                            <tr>
                                <td class="text-muted">User:</td>
                                <td id="detail-user">-</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Program:</td>
                                <td id="detail-program">-</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Feature:</td>
                                <td id="detail-feature">-</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Page URL:</td>
                                <td id="detail-page-url" class="small">-</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted">Model & Performance</h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="text-muted" style="width: 150px;">Provider:</td>
                                <td id="detail-provider">-</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Model:</td>
                                <td id="detail-model">-</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Input Tokens:</td>
                                <td id="detail-input-tokens">-</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Output Tokens:</td>
                                <td id="detail-output-tokens">-</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Total Tokens:</td>
                                <td id="detail-total-tokens" class="fw-bold">-</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Response Time:</td>
                                <td id="detail-response-time">-</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="text-muted">Cost Breakdown</h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="text-muted" style="width: 150px;">Input Cost:</td>
                                <td id="detail-input-cost">-</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Output Cost:</td>
                                <td id="detail-output-cost">-</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Tool Calls:</td>
                                <td id="detail-tool-calls">-</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Tool Cost:</td>
                                <td id="detail-tool-cost">-</td>
                            </tr>
                            <tr>
                                <td class="text-muted fw-bold">Total Cost:</td>
                                <td id="detail-total-cost" class="fw-bold text-success">-</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted">Status & Key</h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="text-muted" style="width: 150px;">Status:</td>
                                <td id="detail-status">-</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Key Type:</td>
                                <td id="detail-key-type">-</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Run ID:</td>
                                <td id="detail-run-id" class="small">-</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="mb-3" id="detail-error-section" style="display: none;">
                    <h6 class="text-danger">Error Message</h6>
                    <div class="alert alert-danger" id="detail-error-message"></div>
                </div>

                <div class="mb-3">
                    <h6 class="text-muted">
                        Raw API Request (Full Prompt to AI)
                        <button class="btn btn-sm btn-outline-secondary float-end" onclick="aiUsage_copyToClipboard('detail-prompt-to-ai')">
                            <i class="bi bi-clipboard"></i> Copy
                        </button>
                    </h6>
                    <pre class="bg-light p-3 rounded" id="detail-prompt-to-ai" style="max-height: 300px; overflow-y: auto; white-space: pre-wrap; font-size: 0.85em;">-</pre>
                </div>

                <div class="mb-3">
                    <h6 class="text-success">
                        Complete AI Response (Raw)
                        <button class="btn btn-sm btn-outline-secondary float-end" onclick="aiUsage_copyToClipboard('detail-complete-response')">
                            <i class="bi bi-clipboard"></i> Copy
                        </button>
                    </h6>
                    <pre class="bg-light p-3 rounded" id="detail-complete-response" style="max-height: 400px; overflow-y: auto; white-space: pre-wrap; font-size: 0.85em;">-</pre>
                </div>

                <div class="mb-3">
                    <h6 class="text-muted">
                        Prompt / Input
                        <button class="btn btn-sm btn-outline-secondary float-end" onclick="aiUsage_copyToClipboard('detail-prompt')">
                            <i class="bi bi-clipboard"></i> Copy
                        </button>
                    </h6>
                    <pre class="bg-light p-3 rounded" id="detail-prompt" style="max-height: 200px; overflow-y: auto; white-space: pre-wrap;">-</pre>
                </div>

                <div class="mb-3">
                    <h6 class="text-muted">
                        Response / Output
                        <button class="btn btn-sm btn-outline-secondary float-end" onclick="aiUsage_copyToClipboard('detail-response')">
                            <i class="bi bi-clipboard"></i> Copy
                        </button>
                    </h6>
                    <pre class="bg-light p-3 rounded" id="detail-response" style="max-height: 200px; overflow-y: auto; white-space: pre-wrap;">-</pre>
                </div>

                <div class="mb-3" id="detail-metadata-section" style="display: none;">
                    <h6 class="text-muted">Request Metadata (JSON)</h6>
                    <pre class="bg-light p-3 rounded" id="detail-metadata" style="max-height: 150px; overflow-y: auto; white-space: pre-wrap;">-</pre>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="/ai/common/common_ai.js"></script>
<script src="/ai/admin/aicentral_usage.js"></script>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/footer.html'; ?>
