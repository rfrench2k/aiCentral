<?php
/**
 * AI Central Admin Settings - HTML
 */
$pageTitle = 'AI Central - System Settings';


require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/header_ai.php';
?>

<link rel="stylesheet" href="/ai/admin/aicentral_settings.css">

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h2 mb-0">
                    <i class="bi bi-sliders"></i> AI Central - System Settings
                </h1>
                <button class="btn btn-primary" onclick="aiCentral_saveAllSettings()">
                    <i class="bi bi-save"></i> Save All Settings
                </button>
            </div>
        </div>
    </div>

    <!-- Settings Container -->
    <div class="row">
        <div class="col-12">
            <div id="aicentral-settings-list">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Loading settings...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/ai/admin/aicentral_settings.js"></script>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/footer.html'; ?>
