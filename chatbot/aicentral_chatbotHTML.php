<?php
/**
 * AI Central Universal Chatbot - HTML Component
 * Include this file in any app to add chatbot functionality
 *
 * Usage:
 * <?php include $_SERVER['DOCUMENT_ROOT'] . '/ai/chatbot/aicentral_chatbotHTML.php'; ?>
 * <script>
 * aiCentral_initChatbot({
 *     programId: 'MYAPP',
 *     chatTypeCode: 'general_assistant',
 *     userId: '<?= $userId ?>',
 *     style: 'sidebar', // or 'floating'
 *     pageContext: 'My Page',
 *     pageData: { item_id: 123 }
 * });
 * </script>
 */

// Get chatbot configuration from parameters or use defaults
$chatbotStyle = $_GET['chatbot_style'] ?? 'floating'; // Default to floating
?>

<link rel="stylesheet" href="/ai/chatbot/aicentral_chatbot.css">

<!-- AI Central Chatbot Container -->
<div id="aicentral-chatbot-container" class="aicentral-chatbot-<?php echo $chatbotStyle; ?>" style="display: none;">

    <!-- Sidebar Style -->
    <div id="aicentral-chatbot-sidebar" class="aicentral-chatbot-sidebar" style="display: none;">
        <div class="aicentral-chatbot-header">
            <h3 class="aicentral-chatbot-title">AI Assistant</h3>
            <div class="aicentral-chatbot-header-actions">
                <button class="aicentral-chatbot-btn-icon" id="aicentral-chatbot-new-conversation" title="New Conversation">
                    <span>+</span>
                </button>
                <button class="aicentral-chatbot-btn-icon" id="aicentral-chatbot-toggle-sidebar" title="Toggle Conversations">
                    <span>☰</span>
                </button>
            </div>
        </div>

        <div class="aicentral-chatbot-layout">
            <!-- Conversations List -->
            <div class="aicentral-chatbot-conversations-panel" id="aicentral-chatbot-conversations-panel">
                <div class="aicentral-chatbot-conversations-header">
                    <h4>Conversations</h4>
                </div>
                <div class="aicentral-chatbot-conversations-list" id="aicentral-chatbot-conversations-list">
                    <!-- Conversations loaded dynamically -->
                </div>
            </div>

            <!-- Chat Area -->
            <div class="aicentral-chatbot-chat-area">
                <div class="aicentral-chatbot-messages" id="aicentral-chatbot-messages">
                    <!-- Messages loaded dynamically -->
                </div>

                <div class="aicentral-chatbot-input-area">
                    <textarea
                        id="aicentral-chatbot-input"
                        class="aicentral-chatbot-input"
                        placeholder="Type your message..."
                        rows="3"
                    ></textarea>
                    <button id="aicentral-chatbot-send-btn" class="aicentral-chatbot-send-btn">Send</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Style -->
    <div id="aicentral-chatbot-floating" class="aicentral-chatbot-floating" style="display: none;">
        <!-- Floating Button -->
        <button id="aicentral-chatbot-floating-btn" class="aicentral-chatbot-floating-btn">
            <span class="aicentral-chatbot-icon">💬</span>
            <span class="aicentral-chatbot-notification-badge" id="aicentral-chatbot-notification-badge" style="display: none;">1</span>
        </button>

        <!-- Floating Window -->
        <div id="aicentral-chatbot-floating-window" class="aicentral-chatbot-floating-window" style="display: none;">
            <div class="aicentral-chatbot-header">
                <h3 class="aicentral-chatbot-title">AI Assistant</h3>
                <div class="aicentral-chatbot-header-actions">
                    <button class="aicentral-chatbot-btn-icon" id="aicentral-chatbot-floating-new" title="New Conversation">
                        <span>+</span>
                    </button>
                    <button class="aicentral-chatbot-btn-icon" id="aicentral-chatbot-floating-minimize" title="Minimize">
                        <span>−</span>
                    </button>
                    <button class="aicentral-chatbot-btn-icon" id="aicentral-chatbot-floating-close" title="Close">
                        <span>×</span>
                    </button>
                </div>
            </div>

            <!-- Conversations Dropdown -->
            <div class="aicentral-chatbot-floating-conversations">
                <select id="aicentral-chatbot-floating-conversation-select" class="aicentral-chatbot-conversation-select">
                    <option value="">Select conversation...</option>
                </select>
            </div>

            <div class="aicentral-chatbot-messages" id="aicentral-chatbot-floating-messages">
                <!-- Messages loaded dynamically -->
            </div>

            <div class="aicentral-chatbot-input-area">
                <textarea
                    id="aicentral-chatbot-floating-input"
                    class="aicentral-chatbot-input"
                    placeholder="Type your message..."
                    rows="2"
                ></textarea>
                <button id="aicentral-chatbot-floating-send-btn" class="aicentral-chatbot-send-btn">Send</button>
            </div>
        </div>
    </div>

    <!-- Loading Indicator -->
    <div id="aicentral-chatbot-loading" class="aicentral-chatbot-loading" style="display: none;">
        <div class="aicentral-chatbot-spinner"></div>
        <span>AI is thinking...</span>
    </div>

    <!-- Context Menu -->
    <div id="aicentral-chatbot-context-menu" class="aicentral-chatbot-context-menu" style="display: none;">
        <div class="aicentral-chatbot-context-item" data-action="rename">Rename</div>
        <div class="aicentral-chatbot-context-item" data-action="delete">Delete</div>
    </div>
</div>

<script src="/ai/chatbot/aicentral_chatbot.js"></script>

<script>
// Auto-initialize chatbot if parameters are set in page
if (typeof aiCentral_chatbotConfig !== 'undefined') {
    document.addEventListener('DOMContentLoaded', function() {
        aiCentral_initChatbot(aiCentral_chatbotConfig);
    });
}
</script>
