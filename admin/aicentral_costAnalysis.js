/**
 * AI Central Admin Cost Analysis - Frontend JavaScript
 */

let aiCentral_costData = null;

/**
 * Initialize cost analysis page
 */
document.addEventListener('DOMContentLoaded', function() {
    // Set default date range (last 30 days)
    const endDate = new Date();
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - 30);

    document.getElementById('aicentral-filter-start-date').value = startDate.toISOString().split('T')[0];
    document.getElementById('aicentral-filter-end-date').value = endDate.toISOString().split('T')[0];

    aiCentral_loadCostData();
    aiCentral_loadPassthroughPrograms();
});

/**
 * Load cost data
 */
async function aiCentral_loadCostData() {
    const startDate = document.getElementById('aicentral-filter-start-date').value;
    const endDate = document.getElementById('aicentral-filter-end-date').value;

    if (!startDate || !endDate) {
        alert('Please select both start and end dates');
        return;
    }

    try {
        const response = await fetch('/ai/admin/aicentral_costAnalysisCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'getCostData',
                start_date: startDate,
                end_date: endDate
            })
        });

        const data = await response.json();
        if (data.success) {
            aiCentral_costData = data;
            aiCentral_renderCostData();
        } else {
            alert('Error: ' + data.error);
        }
    } catch (error) {
        console.error('Error loading cost data:', error);
        alert('Error loading cost data');
    }
}

/**
 * Render all cost data
 */
function aiCentral_renderCostData() {
    if (!aiCentral_costData) return;

    aiCentral_renderSummary();
    aiCentral_renderByProvider();
    aiCentral_renderByModel();
    aiCentral_renderByProgram();
    aiCentral_renderByFeature();
    aiCentral_renderByUser();
    aiCentral_renderDailyTrend();
}

/**
 * Render summary stats
 */
function aiCentral_renderSummary() {
    const summary = aiCentral_costData.summary;
    const statsContainer = document.getElementById('aicentral-cost-stats');

    statsContainer.innerHTML = `
        <div class="col-md-6 col-lg">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-uppercase text-muted small fw-semibold mb-2">Total Cost</div>
                    <div class="h2 fw-bold text-success mb-0">$${summary.total_cost.toFixed(2)}</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-uppercase text-muted small fw-semibold mb-2">Total Requests</div>
                    <div class="h2 fw-bold text-dark mb-0">${summary.total_requests.toLocaleString()}</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-uppercase text-muted small fw-semibold mb-2">Input Tokens</div>
                    <div class="h2 fw-bold text-dark mb-0">${summary.total_input_tokens.toLocaleString()}</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-uppercase text-muted small fw-semibold mb-2">Output Tokens</div>
                    <div class="h2 fw-bold text-dark mb-0">${summary.total_output_tokens.toLocaleString()}</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-uppercase text-muted small fw-semibold mb-2">Avg Cost/Request</div>
                    <div class="h2 fw-bold text-success mb-0">$${summary.avg_cost_per_request.toFixed(4)}</div>
                </div>
            </div>
        </div>
    `;
}

/**
 * Render cost by provider
 */
function aiCentral_renderByProvider() {
    const data = aiCentral_costData.byProvider;
    aiCentral_renderTable('aicentral-cost-by-provider', data, [
        { key: 'provider_code', label: 'Provider' },
        { key: 'requests', label: 'Requests', format: 'number' },
        { key: 'input_tokens', label: 'Input Tokens', format: 'number' },
        { key: 'output_tokens', label: 'Output Tokens', format: 'number' },
        { key: 'cost', label: 'Cost', format: 'currency' }
    ]);
}

/**
 * Render cost by model
 */
function aiCentral_renderByModel() {
    const data = aiCentral_costData.byModel;
    aiCentral_renderTable('aicentral-cost-by-model', data, [
        { key: 'model_code', label: 'Model' },
        { key: 'requests', label: 'Requests', format: 'number' },
        { key: 'cost', label: 'Cost', format: 'currency' }
    ]);
}

/**
 * Render cost by program
 */
function aiCentral_renderByProgram() {
    const data = aiCentral_costData.byProgram;
    aiCentral_renderTable('aicentral-cost-by-program', data, [
        { key: 'program_id', label: 'Program' },
        { key: 'requests', label: 'Requests', format: 'number' },
        { key: 'cost', label: 'Cost', format: 'currency' }
    ]);
}

/**
 * Render cost by feature
 */
function aiCentral_renderByFeature() {
    const data = aiCentral_costData.byFeature;
    aiCentral_renderTable('aicentral-cost-by-feature', data, [
        { key: 'feature_code', label: 'Feature' },
        { key: 'requests', label: 'Requests', format: 'number' },
        { key: 'cost', label: 'Cost', format: 'currency' }
    ]);
}

/**
 * Render cost by user
 */
function aiCentral_renderByUser() {
    const data = aiCentral_costData.byUser;
    aiCentral_renderTable('aicentral-cost-by-user', data, [
        { key: 'user_id', label: 'User' },
        { key: 'requests', label: 'Requests', format: 'number' },
        { key: 'cost', label: 'Cost', format: 'currency' }
    ]);
}

/**
 * Render daily trend
 */
function aiCentral_renderDailyTrend() {
    const data = aiCentral_costData.dailyTrend;
    aiCentral_renderTable('aicentral-cost-daily-trend', data, [
        { key: 'date', label: 'Date' },
        { key: 'requests', label: 'Requests', format: 'number' },
        { key: 'cost', label: 'Cost', format: 'currency' }
    ]);
}

/**
 * Generic table renderer with Bootstrap classes
 */
function aiCentral_renderTable(containerId, data, columns) {
    const container = document.getElementById(containerId);

    if (!data || data.length === 0) {
        container.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-inbox"></i> No data available</div>';
        return;
    }

    let html = '<div class="table-responsive"><table class="table table-hover table-sm mb-0"><thead class="table-light"><tr>';
    columns.forEach(col => {
        html += `<th class="fw-semibold">${col.label}</th>`;
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

            // Add special styling for currency columns
            let cellClass = col.format === 'currency' ? 'text-success fw-semibold' : '';
            html += `<td class="${cellClass}">${aiCentral_escapeHtml(String(value))}</td>`;
        });
        html += '</tr>';
    });

    html += '</tbody></table></div>';
    container.innerHTML = html;
}

/**
 * Load passthrough programs for the dropdown
 */
async function aiCentral_loadPassthroughPrograms() {
    try {
        const response = await fetch('/ai/admin/aicentral_costAnalysisCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getPassthroughPrograms' })
        });

        const data = await response.json();
        if (data.success && data.programs.length > 0) {
            // Show the passthrough section
            document.getElementById('aicentral-passthrough-section').style.display = '';

            const select = document.getElementById('aicentral-passthrough-program');
            data.programs.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.program_id;
                opt.textContent = p.program_name + ' (' + p.program_id + ')';
                select.appendChild(opt);
            });

            // Auto-select if only one program
            if (data.programs.length === 1) {
                select.value = data.programs[0].program_id;
                aiCentral_loadUserBreakdown();
            }
        }
    } catch (error) {
        console.error('Error loading passthrough programs:', error);
    }
}

/**
 * Load user breakdown for selected passthrough program
 */
async function aiCentral_loadUserBreakdown() {
    const programId = document.getElementById('aicentral-passthrough-program').value;
    if (!programId) return;

    const startDate = document.getElementById('aicentral-filter-start-date').value;
    const endDate = document.getElementById('aicentral-filter-end-date').value;

    try {
        const response = await fetch('/ai/admin/aicentral_costAnalysisCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'getUserBreakdown',
                program_id: programId,
                start_date: startDate,
                end_date: endDate
            })
        });

        const data = await response.json();
        if (data.success) {
            // Show result cards
            document.getElementById('aicentral-passthrough-features-card').style.display = '';
            document.getElementById('aicentral-passthrough-users-card').style.display = '';

            // Render features table
            aiCentral_renderTable('aicentral-passthrough-features', data.features, [
                { key: 'feature_code', label: 'Feature' },
                { key: 'requests', label: 'Requests', format: 'number' },
                { key: 'unique_users', label: 'Unique Users', format: 'number' },
                { key: 'cost', label: 'Cost', format: 'currency' }
            ]);

            // Render users table
            aiCentral_renderTable('aicentral-passthrough-users', data.users, [
                { key: 'user_id', label: 'User ID' },
                { key: 'requests', label: 'Requests', format: 'number' },
                { key: 'cost', label: 'Cost', format: 'currency' },
                { key: 'last_active', label: 'Last Active' }
            ]);
        } else {
            console.error('Error:', data.error);
        }
    } catch (error) {
        console.error('Error loading user breakdown:', error);
    }
}

/**
 * Utility: Escape HTML
 */
function aiCentral_escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
