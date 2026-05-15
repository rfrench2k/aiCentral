/**
 * AI Central User Usage Dashboard - Frontend JavaScript (Bootstrap 5.3.3)
 */

let aiCentral_usageData = null;

/**
 * Initialize usage dashboard page
 */
document.addEventListener('DOMContentLoaded', function() {
    // Set default date range (last 30 days)
    const endDate = new Date();
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - 30);

    document.getElementById('aicentral-filter-start-date').value = startDate.toISOString().split('T')[0];
    document.getElementById('aicentral-filter-end-date').value = endDate.toISOString().split('T')[0];

    aiCentral_loadTierInfo();
    aiCentral_loadUsageData();
});

/**
 * Load tier information
 */
async function aiCentral_loadTierInfo() {
    try {
        const response = await fetch('/ai/user/aicentral_usageDashboardCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getTierInfo' })
        });

        const data = await response.json();
        if (data.success) {
            aiCentral_renderTierInfo(data.tier, data.usage);
        }
    } catch (error) {
        console.error('Error loading tier info:', error);
    }
}

/**
 * Render tier information
 */
function aiCentral_renderTierInfo(tier, usage) {
    const container = document.getElementById('aicentral-tier-info');

    const dailyRequestPct = tier.daily_request_limit ?
        Math.min(100, (usage.daily_requests / tier.daily_request_limit) * 100) : 0;
    const monthlyRequestPct = tier.monthly_request_limit ?
        Math.min(100, (usage.monthly_requests / tier.monthly_request_limit) * 100) : 0;
    const monthlyTokenPct = tier.monthly_token_limit ?
        Math.min(100, (usage.monthly_tokens / tier.monthly_token_limit) * 100) : 0;
    const monthlySpendPct = tier.monthly_spend_limit_usd ?
        Math.min(100, (usage.monthly_cost / tier.monthly_spend_limit_usd) * 100) : 0;

    // Determine progress bar color based on percentage (Bootstrap colors)
    const getProgressClass = (pct) => {
        if (pct >= 90) return 'bg-danger';
        if (pct >= 75) return 'bg-warning';
        return 'bg-primary';
    };

    container.innerHTML = `
        <div class="card-body">
            <div class="mb-3">
                <h4 class="text-primary fw-bold mb-1">${aiCentral_escapeHtml(tier.tier_name || 'No Tier')}</h4>
                <p class="text-muted mb-0">${aiCentral_escapeHtml(tier.tier_description || '')}</p>
            </div>

            <div class="row g-3">
                ${tier.daily_request_limit ? `
                <div class="col-md-6">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-semibold small">Daily Requests</span>
                        <span class="text-primary fw-semibold small">${usage.daily_requests} / ${tier.daily_request_limit.toLocaleString()}</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar ${getProgressClass(dailyRequestPct)}" role="progressbar"
                             style="width: ${dailyRequestPct}%"
                             aria-valuenow="${dailyRequestPct}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
                ` : ''}

                ${tier.monthly_request_limit ? `
                <div class="col-md-6">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-semibold small">Monthly Requests</span>
                        <span class="text-primary fw-semibold small">${usage.monthly_requests} / ${tier.monthly_request_limit.toLocaleString()}</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar ${getProgressClass(monthlyRequestPct)}" role="progressbar"
                             style="width: ${monthlyRequestPct}%"
                             aria-valuenow="${monthlyRequestPct}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
                ` : ''}

                ${tier.monthly_token_limit ? `
                <div class="col-md-6">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-semibold small">Monthly Tokens</span>
                        <span class="text-primary fw-semibold small">${usage.monthly_tokens.toLocaleString()} / ${tier.monthly_token_limit.toLocaleString()}</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar ${getProgressClass(monthlyTokenPct)}" role="progressbar"
                             style="width: ${monthlyTokenPct}%"
                             aria-valuenow="${monthlyTokenPct}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
                ` : ''}

                ${tier.monthly_spend_limit_usd ? `
                <div class="col-md-6">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-semibold small">Monthly Spend</span>
                        <span class="text-primary fw-semibold small">$${usage.monthly_cost.toFixed(2)} / $${tier.monthly_spend_limit_usd.toFixed(2)}</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar ${getProgressClass(monthlySpendPct)}" role="progressbar"
                             style="width: ${monthlySpendPct}%"
                             aria-valuenow="${monthlySpendPct}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
                ` : ''}

                ${!tier.daily_request_limit && !tier.monthly_request_limit && !tier.monthly_token_limit && !tier.monthly_spend_limit_usd ? `
                <div class="col-12">
                    <div class="alert alert-success mb-0" role="alert">
                        <i class="bi bi-infinity"></i> <strong>Unlimited Access</strong> - No usage limits apply to your tier
                    </div>
                </div>
                ` : ''}
            </div>
        </div>
    `;
}

/**
 * Load usage data
 */
async function aiCentral_loadUsageData() {
    const startDate = document.getElementById('aicentral-filter-start-date').value;
    const endDate = document.getElementById('aicentral-filter-end-date').value;

    if (!startDate || !endDate) {
        alert('Please select both start and end dates');
        return;
    }

    try {
        const response = await fetch('/ai/user/aicentral_usageDashboardCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'getUsageData',
                start_date: startDate,
                end_date: endDate
            })
        });

        const data = await response.json();
        if (data.success) {
            aiCentral_usageData = data;
            aiCentral_renderUsageData();
        } else {
            alert('Error: ' + data.error);
        }
    } catch (error) {
        console.error('Error loading usage data:', error);
        alert('Error loading usage data');
    }
}

/**
 * Render all usage data
 */
function aiCentral_renderUsageData() {
    if (!aiCentral_usageData) return;

    aiCentral_renderSummary();
    aiCentral_renderByFeature();
    aiCentral_renderByProvider();
    aiCentral_renderRecentActivity();
}

/**
 * Render summary stats
 */
function aiCentral_renderSummary() {
    const summary = aiCentral_usageData.summary;
    const statsContainer = document.getElementById('aicentral-usage-stats');

    statsContainer.innerHTML = `
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-muted text-uppercase small fw-semibold mb-2">Total Requests</div>
                    <div class="display-6 fw-bold">${summary.total_requests.toLocaleString()}</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-muted text-uppercase small fw-semibold mb-2">Input Tokens</div>
                    <div class="display-6 fw-bold">${summary.total_input_tokens.toLocaleString()}</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-muted text-uppercase small fw-semibold mb-2">Output Tokens</div>
                    <div class="display-6 fw-bold">${summary.total_output_tokens.toLocaleString()}</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-muted text-uppercase small fw-semibold mb-2">Total Cost</div>
                    <div class="display-6 fw-bold text-success">$${summary.total_cost.toFixed(2)}</div>
                </div>
            </div>
        </div>
    `;
}

/**
 * Render usage by feature
 */
function aiCentral_renderByFeature() {
    const data = aiCentral_usageData.byFeature;
    aiCentral_renderTable('aicentral-usage-by-feature', data, [
        { key: 'feature_code', label: 'Feature' },
        { key: 'requests', label: 'Requests', format: 'number' },
        { key: 'cost', label: 'Cost', format: 'currency' }
    ]);
}

/**
 * Render usage by provider
 */
function aiCentral_renderByProvider() {
    const data = aiCentral_usageData.byProvider;
    aiCentral_renderTable('aicentral-usage-by-provider', data, [
        { key: 'provider_name', label: 'Provider' },
        { key: 'requests', label: 'Requests', format: 'number' },
        { key: 'cost', label: 'Cost', format: 'currency' }
    ]);
}

/**
 * Render recent activity
 */
function aiCentral_renderRecentActivity() {
    const data = aiCentral_usageData.recentActivity;
    const container = document.getElementById('aicentral-recent-activity');

    if (!data || data.length === 0) {
        container.innerHTML = '<div class="text-center text-muted py-4">No recent activity</div>';
        return;
    }

    let html = '<div class="table-responsive"><table class="table table-hover align-middle mb-0">';
    html += '<thead class="table-light"><tr>';
    html += '<th>Time</th><th>Feature</th><th>Provider</th><th>Model</th><th>Tokens</th><th>Cost</th><th>Status</th>';
    html += '</tr></thead><tbody>';

    data.forEach(row => {
        const statusBadge = row.status === 'success'
            ? '<span class="badge bg-success">success</span>'
            : '<span class="badge bg-danger">failed</span>';

        html += `<tr>
            <td class="small">${new Date(row.request_timestamp).toLocaleString()}</td>
            <td>${aiCentral_escapeHtml(row.feature_code)}</td>
            <td>${aiCentral_escapeHtml(row.provider_name)}</td>
            <td class="small">${aiCentral_escapeHtml(row.model_display_name)}</td>
            <td>${parseInt(row.total_tokens).toLocaleString()}</td>
            <td>$${parseFloat(row.total_cost_usd).toFixed(4)}</td>
            <td>${statusBadge}</td>
        </tr>`;
    });

    html += '</tbody></table></div>';
    container.innerHTML = html;
}

/**
 * Generic table renderer
 */
function aiCentral_renderTable(containerId, data, columns) {
    const container = document.getElementById(containerId);

    if (!data || data.length === 0) {
        container.innerHTML = '<div class="text-center text-muted py-4">No data available</div>';
        return;
    }

    let html = '<div class="table-responsive"><table class="table table-hover align-middle mb-0">';
    html += '<thead class="table-light"><tr>';
    columns.forEach(col => {
        html += `<th>${col.label}</th>`;
    });
    html += '</tr></thead><tbody>';

    data.forEach(row => {
        html += '<tr>';
        columns.forEach(col => {
            let value = row[col.key];
            if (col.format === 'number') {
                value = parseInt(value).toLocaleString();
            } else if (col.format === 'currency') {
                value = '$' + parseFloat(value).toFixed(2);
            }
            html += `<td>${aiCentral_escapeHtml(String(value))}</td>`;
        });
        html += '</tr>';
    });

    html += '</tbody></table></div>';
    container.innerHTML = html;
}

/**
 * Utility: Escape HTML
 */
function aiCentral_escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
