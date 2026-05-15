<?php
// ============================================================================
// AUTHENTICATION SYSTEM - REQUIRED FOR SECURITY
// ============================================================================
// These 3 lines are REQUIRED for the centralized auth system to work:
// 1. Include auth functions (provides login/logout/permission checking)
// 2. Include navbar user component (provides user display and settings modal)
// 3. Check program access (validates user is logged in, redirects to login if not)
// ============================================================================
include_once $_SERVER['DOCUMENT_ROOT'] . '/auth/includes/auth_functions.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/auth/includes/navbar_user.php';
$user = auth_checkProgramAccess('ai', 'GUEST'); // Will redirect to /auth/login.php if not authenticated
// ============================================================================
// END AUTHENTICATION INCLUDES
// ============================================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="/ai/ai-icon.svg" />
    <title><?php echo isset($pageTitle) ? $pageTitle : 'AI Central System'; ?></title>

    <!-- Bootstrap 5.3.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <!-- jQuery (required for AI Central JavaScript) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Set APP_PROGRAM_ID before loading auth-monitor.js -->
    <script>window.APP_PROGRAM_ID = "AI";</script>

    <!-- Auth Monitor - Centralized auth component - Must load BEFORE other scripts -->
    <script src="/auth/includes/auth-monitor.js"></script>

    <!-- Chart.js for dashboards -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <!-- Common AI Central CSS -->
    <link rel="stylesheet" href="/ai/common/common_ai.css">

    <!-- Common AI Central JavaScript -->
    <script src="/ai/common/common_ai.js"></script>

    <?php if (isset($additionalCSS)) echo $additionalCSS; ?>

    <style>
        body {
            padding-top: 70px;
            background: #f8f9fa;
        }
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
        }
        .navbar.bg-primary {
            background-color: #0d6efd !important;
        }
    </style>
</head>
<body>

<?php
// ============================================================================
// AUTHENTICATION SYSTEM - User Settings & Linked Accounts Modals
// ============================================================================
// This outputs the user settings modal and linked accounts modal HTML
// Must be called OUTSIDE the navbar (typically right after <body> tag)
// ============================================================================
echo getNavbarUserModalsHTML('ai');
// ============================================================================
?>

    <!-- Bootstrap Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="/ai/admin/aicentral_dashboardHTML.php">
                <i class="bi bi-robot"></i> AI Central
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php if (in_array($user['user_level'], ['ADMIN', 'SUPERADMIN'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/ai/admin/aicentral_featuresHTML.php">
                            <i class="bi bi-stars"></i> Features
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="modelsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-cpu"></i> Models
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="modelsDropdown">
                            <li>
                                <a class="dropdown-item" href="/ai/admin/aicentral_modelsHTML.php">
                                    <i class="bi bi-cpu"></i> Models
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="/ai/admin/aicentral_model_capabilitiesHTML.php">
                                    <i class="bi bi-gear-fill"></i> Model Capabilities
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="/ai/admin/compare/">
                                    <i class="bi bi-shuffle"></i> Compare Models
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/ai/admin/aicentral_costAnalysisHTML.php">
                            <i class="bi bi-graph-up"></i> Cost Analysis
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/ai/admin/aicentral_usageHTML.php">
                            <i class="bi bi-bar-chart-line"></i> Usage Analysis
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-gear"></i> Admin
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                            <li>
                                <a class="dropdown-item" href="/ai/admin/aicentral_tiersHTML.php">
                                    <i class="bi bi-layers"></i> Tiers
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="/ai/admin/aicentral_usersHTML.php">
                                    <i class="bi bi-people"></i> Users
                                </a>
                            </li>

                            <li><a class="dropdown-item" href="/ai/admin/aicentral_settingsHTML.php"><i class="bi bi-sliders"></i> Settings</a></li>
                            <li><a class="dropdown-item" href="/ai/admin/aicentral_lookupsHTML.php"><i class="bi bi-list-ul"></i> Lookups</a></li>
                            <li><a class="dropdown-item" href="/ai/admin/aiFeaturePermissionsHTML.php"><i class="bi bi-shield-lock"></i> Feature Permissions</a></li>
                            <li><a class="dropdown-item" href="/ai/admin/aiDatabaseCredentialsHTML.php"><i class="bi bi-database-lock"></i> DB Credentials</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>

                <!-- ================================================================ -->
                <!-- AUTHENTICATION SYSTEM - User Display in Navbar                   -->
                <!-- ================================================================ -->
                <div class="navbar-nav ms-auto">
                    <!-- ============================================================ -->
                    <!-- AUTHENTICATION SYSTEM - This line displays:                  -->
                    <!-- - User name and avatar when logged in                        -->
                    <!-- - Settings dropdown with "My Settings", "Linked Accounts"   -->
                    <!-- - AI-specific menu items (API Keys, Preferences, Usage)      -->
                    <!-- - Logout button                                              -->
                    <!-- ============================================================ -->
                    <?php echo getNavbarUserHTML('ai', [
                        'ai_menu_items' => [
                            ['icon' => 'bi-key', 'text' => 'AI - API Keys', 'url' => '/ai/user/aicentral_apiKeysHTML.php'],
                            ['icon' => 'bi-sliders2', 'text' => 'AI - Preferences', 'url' => '/ai/user/aicentral_preferencesHTML.php'],
                            ['icon' => 'bi-bar-chart', 'text' => 'AI - Usage', 'url' => '/ai/user/aicentral_usageDashboardHTML.php']
                        ]
                    ]); ?>
                    <!-- ============================================================ -->
                </div>
                <!-- END AUTHENTICATION NAVBAR SECTION -->
                <!-- ================================================================ -->
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
