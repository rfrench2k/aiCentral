# AI Central Platform

A PHP/MySQL hub that fronts every major AI provider for a fleet of apps. One call to add AI to a new feature, one dashboard for cost across providers, tier-based access control built in.

## What it does

**One function call, any provider.** Apps call `ai_makeRequest(['user_id', 'program_id', 'feature_code', 'prompt', ...])`. AI Central picks the right model for the user's tier, picks the system or user-supplied API key, calls the provider, returns the response. Supports Anthropic Claude, OpenAI, Google Gemini, xAI Grok, Moonshot Kimi, Ollama (self-hosted), and the Claude Code CLI.

**Cost tracked per call.** Every request lands in `ai_usage_log` with input tokens, output tokens, thinking tokens, tool calls, response time, and dollar cost broken down by component. Admin dashboards roll this up per user, per feature, per program, per provider, per model, with trends, outliers, and date-range filtering.

**Swap models per feature, per tier, without touching code.** Each feature has a default model plus per-tier overrides — free tier can route to Haiku, pro to Sonnet, unlimited to Opus. The app keeps making the same call; admin changes the model assignment in the UI. Same for max output tokens and capability allowances (web search, vision, file access).

**Tier-based access control.** Tiers (free, basic, pro, unlimited — configurable) control which models a user can pick, which capabilities are available per feature, daily and monthly request and token limits, and monthly spend caps. Quotas have configurable grace and hard-stop thresholds. The `app_level_ai_mapping` table maps your auth module's user levels to AI tiers automatically.

**Bring your own key.** Users can add their own provider API keys, stored AES-256 encrypted with per-key random IV. When a user sets a feature to "use my key," AI Central swaps their key in for that user's calls — and quota checks are bypassed since the user is paying the provider directly. Keys can be tested in-place against the provider before being saved.

**Model comparison.** Pick any prompt — type one fresh or replay any historical request by `usage_id` — and run it through two to six models in parallel. See responses side by side with token counts, latency, and dollar cost per model. Useful for picking a default when adding a feature, or evaluating a provider's new release against what you're already paying for.

**Passthrough mode for public programs.** Some apps need AI without per-user registration (a public discovery page, a marketing tool). Mark the program `passthrough_mode = 1` and set a `passthrough_tier_id`. AI Central uses the program's tier for model selection and skips per-user quota checks. Cost still rolls up against the program.

**Embeddings and audio transcription.** `ai_generateEmbedding()` routes to OpenAI or Ollama embedding endpoints; `ai_makeTranscriptionRequest()` routes to OpenAI Whisper. Same cost tracking, same tier and quota mechanics as chat requests.

**Universal embeddable chatbot.** One PHP include plus one JS init call gives any app a working chatbot — sidebar or floating bubble style. Persistent conversations per user per program, auto-titled from the first message. Each chat type defines its system prompt, default model, history length, and retention. Every assistant response links back to its `ai_usage_log` row so chatbot cost rolls into the same dashboard.

**Drop-in settings modals.** Three app-agnostic modals — API Keys, Preferences, Usage — that any consuming app can include. One PHP include plus one function call to render. No AI Central branding in the modal chrome, so they fit cleanly into your app's UI.

**Server-to-server API.** `/api/assignUserTier.php` lets your auth module sync user tier assignments when users are created or change app-level. Protected by shared-secret header plus IP whitelist.

## Stack

- PHP 8.0+, MySQL 8.0+
- Vanilla JS + Bootstrap 5, no build step
- One feature = four files (`HTML.php`, `.js`, `Code.php`, `.css`). All page-to-backend communication is AJAX.

## Requirements

- PHP 8.0+, MySQL 8.0+
- An external authentication module mounted at `/auth/`. Not included in this repo — see [The /auth/ contract](#the-auth-contract) below for what it has to provide.
- Optional: Ollama for self-hosted models. If not running at `http://localhost:11434`, set `AICORE_OLLAMA_URL`.
- Optional: Claude Code CLI for the `claude_cli`provider. Set `AICORE_CLAUDE_CLI_PATH` if it isn't on PATH. On IIS/IUSR hosts, set `AICORE_CLAUDE_CLI_HOME` to a writable directory holding the Claude credentials.

## Setup

```
# 1. Local config. Copy the template; leave the file as-is unless you have
#    integrating apps on other servers (see "Server-to-server API" below).
cp common/aPRIV_LOCAL.example.php common/aPRIV_LOCAL.php

# 2. Create the aicore database and load schema + reference seed data.
mysql -u <user> -p -e "CREATE DATABASE aicore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u <user> -p aicore < schema.sql
mysql -u <user> -p aicore < seed_data.sql

# 3. Stand up the /auth/ module and its auth_db database (see contract below).

# 4. Set environment variables on the host (see list below).

# 5. The app writes ai.log files under /ai/. Make sure your web server
#    blocks direct HTTP access to .log files.
```

The seed reflects providers, models, and pricing at the last maintainer refresh. Pricing changes — use the admin Models page to keep it current.

### Environment variables

```
AICORE_DB_HOST                aicore database host
AICORE_DB_NAME                aicore database name
AICORE_DB_USER                aicore database user
AICORE_DB_PASS                aicore database password
AICORE_AUTH_DB_HOST           auth database host
AICORE_AUTH_DB_NAME           auth database name
AICORE_AUTH_DB_USER           auth database user
AICORE_AUTH_DB_PASS           auth database password
AICORE_ANTHROPIC_API_KEY      Claude
AICORE_OPENAI_API_KEY         OpenAI
AICORE_GEMINI_API_KEY         Gemini
AICORE_GROK_API_KEY           Grok
AICORE_KIMI_API_KEY           Kimi
AICORE_ENCRYPTION_KEY         AES-256 key for user-supplied API keys (32+ chars)
AICORE_API_SECRET             Shared secret for /api/* server-to-server callers
AICORE_OLLAMA_URL             (optional) Override Ollama host, default http://localhost:11434
AICORE_CLAUDE_CLI_PATH        (optional) Path to claude CLI, default 'claude' on PATH
AICORE_CLAUDE_CLI_HOME        (optional) Directory the claude CLI reads credentials from
AICORE_CLAUDE_CLI_TIMEOUT     (optional) Subprocess timeout, default 180s
```

`AICORE_ENCRYPTION_KEY` must be at least 32 characters or the user-key flow throws on first use. `AICORE_API_SECRET` must not be empty — an empty value would let any whitelisted caller hit `/api/*` without supplying a header.

### `aPRIV_LOCAL.php`

Holds per-deployment values that aren't worth their own env var. Currently just the API caller whitelist:

```
define('AICORE_API_EXTRA_CALLERS', ['10.0.0.42', '10.0.0.43']);
```

The `/api/*` endpoints always accept loopback (`127.0.0.1`, `localhost`, `::1`). Add other IPs here when integrating apps live on different servers. Leave the array empty if everything is on one box. This file is gitignored.

## The /auth/ contract

AI Central depends on an external authentication module — your single sign-on or user system — mounted at `/auth/` on the same web server. Every admin and user page calls into it via `common/header_ai.php`. To plug your own auth in, the module must provide:

### Files

- `/auth/includes/auth_functions.php` — PHP API (functions listed below)
- `/auth/includes/navbar_user.php` — outputs the navbar's user widget
- `/auth/includes/auth-monitor.js` — client-side session monitor (loaded on every page)
- `/auth/login.php` — login page that `auth_checkProgramAccess()` redirects to

### Functions `auth_functions.php` must expose

```
auth_checkProgramAccess($programCode, $minLevel)
    Verify the session user has at least $minLevel access to $programCode.
    Redirect to /auth/login.php and exit if not. Return an array including
    'user_id' and 'user_level' on success. Levels used by AI Central:
    'GUEST', 'ADMIN', 'SUPERADMIN'.

auth_getAuthenticatedUser($programCode)
    Same check, but for AJAX endpoints — return the user array or null
    (no redirect). The array must include 'user_id', 'user_level', and
    'program_id'.

getNavbarUserHTML($programCode, $options)
    Return HTML for the navbar user widget (avatar, name, dropdown menu).
    $options may include 'ai_menu_items' — an array of additional links
    to inject into the user dropdown.

getNavbarUserModalsHTML($programCode)
    Return HTML for any user-settings and linked-accounts modals.
```

### `auth_db` schema requirements

AI Central reads two tables from your auth database:

```
master_users
    user_id      VARCHAR(125)  PRIMARY KEY   canonical user identifier
    user_name    VARCHAR(...)                display name
    email        VARCHAR(125)
    created_at   TIMESTAMP
    updated_at   TIMESTAMP

user_program_permissions
    email        VARCHAR(125)
    program_id   VARCHAR(20)
    app_level    VARCHAR(20)                 maps to an AI tier via app_level_ai_mapping
```

The admin Users page joins `auth_db.master_users` and `auth_db.user_program_permissions` from inside the aicore connection, so the MySQL user configured in `AICORE_DB_*` needs read access to `auth_db`.

When your auth module creates a user or changes their app-level, it should POST to `/ai/api/assignUserTier.php` (see [Server-to-server API](#server-to-server-api)) so AI Central can sync the tier assignment.

## After install

Schema and seed give you a working platform with providers, models, tiers, and lookups populated. Two tables are intentionally empty:

- `ai_features` — features are registered per integrating app via the admin Features page (or `INSERT` directly) as each app starts using AI Central.
- `chat_types` — chat type definitions per app. Add a row for each chat experience you want to enable. See [chatbot/README.md](chatbot/README.md) for an example.

The admin Dashboard will show zero usage and the Model Comparison page will work as soon as API keys are set. Everything else fills in as apps integrate.

## Adding AI to an app

From any app on the same host:

```
require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/includes/ai_functions.php';

$result = ai_makeRequest([
    'user_id'      => $userId,
    'program_id'   => 'MYAPP',
    'feature_code' => 'summarize_doc',
    'prompt'       => 'Summarize the following text...',
    'options'      => [
        'max_tokens'  => 1024,
        'temperature' => 0.3,
        'system'      => 'You are a concise technical writer.',
    ],
]);

if ($result['success']) {
    echo $result['response'];
    // $result['usage'] -> input_tokens, output_tokens, thinking_tokens
    // $result['cost']  -> dollar breakdown
} else {
    echo 'Error: ' . $result['error'];
}
```

Register the feature once in the admin Features page (set program, code, default model, per-tier overrides), and the app is wired in. Switching providers later means editing one row in the UI — no code change in the app.

## Server-to-server API

`POST /ai/api/assignUserTier.php`

Used by the auth module to sync a user's AI tier when their app-level changes.

Headers:

```
X-API-Secret: <AICORE_API_SECRET value>
```

Body:

```
action=assignUserTier   (or updateUserTier)
user_id=<string>
program_id=<string>
app_level=<string>      looked up in app_level_ai_mapping
```

Response:

```
{ "success": true, "tier_code": "pro", "tier_id": 3 }
```

Authentication is shared-secret plus IP whitelist. Loopback is always allowed; other callers must be listed in `aPRIV_LOCAL.php` (see above). Non-whitelisted IPs and empty or missing secrets are rejected.

## Layout

```
admin/        Admin UI — models, tiers, features, users, cost, usage, comparison
api/          Server-to-server endpoints
chatbot/      Universal embeddable chatbot (HTML + JS + backend)
common/       Shared helpers, header/footer, DB connection
includes/     Orchestration (ai_makeRequest), processors, key manager, settings modals
user/         End-user UI — API keys, preferences, usage dashboard
```

Two databases: `aicore` (this project) and `auth_db` (your auth module's).

## License

MIT — see [LICENSE](LICENSE).
