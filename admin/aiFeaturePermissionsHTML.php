<?php
/**
 * AI Feature Permissions - Admin UI
 * Configure permissions for AI features using Claude CLI
 */

$pageTitle = 'AI Feature Permissions - Admin';
$additionalCSS = '<link rel="stylesheet" href="/ai/admin/aiFeaturePermissions.css">';

require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/header_ai.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1><i class="bi bi-shield-lock"></i> AI Feature Permissions</h1>
            <p class="lead">Configure security guardrails and allowed operations for AI features using Claude Code CLI</p>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-list-ul"></i> Select Feature</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="featureSelect" class="form-label">AI Feature</label>
                        <select class="form-select" id="featureSelect">
                            <option value="">-- Select Feature --</option>
                        </select>
                        <small class="form-text text-muted">Only features using Claude CLI provider are shown</small>
                    </div>

                    <div id="featureInfo" class="alert alert-info d-none">
                        <strong>Feature:</strong> <span id="featureName"></span><br>
                        <strong>Program:</strong> <span id="programId"></span><br>
                        <strong>Provider:</strong> <span id="provider"></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card" id="permissionsCard" style="display: none;">
                <div class="card-header">
                    <h5><i class="bi bi-sliders"></i> Feature Permissions</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        <i class="bi bi-info-circle"></i> All permissions default to OFF. Enable only what's needed for this feature.
                    </p>

                    <div class="permissions-grid">
                        <!-- Database Permissions -->
                        <div class="permission-section">
                            <h6><i class="bi bi-database"></i> Database Access</h6>

                            <div class="form-check form-switch permission-item">
                                <input class="form-check-input" type="checkbox" id="perm_allow_db_read" data-perm="allow_db_read">
                                <label class="form-check-label" for="perm_allow_db_read">
                                    <strong>Allow Database Read</strong>
                                    <div class="permission-desc">SELECT queries only. Safest option.</div>
                                    <div class="permission-tools"><small><i class="bi bi-tools"></i> Enables: Bash tool (for db-query.php)</small></div>
                                </label>
                            </div>

                            <div class="form-check form-switch permission-item">
                                <input class="form-check-input" type="checkbox" id="perm_allow_db_write" data-perm="allow_db_write">
                                <label class="form-check-label" for="perm_allow_db_write">
                                    <strong>Allow Database Write</strong>
                                    <div class="permission-desc">INSERT, UPDATE operations. Use with caution.</div>
                                    <div class="permission-tools"><small><i class="bi bi-tools"></i> Enables: Bash tool + system prompt warnings</small></div>
                                </label>
                            </div>

                            <div class="form-check form-switch permission-item">
                                <input class="form-check-input" type="checkbox" id="perm_allow_db_delete" data-perm="allow_db_delete">
                                <label class="form-check-label" for="perm_allow_db_delete">
                                    <strong>Allow Database Delete</strong>
                                    <div class="permission-desc text-danger">DELETE, TRUNCATE, DROP. EXTREME CAUTION!</div>
                                    <div class="permission-tools"><small><i class="bi bi-tools"></i> Enables: Bash tool + strong warnings</small></div>
                                </label>
                            </div>

                            <div class="form-check form-switch permission-item">
                                <input class="form-check-input" type="checkbox" id="perm_allow_temp_tables" data-perm="allow_temp_tables">
                                <label class="form-check-label" for="perm_allow_temp_tables">
                                    <strong>Allow Temporary Tables</strong>
                                    <div class="permission-desc">CREATE TEMPORARY TABLE for complex analysis.</div>
                                    <div class="permission-tools"><small><i class="bi bi-tools"></i> Controlled via MySQL GRANT + system prompt</small></div>
                                </label>
                            </div>
                        </div>

                        <!-- Web Access Permissions -->
                        <div class="permission-section">
                            <h6><i class="bi bi-globe"></i> Web Access</h6>

                            <div class="form-check form-switch permission-item">
                                <input class="form-check-input" type="checkbox" id="perm_allow_web_search" data-perm="allow_web_search">
                                <label class="form-check-label" for="perm_allow_web_search">
                                    <strong>Allow Web Search</strong>
                                    <div class="permission-desc">Search for documentation, syntax help, etc.</div>
                                    <div class="permission-tools"><small><i class="bi bi-tools"></i> Enables: WebSearch tool</small></div>
                                </label>
                            </div>

                            <div class="form-check form-switch permission-item">
                                <input class="form-check-input" type="checkbox" id="perm_allow_web_fetch" data-perm="allow_web_fetch">
                                <label class="form-check-label" for="perm_allow_web_fetch">
                                    <strong>Allow Web Fetch</strong>
                                    <div class="permission-desc">Fetch specific URLs for documentation.</div>
                                    <div class="permission-tools"><small><i class="bi bi-tools"></i> Enables: WebFetch tool</small></div>
                                </label>
                            </div>
                        </div>

                        <!-- File Access Permissions -->
                        <div class="permission-section">
                            <h6><i class="bi bi-file-earmark"></i> File Access</h6>

                            <div class="form-check form-switch permission-item">
                                <input class="form-check-input" type="checkbox" id="perm_allow_file_read" data-perm="allow_file_read">
                                <label class="form-check-label" for="perm_allow_file_read">
                                    <strong>Allow File Read</strong>
                                    <div class="permission-desc">Read application files, analyze code structure.</div>
                                    <div class="permission-tools"><small><i class="bi bi-tools"></i> Enables: Read, Grep, Glob tools</small></div>
                                </label>
                            </div>

                            <div class="form-check form-switch permission-item">
                                <input class="form-check-input" type="checkbox" id="perm_allow_file_write" data-perm="allow_file_write">
                                <label class="form-check-label" for="perm_allow_file_write">
                                    <strong>Allow File Write</strong>
                                    <div class="permission-desc">Create reports, exports, generated files.</div>
                                    <div class="permission-tools"><small><i class="bi bi-tools"></i> Enables: Write, Edit tools</small></div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <h6><i class="bi bi-list-check"></i> Allowed Claude CLI Tools</h6>
                        <div id="allowedToolsList" class="alert alert-secondary">
                            <em>Select permissions above to see which tools will be enabled</em>
                        </div>
                    </div>

                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" id="savePermissionsBtn">
                            <i class="bi bi-save"></i> Save Permissions
                        </button>
                        <button type="button" class="btn btn-secondary" id="previewPromptBtn">
                            <i class="bi bi-eye"></i> Preview System Prompt
                        </button>
                    </div>

                    <div id="saveMessage" class="alert mt-3 d-none"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="promptPreviewModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-eye"></i> System Prompt Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre id="promptPreviewContent" style="background: #f8f9fa; padding: 15px; border-radius: 5px; max-height: 500px; overflow-y: auto;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/footer.html'; ?>

<script src="/ai/admin/aiFeaturePermissions.js"></script>
