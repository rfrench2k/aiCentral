# Universal Chatbot

A reusable chatbot component for any app integrating with AI Central. All AI calls go through `ai_makeRequest()`, so usage is logged, costs tracked, tier/quota rules enforced, and user-supplied API keys honored automatically.

## Files

- `aicentral_chatbotCode.php` — backend API
- `aicentral_chatbotHTML.php` — HTML component to include in your page
- `aicentral_chatbot.js` — frontend JavaScript
- `aicentral_chatbot.css` — styles (sidebar and floating layouts)

## Backend API

`POST /ai/chatbot/aicentral_chatbotCode.php` with an `action` field. Authentication is session-based via the central `/auth/` module; the user id and program id come from the session, not from request parameters.

Actions:

- `getConversations` — list the user's conversations
- `createConversation` — start a new conversation
- `getMessages` — load conversation history
- `sendMessage` — send a message and get the AI response
- `renameConversation` — change a conversation title
- `deleteConversation` — archive a conversation

A `sendMessage` response includes the AI reply, the new `message_id`, token usage, and per-call cost.

## Integration

Register a chat type for your program:

```sql
INSERT INTO chat_types (
    program_id, chat_type_code, chat_type_name,
    system_prompt, default_provider, default_model,
    chatbot_style, max_history_messages
) VALUES (
    'MYAPP', 'general_assistant', 'General Assistant',
    'You are a helpful assistant.',
    'claude', 'claude-sonnet-4-5-20250929',
    'sidebar', 20
);
```

Include the component on the page:

```php
<?php include $_SERVER['DOCUMENT_ROOT'] . '/ai/chatbot/aicentral_chatbotHTML.php'; ?>
```

Initialize it:

```javascript
aiCentral_initChatbot({
    programId: 'MYAPP',
    chatTypeCode: 'general_assistant',
    userId: userId,
    style: 'sidebar',
    pageContext: 'My Page',
    pageData: { item_id: 123 }
});
```

`pageContext` and `pageData` are passed to the model as part of the system prompt so the assistant knows what the user is looking at.

## Database

Conversations and messages live in the `aicore` database:

- `chat_types` — chat type definitions per program (system prompt, default model, style)
- `chat_conversations` — one row per conversation
- `chat_messages` — one row per message, linked to `ai_usage_log` for cost/usage

See `schema.sql` at the repo root for the full table definitions.
