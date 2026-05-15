/**
 * AI Central Admin Dashboard - Frontend JavaScript
 */

let aiCentral_dashboardData = {
    charts: {},
    refreshInterval: null
};

/**
 * Initialize dashboard
 */
document.addEventListener('DOMContentLoaded', function() {
    aiCentral_loadDashboard();

    // Auto-refresh every 5 minutes
    aiCentral_dashboardData.refreshInterval = setInterval(aiCentral_loadDashboard, 300000);
});

/**
 * Load all dashboard data
 */
async function aiCentral_loadDashboard() {
    await Promise.all([
        aiCentral_loadStats(),
        aiCentral_loadRequestsChart(),
        aiCentral_loadProvidersChart(),
        aiCentral_loadRecentRequests(),
        aiCentral_loadTopPrograms(),
        aiCentral_loadProviderStatus()
    ]);
}

/**
 * Refresh dashboard
 */
function aiCentral_refreshDashboard() {
    aiCentral_loadDashboard();
}

/**
 * Load statistics
 */
async function aiCentral_loadStats() {
    try {
        const response = await fetch('/ai/admin/aicentral_dashboardCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getStats' })
        });

        const data = await response.json();
        if (data.success) {
            const stats = data.stats;

            // Total Requests
            document.getElementById('aicentral-stat-total-requests').textContent =
                aiCentral_formatNumber(stats.totalRequests.value);
            document.getElementById('aicentral-stat-requests-change').textContent =
                aiCentral_formatChange(stats.totalRequests.change);
            document.getElementById('aicentral-stat-requests-change').className =
                'small ' + aiCentral_getChangeClass(stats.totalRequests.change);

            // Total Cost
            document.getElementById('aicentral-stat-total-cost').textContent =
                '$' + stats.totalCost.value.toFixed(2);
            document.getElementById('aicentral-stat-cost-change').textContent =
                aiCentral_formatChange(stats.totalCost.change);
            document.getElementById('aicentral-stat-cost-change').className =
                'small ' + aiCentral_getChangeClass(stats.totalCost.change);

            // Active Users
            document.getElementById('aicentral-stat-active-users').textContent =
                aiCentral_formatNumber(stats.activeUsers.value);
            document.getElementById('aicentral-stat-users-change').textContent =
                aiCentral_formatChange(stats.activeUsers.change);
            document.getElementById('aicentral-stat-users-change').className =
                'small ' + aiCentral_getChangeClass(stats.activeUsers.change);

            // Avg Response Time
            document.getElementById('aicentral-stat-avg-response').textContent =
                aiCentral_formatResponseTime(stats.avgResponseTime.value);
            document.getElementById('aicentral-stat-response-change').textContent =
                aiCentral_formatChange(stats.avgResponseTime.change);
            document.getElementById('aicentral-stat-response-change').className =
                'small ' + aiCentral_getChangeClass(-stats.avgResponseTime.change); // Lower is better
        }
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

/**
 * Load requests chart
 */
async function aiCentral_loadRequestsChart() {
    try {
        const response = await fetch('/ai/admin/aicentral_dashboardCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getRequestsChart' })
        });

        const result = await response.json();
        if (result.success) {
            const ctx = document.getElementById('aicentral-chart-requests').getContext('2d');

            if (aiCentral_dashboardData.charts.requests) {
                aiCentral_dashboardData.charts.requests.destroy();
            }

            aiCentral_dashboardData.charts.requests = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: result.data.map(d => d.date),
                    datasets: [{
                        label: 'Requests',
                        data: result.data.map(d => d.count),
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
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
        console.error('Error loading requests chart:', error);
    }
}

/**
 * Load providers chart
 */
async function aiCentral_loadProvidersChart() {
    try {
        const response = await fetch('/ai/admin/aicentral_dashboardCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getProvidersChart' })
        });

        const result = await response.json();
        if (result.success) {
            const ctx = document.getElementById('aicentral-chart-providers').getContext('2d');

            if (aiCentral_dashboardData.charts.providers) {
                aiCentral_dashboardData.charts.providers.destroy();
            }

            aiCentral_dashboardData.charts.providers = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: result.data.map(d => d.provider),
                    datasets: [{
                        data: result.data.map(d => d.cost),
                        backgroundColor: [
                            '#0d6efd',
                            '#198754',
                            '#ffc107',
                            '#0dcaf0'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'right' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': $' + context.parsed.toFixed(2);
                                }
                            }
                        }
                    }
                }
            });
        }
    } catch (error) {
        console.error('Error loading providers chart:', error);
    }
}

/**
 * Load recent requests
 */
async function aiCentral_loadRecentRequests() {
    try {
        const response = await fetch('/ai/admin/aicentral_dashboardCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getRecentRequests' })
        });

        const result = await response.json();
        if (result.success) {
            const tbody = document.querySelector('#aicentral-recent-requests tbody');

            if (result.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No recent requests</td></tr>';
            } else {
                tbody.innerHTML = result.data.map(req => {
                    const statusClass = req.status === 'success' ? 'bg-success' : 'bg-danger';
                    // Shorten provider names
                    let providerShort = req.provider;
                    if (providerShort.toLowerCase().includes('anthropic')) providerShort = 'Claude';
                    else if (providerShort.toLowerCase().includes('openai')) providerShort = 'OpenAI';
                    else if (providerShort.toLowerCase().includes('kimi') || providerShort.toLowerCase().includes('moonshot')) providerShort = 'Kimi';

                    return `
                    <tr>
                        <td>${aiCentral_formatDateTime(req.timestamp)}</td>
                        <td>${aiCentral_escapeHtml(req.user_id)}</td>
                        <td>${aiCentral_escapeHtml(req.program)}</td>
                        <td>${aiCentral_escapeHtml(req.feature)}</td>
                        <td>${aiCentral_escapeHtml(providerShort)}</td>
                        <td><small>${aiCentral_escapeHtml(req.model || 'N/A')}</small></td>
                        <td>$${req.cost.toFixed(4)}</td>
                        <td><span class="badge ${statusClass}">${req.status}</span></td>
                    </tr>
                    `;
                }).join('');
            }
        }
    } catch (error) {
        console.error('Error loading recent requests:', error);
    }
}

/**
 * Load top programs
 */
async function aiCentral_loadTopPrograms() {
    try {
        const response = await fetch('/ai/admin/aicentral_dashboardCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getTopPrograms' })
        });

        const result = await response.json();
        if (result.success) {
            const container = document.getElementById('aicentral-top-programs');

            if (result.data.length === 0) {
                container.innerHTML = '<div class="text-center text-muted py-4">No program data</div>';
            } else {
                const maxCount = Math.max(...result.data.map(p => p.count));

                container.innerHTML = result.data.map(prog => `
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <strong>${aiCentral_escapeHtml(prog.program)}</strong>
                            <small class="text-muted">${aiCentral_formatNumber(prog.count)} requests</small>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-primary" role="progressbar"
                                style="width: ${(prog.count / maxCount) * 100}%"
                                aria-valuenow="${prog.count}" aria-valuemin="0" aria-valuemax="${maxCount}"></div>
                        </div>
                        <div class="text-end mt-1">
                            <small class="text-muted">$${prog.cost.toFixed(2)}</small>
                        </div>
                    </div>
                `).join('');
            }
        }
    } catch (error) {
        console.error('Error loading top programs:', error);
    }
}

/**
 * Load provider status
 */
async function aiCentral_loadProviderStatus() {
    try {
        const response = await fetch('/ai/admin/aicentral_dashboardCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getProviderStatus' })
        });

        const result = await response.json();
        if (result.success) {
            const container = document.getElementById('aicentral-providers-status');

            container.innerHTML = result.data.map(prov => `
                <div class="col-md-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">${aiCentral_escapeHtml(prov.name)}</h6>
                                <span class="badge ${prov.isActive ? 'bg-success' : 'bg-secondary'}">${prov.isActive ? 'Active' : 'Inactive'}</span>
                            </div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <small class="text-muted d-block">Requests Today</small>
                                    <strong>${aiCentral_formatNumber(prov.requestsToday)}</strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">Cost Today</small>
                                    <strong>$${prov.costToday.toFixed(2)}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
        }
    } catch (error) {
        console.error('Error loading provider status:', error);
    }
}

/**
 * Utility: Format number with commas
 */
function aiCentral_formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

/**
 * Utility: Format change percentage
 */
function aiCentral_formatChange(change) {
    const sign = change > 0 ? '+' : '';
    return sign + change + '%';
}

/**
 * Utility: Get CSS class for change
 */
function aiCentral_getChangeClass(change) {
    if (change > 0) return 'text-success';
    if (change < 0) return 'text-danger';
    return 'text-muted';
}

/**
 * Utility: Format datetime
 */
function aiCentral_formatDateTime(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleString([], {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
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
 * Utility: Format response time in human-readable format
 */
function aiCentral_formatResponseTime(ms) {
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
