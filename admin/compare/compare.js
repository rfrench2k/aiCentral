/**
 * AI Model Comparison Tool - Frontend JavaScript
 * Phases 4-7: JavaScript Implementation
 */

let compare_data = {
    usageId: 0,
    mode: 'standalone', // 'standalone' or 'from_usage'
    originalRecord: null,
    availableModels: [],
    selectedModelIds: [],
    modelCosts: {}, // Store cost estimates per model_id
    prompt: '',
    promptEdited: false,
    currentLayout: 'horizontal',
    comparisonResults: [],
    recordCount: 10,
    // New: Extracted parameters from original request
    extractedParams: {
        systemPrompt: null,
        capabilities: {},
        temperature: 0.7,
        maxTokens: 4096
    }
};

/**
 * Phase 4 Task 4.1-4.2: Initialize page
 */
function compare_init(usageId) {
    console.log('Initializing Model Comparison Tool, usage_id=' + usageId);

    compare_data.usageId = usageId;
    compare_data.mode = usageId > 0 ? 'from_usage' : 'standalone';

    // Setup parameter controls
    const webSearchCheckbox = document.getElementById('cap-web-search');
    const webSearchMax = document.getElementById('cap-web-search-max');

    if (webSearchCheckbox && webSearchMax) {
        webSearchCheckbox.addEventListener('change', () => {
            webSearchMax.disabled = !webSearchCheckbox.checked;
        });
    }

    // Load available models
    compare_loadAvailableModels();

    // If from usage, load original request
    if (compare_data.mode === 'from_usage') {
        compare_loadOriginalRequest();
    } else {
        // Show prompt input for standalone mode
        document.getElementById('prompt-input-section').style.display = 'block';
    }
}

/**
 * Phase 4 Task 4.3-4.4: Load original request data
 */
async function compare_loadOriginalRequest() {
    try {
        const response = await fetch('/ai/admin/compare/compareCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'getOriginalRequest',
                usageId: compare_data.usageId
            })
        });

        const data = await response.json();
        console.log('Original request loaded:', data);

        if (data.success) {
            compare_data.originalRecord = data.record;
            compare_data.prompt = data.record.prompt_text || '';

            // Populate original request section
            document.getElementById('original-request-section').style.display = 'block';
            document.getElementById('original-model').textContent = data.record.model_display_name;
            document.getElementById('original-provider').textContent = data.record.provider_name;
            document.getElementById('original-tokens').textContent = compare_formatNumber(data.record.total_tokens);
            document.getElementById('original-cost').textContent = '$' + parseFloat(data.record.total_cost_usd).toFixed(6);
            document.getElementById('original-response-time').textContent = compare_formatResponseTime(data.record.response_time_ms);
            document.getElementById('original-tool-calls').textContent = data.record.tool_call_count || '0';
            // Handle prompt truncation
            const promptText = data.record.prompt_text || 'No prompt data';
            compare_displayTruncatedPrompt(promptText);

            document.getElementById('original-response').textContent = data.record.response_text || 'No response data';

            // NEW: Extract and display three-layer prompt information
            compare_extractAndDisplayPromptLayers(data.record);
        } else {
            showAlert(data.error || 'Failed to load original request', 'Error', 'error');
        }
    } catch (error) {
        console.error('Error loading original request:', error);
        showAlert('Failed to load original request', 'Error', 'error');
    }
}

/**
 * Phase 4 Task 4.5: Load and render available models
 */
async function compare_loadAvailableModels() {
    try {
        const response = await fetch('/ai/admin/compare/compareCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getAvailableModels' })
        });

        const data = await response.json();
        console.log('Available models loaded:', data);

        if (data.success) {
            compare_data.availableModels = data.providers;
            compare_renderModelSelection(data.providers);
        } else {
            document.getElementById('model-selection-area').innerHTML =
                '<div class="alert alert-danger">Failed to load models: ' + (data.error || 'Unknown error') + '</div>';
        }
    } catch (error) {
        console.error('Error loading models:', error);
        document.getElementById('model-selection-area').innerHTML =
            '<div class="alert alert-danger">Failed to load models</div>';
    }
}

/**
 * Render model selection checkboxes grouped by provider - COMPACT
 */
function compare_renderModelSelection(providers) {
    let html = '';

    providers.forEach(provider => {
        // HARDCODED: Skip Claude Code CLI provider entirely (not apples-to-apples comparison)
        if (provider.provider_code === 'claude_cli') {
            console.log('Filtering out Claude Code CLI provider');
            return;
        }

        // Filter models - exclude code-specific models (not apples-to-apples comparison)
        const filteredModels = provider.models.filter(model => {
            // Exclude Grok Code Fast - it's optimized for code, not general comparison
            if (model.model_code === 'grok-code-fast-1') {
                return false;
            }
            // Exclude any other code-specific models
            if (model.model_code.includes('code-') || model.model_code.includes('-code')) {
                return false;
            }
            return true;
        });

        // Skip provider if no models after filtering
        if (filteredModels.length === 0) {
            return;
        }

        html += `
            <div class="provider-group">
                <div class="provider-group-title">
                    ${compare_escapeHtml(provider.provider_name)}
                </div>
        `;

        filteredModels.forEach(model => {
            html += `
                <div class="model-checkbox">
                    <label>
                        <input type="checkbox"
                               value="${model.model_id}"
                               id="model-checkbox-${model.model_id}"
                               onchange="compare_toggleModel(${model.model_id}, this.checked)">
                        <div class="model-info">
                            <span class="model-name">${compare_escapeHtml(model.model_display_name)}</span>
                            <span class="model-cost text-success small ms-auto" id="cost-${model.model_id}" style="display: none;"></span>
                        </div>
                    </label>
                </div>
            `;
        });

        html += `</div>`;
    });

    document.getElementById('model-selection-area').innerHTML = html;
}

/**
 * Phase 4 Task 4.6: Model selection validation (1-4 max)
 */
function compare_toggleModel(modelId, isChecked) {
    if (isChecked) {
        if (compare_data.selectedModelIds.length >= 4) {
            showAlert('Maximum 4 models can be selected', 'Selection Limit', 'warning');
            // Uncheck the checkbox
            event.target.checked = false;
            return;
        }
        compare_data.selectedModelIds.push(modelId);
    } else {
        const index = compare_data.selectedModelIds.indexOf(modelId);
        if (index > -1) {
            compare_data.selectedModelIds.splice(index, 1);
        }
    }

    console.log('Selected models:', compare_data.selectedModelIds);

    // Update selection count badge
    document.getElementById('selected-count').textContent = compare_data.selectedModelIds.length;

    // Phase 4 Task 4.7: Update cost estimation
    compare_updateCostEstimate();

    // Phase 4 Task 4.9: Enable/disable run button
    compare_updateRunButton();
}

/**
 * Phase 4 Task 4.7-4.8: Update cost estimation - REAL ESTIMATE
 */
async function compare_updateCostEstimate() {
    if (compare_data.selectedModelIds.length === 0) {
        document.getElementById('cost-estimate-section').style.display = 'none';
        return;
    }

    // Get ACTUAL input and output tokens from original request
    let inputTokens = 0;
    let outputTokens = 0;

    if (compare_data.originalRecord) {
        inputTokens = compare_data.originalRecord.input_tokens || 0;
        outputTokens = compare_data.originalRecord.output_tokens || 0;
    } else {
        // For standalone mode, estimate based on prompt length (rough estimate: 4 chars = 1 token)
        const promptText = document.getElementById('standalone-prompt').value;
        inputTokens = Math.ceil(promptText.length / 4);
        outputTokens = inputTokens; // Assume similar output length
    }

    try {
        const params = new URLSearchParams({
            action: 'estimateCost',
            inputTokens: inputTokens,
            outputTokens: outputTokens
        });

        compare_data.selectedModelIds.forEach(id => {
            params.append('modelIds[]', id);
        });

        const response = await fetch('/ai/admin/compare/compareCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params
        });

        const data = await response.json();
        console.log('Cost estimate:', data);

        if (data.success) {
            // Store costs and show inline with each model
            compare_data.modelCosts = {};
            data.estimates.forEach(est => {
                compare_data.modelCosts[est.model_id] = est.estimated_cost;

                // Show cost inline with checkbox
                const costEl = document.getElementById('cost-' + est.model_id);
                if (costEl) {
                    costEl.textContent = '$' + est.estimated_cost.toFixed(6);
                    costEl.style.display = 'inline';
                }
            });

            // Show total cost in header
            document.getElementById('total-cost-display').style.display = 'inline';
            document.getElementById('estimated-cost-header').textContent = '$' + data.total_cost.toFixed(6);

            // Phase 4 Task 4.8: Show warning if cost > $0.50
            if (data.warning) {
                document.getElementById('cost-warning-header').style.display = 'inline';
            } else {
                document.getElementById('cost-warning-header').style.display = 'none';
            }
        }
    } catch (error) {
        console.error('Error estimating cost:', error);
    }
}

/**
 * Phase 4 Task 4.9: Enable/disable Run Comparison button
 */
function compare_updateRunButton() {
    const btn = document.getElementById('btn-run-comparison');
    const hasModels = compare_data.selectedModelIds.length > 0;
    const hasPrompt = compare_data.mode === 'from_usage' || document.getElementById('standalone-prompt').value.trim().length > 0;

    btn.disabled = !(hasModels && hasPrompt);
}

/**
 * Phase 5 Task 5.1-5.6: Run Comparison
 */
async function compare_runComparison() {
    console.log('Running comparison...');

    // Get prompt - check if editing original request or standalone mode
    let prompt = '';
    let systemPrompt = '';
    let temperature = 0.7;
    let maxTokens = 4096;
    let capabilities = {};

    if (compare_data.mode === 'from_usage' && compare_data.promptEdited) {
        // EDIT MODE: Read from edit fields
        prompt = document.getElementById('edit-prompt').value.trim();
        systemPrompt = document.getElementById('edit-system-prompt').value.trim();
        temperature = parseFloat(document.getElementById('edit-temperature').value) || 0.7;
        maxTokens = parseInt(document.getElementById('edit-max-tokens').value) || 4096;

        // Read capabilities from edit checkboxes
        const editWebSearchCheckbox = document.getElementById('edit-web-search');
        const editWebSearchMax = document.getElementById('edit-web-search-max');

        if (editWebSearchCheckbox && editWebSearchCheckbox.checked) {
            capabilities['web_search'] = parseInt(editWebSearchMax.value) || 5;
        }

        console.log('Using EDITED parameters from edit fields');
    } else if (compare_data.mode === 'from_usage') {
        // FROM USAGE MODE (not edited): Use extracted parameters
        prompt = compare_data.prompt;
        systemPrompt = compare_data.extractedParams.systemPrompt || '';
        temperature = compare_data.extractedParams.temperature || 0.7;
        maxTokens = compare_data.extractedParams.maxTokens || 4096;
        capabilities = compare_data.extractedParams.capabilities || {};

        console.log('Using EXTRACTED parameters from original request');
    } else {
        // STANDALONE MODE: Read from standalone fields
        prompt = document.getElementById('standalone-prompt').value.trim();
        systemPrompt = document.getElementById('param-system-prompt').value.trim();
        temperature = parseFloat(document.getElementById('param-temperature').value) || 0.7;
        maxTokens = parseInt(document.getElementById('param-max-tokens').value) || 4096;

        // Read capabilities from standalone checkboxes
        const webSearchCheckbox = document.getElementById('cap-web-search');
        const webSearchMax = document.getElementById('cap-web-search-max');

        if (webSearchCheckbox && webSearchCheckbox.checked) {
            capabilities['web_search'] = parseInt(webSearchMax.value) || 5;
        }

        console.log('Using STANDALONE parameters from UI fields');
    }

    if (!prompt) {
        showAlert('Please enter a prompt', 'Missing Prompt', 'warning');
        return;
    }

    if (compare_data.selectedModelIds.length === 0) {
        showAlert('Please select at least one model', 'No Models Selected', 'warning');
        return;
    }

    // Auto-collapse panel when starting comparison
    compare_togglePanel('collapse');

    // Show progress
    document.getElementById('progress-section').style.display = 'block';
    document.getElementById('results-section').style.display = 'none';
    document.getElementById('results-placeholder').style.display = 'none';
    compare_updateProgress(0, 'Preparing comparison...', 'Setting up API requests');

    try {
        // Build request
        const params = new URLSearchParams({
            action: 'runComparison',
            prompt: prompt,
            originalUsageId: compare_data.usageId,
            promptEdited: compare_data.promptEdited
        });

        compare_data.selectedModelIds.forEach(id => {
            params.append('modelIds[]', id);
        });

        // Include parameters
        if (systemPrompt) {
            params.append('systemPrompt', systemPrompt);
        }

        params.append('temperature', temperature);
        params.append('maxTokens', maxTokens);

        // Include capabilities
        if (Object.keys(capabilities).length > 0) {
            params.append('capabilities', JSON.stringify(capabilities));
        }

        console.log('Sending comparison request with parameters:', {
            mode: compare_data.mode,
            edited: compare_data.promptEdited,
            systemPrompt: systemPrompt ? 'yes' : 'no',
            temperature,
            maxTokens,
            capabilities
        });

        // Make API call
        compare_updateProgress(20, 'Sending requests...', 'Executing prompt across ' + compare_data.selectedModelIds.length + ' models');

        const response = await fetch('/ai/admin/compare/compareCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params
        });

        const data = await response.json();
        console.log('Comparison results:', data);

        if (data.success) {
            compare_updateProgress(90, 'Processing results...', 'Formatting responses');

            // NEW: Add original request as first result if we have one
            let resultsToDisplay = data.results;
            if (compare_data.mode === 'from_usage' && compare_data.originalRecord) {
                const originalAsResult = {
                    model_id: compare_data.originalRecord.model_id,
                    model_display_name: compare_data.originalRecord.model_display_name + ' (Original)',
                    provider_name: compare_data.originalRecord.provider_name,
                    success: true,
                    response: compare_data.originalRecord.response_text,
                    response_type: 'text',
                    usage: {
                        input_tokens: compare_data.originalRecord.input_tokens,
                        output_tokens: compare_data.originalRecord.output_tokens
                    },
                    cost: {
                        total_cost: parseFloat(compare_data.originalRecord.total_cost_usd)
                    },
                    tool_calls: JSON.parse(compare_data.originalRecord.tool_calls_json || '[]'),
                    response_time_ms: compare_data.originalRecord.response_time_ms,
                    error: null,
                    is_original: true
                };
                resultsToDisplay = [originalAsResult, ...data.results];
            }

            compare_data.comparisonResults = resultsToDisplay;

            // Render results
            setTimeout(() => {
                compare_renderResults(resultsToDisplay);
                document.getElementById('progress-section').style.display = 'none';
                document.getElementById('results-section').style.display = 'block';
                document.getElementById('results-placeholder').style.display = 'none';
            }, 500);
        } else {
            compare_updateProgress(100, 'Error', data.error || 'Comparison failed');
            setTimeout(() => {
                document.getElementById('progress-section').style.display = 'none';
                showAlert(data.error || 'Comparison failed', 'Error', 'error');
            }, 2000);
        }
    } catch (error) {
        console.error('Error running comparison:', error);
        document.getElementById('progress-section').style.display = 'none';
        showAlert('Failed to run comparison', 'Error', 'error');
    }
}

/**
 * Update progress indicator
 */
function compare_updateProgress(percent, message, details) {
    document.getElementById('progress-message').textContent = message;
    document.getElementById('progress-details').textContent = details;
    document.getElementById('progress-bar').style.width = percent + '%';
    document.getElementById('progress-bar').textContent = percent + '%';
}

/**
 * Phase 6 Task 6.1-6.10: Render comparison results
 */
function compare_renderResults(results) {
    console.log('Rendering results, layout=' + compare_data.currentLayout);

    // Show prompt tabs section if we have original request data
    if (compare_data.mode === 'from_usage' && compare_data.originalRecord) {
        document.getElementById('prompt-tabs-section').style.display = 'block';
    }

    if (compare_data.currentLayout === 'horizontal') {
        compare_renderHorizontal(results);
    } else {
        compare_renderVertical(results);
    }
}

/**
 * Phase 6 Task 6.5: Render horizontal layout
 */
function compare_renderHorizontal(results) {
    const container = document.getElementById('horizontal-results');
    let html = '';

    results.forEach((result, index) => {
        const statusClass = result.success ? 'success' : 'failed';
        const originalClass = result.is_original ? 'original' : '';
        const metrics = compare_calculateMetrics(results);

        html += `
            <div class="col-md-${12 / Math.min(results.length, 4)}">
                <div class="model-result-column ${statusClass} ${originalClass}">
                    <div class="model-header">
                        <div>
                            <div class="model-title">${compare_escapeHtml(result.model_display_name)}</div>
                            <div class="model-provider">${compare_escapeHtml(result.provider_name)}</div>
                        </div>
                        <span class="status-badge badge ${result.success ? 'bg-success' : 'bg-danger'}">
                            ${result.success ? 'Success' : 'Failed'}
                        </span>
                    </div>

                    ${result.success ? compare_renderMetrics(result, metrics, index) : ''}
                    ${result.success ? compare_renderResponse(result, index) : compare_renderError(result)}
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

/**
 * Phase 6 Task 6.6-6.9: Render vertical layout
 */
function compare_renderVertical(results) {
    document.getElementById('vertical-controls').style.display = 'block';

    const container = document.getElementById('vertical-results');
    let html = '';

    const metrics = compare_calculateMetrics(results);

    results.forEach((result, index) => {
        const statusClass = result.success ? 'success' : 'failed';

        html += `
            <div class="model-result-card ${statusClass}">
                <div class="model-header">
                    <div>
                        <div class="model-title">${compare_escapeHtml(result.model_display_name)}</div>
                        <div class="model-provider">${compare_escapeHtml(result.provider_name)}</div>
                    </div>
                    <span class="status-badge badge ${result.success ? 'bg-success' : 'bg-danger'}">
                        ${result.success ? 'Success' : 'Failed'}
                    </span>
                </div>

                ${result.success ? compare_renderMetrics(result, metrics, index) : ''}
                ${result.success ? compare_renderResponse(result, index) : compare_renderError(result)}
            </div>
        `;
    });

    container.innerHTML = html;
}

/**
 * Phase 7: Calculate metrics for color coding
 */
function compare_calculateMetrics(results) {
    const successResults = results.filter(r => r.success);

    if (successResults.length === 0) {
        return {};
    }

    // Extract metric values
    const times = successResults.map(r => r.response_time_ms);
    const costs = successResults.map(r => r.cost?.total_cost || 0);
    const tokens = successResults.map(r => (r.usage?.input_tokens || 0) + (r.usage?.output_tokens || 0));

    return {
        bestTime: Math.min(...times),
        worstTime: Math.max(...times),
        bestCost: Math.min(...costs),
        worstCost: Math.max(...costs),
        bestTokens: Math.min(...tokens),
        worstTokens: Math.max(...tokens)
    };
}

/**
 * Phase 7: Render metrics with color coding
 */
function compare_renderMetrics(result, metrics, index) {
    const time = result.response_time_ms;
    const cost = result.cost?.total_cost || 0;
    const totalTokens = (result.usage?.input_tokens || 0) + (result.usage?.output_tokens || 0);
    const toolCalls = result.tool_calls?.length || 0;

    // Determine color classes
    const timeClass = compare_getMetricClass(time, metrics.bestTime, metrics.worstTime, true);
    const costClass = compare_getMetricClass(cost, metrics.bestCost, metrics.worstCost, true);
    const tokensClass = compare_getMetricClass(totalTokens, metrics.bestTokens, metrics.worstTokens, true);

    return `
        <div class="metrics-bar">
            <div class="metric-item">
                <div class="metric-label">Response Time</div>
                <div class="metric-value ${timeClass}">${compare_formatResponseTime(time)}</div>
            </div>
            <div class="metric-item">
                <div class="metric-label">Total Tokens</div>
                <div class="metric-value ${tokensClass}">${compare_formatNumber(totalTokens)}</div>
            </div>
            <div class="metric-item">
                <div class="metric-label">Tool Calls</div>
                <div class="metric-value">${toolCalls}</div>
            </div>
            <div class="metric-item">
                <div class="metric-label">Total Cost</div>
                <div class="metric-value ${costClass}">$${cost.toFixed(6)}</div>
            </div>
        </div>
    `;
}

/**
 * Get metric color class (green=best, yellow=middle, red=worst)
 */
function compare_getMetricClass(value, best, worst, lowerIsBetter) {
    if (best === worst) return '';

    if (lowerIsBetter) {
        if (value === best) return 'metric-best';
        if (value === worst) return 'metric-worst';
        return 'metric-middle';
    } else {
        if (value === worst) return 'metric-best';
        if (value === best) return 'metric-worst';
        return 'metric-middle';
    }
}

/**
 * Phase 6 Task 6.2-6.4: Render response based on type
 */
function compare_renderResponse(result, index) {
    const responseType = result.response_type || 'text';
    const response = result.response || '';

    if (responseType === 'json_array') {
        // Phase 6 Task 6.2: Render as table
        return compare_renderTableResponse(response, index);
    } else if (responseType === 'json_object') {
        // Phase 6 Task 6.3: Render as formatted JSON
        return compare_renderJsonResponse(response, index);
    } else {
        // Phase 6 Task 6.4: Render as plain text
        return compare_renderTextResponse(response);
    }
}

/**
 * Render JSON array as table
 */
function compare_renderTableResponse(response, index) {
    try {
        let jsonData = response;

        // Try to extract JSON from markdown code blocks first
        const codeBlockMatch = response.match(/```(?:json)?\s*(\[[\s\S]*?\]|\{[\s\S]*?\})\s*```/);
        if (codeBlockMatch) {
            jsonData = codeBlockMatch[1].trim();
        } else {
            // Try to extract JSON array from response
            const jsonMatch = response.match(/(\[[\s\S]*\])/);
            if (jsonMatch) {
                jsonData = jsonMatch[1].trim();
            }
        }

        const data = JSON.parse(jsonData);

        if (!Array.isArray(data) || data.length === 0) {
            return compare_renderTextResponse(response);
        }

        // Get column headers from first object
        const headers = Object.keys(data[0]);

        let html = `
            <div class="response-content">
                <div class="table-wrapper">
                    <table class="table table-sm table-striped table-hover">
                        <thead>
                            <tr>
        `;

        headers.forEach(header => {
            html += `<th>${compare_escapeHtml(header)}</th>`;
        });

        html += `
                            </tr>
                        </thead>
                        <tbody>
        `;

        // Apply record count limit for vertical mode
        let displayData = data;
        if (compare_data.currentLayout === 'vertical' && compare_data.recordCount !== 'all') {
            displayData = data.slice(0, parseInt(compare_data.recordCount));
        }

        displayData.forEach(row => {
            html += '<tr>';
            headers.forEach(header => {
                const value = row[header];
                html += `<td>${compare_escapeHtml(String(value))}</td>`;
            });
            html += '</tr>';
        });

        html += `
                        </tbody>
                    </table>
                </div>
                <button class="btn btn-sm btn-outline-secondary json-toggle-btn" onclick="compare_toggleJson(${index})">
                    <i class="bi bi-code"></i> Show JSON
                </button>
                <div id="json-view-${index}" style="display: none;">
                    <pre>${compare_escapeHtml(JSON.stringify(data, null, 2))}</pre>
                </div>
            </div>
        `;

        return html;
    } catch (e) {
        return compare_renderTextResponse(response);
    }
}

/**
 * Render JSON object
 */
function compare_renderJsonResponse(response, index) {
    try {
        let jsonData = response;

        // Try to extract JSON from markdown code blocks first
        const codeBlockMatch = response.match(/```(?:json)?\s*(\{[\s\S]*?\})\s*```/);
        if (codeBlockMatch) {
            jsonData = codeBlockMatch[1].trim();
        } else {
            // Try to extract JSON object from response
            const jsonMatch = response.match(/(\{[\s\S]*\})/);
            if (jsonMatch) {
                jsonData = jsonMatch[1].trim();
            }
        }

        const data = JSON.parse(jsonData);
        return `
            <div class="response-content">
                <pre>${compare_escapeHtml(JSON.stringify(data, null, 2))}</pre>
            </div>
        `;
    } catch (e) {
        return compare_renderTextResponse(response);
    }
}

/**
 * Render plain text
 */
function compare_renderTextResponse(response) {
    return `
        <div class="response-content">
            <pre>${compare_escapeHtml(response)}</pre>
        </div>
    `;
}

/**
 * Render error message
 */
function compare_renderError(result) {
    return `
        <div class="error-message">
            <strong>Error:</strong> ${compare_escapeHtml(result.error || 'Unknown error')}
        </div>
    `;
}

/**
 * Toggle JSON view
 */
function compare_toggleJson(index) {
    const jsonView = document.getElementById('json-view-' + index);
    if (jsonView.style.display === 'none') {
        jsonView.style.display = 'block';
    } else {
        jsonView.style.display = 'none';
    }
}

/**
 * Phase 6 Task 6.7: Set layout (horizontal/vertical)
 */
function compare_setLayout(layout) {
    compare_data.currentLayout = layout;
    console.log('Setting layout to ' + layout);

    if (layout === 'horizontal') {
        document.getElementById('btn-horizontal-layout').classList.add('active');
        document.getElementById('btn-vertical-layout').classList.remove('active');
        document.getElementById('horizontal-results-container').style.display = 'block';
        document.getElementById('vertical-results-container').style.display = 'none';
        document.getElementById('vertical-controls').style.display = 'none';
    } else {
        document.getElementById('btn-horizontal-layout').classList.remove('active');
        document.getElementById('btn-vertical-layout').classList.add('active');
        document.getElementById('horizontal-results-container').style.display = 'none';
        document.getElementById('vertical-results-container').style.display = 'block';
        document.getElementById('vertical-controls').style.display = 'block';
    }

    // Re-render results if they exist
    if (compare_data.comparisonResults.length > 0) {
        compare_renderResults(compare_data.comparisonResults);
    }
}

/**
 * Phase 6 Task 6.8: Update record count for vertical mode
 */
function compare_updateRecordCount() {
    const selector = document.getElementById('record-count-selector');
    compare_data.recordCount = selector.value;
    console.log('Record count set to ' + compare_data.recordCount);

    // Re-render vertical results
    if (compare_data.currentLayout === 'vertical' && compare_data.comparisonResults.length > 0) {
        compare_renderVertical(compare_data.comparisonResults);
    }
}

/**
 * Phase 9: Edit prompt functionality
 */
function compare_editPrompt() {
    if (!compare_data.originalRecord) return;

    // Show edit parameters section
    document.getElementById('edit-original-params').style.display = 'block';

    // Show warning
    document.getElementById('prompt-edited-warning').style.display = 'block';
    compare_data.promptEdited = true;

    // Clear original model results
    document.getElementById('original-response').textContent = '(Original results cleared - re-run comparison)';

    console.log('Edit mode enabled - parameters are now editable');
}

/**
 * Utility: Format number with commas
 */
function compare_formatNumber(num) {
    if (!num) return '0';
    return parseInt(num).toLocaleString();
}

/**
 * Utility: Format response time
 */
function compare_formatResponseTime(ms) {
    if (!ms) return 'N/A';
    if (ms < 1000) {
        return Math.round(ms) + 'ms';
    } else if (ms < 60000) {
        return (ms / 1000).toFixed(1) + 's';
    } else {
        const minutes = Math.floor(ms / 60000);
        const seconds = Math.round((ms % 60000) / 1000);
        return seconds > 0 ? `${minutes}m ${seconds}s` : `${minutes}m`;
    }
}

/**
 * Utility: Escape HTML
 */
function compare_escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Display truncated prompt with expand option
 */
function compare_displayTruncatedPrompt(promptText) {
    const previewElement = document.getElementById('original-prompt-preview');
    const fullElement = document.getElementById('original-prompt-full');
    const expandBtn = document.getElementById('original-prompt-expand-btn');

    if (!promptText || promptText === 'No prompt data') {
        previewElement.textContent = promptText;
        expandBtn.style.display = 'none';
        fullElement.style.display = 'none';
        return;
    }

    // Split by lines and take first 4-5 lines
    const lines = promptText.split('\n');
    const previewLines = lines.slice(0, 5);
    const preview = previewLines.join('\n');

    // Show preview
    previewElement.textContent = preview;

    // Store full text and show expand button if there's more content
    if (lines.length > 5) {
        fullElement.textContent = promptText;
        expandBtn.style.display = 'block';
        expandBtn.innerHTML = '<i class="bi bi-chevron-down"></i> Show Full Prompt (' + (lines.length - 5) + ' more lines)';
    } else {
        expandBtn.style.display = 'none';
        fullElement.style.display = 'none';
    }
}

/**
 * Toggle prompt expansion
 */
let promptExpanded = false;
function compare_togglePromptExpand() {
    const previewElement = document.getElementById('original-prompt-preview');
    const fullElement = document.getElementById('original-prompt-full');
    const expandBtn = document.getElementById('original-prompt-expand-btn');

    promptExpanded = !promptExpanded;

    if (promptExpanded) {
        previewElement.style.display = 'none';
        fullElement.style.display = 'block';
        expandBtn.innerHTML = '<i class="bi bi-chevron-up"></i> Show Less';
    } else {
        previewElement.style.display = 'block';
        fullElement.style.display = 'none';
        const lines = fullElement.textContent.split('\n');
        expandBtn.innerHTML = '<i class="bi bi-chevron-down"></i> Show Full Prompt (' + (lines.length - 5) + ' more lines)';
    }
}

/**
 * ========== SLIDING PANEL FUNCTIONALITY ==========
 */

/**
 * Toggle panel collapse/expand
 */
function compare_togglePanel(action) {
    const panel = document.getElementById('setup-panel');
    const strip = document.getElementById('panel-strip');
    const results = document.getElementById('results-area');

    console.log('Toggling panel:', action);

    if (action === 'collapse') {
        panel.classList.remove('setup-panel-expanded');
        panel.classList.add('setup-panel-collapsed');
        strip.classList.remove('d-none');
        results.classList.add('results-area-expanded');
        results.classList.remove('col-lg-8');
    } else {
        panel.classList.add('setup-panel-expanded');
        panel.classList.remove('setup-panel-collapsed');
        strip.classList.add('d-none');
        results.classList.remove('results-area-expanded');
        results.classList.add('col-lg-8');
    }
}

/**
 * ========== THREE-LAYER PROMPT EXTRACTION ==========
 */

/**
 * Extract and display three layers of prompt information
 * @param {object} record - The original usage record with prompt_to_ai
 */
function compare_extractAndDisplayPromptLayers(record) {
    console.log('Extracting prompt layers from record');

    // Layer 1: User Input (prompt_text)
    const userInput = record.prompt_text || 'No user input available';
    document.getElementById('display-user-input').textContent = userInput;

    // Parse prompt_to_ai JSON
    let apiRequest = null;
    try {
        if (record.prompt_to_ai) {
            apiRequest = JSON.parse(record.prompt_to_ai);
        }
    } catch (e) {
        console.error('Failed to parse prompt_to_ai:', e);
        document.getElementById('display-app-context').textContent = 'Failed to parse API request JSON';
        document.getElementById('display-api-request').textContent = 'Invalid JSON';
        return;
    }

    if (!apiRequest) {
        document.getElementById('display-app-context').textContent = 'No API request data available';
        document.getElementById('display-api-request').textContent = '{}';
        return;
    }

    // Layer 2: Application Context (system prompt from messages)
    let systemPrompt = 'No system prompt found';
    if (apiRequest.messages && Array.isArray(apiRequest.messages)) {
        // Look for system message - could be role="system" or role="user" depending on provider
        const systemMsg = apiRequest.messages.find(m => m.role === 'system');
        if (systemMsg && systemMsg.content) {
            systemPrompt = systemMsg.content;
            compare_data.extractedParams.systemPrompt = systemPrompt;
        } else {
            // Some providers might not have a separate system message
            systemPrompt = 'No separate system prompt (may be included in user message)';
        }
    }
    document.getElementById('display-app-context').textContent = systemPrompt;

    // Layer 3: Full API Request
    document.getElementById('display-api-request').textContent = JSON.stringify(apiRequest, null, 2);

    // Extract capabilities from tools array
    const capabilities = {};
    if (apiRequest.tools && Array.isArray(apiRequest.tools)) {
        apiRequest.tools.forEach(tool => {
            // Handle different tool formats
            if (tool.name) {
                // Format: {type: "web_search_20250305", name: "web_search", max_uses: 5}
                const capName = tool.name.replace('$', '');
                capabilities[capName] = tool.max_uses || 5;
            } else if (tool.function && tool.function.name) {
                // Format: {type: "builtin_function", function: {name: "$web_search"}}
                const capName = tool.function.name.replace('$', '');
                capabilities[capName] = 5; // Default max_uses
            } else if (tool.type && tool.type.includes('web_search')) {
                // Format: {type: "web_search"}
                capabilities['web_search'] = 5;
            }
        });
    }
    compare_data.extractedParams.capabilities = capabilities;
    console.log('Extracted capabilities:', capabilities);

    // Extract temperature and max_tokens
    compare_data.extractedParams.temperature = apiRequest.temperature || 0.7;
    compare_data.extractedParams.maxTokens = apiRequest.max_tokens || apiRequest.max_output_tokens || 4096;

    console.log('Extracted parameters:', compare_data.extractedParams);

    // NEW: Populate EDIT fields with extracted parameters (for when user clicks Edit)
    document.getElementById('edit-prompt').value = record.prompt_text || '';
    document.getElementById('edit-temperature').value = compare_data.extractedParams.temperature;
    document.getElementById('edit-max-tokens').value = compare_data.extractedParams.maxTokens;

    if (compare_data.extractedParams.systemPrompt) {
        document.getElementById('edit-system-prompt').value = compare_data.extractedParams.systemPrompt;
    }

    // Set edit capabilities checkboxes
    const editWebSearchCheckbox = document.getElementById('edit-web-search');
    const editWebSearchMax = document.getElementById('edit-web-search-max');

    // Setup checkbox handler
    editWebSearchCheckbox.addEventListener('change', () => {
        editWebSearchMax.disabled = !editWebSearchCheckbox.checked;
    });

    if (capabilities['web_search']) {
        editWebSearchCheckbox.checked = true;
        editWebSearchMax.disabled = false;
        editWebSearchMax.value = capabilities['web_search'];
    }
}
