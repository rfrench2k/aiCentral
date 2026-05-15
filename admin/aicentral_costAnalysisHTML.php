<?php
/**
 * AI Central Admin Cost Analysis - HTML
 */
$pageTitle = 'AI Central - Cost Analysis';


require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/header_ai.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h2 fw-bold text-dark">
                <i class="bi bi-graph-up text-primary"></i> Cost Analysis
            </h1>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label for="aicentral-filter-start-date" class="form-label fw-semibold text-dark">From Date</label>
                            <input type="date" id="aicentral-filter-start-date" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label for="aicentral-filter-end-date" class="form-label fw-semibold text-dark">To Date</label>
                            <input type="date" id="aicentral-filter-end-date" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary w-100" onclick="aiCentral_loadCostData()">
                                <i class="bi bi-funnel"></i> Apply Filter
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="row g-3 mb-4" id="aicentral-cost-stats">
        <div class="col-md-6 col-lg">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-uppercase text-muted small fw-semibold mb-2">Total Cost</div>
                    <div class="h2 fw-bold text-success mb-0">$0.00</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-uppercase text-muted small fw-semibold mb-2">Total Requests</div>
                    <div class="h2 fw-bold text-dark mb-0">0</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-uppercase text-muted small fw-semibold mb-2">Input Tokens</div>
                    <div class="h2 fw-bold text-dark mb-0">0</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-uppercase text-muted small fw-semibold mb-2">Output Tokens</div>
                    <div class="h2 fw-bold text-dark mb-0">0</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-uppercase text-muted small fw-semibold mb-2">Avg Cost/Request</div>
                    <div class="h2 fw-bold text-success mb-0">$0.00</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cost Breakdowns -->
    <div class="row g-4 mb-4">
        <!-- By Provider -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="bi bi-building text-primary"></i> Cost by Provider
                    </h5>
                </div>
                <div class="card-body">
                    <div id="aicentral-cost-by-provider">
                        <div class="text-center text-muted py-5">
                            <div class="spinner-border spinner-border-sm" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <div class="mt-2">Loading...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- By Model -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="bi bi-cpu text-primary"></i> Cost by Model
                    </h5>
                </div>
                <div class="card-body">
                    <div id="aicentral-cost-by-model">
                        <div class="text-center text-muted py-5">
                            <div class="spinner-border spinner-border-sm" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <div class="mt-2">Loading...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- By Program -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="bi bi-app text-primary"></i> Cost by Program
                    </h5>
                </div>
                <div class="card-body">
                    <div id="aicentral-cost-by-program">
                        <div class="text-center text-muted py-5">
                            <div class="spinner-border spinner-border-sm" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <div class="mt-2">Loading...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- By Feature -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="bi bi-stars text-primary"></i> Cost by Feature
                    </h5>
                </div>
                <div class="card-body">
                    <div id="aicentral-cost-by-feature">
                        <div class="text-center text-muted py-5">
                            <div class="spinner-border spinner-border-sm" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <div class="mt-2">Loading...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- By User (Top 10) -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="bi bi-people text-primary"></i> Top 10 Users by Cost
                    </h5>
                </div>
                <div class="card-body">
                    <div id="aicentral-cost-by-user">
                        <div class="text-center text-muted py-5">
                            <div class="spinner-border spinner-border-sm" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <div class="mt-2">Loading...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Daily Trend -->
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="bi bi-calendar-week text-primary"></i> Daily Cost Trend
                    </h5>
                </div>
                <div class="card-body">
                    <div id="aicentral-cost-daily-trend">
                        <div class="text-center text-muted py-5">
                            <div class="spinner-border spinner-border-sm" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <div class="mt-2">Loading...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Passthrough Program User Breakdown -->
    <div class="row g-4 mb-4" id="aicentral-passthrough-section" style="display: none;">
        <div class="col-12">
            <hr class="my-2">
            <h3 class="h4 fw-bold text-dark mt-3 mb-3">
                <i class="bi bi-person-lines-fill text-primary"></i> Passthrough Program - User Breakdown
            </h3>
        </div>
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="bi bi-funnel text-primary"></i> Select Program
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="aicentral-passthrough-program" class="form-label fw-semibold text-dark">Program</label>
                            <select id="aicentral-passthrough-program" class="form-select">
                                <option value="">-- Select --</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary" onclick="aiCentral_loadUserBreakdown()">
                                <i class="bi bi-search"></i> Load Breakdown
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Feature summary for selected program -->
        <div class="col-lg-6" id="aicentral-passthrough-features-card" style="display: none;">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="bi bi-stars text-primary"></i> Features
                    </h5>
                </div>
                <div class="card-body">
                    <div id="aicentral-passthrough-features"></div>
                </div>
            </div>
        </div>

        <!-- User breakdown table -->
        <div class="col-lg-6" id="aicentral-passthrough-users-card" style="display: none;">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="bi bi-people text-primary"></i> Users (Top 50 by Cost)
                    </h5>
                </div>
                <div class="card-body">
                    <div id="aicentral-passthrough-users"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/ai/admin/aicentral_costAnalysis.js"></script>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/footer.html'; ?>
