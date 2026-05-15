/**
 * AI Central Admin - Usage Analysis
 * Frontend JavaScript
 */

let aiUsage_data = {
    charts: {},
    currentPage: 1,
    pageSize: 50,
    totalRecords: 0,
    filters: {}
};

/**
 * Initialize page
 */
document.addEventListener('DOMContentLoaded', function() {
    aiUsage_init();
});

/**
 * Initialize usage analysis
 */
function aiUsage_init() {
    // Load filter dropdowns
    aiUsage_loadUsers();
    aiUsage_loadProviders();
    aiUsage_loadModels();
    aiUsage_loadFeatures();

    // Set up date range toggle
    document.getElementById('filter-daterange').addEventListener('change', function() {
        const customRange = document.getElementById('custom-date-range');
        const customRangeTo = document.getElementById('custom-date-range-to');
        if (this.value === 'custom') {
            customRange.style.display = 'block';
            customRangeTo.style.display = 'block';
        } else {
            customRange.style.display = 'none';
            customRangeTo.style.display = 'none';
        }
    });

    // Set up tab change listeners
    document.querySelectorAll('#usage-tabs button[data-bs-toggle="tab"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', function(event) {
            aiUsage_loadData();
        });
    });

    // Restore filters from localStorage
    aiUsage_restoreFilters();

    // Load initial data
    aiUsage_applyFilters();
}

/**
 * Load users for filter dropdown
 */
async function aiUsage_loadUsers() {
    try {
        const response = await fetch('/ai/admin/aicentral_usageCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getUsers' })
        });

        const data = await response.json();
        if (data.success) {
            const select = document.getElementById('filter-user');
            data.users.forEach(user => {
                const option = document.createElement('option');
                option.value = user.user_id;
                option.textContent = user.user_name || user.user_id;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading users:', error);
    }
}

/**
 * Load providers for filter dropdown
 */
async function aiUsage_loadProviders() {
    try {
        const response = await fetch('/ai/admin/aicentral_usageCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getProviders' })
        });

        const data = await response.json();
        if (data.success) {
            const select = document.getElementById('filter-provider');
            data.providers.forEach(provider => {
                const option = document.createElement('option');
                option.value = provider.provider_id;
                option.textContent = provider.provider_name;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading providers:', error);
    }
}

/**
 * Load models for filter dropdown
 */
async function aiUsage_loadModels() {
    try {
        const response = await fetch('/ai/admin/aicentral_usageCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getModels' })
        });

        const data = await response.json();
        if (data.success) {
            const select = document.getElementById('filter-model');
            data.models.forEach(model => {
                const option = document.createElement('option');
                option.value = model.model_id;
                option.textContent = model.model_display_name;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading models:', error);
    }
}

/**
 * Load features for filter dropdown
 */
async function aiUsage_loadFeatures() {
    try {
        const response = await fetch('/ai/admin/aicentral_usageCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getFeatures' })
        });

        const data = await response.json();
        if (data.success) {
            const select = document.getElementById('filter-feature');
            data.features.forEach(feature => {
                const option = document.createElement('option');
                option.value = feature.feature_code;
                option.textContent = feature.feature_name + ' (' + feature.program_id + ')';
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading features:', error);
    }
}

/**
 * Apply filters and reload data
 */
function aiUsage_applyFilters() {
    aiUsage_data.currentPage = 1;
    aiUsage_collectFilters();
    // Save filters to localStorage
    localStorage.setItem('aiUsage_filters', JSON.stringify(aiUsage_data.filters));
    aiUsage_loadData();
}

/**
 * Collect current filter values
 */
function aiUsage_collectFilters() {
    aiUsage_data.filters = {
        dateRange: document.getElementById('filter-daterange').value,
        dateFrom: document.getElementById('filter-date-from').value,
        dateTo: document.getElementById('filter-date-to').value,
        user: document.getElementById('filter-user').value,
        program: document.getElementById('filter-program').value,
        provider: document.getElementById('filter-provider').value,
        model: document.getElementById('filter-model').value,
        feature: document.getElementById('filter-feature').value,
        status: document.getElementById('filter-status').value,
        cost: document.getElementById('filter-cost').value,
        tokens: document.getElementById('filter-tokens').value
    };
}

/**
 * Toggle advanced filters visibility
 */
function aiUsage_toggleAdvancedFilters() {
    const advancedFilters = document.getElementById('advanced-filters');
    const toggleBtn = document.getElementById('btn-toggle-filters');
    const icon = toggleBtn.querySelector('i');

    if (advancedFilters.style.display === 'none') {
        advancedFilters.style.display = 'block';
        icon.className = 'bi bi-chevron-up';
        toggleBtn.innerHTML = '<i class="bi bi-chevron-up"></i> Less Filters';
        console.log('Advanced filters expanded');
    } else {
        advancedFilters.style.display = 'none';
        icon.className = 'bi bi-chevron-down';
        toggleBtn.innerHTML = '<i class="bi bi-chevron-down"></i> More Filters';
        console.log('Advanced filters collapsed');
    }
}

/**
 * Reset all filters
 */
function aiUsage_resetFilters() {
    document.getElementById('filter-daterange').value = 'last7days';
    document.getElementById('filter-date-from').value = '';
    document.getElementById('filter-date-to').value = '';
    document.getElementById('filter-user').value = '';
    document.getElementById('filter-program').value = '';
    document.getElementById('filter-provider').value = '';
    document.getElementById('filter-model').value = '';
    document.getElementById('filter-feature').value = '';
    document.getElementById('filter-status').value = 'success';
    document.getElementById('filter-cost').value = '';
    document.getElementById('filter-tokens').value = '';

    document.getElementById('custom-date-range').style.display = 'none';
    document.getElementById('custom-date-range-to').style.display = 'none';

    // Clear localStorage
    localStorage.removeItem('aiUsage_filters');

    aiUsage_applyFilters();
}

/**
 * Restore filters from localStorage
 */
function aiUsage_restoreFilters() {
    const saved = localStorage.getItem('aiUsage_filters');
    if (saved) {
        try {
            const filters = JSON.parse(saved);
            if (filters.dateRange) document.getElementById('filter-daterange').value = filters.dateRange;
            if (filters.dateFrom) document.getElementById('filter-date-from').value = filters.dateFrom;
            if (filters.dateTo) document.getElementById('filter-date-to').value = filters.dateTo;
            if (filters.user) document.getElementById('filter-user').value = filters.user;
            if (filters.program) document.getElementById('filter-program').value = filters.program;
            if (filters.provider) document.getElementById('filter-provider').value = filters.provider;
            if (filters.model) document.getElementById('filter-model').value = filters.model;
            if (filters.feature) document.getElementById('filter-feature').value = filters.feature;
            if (filters.status) document.getElementById('filter-status').value = filters.status;
            if (filters.cost) document.getElementById('filter-cost').value = filters.cost;
            if (filters.tokens) document.getElementById('filter-tokens').value = filters.tokens;

            // Show custom date range if needed
            if (filters.dateRange === 'custom') {
                document.getElementById('custom-date-range').style.display = 'block';
                document.getElementById('custom-date-range-to').style.display = 'block';
            }
        } catch (e) {
            console.error('Error restoring filters:', e);
        }
    }
}

/**
 * Refresh data
 */
function aiUsage_refreshData() {
    aiUsage_loadData();
}

/**
 * Load all data
 */
async function aiUsage_loadData() {
    // Get the currently active tab
    const activeTab = document.querySelector('#usage-tabs .nav-link.active');
    const activeTabId = activeTab ? activeTab.id : 'overview-tab';

    // Load data based on active tab
    switch(activeTabId) {
        case 'overview-tab':
            await Promise.all([
                aiUsage_loadSummary(),
                aiUsage_loadCharts(),
                aiUsage_loadUsageTable()
            ]);
            break;
        case 'by-feature-tab':
            await aiUsage_loadByFeatureData();
            break;
        case 'by-user-tab':
            await aiUsage_loadByUserData();
            break;
        case 'by-model-tab':
            await aiUsage_loadByModelData();
            break;
        case 'by-program-tab':
            await aiUsage_loadByProgramData();
            break;
        case 'outliers-tab':
            await aiUsage_loadOutliersData();
            break;
        case 'trends-tab':
            await aiUsage_loadTrendsData();
            break;
        case 'details-tab':
            await aiUsage_loadUsageTable();
            break;
        default:
            await Promise.all([
                aiUsage_loadSummary(),
                aiUsage_loadCharts(),
                aiUsage_loadUsageTable()
            ]);
    }
}

/**
 * Load summary statistics
 */
async function aiUsage_loadSummary() {
    try {
        const params = new URLSearchParams({ action: 'getSummary' });
        Object.entries(aiUsage_data.filters).forEach(([key, value]) => {
            if (value) params.append(key, value);
        });

        const response = await fetch('/ai/admin/aicentral_usageCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params
        });

        const data = await response.json();
        if (data.success) {
            const summary = data.summary;
            document.getElementById('summary-total-requests').textContent =
                aiUsage_formatNumber(summary.totalRequests);
            document.getElementById('summary-total-cost').textContent =
                '$' + parseFloat(summary.totalCost).toFixed(4);
            document.getElementById('summary-total-tokens').textContent =
                aiUsage_formatNumber(summary.totalTokens);
            document.getElementById('summary-avg-response').textContent =
                aiUsage_formatResponseTime(summary.avgResponse);
        }
    } catch (error) {
        console.error('Error loading summary:', error);
    }
}

/**
 * Load charts
 */
async function aiUsage_loadCharts() {
    await Promise.all([
        aiUsage_loadUsageOverTimeChart(),
        aiUsage_loadCostByProviderChart(),
        aiUsage_loadTopUsersChart(),
        aiUsage_loadTopProgramsChart()
    ]);
}

/**
 * Load usage over time chart
 */
async function aiUsage_loadUsageOverTimeChart() {
    try {
        const params = new URLSearchParams({ action: 'getUsageOverTime' });
        Object.entries(aiUsage_data.filters).forEach(([key, value]) => {
            if (value) params.append(key, value);
        });

        const response = await fetch('/ai/admin/aicentral_usageCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params
        });

        const data = await response.json();
        if (data.success) {
            const ctx = document.getElementById('chart-usage-overtime');

            // Destroy existing chart
            if (aiUsage_data.charts.usageOvertime) {
                aiUsage_data.charts.usageOvertime.destroy();
            }

            aiUsage_data.charts.usageOvertime = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.chartData.labels,
                    datasets: [{
                        label: 'Requests',
                        data: data.chartData.requests,
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }
    } catch (error) {
        console.error('Error loading usage over time chart:', error);
    }
}

/**
 * Load cost by provider chart
 */
async function aiUsage_loadCostByProviderChart() {
    try {
        const params = new URLSearchParams({ action: 'getCostByProvider' });
        Object.entries(aiUsage_data.filters).forEach(([key, value]) => {
            if (value) params.append(key, value);
        });

        const response = await fetch('/ai/admin/aicentral_usageCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params
        });

        const data = await response.json();
        if (data.success) {
            const ctx = document.getElementById('chart-cost-provider');

            // Destroy existing chart
            if (aiUsage_data.charts.costProvider) {
                aiUsage_data.charts.costProvider.destroy();
            }

            aiUsage_data.charts.costProvider = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.chartData.labels,
                    datasets: [{
                        data: data.chartData.costs,
                        backgroundColor: [
                            '#0d6efd',
                            '#198754',
                            '#ffc107',
                            '#dc3545',
                            '#6c757d'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }
    } catch (error) {
        console.error('Error loading cost by provider chart:', error);
    }
}

/**
 * Load top users chart
 */
async function aiUsage_loadTopUsersChart() {
    try {
        const params = new URLSearchParams({ action: 'getTopUsers' });
        Object.entries(aiUsage_data.filters).forEach(([key, value]) => {
            if (value) params.append(key, value);
        });

        const response = await fetch('/ai/admin/aicentral_usageCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params
        });

        const data = await response.json();
        console.log('Top Users data received:', data);
        if (data.success) {
            const ctx = document.getElementById('chart-top-users');

            // Destroy existing chart
            if (aiUsage_data.charts.topUsers) {
                aiUsage_data.charts.topUsers.destroy();
            }

            console.log('Rendering Top Users chart with', data.chartData.labels.length, 'users');
            aiUsage_data.charts.topUsers = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.chartData.labels,
                    datasets: [{
                        label: 'Requests',
                        data: data.chartData.requests,
                        backgroundColor: '#0d6efd'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: { beginAtZero: true }
                    }
                }
            });
        }
    } catch (error) {
        console.error('Error loading top users chart:', error);
    }
}

/**
 * Load top programs chart
 */
async function aiUsage_loadTopProgramsChart() {
    try {
        const params = new URLSearchParams({ action: 'getTopPrograms' });
        Object.entries(aiUsage_data.filters).forEach(([key, value]) => {
            if (value) params.append(key, value);
        });

        const response = await fetch('/ai/admin/aicentral_usageCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params
        });

        const data = await response.json();
        if (data.success) {
            const ctx = document.getElementById('chart-top-programs');

            // Destroy existing chart
            if (aiUsage_data.charts.topPrograms) {
                aiUsage_data.charts.topPrograms.destroy();
            }

            aiUsage_data.charts.topPrograms = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.chartData.labels,
                    datasets: [{
                        label: 'Requests',
                        data: data.chartData.requests,
                        backgroundColor: '#198754'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: { beginAtZero: true }
                    }
                }
            });
        }
    } catch (error) {
        console.error('Error loading top programs chart:', error);
    }
}

/**
 * Load usage table
 */
async function aiUsage_loadUsageTable() {
    try {
        const params = new URLSearchParams({
            action: 'getUsageData',
            page: aiUsage_data.currentPage,
            pageSize: aiUsage_data.pageSize
        });
        Object.entries(aiUsage_data.filters).forEach(([key, value]) => {
            if (value) params.append(key, value);
        });

        const response = await fetch('/ai/admin/aicentral_usageCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params
        });

        const data = await response.json();
        if (data.success) {
            aiUsage_data.totalRecords = data.totalRecords;
            aiUsage_renderTable(data.records);
            aiUsage_renderPagination();

            document.getElementById('usage-count').textContent = data.totalRecords;
            document.getElementById('showing-count').textContent = data.records.length;
        }
    } catch (error) {
        console.error('Error loading usage table:', error);
        document.getElementById('usage-table-body').innerHTML =
            '<tr><td colspan="11" class="text-center text-danger py-4">Error loading data</td></tr>';
    }
}

/**
 * Render table rows
 */
function aiUsage_renderTable(records) {
    const tbody = document.getElementById('usage-table-body');

    if (records.length === 0) {
        tbody.innerHTML = '<tr><td colspan="12" class="text-center py-4">No records found</td></tr>';
        return;
    }

    let html = '';
    records.forEach(record => {
        const statusBadge = record.status === 'success'
            ? '<span class="badge bg-success">Success</span>'
            : '<span class="badge bg-danger">Failed</span>';

        // Format tool call count - show badge if > 0
        const toolCount = record.tool_call_count || 0;
        const toolDisplay = toolCount > 0
            ? `<span class="badge bg-info">${toolCount}</span>`
            : '<span class="text-muted">-</span>';

        html += `
            <tr onclick="aiUsage_showDetail(${record.usage_id})" style="cursor: pointer;">
                <td class="small">${aiUsage_formatDateTime(record.request_timestamp)}</td>
                <td class="small">${aiUsage_escapeHtml(record.user_id)}</td>
                <td class="small">${aiUsage_escapeHtml(record.program_id)}</td>
                <td class="small">${aiUsage_escapeHtml(record.feature_code)}</td>
                <td class="small">${aiUsage_escapeHtml(record.provider_name)}</td>
                <td class="small">${aiUsage_escapeHtml(record.model_display_name)}</td>
                <td class="text-end small">${aiUsage_formatNumber(record.total_tokens)}</td>
                <td class="text-end small">${toolDisplay}</td>
                <td class="text-end small">$${parseFloat(record.total_cost_usd).toFixed(4)}</td>
                <td class="text-end small">${record.response_time_ms ? aiUsage_formatResponseTime(record.response_time_ms) : 'N/A'}</td>
                <td>${statusBadge}</td>
                <td>
                    <div class="btn-group" role="group">
                        <button class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation(); aiUsage_showDetail(${record.usage_id})" title="View usage details">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-success" onclick="event.stopPropagation(); aiUsage_compareModels(${record.usage_id})" title="Compare this prompt across multiple AI models">
                            <i class="bi bi-shuffle"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

/**
 * Render pagination
 */
function aiUsage_renderPagination() {
    const totalPages = Math.ceil(aiUsage_data.totalRecords / aiUsage_data.pageSize);
    const currentPage = aiUsage_data.currentPage;
    const pagination = document.getElementById('pagination');

    if (totalPages <= 1) {
        pagination.innerHTML = '';
        return;
    }

    let html = '';

    // Previous button
    html += `
        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="aiUsage_goToPage(${currentPage - 1}); return false;">
                <i class="bi bi-chevron-left"></i>
            </a>
        </li>
    `;

    // Page numbers
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);

    if (startPage > 1) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="aiUsage_goToPage(1); return false;">1</a></li>`;
        if (startPage > 2) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }

    for (let i = startPage; i <= endPage; i++) {
        html += `
            <li class="page-item ${i === currentPage ? 'active' : ''}">
                <a class="page-link" href="#" onclick="aiUsage_goToPage(${i}); return false;">${i}</a>
            </li>
        `;
    }

    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
        html += `<li class="page-item"><a class="page-link" href="#" onclick="aiUsage_goToPage(${totalPages}); return false;">${totalPages}</a></li>`;
    }

    // Next button
    html += `
        <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="aiUsage_goToPage(${currentPage + 1}); return false;">
                <i class="bi bi-chevron-right"></i>
            </a>
        </li>
    `;

    pagination.innerHTML = html;
}

/**
 * Go to specific page
 */
function aiUsage_goToPage(page) {
    aiUsage_data.currentPage = page;
    aiUsage_loadUsageTable();
}

/**
 * Show detail modal
 */
async function aiUsage_showDetail(usageId) {
    try {
        const response = await fetch('/ai/admin/aicentral_usageCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getUsageDetail', usageId: usageId })
        });

        const data = await response.json();
        if (data.success) {
            const record = data.record;

            // Populate modal fields
            document.getElementById('detail-usage-id').textContent = record.usage_id;
            document.getElementById('detail-timestamp').textContent = aiUsage_formatDateTime(record.request_timestamp);
            document.getElementById('detail-user').textContent = record.user_id;
            document.getElementById('detail-program').textContent = record.program_id;
            document.getElementById('detail-feature').textContent = record.feature_code;
            document.getElementById('detail-page-url').textContent = record.page_url || 'N/A';
            document.getElementById('detail-provider').textContent = record.provider_name;
            document.getElementById('detail-model').textContent = record.model_display_name;
            document.getElementById('detail-input-tokens').textContent = aiUsage_formatNumber(record.input_tokens);
            document.getElementById('detail-output-tokens').textContent = aiUsage_formatNumber(record.output_tokens);
            document.getElementById('detail-total-tokens').textContent = aiUsage_formatNumber(record.total_tokens);
            document.getElementById('detail-response-time').textContent = record.response_time_ms ? aiUsage_formatResponseTime(record.response_time_ms) : 'N/A';
            document.getElementById('detail-input-cost').textContent = '$' + parseFloat(record.input_cost_usd).toFixed(6);
            document.getElementById('detail-output-cost').textContent = '$' + parseFloat(record.output_cost_usd).toFixed(6);

            // Tool call information
            const toolCallCount = record.tool_call_count || 0;
            const toolCallCost = parseFloat(record.tool_call_cost_usd || 0);

            if (toolCallCount > 0) {
                // Parse tool_calls_json to show breakdown
                let toolDetails = '';
                try {
                    if (record.tool_calls_json) {
                        const toolCalls = JSON.parse(record.tool_calls_json);
                        const toolBreakdown = toolCalls.map(tc => `${tc.type}: ${tc.count}`).join(', ');
                        toolDetails = `<span class="badge bg-info">${toolCallCount}</span> <span class="small text-muted">(${toolBreakdown})</span>`;
                    } else {
                        toolDetails = `<span class="badge bg-info">${toolCallCount}</span>`;
                    }
                } catch (e) {
                    toolDetails = `<span class="badge bg-info">${toolCallCount}</span>`;
                }
                document.getElementById('detail-tool-calls').innerHTML = toolDetails;
                document.getElementById('detail-tool-cost').textContent = '$' + toolCallCost.toFixed(6);
            } else {
                document.getElementById('detail-tool-calls').innerHTML = '<span class="text-muted">None</span>';
                document.getElementById('detail-tool-cost').textContent = '$0.000000';
            }

            document.getElementById('detail-total-cost').textContent = '$' + parseFloat(record.total_cost_usd).toFixed(6);

            const statusBadge = record.status === 'success'
                ? '<span class="badge bg-success">Success</span>'
                : '<span class="badge bg-danger">Failed</span>';
            document.getElementById('detail-status').innerHTML = statusBadge;

            document.getElementById('detail-key-type').textContent = record.key_type;
            document.getElementById('detail-run-id').textContent = record.run_id || 'N/A';
            document.getElementById('detail-prompt-to-ai').textContent = record.prompt_to_ai || 'No raw API request data';
            document.getElementById('detail-complete-response').textContent = record.complete_ai_response || 'No raw API response data';
            document.getElementById('detail-prompt').textContent = record.prompt_text || 'No prompt data';
            document.getElementById('detail-response').textContent = record.response_text || 'No response data';

            // Error section
            if (record.status === 'failed' && record.error_message) {
                document.getElementById('detail-error-section').style.display = 'block';
                document.getElementById('detail-error-message').textContent = record.error_message;
            } else {
                document.getElementById('detail-error-section').style.display = 'none';
            }

            // Metadata section
            if (record.request_metadata) {
                document.getElementById('detail-metadata-section').style.display = 'block';
                document.getElementById('detail-metadata').textContent = JSON.stringify(JSON.parse(record.request_metadata), null, 2);
            } else {
                document.getElementById('detail-metadata-section').style.display = 'none';
            }

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('detailModal'));
            modal.show();
        } else {
            showAlert(data.error || 'Failed to load usage details', 'Error', 'error');
        }
    } catch (error) {
        console.error('Error loading usage detail:', error);
        showAlert('Failed to load usage details', 'Error', 'error');
    }
}

/**
 * Export data to CSV
 */
async function aiUsage_exportData() {
    try {
        const params = new URLSearchParams({ action: 'exportCSV' });
        Object.entries(aiUsage_data.filters).forEach(([key, value]) => {
            if (value) params.append(key, value);
        });

        // Build URL with parameters
        const url = '/ai/admin/aicentral_usageCode.php?' + params.toString();

        // Trigger download
        window.location.href = url;
    } catch (error) {
        console.error('Error exporting data:', error);
        showAlert('Failed to export data', 'Error', 'error');
    }
}

/**
 * Copy text to clipboard
 */
function aiUsage_copyToClipboard(elementId) {
    const element = document.getElementById(elementId);
    const text = element.textContent;

    navigator.clipboard.writeText(text).then(() => {
        showAlert('Copied to clipboard!', 'Success', 'success');
    }).catch(err => {
        console.error('Failed to copy:', err);
        showAlert('Failed to copy to clipboard', 'Error', 'error');
    });
}

/**
 * Format number with commas
 */
function aiUsage_formatNumber(num) {
    if (!num) return '0';
    return parseInt(num).toLocaleString();
}

/**
 * Format date/time
 */
function aiUsage_formatDateTime(dateStr) {
    if (!dateStr) return 'N/A';
    const date = new Date(dateStr);
    return date.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Escape HTML
 */
function aiUsage_escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Format response time in human-readable format
 */
function aiUsage_formatResponseTime(ms) {
    if (!ms) return 'N/A';
    if (ms < 1000) {
        // Less than 1 second: show in milliseconds
        return Math.round(ms) + 'ms';
    } else if (ms < 60000) {
        // Less than 1 minute: show in seconds
        return (ms / 1000).toFixed(1) + 's';
    } else {
        // 1 minute or more: show as "Xm Ys"
        const minutes = Math.floor(ms / 60000);
        const seconds = Math.round((ms % 60000) / 1000);
        return seconds > 0 ? `${minutes}m ${seconds}s` : `${minutes}m`;
    }
}

/**
 * Load By Feature data
 */
async function aiUsage_loadByFeatureData() {
    try {
        const params = new URLSearchParams({ action: 'getByFeatureData' });
        Object.entries(aiUsage_data.filters).forEach(([key, value]) => {
            if (value) params.append(key, value);
        });

        const response = await fetch('/ai/admin/aicentral_usageCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params
        });

        const data = await response.json();
        if (data.success) {
            aiUsage_renderByFeatureTable(data.records);
            document.getElementById('by-feature-count').textContent = data.records.length;
        }
    } catch (error) {
        console.error('Error loading by feature data:', error);
    }
}

/**
 * Render By Feature table
 */
function aiUsage_renderByFeatureTable(records) {
    const tbody = document.getElementById('by-feature-table-body');

    if (records.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4">No records found</td></tr>';
        return;
    }

    let html = '';
    records.forEach(record => {
        html += `
            <tr>
                <td class="small">${aiUsage_escapeHtml(record.feature_name || record.feature_code)}</td>
                <td class="small">${aiUsage_escapeHtml(record.program_id)}</td>
                <td class="text-end small">${aiUsage_formatNumber(record.total_requests)}</td>
                <td class="text-end small">${aiUsage_formatNumber(Math.round(record.avg_tokens))}</td>
                <td class="text-end small">$${parseFloat(record.avg_cost).toFixed(4)}</td>
                <td class="text-end small">${aiUsage_formatResponseTime(record.avg_response)}</td>
                <td class="text-end small fw-bold text-success">$${parseFloat(record.total_cost).toFixed(4)}</td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

/**
 * Load By User data
 */
async function aiUsage_loadByUserData() {
    try {
        const params = new URLSearchParams({ action: 'getByUserData' });
        Object.entries(aiUsage_data.filters).forEach(([key, value]) => {
            if (value) params.append(key, value);
        });

        const response = await fetch('/ai/admin/aicentral_usageCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params
        });

        const data = await response.json();
        if (data.success) {
            aiUsage_renderByUserTable(data.records);
            document.getElementById('by-user-count').textContent = data.records.length;
        }
    } catch (error) {
        console.error('Error loading by user data:', error);
    }
}

/**
 * Render By User table
 */
function aiUsage_renderByUserTable(records) {
    const tbody = document.getElementById('by-user-table-body');

    if (records.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4">No records found</td></tr>';
        return;
    }

    let html = '';
    records.forEach(record => {
        html += `
            <tr>
                <td class="small">${aiUsage_escapeHtml(record.user_name)}</td>
                <td class="text-end small">${aiUsage_formatNumber(record.total_requests)}</td>
                <td class="text-end small">${aiUsage_formatNumber(Math.round(record.avg_tokens))}</td>
                <td class="text-end small">$${parseFloat(record.avg_cost).toFixed(4)}</td>
                <td class="text-end small">${aiUsage_formatResponseTime(record.avg_response)}</td>
                <td class="text-end small fw-bold text-success">$${parseFloat(record.total_cost).toFixed(4)}</td>
                <td class="text-end small">${aiUsage_formatNumber(record.total_tokens)}</td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

/**
 * Load By Model data
 */
async function aiUsage_loadByModelData() {
    try {
        const params = new URLSearchParams({ action: 'getByModelData' });
        Object.entries(aiUsage_data.filters).forEach(([key, value]) => {
            if (value) params.append(key, value);
        });

        const response = await fetch('/ai/admin/aicentral_usageCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params
        });

        const data = await response.json();
        if (data.success) {
            aiUsage_renderByModelTable(data.records);
            document.getElementById('by-model-count').textContent = data.records.length;
        }
    } catch (error) {
        console.error('Error loading by model data:', error);
    }
}

/**
 * Render By Model table
 */
function aiUsage_renderByModelTable(records) {
    const tbody = document.getElementById('by-model-table-body');

    if (records.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4">No records found</td></tr>';
        return;
    }

    let html = '';
    records.forEach(record => {
        html += `
            <tr>
                <td class="small">${aiUsage_escapeHtml(record.model_display_name)}</td>
                <td class="small">${aiUsage_escapeHtml(record.provider_name)}</td>
                <td class="text-end small">${aiUsage_formatNumber(record.total_requests)}</td>
                <td class="text-end small">${aiUsage_formatNumber(Math.round(record.avg_tokens))}</td>
                <td class="text-end small">$${parseFloat(record.avg_cost).toFixed(4)}</td>
                <td class="text-end small">${aiUsage_formatResponseTime(record.avg_response)}</td>
                <td class="text-end small fw-bold text-success">$${parseFloat(record.total_cost).toFixed(4)}</td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

/**
 * Load By Program data
 */
async function aiUsage_loadByProgramData() {
    try {
        const params = new URLSearchParams({ action: 'getByProgramData' });
        Object.entries(aiUsage_data.filters).forEach(([key, value]) => {
            if (value) params.append(key, value);
        });

        const response = await fetch('/ai/admin/aicentral_usageCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params
        });

        const data = await response.json();
        if (data.success) {
            aiUsage_renderByProgramTable(data.records);
            document.getElementById('by-program-count').textContent = data.records.length;
        }
    } catch (error) {
        console.error('Error loading by program data:', error);
    }
}

/**
 * Render By Program table
 */
function aiUsage_renderByProgramTable(records) {
    const tbody = document.getElementById('by-program-table-body');

    if (records.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4">No records found</td></tr>';
        return;
    }

    let html = '';
    records.forEach(record => {
        html += `
            <tr>
                <td class="small">${aiUsage_escapeHtml(record.program_id)}</td>
                <td class="text-end small">${aiUsage_formatNumber(record.total_requests)}</td>
                <td class="text-end small">${aiUsage_formatNumber(Math.round(record.avg_tokens))}</td>
                <td class="text-end small">$${parseFloat(record.avg_cost).toFixed(4)}</td>
                <td class="text-end small">${aiUsage_formatResponseTime(record.avg_response)}</td>
                <td class="text-end small fw-bold text-success">$${parseFloat(record.total_cost).toFixed(4)}</td>
                <td class="text-end small">${aiUsage_formatNumber(record.total_tokens)}</td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

/**
 * Load Outliers data
 */
async function aiUsage_loadOutliersData() {
    try {
        const params = new URLSearchParams({ action: 'getOutliersData' });
        Object.entries(aiUsage_data.filters).forEach(([key, value]) => {
            if (value) params.append(key, value);
        });

        const response = await fetch('/ai/admin/aicentral_usageCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params
        });

        const data = await response.json();
        if (data.success) {
            aiUsage_renderOutliersData(data.data);
        }
    } catch (error) {
        console.error('Error loading outliers data:', error);
    }
}

/**
 * Render Outliers data
 */
function aiUsage_renderOutliersData(data) {
    // High Token Users
    let html = '';
    if (data.highTokenUsers.length === 0) {
        html = '<tr><td colspan="3" class="text-center py-2 small">No data available</td></tr>';
    } else {
        data.highTokenUsers.forEach(record => {
            html += `
                <tr>
                    <td class="small">${aiUsage_escapeHtml(record.user_name)}</td>
                    <td class="text-end small fw-bold">${aiUsage_formatNumber(Math.round(record.avg_tokens))}</td>
                    <td class="text-end small text-muted">${aiUsage_formatNumber(record.total_requests)}</td>
                </tr>
            `;
        });
    }
    document.getElementById('outliers-high-tokens-users').innerHTML = html;

    // High Cost Users
    html = '';
    if (data.highCostUsers.length === 0) {
        html = '<tr><td colspan="3" class="text-center py-2 small">No data available</td></tr>';
    } else {
        data.highCostUsers.forEach(record => {
            html += `
                <tr>
                    <td class="small">${aiUsage_escapeHtml(record.user_name)}</td>
                    <td class="text-end small fw-bold">$${parseFloat(record.avg_cost).toFixed(4)}</td>
                    <td class="text-end small text-muted">${aiUsage_formatNumber(record.total_requests)}</td>
                </tr>
            `;
        });
    }
    document.getElementById('outliers-high-cost-users').innerHTML = html;

    // Slow Users
    html = '';
    if (data.slowUsers.length === 0) {
        html = '<tr><td colspan="3" class="text-center py-2 small">No data available</td></tr>';
    } else {
        data.slowUsers.forEach(record => {
            html += `
                <tr>
                    <td class="small">${aiUsage_escapeHtml(record.user_name)}</td>
                    <td class="text-end small fw-bold">${aiUsage_formatResponseTime(record.avg_response)}</td>
                    <td class="text-end small text-muted">${aiUsage_formatNumber(record.total_requests)}</td>
                </tr>
            `;
        });
    }
    document.getElementById('outliers-slow-users').innerHTML = html;

    // High Token Requests
    html = '';
    if (data.highTokenRequests.length === 0) {
        html = '<tr><td colspan="3" class="text-center py-2 small">No data available</td></tr>';
    } else {
        data.highTokenRequests.forEach(record => {
            html += `
                <tr>
                    <td class="small">${aiUsage_escapeHtml(record.user_id)}</td>
                    <td class="small">${aiUsage_escapeHtml(record.feature_code)}</td>
                    <td class="text-end small fw-bold">${aiUsage_formatNumber(record.total_tokens)}</td>
                </tr>
            `;
        });
    }
    document.getElementById('outliers-high-token-requests').innerHTML = html;

    // Expensive Requests
    html = '';
    if (data.expensiveRequests.length === 0) {
        html = '<tr><td colspan="3" class="text-center py-2 small">No data available</td></tr>';
    } else {
        data.expensiveRequests.forEach(record => {
            html += `
                <tr>
                    <td class="small">${aiUsage_escapeHtml(record.user_id)}</td>
                    <td class="small">${aiUsage_escapeHtml(record.feature_code)}</td>
                    <td class="text-end small fw-bold">$${parseFloat(record.total_cost_usd).toFixed(4)}</td>
                </tr>
            `;
        });
    }
    document.getElementById('outliers-expensive-requests').innerHTML = html;

    // Slow Requests
    html = '';
    if (data.slowRequests.length === 0) {
        html = '<tr><td colspan="3" class="text-center py-2 small">No data available</td></tr>';
    } else {
        data.slowRequests.forEach(record => {
            html += `
                <tr>
                    <td class="small">${aiUsage_escapeHtml(record.user_id)}</td>
                    <td class="small">${aiUsage_escapeHtml(record.feature_code)}</td>
                    <td class="text-end small fw-bold">${aiUsage_formatResponseTime(record.response_time_ms)}</td>
                </tr>
            `;
        });
    }
    document.getElementById('outliers-slow-requests').innerHTML = html;
}

/**
 * Load Trends data
 */
async function aiUsage_loadTrendsData() {
    try {
        const params = new URLSearchParams({ action: 'getTrendsData' });
        Object.entries(aiUsage_data.filters).forEach(([key, value]) => {
            if (value) params.append(key, value);
        });

        const response = await fetch('/ai/admin/aicentral_usageCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params
        });

        const data = await response.json();
        if (data.success) {
            aiUsage_renderTrendsCharts(data.data);
        }
    } catch (error) {
        console.error('Error loading trends data:', error);
    }
}

/**
 * Render Trends charts
 */
function aiUsage_renderTrendsCharts(data) {
    // Average Tokens Trend
    const ctxTokens = document.getElementById('chart-trend-tokens');
    if (aiUsage_data.charts.trendTokens) {
        aiUsage_data.charts.trendTokens.destroy();
    }
    aiUsage_data.charts.trendTokens = new Chart(ctxTokens, {
        type: 'line',
        data: {
            labels: data.avgTokens.labels,
            datasets: [{
                label: 'Avg Tokens',
                data: data.avgTokens.values,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    // Average Cost Trend
    const ctxCost = document.getElementById('chart-trend-cost');
    if (aiUsage_data.charts.trendCost) {
        aiUsage_data.charts.trendCost.destroy();
    }
    aiUsage_data.charts.trendCost = new Chart(ctxCost, {
        type: 'line',
        data: {
            labels: data.avgCost.labels,
            datasets: [{
                label: 'Avg Cost (USD)',
                data: data.avgCost.values,
                borderColor: '#198754',
                backgroundColor: 'rgba(25, 135, 84, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toFixed(4);
                        }
                    }
                }
            }
        }
    });

    // Average Response Time Trend
    const ctxResponse = document.getElementById('chart-trend-response');
    if (aiUsage_data.charts.trendResponse) {
        aiUsage_data.charts.trendResponse.destroy();
    }
    aiUsage_data.charts.trendResponse = new Chart(ctxResponse, {
        type: 'line',
        data: {
            labels: data.avgResponse.labels,
            datasets: [{
                label: 'Avg Response (ms)',
                data: data.avgResponse.values,
                borderColor: '#ffc107',
                backgroundColor: 'rgba(255, 193, 7, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value + 'ms';
                        }
                    }
                }
            }
        }
    });

    // Request Volume Trend
    const ctxVolume = document.getElementById('chart-trend-volume');
    if (aiUsage_data.charts.trendVolume) {
        aiUsage_data.charts.trendVolume.destroy();
    }
    aiUsage_data.charts.trendVolume = new Chart(ctxVolume, {
        type: 'bar',
        data: {
            labels: data.requestVolume.labels,
            datasets: [{
                label: 'Requests',
                data: data.requestVolume.values,
                backgroundColor: '#6f42c1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
}

/**
 * Open model comparison in new tab
 */
function aiUsage_compareModels(usageId) {
    const url = '/ai/admin/compare/index.php?usage_id=' + usageId;
    window.open(url, '_blank');
}
