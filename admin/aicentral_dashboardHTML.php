<?php
/**
 * AI Central Admin Dashboard - HTML
 */
$pageTitle = 'AI Central - Dashboard';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/header_ai.php';
?>

<div class="row mb-4">
    <div class="col">
        <h1 class="text-primary">
            <i class="bi bi-speedometer2"></i> AI Central Dashboard
        </h1>
        <p class="text-muted">Monitor AI usage, costs, and system performance</p>
    </div>
    <div class="col-auto">
        <button class="btn btn-primary" onclick="aiCentral_refreshDashboard()">
            <i class="bi bi-arrow-clockwise"></i> Refresh
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
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
                        <h6 class="text-muted small mb-1">Total Requests (30d)</h6>
                        <h3 class="mb-0" id="aicentral-stat-total-requests">---</h3>
                        <small id="aicentral-stat-requests-change" class="text-muted">Loading...</small>
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
                        <h6 class="text-muted small mb-1">Total Cost (30d)</h6>
                        <h3 class="mb-0" id="aicentral-stat-total-cost">---</h3>
                        <small id="aicentral-stat-cost-change" class="text-muted">Loading...</small>
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
                            <i class="bi bi-people-fill fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted small mb-1">Active Users (30d)</h6>
                        <h3 class="mb-0" id="aicentral-stat-active-users">---</h3>
                        <small id="aicentral-stat-users-change" class="text-muted">Loading...</small>
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
                            <i class="bi bi-lightning-fill fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted small mb-1">Avg Response Time</h6>
                        <h3 class="mb-0" id="aicentral-stat-avg-response">---</h3>
                        <small id="aicentral-stat-response-change" class="text-muted">Loading...</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts and Recent Activity -->
<div class="row g-4 mb-4">
    <!-- Left Column: Chart and Recent Requests -->
    <div class="col-lg-8">
        <!-- Requests Over Time Chart -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-graph-up"></i> Requests Over Time (30 Days)</h5>
            </div>
            <div class="card-body">
                <canvas id="aicentral-chart-requests" style="max-height: 300px;"></canvas>
            </div>
        </div>

        <!-- Recent Requests -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Requests</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="aicentral-recent-requests">
                        <thead class="table-light">
                            <tr>
                                <th>Time</th>
                                <th>User</th>
                                <th>Program</th>
                                <th>Feature</th>
                                <th>Provider</th>
                                <th>Model</th>
                                <th>Cost</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="8" class="text-center py-4">
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

    <!-- Right Column: Cost by Provider and Top Programs -->
    <div class="col-lg-4">
        <!-- Cost by Provider Chart -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-pie-chart-fill"></i> Cost by Provider</h5>
            </div>
            <div class="card-body">
                <canvas id="aicentral-chart-providers" style="max-height: 300px;"></canvas>
            </div>
        </div>

        <!-- Top Programs -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-trophy-fill"></i> Top Programs (30d)</h5>
            </div>
            <div class="card-body" id="aicentral-top-programs">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Provider Status -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-cloud-check-fill"></i> Provider Status</h5>
            </div>
            <div class="card-body">
                <div class="row g-3" id="aicentral-providers-status">
                    <div class="col-12 text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/ai/admin/aicentral_dashboard.js"></script>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/footer.html'; ?>
