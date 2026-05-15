/**
 * AI Central Chatbot - Frontend JavaScript
 * Handles all chatbot UI interactions and API calls
 */

let aiCentral_chatbotState = {
    initialized: false,
    programId: null,
    chatTypeCode: null,
    userId: null,
    style: 'floating',
    pageContext: null,
    pageData: null,
    currentConversationId: null,
    conversations: [],
    messages: [],
    isLoading: false
};

/**
 * Initialize chatbot
 */
function aiCentral_initChatbot(config) {
    if (aiCentral_chatbotState.initialized) {
        console.warn('AI Central Chatbot already initialized');
        return;
    }

    // Store config
    aiCentral_chatbotState.programId = config.programId;
    aiCentral_chatbotState.chatTypeCode = config.chatTypeCode;
    aiCentral_chatbotState.userId = config.userId;
    aiCentral_chatbotState.style = config.style || 'floating';
    aiCentral_chatbotState.pageContext = config.pageContext;
    aiCentral_chatbotState.pageData = config.pageData;

    // Show appropriate style
    const container = document.getElementById('aicentral-chatbot-container');
    container.style.display = 'block';

    if (aiCentral_chatbotState.style === 'sidebar') {
        document.getElementById('aicentral-chatbot-sidebar').style.display = 'flex';
        aiCentral_initSidebarChatbot();
    } else {
        document.getElementById('aicentral-chatbot-floating').style.display = 'block';
        aiCentral_initFloatingChatbot();
    }

    aiCentral_chatbotState.initialized = true;
    console.log('AI Central Chatbot initialized:', aiCentral_chatbotState);
}

/**
 * Initialize sidebar chatbot
 */
function aiCentral_initSidebarChatbot() {
    // Load conversations
    aiCentral_loadConversations();

    // Event listeners
    document.getElementById('aicentral-chatbot-new-conversation').addEventListener('click', aiCentral_createNewConversation);
    document.getElementById('aicentral-chatbot-toggle-sidebar').addEventListener('click', aiCentral_toggleConversationsPanel);
    document.getElementById('aicentral-chatbot-send-btn').addEventListener('click', () => aiCentral_sendMessage('sidebar'));
    document.getElementById('aicentral-chatbot-input').addEventListener('keypress', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            aiCentral_sendMessage('sidebar');
        }
    });
}

/**
 * Initialize floating chatbot
 */
function aiCentral_initFloatingChatbot() {
    // Event listeners
    document.getElementById('aicentral-chatbot-floating-btn').addEventListener('click', aiCentral_toggleFloatingWindow);
    document.getElementById('aicentral-chatbot-floating-close').addEventListener('click', aiCentral_closeFloatingWindow);
    document.getElementById('aicentral-chatbot-floating-minimize').addEventListener('click', aiCentral_minimizeFloatingWindow);
    document.getElementById('aicentral-chatbot-floating-new').addEventListener('click', aiCentral_createNewConversation);
    document.getElementById('aicentral-chatbot-floating-send-btn').addEventListener('click', () => aiCentral_sendMessage('floating'));
    document.getElementById('aicentral-chatbot-floating-input').addEventListener('keypress', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            aiCentral_sendMessage('floating');
        }
    });
    document.getElementById('aicentral-chatbot-floating-conversation-select').addEventListener('change', (e) => {
        aiCentral_loadConversation(e.target.value);
    });

    // Load conversations
    aiCentral_loadConversations();
}

/**
 * Load conversations list
 */
async function aiCentral_loadConversations() {
    try {
        const response = await fetch('/ai/chatbot/aicentral_chatbotCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'getConversations',
                user_id: aiCentral_chatbotState.userId,
                program_id: aiCentral_chatbotState.programId
            })
        });

        const data = await response.json();
        if (data.success) {
            aiCentral_chatbotState.conversations = data.conversations;
            aiCentral_renderConversationsList();

            // Auto-select first conversation or create new one
            if (data.conversations.length > 0) {
                aiCentral_loadConversation(data.conversations[0].conversation_id);
            } else {
                aiCentral_createNewConversation();
            }
        }
    } catch (error) {
        console.error('Error loading conversations:', error);
    }
}

/**
 * Render conversations list
 */
function aiCentral_renderConversationsList() {
    const list = document.getElementById('aicentral-chatbot-conversations-list');
    const select = document.getElementById('aicentral-chatbot-floating-conversation-select');

    if (list) {
        list.innerHTML = aiCentral_chatbotState.conversations.map(conv => `
            <div class="aicentral-chatbot-conversation-item ${conv.conversation_id === aiCentral_chatbotState.currentConversationId ? 'active' : ''}"
                 data-id="${conv.conversation_id}"
                 onclick="aiCentral_loadConversation(${conv.conversation_id})">
                <div class="aicentral-conversation-title">${aiCentral_escapeHtml(conv.conversation_title)}</div>
                <div class="aicentral-conversation-meta">${aiCentral_formatDate(conv.last_message_at)} • ${conv.message_count} msgs</div>
            </div>
        `).join('');
    }

    if (select) {
        select.innerHTML = '<option value="">Select conversation...</option>' +
            aiCentral_chatbotState.conversations.map(conv => `
                <option value="${conv.conversation_id}" ${conv.conversation_id === aiCentral_chatbotState.currentConversationId ? 'selected' : ''}>
                    ${aiCentral_escapeHtml(conv.conversation_title)}
                </option>
            `).join('');
    }
}

/**
 * Create new conversation
 */
async function aiCentral_createNewConversation() {
    try {
        const response = await fetch('/ai/chatbot/aicentral_chatbotCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'createConversation',
                user_id: aiCentral_chatbotState.userId,
                program_id: aiCentral_chatbotState.programId,
                chat_type_code: aiCentral_chatbotState.chatTypeCode
            })
        });

        const data = await response.json();
        if (data.success) {
            aiCentral_chatbotState.currentConversationId = data.conversation_id;
            aiCentral_loadConversations(); // Refresh list
            aiCentral_clearMessages();
        }
    } catch (error) {
        console.error('Error creating conversation:', error);
    }
}

/**
 * Load conversation messages
 */
async function aiCentral_loadConversation(conversationId) {
    if (!conversationId) return;

    aiCentral_chatbotState.currentConversationId = conversationId;

    try {
        const response = await fetch('/ai/chatbot/aicentral_chatbotCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'getMessages',
                user_id: aiCentral_chatbotState.userId,
                program_id: aiCentral_chatbotState.programId,
                conversation_id: conversationId
            })
        });

        const data = await response.json();
        if (data.success) {
            aiCentral_chatbotState.messages = data.messages;
            aiCentral_renderMessages();
        }
    } catch (error) {
        console.error('Error loading conversation:', error);
    }
}

/**
 * Send message
 */
async function aiCentral_sendMessage(style) {
    const inputId = style === 'sidebar' ? 'aicentral-chatbot-input' : 'aicentral-chatbot-floating-input';
    const input = document.getElementById(inputId);
    const message = input.value.trim();

    if (!message || !aiCentral_chatbotState.currentConversationId) return;

    // Add user message to UI immediately
    aiCentral_addMessageToUI('user', message);
    input.value = '';

    // Show loading
    aiCentral_showLoading(true);

    try {
        const response = await fetch('/ai/chatbot/aicentral_chatbotCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'sendMessage',
                user_id: aiCentral_chatbotState.userId,
                program_id: aiCentral_chatbotState.programId,
                conversation_id: aiCentral_chatbotState.currentConversationId,
                chat_type_code: aiCentral_chatbotState.chatTypeCode,
                message: message,
                page_context: aiCentral_chatbotState.pageContext || '',
                page_data: aiCentral_chatbotState.pageData ? JSON.stringify(aiCentral_chatbotState.pageData) : ''
            })
        });

        const data = await response.json();
        aiCentral_showLoading(false);

        if (data.success) {
            aiCentral_addMessageToUI('assistant', data.response);

            // Show cost info if available
            if (data.cost) {
                console.log('AI Cost:', data.cost);
            }
        } else {
            aiCentral_addMessageToUI('system', 'Error: ' + data.error);
        }
    } catch (error) {
        aiCentral_showLoading(false);
        console.error('Error sending message:', error);
        aiCentral_addMessageToUI('system', 'Error sending message. Please try again.');
    }
}

/**
 * Render messages
 */
function aiCentral_renderMessages() {
    const messagesId = aiCentral_chatbotState.style === 'sidebar' ? 'aicentral-chatbot-messages' : 'aicentral-chatbot-floating-messages';
    const messagesDiv = document.getElementById(messagesId);

    messagesDiv.innerHTML = aiCentral_chatbotState.messages.map(msg => `
        <div class="aicentral-chatbot-message aicentral-chatbot-message-${msg.message_role}">
            <div class="aicentral-chatbot-message-content">${aiCentral_formatMessage(msg.message_text)}</div>
            <div class="aicentral-chatbot-message-time">${aiCentral_formatTime(msg.created_at)}</div>
        </div>
    `).join('');

    aiCentral_scrollToBottom(messagesDiv);
}

/**
 * Add message to UI
 */
function aiCentral_addMessageToUI(role, text) {
    const messagesId = aiCentral_chatbotState.style === 'sidebar' ? 'aicentral-chatbot-messages' : 'aicentral-chatbot-floating-messages';
    const messagesDiv = document.getElementById(messagesId);

    const messageDiv = document.createElement('div');
    messageDiv.className = `aicentral-chatbot-message aicentral-chatbot-message-${role}`;
    messageDiv.innerHTML = `
        <div class="aicentral-chatbot-message-content">${aiCentral_formatMessage(text)}</div>
        <div class="aicentral-chatbot-message-time">Just now</div>
    `;

    messagesDiv.appendChild(messageDiv);
    aiCentral_scrollToBottom(messagesDiv);
}

/**
 * Clear messages
 */
function aiCentral_clearMessages() {
    aiCentral_chatbotState.messages = [];
    aiCentral_renderMessages();
}

/**
 * Show/hide loading indicator
 */
function aiCentral_showLoading(show) {
    aiCentral_chatbotState.isLoading = show;
    const loader = document.getElementById('aicentral-chatbot-loading');
    if (loader) {
        loader.style.display = show ? 'flex' : 'none';
    }
}

/**
 * Toggle floating window
 */
function aiCentral_toggleFloatingWindow() {
    const window = document.getElementById('aicentral-chatbot-floating-window');
    window.style.display = window.style.display === 'none' ? 'flex' : 'none';
}

/**
 * Close floating window
 */
function aiCentral_closeFloatingWindow() {
    document.getElementById('aicentral-chatbot-floating-window').style.display = 'none';
}

/**
 * Minimize floating window
 */
function aiCentral_minimizeFloatingWindow() {
    aiCentral_closeFloatingWindow();
}

/**
 * Toggle conversations panel (sidebar)
 */
function aiCentral_toggleConversationsPanel() {
    const panel = document.getElementById('aicentral-chatbot-conversations-panel');
    panel.classList.toggle('collapsed');
}

/**
 * Utility: Format message (convert markdown, links, etc)
 */
function aiCentral_formatMessage(text) {
    // Basic markdown support
    text = aiCentral_escapeHtml(text);

    // Bold
    text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

    // Italic
    text = text.replace(/\*(.*?)\*/g, '<em>$1</em>');

    // Code blocks
    text = text.replace(/```(.*?)```/gs, '<pre><code>$1</code></pre>');

    // Inline code
    text = text.replace(/`(.*?)`/g, '<code>$1</code>');

    // Line breaks
    text = text.replace(/\n/g, '<br>');

    return text;
}

/**
 * Utility: Escape HTML
 */
function aiCentral_escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Utility: Format date
 */
function aiCentral_formatDate(dateStr) {
    const date = new Date(dateStr);
    const now = new Date();
    const diffDays = Math.floor((now - date) / (1000 * 60 * 60 * 24));

    if (diffDays === 0) return 'Today';
    if (diffDays === 1) return 'Yesterday';
    if (diffDays < 7) return diffDays + ' days ago';

    return date.toLocaleDateString();
}

/**
 * Utility: Format time
 */
function aiCentral_formatTime(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

/**
 * Utility: Scroll to bottom
 */
function aiCentral_scrollToBottom(element) {
    element.scrollTop = element.scrollHeight;
}

// Export for global access
window.aiCentral_initChatbot = aiCentral_initChatbot;
window.aiCentral_loadConversation = aiCentral_loadConversation;
