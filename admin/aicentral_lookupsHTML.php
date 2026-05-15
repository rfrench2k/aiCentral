<?php
/**
 * AI Central Admin - Lookups Management
 */
$pageTitle = 'AI Central - Lookups Management';

require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/header_ai.php';
?>

<link rel="stylesheet" href="/ai/admin/aicentral_lookups.css">

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h2 mb-0">
                    <i class="bi bi-list-ul text-primary"></i> Lookups Management
                </h1>
                <button class="btn btn-primary" onclick="aiLookups_showAddDialog()">
                    <i class="bi bi-plus-lg"></i> Add Lookup
                </button>
            </div>
            <p class="text-muted mt-2">Manage system lookup values and categories</p>
        </div>
    </div>

    <!-- Filter -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <select id="lookup-filter-category" class="form-select" onchange="aiLookups_filterLookups()">
                                <option value="">All Categories</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <input type="text" id="lookup-filter-search" class="form-control"
                                   placeholder="Search lookups..." onkeyup="aiLookups_filterLookups()">
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-outline-secondary w-100" onclick="aiLookups_clearFilters()">
                                <i class="bi bi-x-lg"></i> Clear
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lookups Table -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Value</th>
                                    <th>Description</th>
                                    <th width="100">Order</th>
                                    <th width="150">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="lookups-table-body">
                                <tr>
                                    <td colspan="5" class="text-center py-4">
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
    </div>
</div>

<!-- Add/Edit Lookup Modal -->
<div class="modal fade" id="lookup-modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="lookup-modal-title">Add Lookup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="lookup-form">
                    <input type="hidden" id="lookup-id" name="id">

                    <div class="mb-3">
                        <label for="lookup-category" class="form-label">
                            Category <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="lookup-category" name="lookup_name"
                               list="category-list" required
                               placeholder="e.g., feature_type, model_tier">
                        <datalist id="category-list">
                            <!-- Populated by JavaScript -->
                        </datalist>
                        <div class="form-text">Enter existing category or create new</div>
                    </div>

                    <div class="mb-3">
                        <label for="lookup-value" class="form-label">
                            Value <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="lookup-value" name="lookup_value" required
                               placeholder="e.g., vision_analysis">
                    </div>

                    <div class="mb-3">
                        <label for="lookup-order" class="form-label">Sort Order</label>
                        <input type="number" class="form-control" id="lookup-order" name="lookup_order"
                               placeholder="10, 20, 30...">
                        <div class="form-text">Controls display order (lower numbers first)</div>
                    </div>

                    <div class="mb-3">
                        <label for="lookup-desc" class="form-label">Description</label>
                        <textarea class="form-control" id="lookup-desc" name="lookup_desc" rows="2"
                                  placeholder="Brief description of this lookup value"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="aiLookups_saveLookup()">
                    <i class="bi bi-check-lg"></i> Save
                </button>
            </div>
        </div>
    </div>
</div>

<script src="/ai/admin/aicentral_lookups.js"></script>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/footer.html'; ?>
