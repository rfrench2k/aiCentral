<?php
/**
 * AI Database Credentials - Admin UI
 * Manage encrypted database credentials for AI features
 */

$pageTitle = 'AI Database Credentials - Admin';
$additionalCSS = '<link rel="stylesheet" href="/ai/admin/aiDatabaseCredentials.css">';

require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/header_ai.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1><i class="bi bi-database-lock"></i> AI Database Credentials</h1>
            <p class="lead">Manage encrypted database credentials for AI features (READ-ONLY, READ-WRITE, FULL access)</p>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="bi bi-list"></i> Database Credentials</h5>
                    <button type="button" class="btn btn-primary btn-sm" id="addCredentialBtn">
                        <i class="bi bi-plus-circle"></i> Add New Credential
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="credentialsTable">
                            <thead>
                                <tr>
                                    <th>Database</th>
                                    <th>Permission Level</th>
                                    <th>Host</th>
                                    <th>Username</th>
                                    <th>Password</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="8" class="text-center">
                                        <i class="bi bi-hourglass-split"></i> Loading credentials...
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

<!-- Add/Edit Credential Modal -->
<div class="modal fade" id="credentialModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="credentialModalTitle">
                    <i class="bi bi-plus-circle"></i> Add Database Credential
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="credentialForm">
                    <input type="hidden" id="credentialId" name="credentialId">

                    <div class="mb-3">
                        <label for="databaseName" class="form-label">Database Name *</label>
                        <input type="text" class="form-control" id="databaseName" name="databaseName" required
                               placeholder="e.g., myapp_db">
                        <small class="form-text text-muted">The name of the database to connect to</small>
                    </div>

                    <div class="mb-3">
                        <label for="permissionLevel" class="form-label">Permission Level *</label>
                        <select class="form-select" id="permissionLevel" name="permissionLevel" required>
                            <option value="">-- Select Permission Level --</option>
                            <option value="readonly">Read-Only (SELECT, SHOW VIEW)</option>
                            <option value="readwrite">Read-Write (+ INSERT, UPDATE, CREATE TEMPORARY TABLES)</option>
                            <option value="full">Full Access (ALL PRIVILEGES)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="dbHost" class="form-label">Database Host *</label>
                        <input type="text" class="form-control" id="dbHost" name="dbHost" value="localhost" required>
                    </div>

                    <div class="mb-3">
                        <label for="dbUsername" class="form-label">Database Username *</label>
                        <input type="text" class="form-control" id="dbUsername" name="dbUsername" required
                               placeholder="e.g., myapp_ro">
                    </div>

                    <div class="mb-3">
                        <label for="dbPassword" class="form-label">Database Password *</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="dbPassword" name="dbPassword" required>
                            <button class="btn btn-outline-secondary" type="button" id="togglePasswordBtn">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <small class="form-text text-muted">Password will be encrypted before storage</small>
                    </div>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> <strong>Note:</strong> Passwords are encrypted using AES-256-CBC before storage.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveCredentialBtn">
                    <i class="bi bi-save"></i> Save Credential
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Test Connection Modal -->
<div class="modal fade" id="testConnectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plug"></i> Test Database Connection</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="testConnectionResult"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/footer.html'; ?>

<script src="/ai/admin/aiDatabaseCredentials.js"></script>
