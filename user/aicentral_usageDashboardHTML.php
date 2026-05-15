<?php
/**
 * AI Central User Usage Dashboard - HTML (Bootstrap 5.3.3)
 */
$pageTitle = 'My AI Usage - AI Central';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/header_ai.php';
?>

<link rel="stylesheet" href="/ai/user/aicentral_usageDashboard.css">

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="display-5 fw-bold">My AI Usage</h1>
            <p class="text-muted">Track your AI requests, token usage, and costs</p>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label for="aicentral-filter-start-date" class="form-label fw-semibold">From:</label>
                            <input type="date" id="aicentral-filter-start-date" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label for="aicentral-filter-end-date" class="form-label fw-semibold">To:</label>
                            <input type="date" id="aicentral-filter-end-date" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary w-100" onclick="aiCentral_loadUsageData()">
                                <i class="bi bi-funnel"></i> Apply Filter
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Current Tier Info -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-primary shadow-sm" id="aicentral-tier-info">
                <div class="card-body">
                    <div class="text-center text-muted">
                        <div class="spinner-border spinner-border-sm" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        Loading tier information...
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="row g-3 mb-4" id="aicentral-usage-stats">
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-muted text-uppercase small fw-semibold mb-2">Total Requests</div>
                    <div class="display-6 fw-bold">0</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-muted text-uppercase small fw-semibold mb-2">Input Tokens</div>
                    <div class="display-6 fw-bold">0</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-muted text-uppercase small fw-semibold mb-2">Output Tokens</div>
                    <div class="display-6 fw-bold">0</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-muted text-uppercase small fw-semibold mb-2">Total Cost</div>
                    <div class="display-6 fw-bold text-success">$0.00</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Usage Breakdowns -->
    <div class="row g-4 mb-4">
        <!-- By Feature -->
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h5 class="card-title mb-0 fw-bold">
                        <i class="bi bi-stars text-primary"></i> Usage by Feature
                    </h5>
                </div>
                <div class="card-body">
                    <div id="aicentral-usage-by-feature">
                        <div class="text-center text-muted py-4">
                            <div class="spinner-border spinner-border-sm" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            Loading...
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- By Provider -->
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h5 class="card-title mb-0 fw-bold">
                        <i class="bi bi-cpu text-primary"></i> Usage by Provider
                    </h5>
                </div>
                <div class="card-body">
                    <div id="aicentral-usage-by-provider">
                        <div class="text-center text-muted py-4">
                            <div class="spinner-border spinner-border-sm" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            Loading...
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h5 class="card-title mb-0 fw-bold">
                        <i class="bi bi-clock-history text-primary"></i> Recent Activity (Last 10)
                    </h5>
                </div>
                <div class="card-body">
                    <div id="aicentral-recent-activity">
                        <div class="text-center text-muted py-4">
                            <div class="spinner-border spinner-border-sm" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            Loading...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/ai/user/aicentral_usageDashboard.js"></script>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/footer.html'; ?>
