<?php
/**
 * AI Central User Preferences - HTML
 */
$pageTitle = 'My AI Preferences - AI Central';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/header_ai.php';
?>

<div class="container my-4">
    <!-- Page Header -->
    <div class="mb-4">
        <h1 class="display-5 fw-bold mb-2">My AI Preferences</h1>
        <p class="text-secondary mb-0">Customize your AI experience with default models and feature settings</p>
    </div>

    <!-- Save Button -->
    <div class="mb-4">
        <button class="btn btn-primary btn-lg" onclick="aiCentral_saveAllPreferences()">
            <i class="bi bi-save"></i> Save All Preferences
        </button>
    </div>

    <!-- Preferences Groups -->
    <div id="aicentral-preferences-list">
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="text-secondary mt-3">Loading preferences...</p>
        </div>
    </div>
</div>

<script src="/ai/user/aicentral_preferences.js"></script>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/footer.html'; ?>
