<?php
/**
 * AI Central Admin Users - HTML
 */
$pageTitle = 'Users Management - AI Central';


require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/header_ai.php';
?>

<link rel="stylesheet" href="/ai/admin/aicentral_users.css">

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h2 mb-0">
                    <i class="bi bi-people-fill text-primary"></i> Users Management
                </h1>
                <div class="text-muted small">
                    Users are automatically synced from Auth system
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-md-3">
            <select id="aicentral-filter-tier" class="form-select" onchange="aiCentral_filterUsers()">
                <option value="">All Tiers</option>
            </select>
        </div>
        <div class="col-md-9">
            <input type="text" id="aicentral-filter-search" class="form-control"
                   placeholder="Search users (name or email)..." onkeyup="aiCentral_filterUsers()">
        </div>
    </div>

    <!-- Stats Summary -->
    <div class="row mb-4" id="aicentral-users-stats">
        <div class="col-md">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body">
                    <h3 class="mb-1 text-primary">0</h3>
                    <p class="text-muted text-uppercase small mb-0 fw-semibold">Total Users</p>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body">
                    <h3 class="mb-1 text-info">0</h3>
                    <p class="text-muted text-uppercase small mb-0 fw-semibold">Free Tier</p>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body">
                    <h3 class="mb-1 text-success">0</h3>
                    <p class="text-muted text-uppercase small mb-0 fw-semibold">Basic Tier</p>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body">
                    <h3 class="mb-1 text-warning">0</h3>
                    <p class="text-muted text-uppercase small mb-0 fw-semibold">Pro Tier</p>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body">
                    <h3 class="mb-1 text-danger">0</h3>
                    <p class="text-muted text-uppercase small mb-0 fw-semibold">Unlimited</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Users List -->
    <div class="row">
        <div class="col">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div id="aicentral-users-list">
                        <div class="text-center py-5 text-muted">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3">Loading users...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit User Tiers Modal -->
<div class="modal fade" id="aicentral-user-modal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel">
                    <i class="bi bi-pencil-square"></i> Edit User Tiers by Program
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="aicentral-user-id">

                <div class="mb-3">
                    <label for="aicentral-user-id-display" class="form-label">User ID</label>
                    <input type="text" id="aicentral-user-id-display" class="form-control" readonly>
                </div>

                <div class="mb-3">
                    <label for="aicentral-user-name" class="form-label">Name</label>
                    <input type="text" id="aicentral-user-name" class="form-control" readonly>
                </div>

                <div class="mb-3">
                    <label for="aicentral-user-email" class="form-label">Email</label>
                    <input type="text" id="aicentral-user-email" class="form-control" readonly>
                </div>

                <hr>

                <h6 class="mb-3">AI Tiers by Program</h6>
                <div id="aicentral-program-tiers-list">
                    <div class="text-center py-3">
                        <div class="spinner-border spinner-border-sm" role="status"></div>
                        Loading program tiers...
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="/ai/admin/aicentral_users.js"></script>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/footer.html'; ?>
