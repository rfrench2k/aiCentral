/**
 * AI Feature Permissions - Frontend JavaScript
 * Handles UI interactions and API calls
 */

$(document).ready(function() {

    let currentFeatureId = null;
    let currentPermissions = {};

    // Load features on page load
    loadFeatures();

    // Feature selection change
    $('#featureSelect').on('change', function() {
        const featureId = $(this).val();

        if (featureId) {
            currentFeatureId = featureId;
            loadFeaturePermissions(featureId);
            showFeatureInfo();
            $('#permissionsCard').slideDown();
        } else {
            currentFeatureId = null;
            $('#permissionsCard').slideUp();
            $('#featureInfo').addClass('d-none');
        }
    });

    // Permission checkbox change
    $('.form-check-input').on('change', function() {
        const permType = $(this).data('perm');
        const isEnabled = $(this).is(':checked');

        currentPermissions[permType] = isEnabled;

        // Update allowed tools preview
        updateAllowedToolsPreview();
    });

    // Save permissions button
    $('#savePermissionsBtn').on('click', function() {
        if (!currentFeatureId) {
            showMessage('Please select a feature first', 'danger');
            return;
        }

        savePermissions();
    });

    // Preview prompt button
    $('#previewPromptBtn').on('click', function() {
        if (!currentFeatureId) {
            showMessage('Please select a feature first', 'danger');
            return;
        }

        previewPrompt();
    });

    /**
     * Load all features from backend
     */
    function loadFeatures() {
        $.ajax({
            url: '/ai/admin/aiFeaturePermissionsCode.php',
            type: 'POST',
            data: { action: 'getFeatures' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    populateFeatureDropdown(response.features);
                } else {
                    showMessage(response.error || 'Failed to load features', 'danger');
                }
            },
            error: function() {
                showMessage('Failed to load features (network error)', 'danger');
            }
        });
    }

    /**
     * Populate feature dropdown
     */
    function populateFeatureDropdown(features) {
        const $select = $('#featureSelect');
        $select.find('option:not(:first)').remove();

        features.forEach(feature => {
            $select.append(
                $('<option>')
                    .val(feature.feature_id)
                    .text(`${feature.program_id} - ${feature.feature_name}`)
                    .data('feature', feature)
            );
        });
    }

    /**
     * Show feature info
     */
    function showFeatureInfo() {
        const selectedOption = $('#featureSelect option:selected');
        const feature = selectedOption.data('feature');

        if (feature) {
            $('#featureName').text(feature.feature_name);
            $('#programId').text(feature.program_id);
            $('#provider').text(feature.default_provider);
            $('#featureInfo').removeClass('d-none');
        }
    }

    /**
     * Load feature permissions
     */
    function loadFeaturePermissions(featureId) {
        $.ajax({
            url: '/ai/admin/aiFeaturePermissionsCode.php',
            type: 'POST',
            data: {
                action: 'getFeaturePermissions',
                featureId: featureId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    currentPermissions = response.permissions;
                    updatePermissionCheckboxes(response.permissions);
                    updateAllowedToolsPreview();
                } else {
                    showMessage(response.error || 'Failed to load permissions', 'danger');
                }
            },
            error: function() {
                showMessage('Failed to load permissions (network error)', 'danger');
            }
        });
    }

    /**
     * Update permission checkboxes
     */
    function updatePermissionCheckboxes(permissions) {
        Object.keys(permissions).forEach(permType => {
            $(`#perm_${permType}`).prop('checked', permissions[permType]);
        });
    }

    /**
     * Update allowed tools preview
     */
    function updateAllowedToolsPreview() {
        $.ajax({
            url: '/ai/admin/aiFeaturePermissionsCode.php',
            type: 'POST',
            data: {
                action: 'previewTools',
                permissions: JSON.stringify(currentPermissions)
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    let html = '<strong>--allowed-tools</strong> ' + response.toolString;

                    if (response.warning) {
                        html = '<i class="bi bi-exclamation-triangle text-warning"></i> ' +
                               response.warning + '<br>' + html;
                    }

                    $('#allowedToolsList').html(html);
                } else {
                    $('#allowedToolsList').html('<em>Error loading tools preview</em>');
                }
            },
            error: function() {
                $('#allowedToolsList').html('<em>Error loading tools preview</em>');
            }
        });
    }

    /**
     * Save permissions
     */
    function savePermissions() {
        const btn = $('#savePermissionsBtn');
        btn.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Saving...');

        $.ajax({
            url: '/ai/admin/aiFeaturePermissionsCode.php',
            type: 'POST',
            data: {
                action: 'saveFeaturePermissions',
                featureId: currentFeatureId,
                permissions: JSON.stringify(currentPermissions)
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showMessage(response.message || 'Permissions saved successfully!', 'success');
                } else {
                    showMessage(response.error || 'Failed to save permissions', 'danger');
                }
            },
            error: function() {
                showMessage('Failed to save permissions (network error)', 'danger');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="bi bi-save"></i> Save Permissions');
            }
        });
    }

    /**
     * Preview system prompt
     */
    function previewPrompt() {
        const btn = $('#previewPromptBtn');
        btn.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Loading...');

        $.ajax({
            url: '/ai/admin/aiFeaturePermissionsCode.php',
            type: 'POST',
            data: {
                action: 'previewPrompt',
                featureId: currentFeatureId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#promptPreviewContent').text(response.prompt);
                    $('#promptPreviewModal').modal('show');
                } else {
                    showMessage(response.error || 'Failed to load prompt preview', 'danger');
                }
            },
            error: function() {
                showMessage('Failed to load prompt preview (network error)', 'danger');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="bi bi-eye"></i> Preview System Prompt');
            }
        });
    }

    /**
     * Show message
     */
    function showMessage(message, type) {
        const $msg = $('#saveMessage');
        $msg.removeClass('d-none alert-success alert-danger alert-warning alert-info')
            .addClass(`alert-${type}`)
            .html(message)
            .fadeIn();

        setTimeout(function() {
            $msg.fadeOut();
        }, 5000);
    }

});
