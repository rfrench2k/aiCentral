<?php
/**
 * AI Deep Insights - Common Library
 * Provides AI-powered database analysis using Claude Code CLI
 *
 * This class handles:
 * - Permission checking and enforcement
 * - Database credential selection and decryption
 * - System prompt building with security rules
 * - Claude CLI execution with JSON output
 * - Usage tracking to AI Central
 * - Tier limit enforcement
 * - Temp file management
 *
 * @author AI Deep Insights System
 * @version 1.0.0
 * @date 2025-11-16
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/KeyManager.php';
require_once __DIR__ . '/../common/aPRIV_DB_AI.php';
require_once __DIR__ . '/../common/common_ai.php';

class AIDeepInsights {

    // Configuration
    private $featureCode;
    private $userId;
    private $programId;
    private $databaseName;

    // Database connection
    private $conn;

    // Cached data
    private $permissions = null;
    private $featureId = null;
    private $dbCredentials = null;

    // Temp directory
    private $tempDir;

    /**
     * Constructor
     *
     * @param string $featureCode Feature code (e.g., 'deep_insights')
     * @param string $userId User ID
     * @param string $programId Program ID (the calling app's program code)
     * @param string $databaseName Database name to introspect for the insight query
     */
    public function __construct($featureCode, $userId, $programId, $databaseName) {
        $this->featureCode = $featureCode;
        $this->userId = $userId;
        $this->programId = $programId;
        $this->databaseName = $databaseName;

        // Set temp directory
        $this->tempDir = $_SERVER['DOCUMENT_ROOT'] . '/xxtemp/' . strtolower($programId) . '/' . strtolower($featureCode) . '/';

        // Create temp directory if it doesn't exist
        if (!file_exists($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }

        // Get database connection to aicore
        $this->conn = $this->getAICoreConnection();

        // Get feature_id
        $this->featureId = $this->getFeatureId();

        aiCentral_logMessage("AIDeepInsights initialized: feature={$featureCode}, user={$userId}, program={$programId}", 'INFO');
    }

    /**
     * Get database connection to aicore
     *
     * @return mysqli
     */
    private function getAICoreConnection() {
        $conn = ai_getDBConnection();

        if (!$conn) {
            aiCentral_logMessage("Failed to connect to aicore", 'ERROR');
            throw new Exception("Database connection failed");
        }

        return $conn;
    }

    /**
     * Get feature_id from feature_code
     *
     * @return int
     */
    private function getFeatureId() {
        $sql = "SELECT feature_id FROM ai_features WHERE program_id = ? AND feature_code = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ss', $this->programId, $this->featureCode);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return $row['feature_id'];
        }

        throw new Exception("Feature not found: {$this->featureCode}");
    }

    /**
     * Check feature permissions
     * Returns array of enabled permissions
     *
     * @return array Associative array of permission_type => is_enabled
     */
    private function checkPermissions() {
        // Return cached if available
        if ($this->permissions !== null) {
            return $this->permissions;
        }

        $sql = "SELECT permission_type, is_enabled FROM ai_feature_permissions WHERE feature_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $this->featureId);
        $stmt->execute();
        $result = $stmt->get_result();

        $permissions = [];
        while ($row = $result->fetch_assoc()) {
            $permissions[$row['permission_type']] = (bool)$row['is_enabled'];
        }

        // Cache for this request
        $this->permissions = $permissions;

        aiCentral_logMessage("Permissions loaded: " . json_encode($permissions), 'DEBUG');

        return $permissions;
    }

    /**
     * Select and decrypt database credentials based on permissions
     *
     * @return array ['host' => string, 'username' => string, 'password' => string]
     */
    private function selectDatabaseCredentials() {
        // Return cached if available
        if ($this->dbCredentials !== null) {
            return $this->dbCredentials;
        }

        $permissions = $this->checkPermissions();

        // Determine permission level needed
        $permissionLevel = 'readonly'; // Default to most restrictive

        if (!empty($permissions['allow_db_delete']) && $permissions['allow_db_delete']) {
            $permissionLevel = 'full';
        } elseif (!empty($permissions['allow_db_write']) && $permissions['allow_db_write']) {
            $permissionLevel = 'readwrite';
        }

        // Get credentials from database
        $sql = "SELECT db_host, db_username, encrypted_password, encryption_iv
                FROM ai_database_credentials
                WHERE database_name = ? AND permission_level = ? AND is_active = 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ss', $this->databaseName, $permissionLevel);
        $stmt->execute();
        $result = $stmt->get_result();

        if (!$row = $result->fetch_assoc()) {
            throw new Exception("No credentials found for database: {$this->databaseName} with permission level: {$permissionLevel}");
        }

        // Decrypt password
        $keyManager = new KeyManager();
        $password = $keyManager->decryptKey($row['encrypted_password'], $row['encryption_iv']);

        if ($password === false) {
            throw new Exception("Failed to decrypt database password");
        }

        $credentials = [
            'host' => $row['db_host'],
            'username' => $row['db_username'],
            'password' => $password,
            'permission_level' => $permissionLevel
        ];

        // Cache for this request
        $this->dbCredentials = $credentials;

        aiCentral_logMessage("Database credentials selected: level={$permissionLevel}, user={$credentials['username']}", 'DEBUG');

        return $credentials;
    }

    /**
     * Build system prompt with security rules and permissions
     *
     * @param string $userQuestion User's question
     * @param array $conversationHistory Previous conversation messages
     * @return string Complete system prompt
     */
    private function buildSystemPrompt($userQuestion, $conversationHistory = []) {
        $permissions = $this->checkPermissions();
        $credentials = $this->selectDatabaseCredentials();

        $prompt = "You are a database analysis assistant with access to the {$this->databaseName} database. ";
        $prompt .= "The working directory is " . $_SERVER['DOCUMENT_ROOT'] . "/{$this->programId}\n\n";

        // Add conversation history if exists
        if (!empty($conversationHistory)) {
            $prompt .= "CONVERSATION HISTORY (for context):\n";
            foreach ($conversationHistory as $msg) {
                if ($msg['role'] === 'user') {
                    $prompt .= "User: \"" . $msg['question'] . "\"\n";
                } else {
                    // Include text answer from assistant for context
                    $answer = isset($msg['text_answer']) ? $msg['text_answer'] : $msg['textAnswer'];
                    $prompt .= "Assistant: \"" . substr($answer, 0, 500) . (strlen($answer) > 500 ? '...' : '') . "\"\n";
                }
            }
            $prompt .= "\n";
        }

        $prompt .= "CURRENT QUESTION:\n";
        $prompt .= "User: \"$userQuestion\"\n\n";

        // CRITICAL SECURITY RULE
        $prompt .= "CRITICAL SECURITY RULE - THE MOST IMPORTANT INSTRUCTION:\n";
        $prompt .= "=================================================================\n";
        $prompt .= "THE LOGGED-IN USER IS: {$this->userId}\n\n";
        $prompt .= "SECURITY RULE (FOLLOW THIS FOR EVERY QUERY):\n";
        $prompt .= "IF a table has a 'user_id' column, YOU MUST ALWAYS filter by: WHERE user_id = '{$this->userId}'\n";
        $prompt .= "This applies to ANY table with a user_id field - no exceptions!\n\n";
        $prompt .= "WHY: Tables with user_id contain personal data. Querying without the filter would expose other users' private data.\n";
        $prompt .= "=================================================================\n\n";

        // Database access instructions
        $prompt .= "DATABASE ACCESS:\n";
        $prompt .= "You have {$credentials['permission_level']} access to the {$this->databaseName} database.\n";
        $prompt .= "Use this command to query the database:\n";
        $prompt .= "   php \"" . $_SERVER['DOCUMENT_ROOT'] . "/{$this->programId}/db-query.php\" \"YOUR_SQL_QUERY_HERE\"\n\n";

        // Permission-specific warnings
        if ($permissions['allow_db_read']) {
            $prompt .= "✓ You CAN read data (SELECT queries)\n";
        }
        if ($permissions['allow_db_write']) {
            $prompt .= "⚠ You CAN write data (INSERT, UPDATE) - use with caution\n";
        } else {
            $prompt .= "✗ You CANNOT write data (INSERT, UPDATE not allowed)\n";
        }
        if ($permissions['allow_db_delete']) {
            $prompt .= "⚠⚠ You CAN delete data (DELETE, DROP, TRUNCATE) - EXTREME CAUTION REQUIRED\n";
        } else {
            $prompt .= "✗ You CANNOT delete data (DELETE, DROP, TRUNCATE not allowed)\n";
        }
        if ($permissions['allow_temp_tables']) {
            $prompt .= "✓ You CAN create temporary tables for complex analysis\n";
        }

        $prompt .= "\nHOW TO CHECK IF A TABLE HAS user_id:\n";
        $prompt .= "Before querying any table, check its structure:\n";
        $prompt .= "   php db-query.php \"DESCRIBE table_name\"\n";
        $prompt .= "If the output shows a 'user_id' column, you MUST filter by it.\n\n";

        // Output format instructions
        $prompt .= "YOUR RESPONSE FORMAT:\n";
        $prompt .= "Your FINAL response must be ONLY a valid JSON object in this format:\n";
        $prompt .= "{\n";
        $prompt .= "    \"textAnswer\": \"Natural language summary of the answer\",\n";
        $prompt .= "    \"chartData\": {\n";
        $prompt .= "        \"type\": \"bar|pie|line|doughnut\",\n";
        $prompt .= "        \"title\": \"Chart Title\",\n";
        $prompt .= "        \"labels\": [\"Label1\", \"Label2\"],\n";
        $prompt .= "        \"datasets\": [{\"label\": \"Value\", \"data\": [100, 200], \"backgroundColor\": [\"#0d6efd\", \"#198754\"]}],\n";
        $prompt .= "        \"formatCurrency\": true|false\n";
        $prompt .= "    },\n";
        $prompt .= "    \"tableData\": {\n";
        $prompt .= "        \"headers\": [\"Column1\", \"Column2\"],\n";
        $prompt .= "        \"rows\": [[\"row1col1\", \"row1col2\"], [\"row2col1\", \"row2col2\"]]\n";
        $prompt .= "    }\n";
        $prompt .= "}\n\n";

        $prompt .= "IMPORTANT:\n";
        $prompt .= "- Include chartData for visual data (optional)\n";
        $prompt .= "- Include tableData for detailed listings (optional)\n";
        $prompt .= "- Use colors: blues/greens for positive, reds for negative\n";
        $prompt .= "- Format currency as \$1,234.56 in textAnswer\n";
        $prompt .= "- If no data found, provide helpful explanation in textAnswer\n";
        $prompt .= "- Use conversation history to provide coherent responses\n";
        $prompt .= "- Your response will be parsed as JSON, so ensure valid JSON syntax\n";

        return $prompt;
    }

    /**
     * Build allowed tools list for Claude CLI based on permissions
     *
     * @return string Comma-separated list of allowed tools
     */
    private function buildAllowedTools() {
        $permissions = $this->checkPermissions();
        $tools = [];

        // Database access requires Bash tool
        if (!empty($permissions['allow_db_read']) && $permissions['allow_db_read']) {
            $tools[] = 'Bash';
        }

        // Web access
        if (!empty($permissions['allow_web_search']) && $permissions['allow_web_search']) {
            $tools[] = 'WebSearch';
        }
        if (!empty($permissions['allow_web_fetch']) && $permissions['allow_web_fetch']) {
            $tools[] = 'WebFetch';
        }

        // File access
        if (!empty($permissions['allow_file_read']) && $permissions['allow_file_read']) {
            $tools[] = 'Read';
            $tools[] = 'Grep';
            $tools[] = 'Glob';
        }
        if (!empty($permissions['allow_file_write']) && $permissions['allow_file_write']) {
            $tools[] = 'Write';
            $tools[] = 'Edit';
        }

        // Always return at least one tool, or Claude will error
        if (empty($tools)) {
            aiCentral_logMessage("No permissions enabled! Allowing only Read tool as fallback.", 'WARNING');
            $tools[] = 'Read'; // Minimal access
        }

        $toolList = implode(',', $tools);
        aiCentral_logMessage("Allowed tools: {$toolList}", 'DEBUG');

        return $toolList;
    }

    /**
     * Execute Claude CLI with prompt and return JSON output
     *
     * @param string $prompt Full system prompt
     * @return string JSON output from Claude
     */
    private function executeClaude($prompt) {
        // Create temp prompt file
        $promptFile = $this->tempDir . 'prompt_' . time() . '_' . uniqid() . '.txt';
        file_put_contents($promptFile, $prompt);

        aiCentral_logMessage("Created prompt file: {$promptFile}", 'DEBUG');

        // Build Claude command using shell_exec with < file redirect. CLI path
        // is configurable via AICORE_CLAUDE_CLI_PATH (default 'claude').
        $allowedTools = $this->buildAllowedTools();
        $cliPath = getenv('AICORE_CLAUDE_CLI_PATH') ?: 'claude';
        $command = escapeshellarg($cliPath)
                 . " --print --output-format json --dangerously-skip-permissions"
                 . " --allowed-tools {$allowedTools}"
                 . " < " . escapeshellarg($promptFile) . " 2>&1";

        aiCentral_logMessage("Executing Claude CLI: {$command}", 'DEBUG');

        // Point HOME/USERPROFILE/APPDATA at the directory where Claude CLI
        // credentials live. IIS runs as IUSR which has no home directory, so
        // AICORE_CLAUDE_CLI_HOME must be set to a directory containing the
        // Claude credentials (.claude/.credentials.json with valid OAuth).
        $authHome = getenv('AICORE_CLAUDE_CLI_HOME') ?: ($_SERVER['DOCUMENT_ROOT'] ?? dirname(dirname(dirname(__DIR__))));

        $oldHome = getenv('HOME');
        $oldUserProfile = getenv('USERPROFILE');
        $oldAppData = getenv('APPDATA');

        putenv("HOME=$authHome");
        putenv("USERPROFILE=$authHome");
        putenv("APPDATA=$authHome");

        // Execute command
        $startTime = microtime(true);
        $output = shell_exec($command);
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000);

        // Restore environment variables
        $this->restoreEnvVars($oldHome, $oldUserProfile, $oldAppData);

        aiCentral_logMessage("Claude execution completed in {$duration}ms, output length: " . strlen($output ?? ''), 'DEBUG');

        // Delete prompt file
        if (file_exists($promptFile)) {
            unlink($promptFile);
        }

        // Check if command executed successfully
        if ($output === null || empty(trim($output))) {
            throw new Exception('Claude CLI returned empty response');
        }

        return $output;
    }

    /**
     * Restore environment variables after Claude execution
     */
    private function restoreEnvVars($oldHome, $oldUserProfile, $oldAppData) {
        if ($oldHome !== false) {
            putenv("HOME=$oldHome");
        } else {
            putenv("HOME");
        }
        if ($oldUserProfile !== false) {
            putenv("USERPROFILE=$oldUserProfile");
        } else {
            putenv("USERPROFILE");
        }
        if ($oldAppData !== false) {
            putenv("APPDATA=$oldAppData");
        } else {
            putenv("APPDATA");
        }
    }

    /**
     * Extract structured JSON from Claude result text.
     * Claude often includes thinking/reasoning text before the JSON, or wraps it in
     * markdown code blocks. This method tries multiple strategies to extract the JSON.
     *
     * @param string $result Raw result text from Claude
     * @return array|null Parsed JSON array with textAnswer key, or null if not found
     */
    private function extractJsonFromResult($result) {
        if (empty($result)) {
            return null;
        }

        // Strategy 1: Direct JSON decode (clean response - no thinking text)
        $decoded = json_decode($result, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && isset($decoded['textAnswer'])) {
            aiCentral_logMessage("JSON extraction: direct decode succeeded", 'DEBUG');
            return $decoded;
        }

        // Strategy 2: Extract from markdown ```json ... ``` or ``` ... ``` code blocks
        // Use greedy match to get the LAST/largest code block (most likely the final response)
        if (preg_match_all('/```(?:json)?\s*(\{.+?\})\s*```/s', $result, $allMatches)) {
            // Try each match in reverse order (last code block is most likely the final answer)
            $matches = array_reverse($allMatches[1]);
            foreach ($matches as $jsonCandidate) {
                $decoded = json_decode($jsonCandidate, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && isset($decoded['textAnswer'])) {
                    aiCentral_logMessage("JSON extraction: markdown code block succeeded", 'DEBUG');
                    return $decoded;
                }
            }
        }

        // Strategy 3: Find JSON object containing "textAnswer" using brace matching
        // Search for the last occurrence of "textAnswer" and walk backward to find opening brace
        $searchKey = '"textAnswer"';
        $lastPos = strrpos($result, $searchKey);
        if ($lastPos !== false) {
            // Walk backward to find the opening brace
            $braceStart = strrpos(substr($result, 0, $lastPos), '{');
            if ($braceStart !== false) {
                // Find matching closing brace using depth counting
                $depth = 0;
                $len = strlen($result);
                $braceEnd = -1;
                for ($i = $braceStart; $i < $len; $i++) {
                    $char = $result[$i];
                    if ($char === '{') $depth++;
                    if ($char === '}') {
                        $depth--;
                        if ($depth === 0) {
                            $braceEnd = $i;
                            break;
                        }
                    }
                }

                if ($braceEnd > $braceStart) {
                    $jsonStr = substr($result, $braceStart, $braceEnd - $braceStart + 1);
                    $decoded = json_decode($jsonStr, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && isset($decoded['textAnswer'])) {
                        aiCentral_logMessage("JSON extraction: brace matching succeeded", 'DEBUG');
                        return $decoded;
                    }
                }
            }
        }

        aiCentral_logMessage("JSON extraction: all strategies failed, result starts with: " . substr($result, 0, 200), 'WARNING');
        return null;
    }

    /**
     * Parse Claude CLI JSON response
     *
     * @param string $jsonOutput JSON output from Claude
     * @return array Parsed response with tokens, cost, result
     */
    private function parseClaudeResponse($jsonOutput) {
        $json = json_decode($jsonOutput, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            aiCentral_logMessage("Failed to parse Claude JSON: " . json_last_error_msg(), 'ERROR');
            throw new Exception('Invalid JSON response from Claude: ' . json_last_error_msg());
        }

        // Extract usage data
        $inputTokens = $json['usage']['input_tokens'] ?? 0;
        $outputTokens = $json['usage']['output_tokens'] ?? 0;
        $cacheCreationTokens = $json['usage']['cache_creation_input_tokens'] ?? 0;
        $cacheReadTokens = $json['usage']['cache_read_input_tokens'] ?? 0;
        $totalCost = $json['total_cost_usd'] ?? 0.0;
        $duration = $json['duration_ms'] ?? 0;

        // Extract model usage - can have multiple models!
        $modelUsage = $json['modelUsage'] ?? [];
        $primaryModel = !empty($modelUsage) ? array_keys($modelUsage)[0] : 'unknown';

        // Extract result
        $result = $json['result'] ?? '';

        // Try to extract structured JSON from result (handles thinking text, code blocks, etc.)
        $resultJson = $this->extractJsonFromResult($result);
        if ($resultJson !== null) {
            $parsedResult = [
                'textAnswer' => $resultJson['textAnswer'] ?? 'No answer provided',
                'chartData' => $resultJson['chartData'] ?? null,
                'tableData' => $resultJson['tableData'] ?? null
            ];
        } else {
            // Plain text result - no structured JSON found
            $parsedResult = [
                'textAnswer' => $result,
                'chartData' => null,
                'tableData' => null
            ];
        }

        return [
            'result' => $parsedResult,
            'tokens' => [
                'input' => $inputTokens,
                'output' => $outputTokens,
                'cache_creation' => $cacheCreationTokens,
                'cache_read' => $cacheReadTokens,
                'total' => $inputTokens + $outputTokens
            ],
            'cost' => $totalCost,
            'duration_ms' => $duration,
            'model_used' => $primaryModel,
            'model_usage_details' => $modelUsage
        ];
    }

    /**
     * Log usage to ai_usage_log table
     *
     * @param array $usageData Usage data from parseClaudeResponse
     * @param string $prompt Original prompt
     * @param string $response Response text
     * @return void
     */
    private function logUsage($usageData, $prompt, $response) {
        try {
            // Get provider_id for claude_cli
            $sql = "SELECT provider_id FROM ai_providers WHERE provider_code = 'claude_cli'";
            $result = $this->conn->query($sql);
            $row = $result->fetch_assoc();
            $providerId = $row['provider_id'];

            // Get model_id from model_code
            $modelCode = $usageData['model_used'];
            $sql = "SELECT model_id FROM ai_models WHERE provider_id = ? AND model_code = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param('is', $providerId, $modelCode);
            $stmt->execute();
            $result = $stmt->get_result();

            $modelId = null;
            if ($row = $result->fetch_assoc()) {
                $modelId = $row['model_id'];
            }

            // Auto-insert model if not found
            if ($modelId === null && !empty($modelCode)) {
                $sql = "INSERT INTO ai_models (provider_id, model_code, model_display_name, is_active, input_cost_per_million, output_cost_per_million, pricing_effective_date) VALUES (?, ?, ?, 1, 0, 0, CURDATE())";
                $stmt = $this->conn->prepare($sql);
                $modelDisplayName = $modelCode;
                $stmt->bind_param('iss', $providerId, $modelCode, $modelDisplayName);
                $stmt->execute();
                $modelId = $this->conn->insert_id;
                aiCentral_logMessage("Auto-inserted new model: {$modelCode} (id={$modelId})", 'INFO');
            }

            // Calculate costs
            $totalCost = $usageData['cost'];
            $inputCost = $totalCost * ($usageData['tokens']['input'] / max(1, $usageData['tokens']['total']));
            $outputCost = $totalCost * ($usageData['tokens']['output'] / max(1, $usageData['tokens']['total']));

            // Insert into ai_usage_log
            $sql = "INSERT INTO ai_usage_log (
                user_id, program_id, feature_code, page_url,
                provider_id, model_id, key_type, key_id,
                input_tokens, output_tokens,
                input_cost_usd, output_cost_usd,
                response_time_ms,
                status, prompt_text, response_text, request_metadata
            ) VALUES (?, ?, ?, ?, ?, ?, 'system', NULL, ?, ?, ?, ?, ?, 'success', ?, ?, ?)";

            $pageUrl = $_SERVER['REQUEST_URI'] ?? '';
            $requestMetadata = json_encode([
                'model_usage' => $usageData['model_usage_details'],
                'cache_creation_tokens' => $usageData['tokens']['cache_creation'],
                'cache_read_tokens' => $usageData['tokens']['cache_read']
            ]);

            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param(
                'ssssiiiiiddsss',
                $this->userId,
                $this->programId,
                $this->featureCode,
                $pageUrl,
                $providerId,
                $modelId,
                $usageData['tokens']['input'],
                $usageData['tokens']['output'],
                $inputCost,
                $outputCost,
                $usageData['duration_ms'],
                $prompt,
                $response,
                $requestMetadata
            );

            if ($stmt->execute()) {
                aiCentral_logMessage("Usage logged to ai_usage_log: tokens={$usageData['tokens']['total']}, cost=\${$totalCost}", 'INFO');
            } else {
                aiCentral_logMessage("Failed to log usage: " . $stmt->error, 'ERROR');
            }
        } catch (Exception $e) {
            // Never let logging failures kill the response
            aiCentral_logMessage("Usage logging failed (non-fatal): " . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Check if user is within tier limits
     *
     * @return array ['allowed' => bool, 'message' => string, 'warning' => bool]
     */
    private function checkTierLimits() {
        // Get user's tier
        $sql = "SELECT tier_id FROM user_tier_assignments WHERE user_id = ? AND (program_id = ? OR program_id IS NULL) ORDER BY program_id DESC LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ss', $this->userId, $this->programId);
        $stmt->execute();
        $result = $stmt->get_result();

        if (!$row = $result->fetch_assoc()) {
            return [
                'allowed' => false,
                'message' => 'No tier assigned. Please contact administrator.',
                'warning' => false
            ];
        }

        $tierId = $row['tier_id'];

        // Get tier limits
        $sql = "SELECT tier_code, daily_request_limit, monthly_request_limit FROM ai_tiers WHERE tier_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $tierId);
        $stmt->execute();
        $result = $stmt->get_result();
        $tier = $result->fetch_assoc();

        // Unlimited tier
        if ($tier['daily_request_limit'] === null && $tier['monthly_request_limit'] === null) {
            return [
                'allowed' => true,
                'message' => 'Unlimited tier',
                'warning' => false
            ];
        }

        // Count today's requests
        $sql = "SELECT COUNT(*) as count FROM ai_usage_log
                WHERE user_id = ? AND program_id = ?
                AND DATE(request_timestamp) = CURDATE()";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ss', $this->userId, $this->programId);
        $stmt->execute();
        $result = $stmt->get_result();
        $dailyCount = $result->fetch_assoc()['count'];

        // Count this month's requests
        $sql = "SELECT COUNT(*) as count FROM ai_usage_log
                WHERE user_id = ? AND program_id = ?
                AND YEAR(request_timestamp) = YEAR(NOW())
                AND MONTH(request_timestamp) = MONTH(NOW())";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ss', $this->userId, $this->programId);
        $stmt->execute();
        $result = $stmt->get_result();
        $monthlyCount = $result->fetch_assoc()['count'];

        // Check daily limit
        if ($tier['daily_request_limit'] !== null) {
            if ($dailyCount >= $tier['daily_request_limit']) {
                return [
                    'allowed' => false,
                    'message' => "Daily limit reached ({$dailyCount}/{$tier['daily_request_limit']}). Resets at midnight.",
                    'warning' => false
                ];
            }

            // Warning at 80%
            if ($dailyCount >= ($tier['daily_request_limit'] * 0.8)) {
                return [
                    'allowed' => true,
                    'message' => "Warning: {$dailyCount}/{$tier['daily_request_limit']} daily requests used (80%+)",
                    'warning' => true
                ];
            }
        }

        // Check monthly limit
        if ($tier['monthly_request_limit'] !== null) {
            if ($monthlyCount >= $tier['monthly_request_limit']) {
                return [
                    'allowed' => false,
                    'message' => "Monthly limit reached ({$monthlyCount}/{$tier['monthly_request_limit']}). Resets next month.",
                    'warning' => false
                ];
            }

            // Warning at 80%
            if ($monthlyCount >= ($tier['monthly_request_limit'] * 0.8)) {
                return [
                    'allowed' => true,
                    'message' => "Warning: {$monthlyCount}/{$tier['monthly_request_limit']} monthly requests used (80%+)",
                    'warning' => true
                ];
            }
        }

        return [
            'allowed' => true,
            'message' => "Within limits: daily {$dailyCount}/{$tier['daily_request_limit']}, monthly {$monthlyCount}/{$tier['monthly_request_limit']}",
            'warning' => false
        ];
    }

    /**
     * Clean up old temp files
     *
     * @return void
     */
    private function manageTempFiles() {
        if (!file_exists($this->tempDir)) {
            return;
        }

        $files = glob($this->tempDir . '*');
        $deletedCount = 0;
        $oneHourAgo = time() - 3600;

        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $oneHourAgo) {
                unlink($file);
                $deletedCount++;
            }
        }

        if ($deletedCount > 0) {
            aiCentral_logMessage("Cleaned up {$deletedCount} old temp files from {$this->tempDir}", 'DEBUG');
        }
    }

    /**
     * Main method: Ask database question with AI analysis
     *
     * @param string $question User's question
     * @param array $conversationHistory Previous messages
     * @param array $options Optional parameters
     * @return array ['success' => bool, 'data' => array, 'error' => string]
     */
    public function askDatabaseQuestion($question, $conversationHistory = [], $options = []) {
        try {
            // Check tier limits FIRST
            $tierCheck = $this->checkTierLimits();
            if (!$tierCheck['allowed']) {
                return [
                    'success' => false,
                    'error' => $tierCheck['message'],
                    'data' => null
                ];
            }

            // Log warning if approaching limit
            if ($tierCheck['warning']) {
                aiCentral_logMessage($tierCheck['message'], 'WARNING');
            }

            // Check permissions
            $permissions = $this->checkPermissions();
            if (empty($permissions['allow_db_read']) || !$permissions['allow_db_read']) {
                return [
                    'success' => false,
                    'error' => 'Database read permission not enabled for this feature',
                    'data' => null
                ];
            }

            // Build system prompt
            $prompt = $this->buildSystemPrompt($question, $conversationHistory);

            // Execute Claude
            $output = $this->executeClaude($prompt);

            // Parse response
            $parsed = $this->parseClaudeResponse($output);

            // Log usage
            $this->logUsage($parsed, $prompt, json_encode($parsed['result']));

            // Clean up old temp files
            $this->manageTempFiles();

            // Return result
            return [
                'success' => true,
                'data' => [
                    'textAnswer' => $parsed['result']['textAnswer'],
                    'chartData' => $parsed['result']['chartData'],
                    'tableData' => $parsed['result']['tableData'],
                    'tokens' => $parsed['tokens'],
                    'cost' => $parsed['cost'],
                    'responseTime' => $parsed['duration_ms'],
                    'tierWarning' => $tierCheck['warning'] ? $tierCheck['message'] : null
                ],
                'error' => null
            ];

        } catch (Exception $e) {
            aiCentral_logMessage("AIDeepInsights error: " . $e->getMessage(), 'ERROR');

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Destructor - close database connection
     */
    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}
?>
