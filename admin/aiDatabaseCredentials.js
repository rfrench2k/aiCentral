/**
 * AI Database Credentials - Frontend JavaScript
 * Handles UI interactions and API calls
 */

$(document).ready(function() {

    let editingCredentialId = null;

    // Load credentials on page load
    loadCredentials();

    // Add credential button
    $('#addCredentialBtn').on('click', function() {
        editingCredentialId = null;
        $('#credentialModalTitle').html('<i class="bi bi-plus-circle"></i> Add Database Credential');
        $('#credentialForm')[0].reset();
        $('#credentialId').val('');
        $('#dbPassword').prop('required', true);
        $('#credentialModal').modal('show');
    });

    // Save credential button
    $('#saveCredentialBtn').on('click', function() {
        saveCredential();
    });

    // Toggle password visibility
    $('#togglePasswordBtn').on('click', function() {
        const $input = $('#dbPassword');
        const $icon = $(this).find('i');

        if ($input.attr('type') === 'password') {
            $input.attr('type', 'text');
            $icon.removeClass('bi-eye').addClass('bi-eye-slash');
        } else {
            $input.attr('type', 'password');
            $icon.removeClass('bi-eye-slash').addClass('bi-eye');
        }
    });

    /**
     * Load all credentials
     */
    function loadCredentials() {
        $.ajax({
            url: '/ai/admin/aiDatabaseCredentialsCode.php',
            type: 'POST',
            data: { action: 'getCredentials' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    populateCredentialsTable(response.credentials);
                } else {
                    showError('Failed to load credentials: ' + (response.error || 'Unknown error'));
                }
            },
            error: function() {
                showError('Failed to load credentials (network error)');
            }
        });
    }

    /**
     * Populate credentials table
     */
    function populateCredentialsTable(credentials) {
        const $tbody = $('#credentialsTable tbody');
        $tbody.empty();

        if (credentials.length === 0) {
            $tbody.append(`
                <tr>
                    <td colspan="8" class="text-center text-muted">
                        <i class="bi bi-inbox"></i> No database credentials configured yet
                    </td>
                </tr>
            `);
            return;
        }

        credentials.forEach(cred => {
            const statusBadge = cred.is_active
                ? '<span class="badge bg-success">Active</span>'
                : '<span class="badge bg-secondary">Inactive</span>';

            const permissionBadge = getPermissionBadge(cred.permission_level);

            const createdDate = new Date(cred.created_at).toLocaleDateString();

            $tbody.append(`
                <tr data-credential-id="${cred.credential_id}">
                    <td><strong>${cred.database_name}</strong></td>
                    <td>${permissionBadge}</td>
                    <td>${cred.db_host}</td>
                    <td><code>${cred.db_username}</code></td>
                    <td><span class="text-muted"><i class="bi bi-lock"></i> Encrypted</span></td>
                    <td>${statusBadge}</td>
                    <td>${createdDate}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary test-btn" data-id="${cred.credential_id}" title="Test Connection">
                            <i class="bi bi-plug"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary edit-btn" data-id="${cred.credential_id}" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger delete-btn" data-id="${cred.credential_id}" title="Delete">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `);
        });

        // Attach event handlers
        $('.test-btn').on('click', function() {
            const credId = $(this).data('id');
            testConnection(credId);
        });

        $('.edit-btn').on('click', function() {
            const credId = $(this).data('id');
            editCredential(credId, credentials);
        });

        $('.delete-btn').on('click', function() {
            const credId = $(this).data('id');
            deleteCredential(credId);
        });
    }

    /**
     * Get permission badge HTML
     */
    function getPermissionBadge(level) {
        const badges = {
            'readonly': '<span class="badge bg-info">Read-Only</span>',
            'readwrite': '<span class="badge bg-warning">Read-Write</span>',
            'full': '<span class="badge bg-danger">Full Access</span>'
        };
        return badges[level] || level;
    }

    /**
     * Save credential
     */
    function saveCredential() {
        const formData = {
            action: editingCredentialId ? 'updateCredential' : 'addCredential',
            databaseName: $('#databaseName').val(),
            permissionLevel: $('#permissionLevel').val(),
            dbHost: $('#dbHost').val(),
            dbUsername: $('#dbUsername').val(),
            dbPassword: $('#dbPassword').val()
        };

        if (editingCredentialId) {
            formData.credentialId = editingCredentialId;
        }

        // Validation
        if (!formData.databaseName || !formData.permissionLevel || !formData.dbHost || !formData.dbUsername) {
            alert('Please fill in all required fields');
            return;
        }

        if (!editingCredentialId && !formData.dbPassword) {
            alert('Password is required for new credentials');
            return;
        }

        const $btn = $('#saveCredentialBtn');
        $btn.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Saving...');

        $.ajax({
            url: '/ai/admin/aiDatabaseCredentialsCode.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#credentialModal').modal('hide');
                    loadCredentials();
                    showSuccess(response.message || 'Credential saved successfully!');
                } else {
                    showError(response.error || 'Failed to save credential');
                }
            },
            error: function() {
                showError('Failed to save credential (network error)');
            },
            complete: function() {
                $btn.prop('disabled', false).html('<i class="bi bi-save"></i> Save Credential');
            }
        });
    }

    /**
     * Edit credential
     */
    function editCredential(credentialId, credentials) {
        const cred = credentials.find(c => c.credential_id === credentialId);

        if (!cred) {
            showError('Credential not found');
            return;
        }

        editingCredentialId = credentialId;
        $('#credentialModalTitle').html('<i class="bi bi-pencil"></i> Edit Database Credential');
        $('#credentialId').val(cred.credential_id);
        $('#databaseName').val(cred.database_name);
        $('#permissionLevel').val(cred.permission_level);
        $('#dbHost').val(cred.db_host);
        $('#dbUsername').val(cred.db_username);
        $('#dbPassword').val('').prop('required', false);

        $('#credentialModal').modal('show');
    }

    /**
     * Delete credential
     */
    function deleteCredential(credentialId) {
        if (!confirm('Are you sure you want to delete this database credential? This action cannot be undone.')) {
            return;
        }

        $.ajax({
            url: '/ai/admin/aiDatabaseCredentialsCode.php',
            type: 'POST',
            data: {
                action: 'deleteCredential',
                credentialId: credentialId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    loadCredentials();
                    showSuccess(response.message || 'Credential deleted successfully');
                } else {
                    showError(response.error || 'Failed to delete credential');
                }
            },
            error: function() {
                showError('Failed to delete credential (network error)');
            }
        });
    }

    /**
     * Test connection
     */
    function testConnection(credentialId) {
        $('#testConnectionResult').html('<div class="text-center"><i class="bi bi-hourglass-split"></i> Testing connection...</div>');
        $('#testConnectionModal').modal('show');

        $.ajax({
            url: '/ai/admin/aiDatabaseCredentialsCode.php',
            type: 'POST',
            data: {
                action: 'testConnection',
                credentialId: credentialId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.test_success) {
                    $('#testConnectionResult').html(`
                        <div class="alert alert-success">
                            <h5><i class="bi bi-check-circle"></i> Connection Successful!</h5>
                            <p class="mb-0">
                                <strong>Database:</strong> ${response.database}<br>
                                <strong>Host:</strong> ${response.host}<br>
                                <strong>Username:</strong> ${response.username}
                            </p>
                        </div>
                    `);
                } else {
                    $('#testConnectionResult').html(`
                        <div class="alert alert-danger">
                            <h5><i class="bi bi-x-circle"></i> Connection Failed</h5>
                            <p class="mb-0">${response.error || response.message || 'Unknown error'}</p>
                        </div>
                    `);
                }
            },
            error: function() {
                $('#testConnectionResult').html(`
                    <div class="alert alert-danger">
                        <h5><i class="bi bi-x-circle"></i> Test Failed</h5>
                        <p class="mb-0">Network error occurred</p>
                    </div>
                `);
            }
        });
    }

    /**
     * Show success message
     */
    function showSuccess(message) {
        // You can implement a toast notification here
        alert(message);
    }

    /**
     * Show error message
     */
    function showError(message) {
        alert('Error: ' + message);
    }

});
