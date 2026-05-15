/**
 * AI Central Admin Users - Frontend JavaScript
 */

let aiCentral_usersData = {
    users: [],
    tiers: [],
    filteredUsers: []
};

let aiCentral_userModal = null;

/**
 * Initialize users page
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap modal
    const modalElement = document.getElementById('aicentral-user-modal');
    if (modalElement) {
        aiCentral_userModal = new bootstrap.Modal(modalElement);
    }

    aiCentral_loadTiers();
    aiCentral_loadUsers();
});

/**
 * Load tiers for dropdown
 */
async function aiCentral_loadTiers() {
    try {
        const response = await fetch('/ai/admin/aicentral_usersCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getTiers' })
        });

        const data = await response.json();
        if (data.success) {
            aiCentral_usersData.tiers = data.tiers;

            // Populate filter dropdown
            const filterSelect = document.getElementById('aicentral-filter-tier');
            filterSelect.innerHTML = '<option value="">All Tiers</option>' +
                data.tiers.map(t => `<option value="${aiCentral_escapeHtml(t.tier_code)}">${aiCentral_escapeHtml(t.tier_name)}</option>`).join('');
        }
    } catch (error) {
        console.error('Error loading tiers:', error);
    }
}

/**
 * Load users
 */
async function aiCentral_loadUsers() {
    try {
        const response = await fetch('/ai/admin/aicentral_usersCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getUsers' })
        });

        const data = await response.json();
        if (data.success) {
            aiCentral_usersData.users = data.users;
            aiCentral_updateStats();
            aiCentral_filterUsers();
        }
    } catch (error) {
        console.error('Error loading users:', error);
    }
}

/**
 * Update stats display
 */
function aiCentral_updateStats() {
    const tierCounts = {
        total: aiCentral_usersData.users.length,
        free: 0,
        basic: 0,
        pro: 0,
        unlimited: 0
    };

    // Count users by tier based on their program_tiers string
    aiCentral_usersData.users.forEach(user => {
        if (user.program_tiers) {
            // Parse program_tiers like "MYAPP: unlimited, OTHERAPP: basic"
            const tiers = user.program_tiers.toLowerCase();
            if (tiers.includes('free')) tierCounts.free++;
            if (tiers.includes('basic')) tierCounts.basic++;
            if (tiers.includes('pro')) tierCounts.pro++;
            if (tiers.includes('unlimited')) tierCounts.unlimited++;
        }
    });

    const stats = document.getElementById('aicentral-users-stats');
    stats.innerHTML = `
        <div class="col-md">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body">
                    <h3 class="mb-1 text-primary">${tierCounts.total}</h3>
                    <p class="text-muted text-uppercase small mb-0 fw-semibold">Total Users</p>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body">
                    <h3 class="mb-1 text-info">${tierCounts.free}</h3>
                    <p class="text-muted text-uppercase small mb-0 fw-semibold">Free Tier</p>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body">
                    <h3 class="mb-1 text-success">${tierCounts.basic}</h3>
                    <p class="text-muted text-uppercase small mb-0 fw-semibold">Basic Tier</p>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body">
                    <h3 class="mb-1 text-warning">${tierCounts.pro}</h3>
                    <p class="text-muted text-uppercase small mb-0 fw-semibold">Pro Tier</p>
                </div>
            </div>
        </div>
        <div class="col-md">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body">
                    <h3 class="mb-1 text-danger">${tierCounts.unlimited}</h3>
                    <p class="text-muted text-uppercase small mb-0 fw-semibold">Unlimited</p>
                </div>
            </div>
        </div>
    `;
}

/**
 * Filter users
 */
function aiCentral_filterUsers() {
    const tierFilter = document.getElementById('aicentral-filter-tier').value;
    const searchTerm = document.getElementById('aicentral-filter-search').value.toLowerCase();

    aiCentral_usersData.filteredUsers = aiCentral_usersData.users.filter(user => {
        if (tierFilter && user.default_ai_tier !== tierFilter) return false;
        if (searchTerm &&
            !user.user_id.toLowerCase().includes(searchTerm) &&
            !(user.name && user.name.toLowerCase().includes(searchTerm)) &&
            !(user.email && user.email.toLowerCase().includes(searchTerm))) return false;
        return true;
    });

    aiCentral_renderUsers();
}

/**
 * Render users
 */
function aiCentral_renderUsers() {
    const container = document.getElementById('aicentral-users-list');

    if (aiCentral_usersData.filteredUsers.length === 0) {
        container.innerHTML = `
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox display-1"></i>
                <p class="mt-3">No users found. Click "Sync from Auth DB" to import users.</p>
            </div>
        `;
        return;
    }

    container.innerHTML = `
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>User ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Tier</th>
                        <th>Created</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${aiCentral_usersData.filteredUsers.map(user => aiCentral_renderUserRow(user)).join('')}
                </tbody>
            </table>
        </div>
    `;
}

/**
 * Render single user row
 */
function aiCentral_renderUserRow(user) {
    // Parse and display program tiers as individual buttons
    let tierBadges = '';
    if (user.program_tiers) {
        const tierPairs = user.program_tiers.split(', ');
        tierPairs.forEach(pair => {
            const [program, tier] = pair.split(':').map(s => s.trim());
            const badgeClass = tier === 'unlimited' ? 'bg-danger' :
                              tier === 'pro' ? 'bg-warning' :
                              tier === 'basic' ? 'bg-success' :
                              tier === 'free' ? 'bg-info' : 'bg-secondary';
            tierBadges += `<span class="badge ${badgeClass} me-1">${program}: ${tier}</span>`;
        });
    } else {
        tierBadges = '<span class="badge bg-secondary">No assignments</span>';
    }

    return `
        <tr>
            <td><code class="small">${aiCentral_escapeHtml(user.user_id)}</code></td>
            <td>${aiCentral_escapeHtml(user.name || 'N/A')}</td>
            <td>${aiCentral_escapeHtml(user.email || 'N/A')}</td>
            <td>${tierBadges}</td>
            <td>${new Date(user.created_at).toLocaleDateString()}</td>
            <td class="text-end">
                <button class="btn btn-sm btn-outline-primary" onclick="aiCentral_editUser('${user.user_id}')">
                    <i class="bi bi-pencil"></i> Edit Tiers
                </button>
            </td>
        </tr>
    `;
}

/**
 * Sync users from auth DB
 */
async function aiCentral_syncUsers() {
    if (!confirm('Sync users from auth_db? This will import all users and update existing records.')) return;

    try {
        const response = await fetch('/ai/admin/aicentral_usersCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'syncUsers' })
        });

        const data = await response.json();
        if (data.success) {
            alert(`Sync complete: ${data.synced} new users, ${data.updated} updated`);
            aiCentral_loadUsers();
        } else {
            alert('Error: ' + data.error);
        }
    } catch (error) {
        console.error('Error syncing users:', error);
        alert('Error syncing users');
    }
}

/**
 * Edit user - Load program-specific tiers
 */
async function aiCentral_editUser(userId) {
    const user = aiCentral_usersData.users.find(u => u.user_id === userId);
    if (!user) return;

    document.getElementById('aicentral-user-id').value = user.user_id;
    document.getElementById('aicentral-user-id-display').value = user.user_id;
    document.getElementById('aicentral-user-name').value = user.name || 'N/A';
    document.getElementById('aicentral-user-email').value = user.email || 'N/A';

    // Show modal
    if (aiCentral_userModal) {
        aiCentral_userModal.show();
    }

    // Load program-specific tiers
    await loadProgramTiers(userId);
}

/**
 * Load program-specific tiers for a user
 */
async function loadProgramTiers(userId) {
    const container = document.getElementById('aicentral-program-tiers-list');
    container.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div> Loading...</div>';

    try {
        const response = await fetch('/ai/admin/aicentral_usersCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'getUserProgramTiers', user_id: userId })
        });

        const data = await response.json();
        if (data.success) {
            renderProgramTiers(data.programs, userId);
        } else {
            container.innerHTML = '<p class="text-danger">Error loading tiers: ' + data.error + '</p>';
        }
    } catch (error) {
        console.error('Error loading program tiers:', error);
        container.innerHTML = '<p class="text-danger">Error loading tiers</p>';
    }
}

/**
 * Render program tiers with editable dropdowns
 */
function renderProgramTiers(programs, userId) {
    const container = document.getElementById('aicentral-program-tiers-list');

    if (!programs || programs.length === 0) {
        container.innerHTML = '<p class="text-muted">No program permissions found for this user.</p>';
        return;
    }

    let html = '<div class="list-group">';
    programs.forEach(prog => {
        const tierOptions = aiCentral_usersData.tiers.map(t =>
            `<option value="${aiCentral_escapeHtml(t.tier_code)}" ${prog.current_tier === t.tier_code ? 'selected' : ''}>${aiCentral_escapeHtml(t.tier_name)}</option>`
        ).join('');

        html += `
            <div class="list-group-item">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <strong>${prog.program_id}</strong>
                        <br><small class="text-muted">App Level: ${prog.app_level || 'N/A'}</small>
                    </div>
                    <div class="col-md-6">
                        <select class="form-select form-select-sm" id="tier-${prog.program_id}" onchange="updateProgramTier('${userId}', '${prog.program_id}', this.value)">
                            <option value="">No tier assigned</option>
                            ${tierOptions}
                        </select>
                    </div>
                    <div class="col-md-2 text-end">
                        <span class="badge bg-info">${prog.app_level || 'free'}</span>
                    </div>
                </div>
            </div>
        `;
    });
    html += '</div>';

    container.innerHTML = html;
}

/**
 * Update program tier immediately
 */
async function updateProgramTier(userId, programId, tierCode) {
    try {
        const response = await fetch('/ai/admin/aicentral_usersCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'updateProgramTier',
                user_id: userId,
                program_id: programId,
                tier_code: tierCode
            })
        });

        const data = await response.json();
        if (data.success) {
            showNotification('Tier updated successfully', 'success');
            // Reload users list to reflect changes
            await aiCentral_loadUsers();
        } else {
            showAlert('Error updating tier: ' + data.error, 'Error', 'error');
        }
    } catch (error) {
        console.error('Error updating tier:', error);
        showAlert('Error updating tier', 'Error', 'error');
    }
}

/**
 * Close user dialog (for backwards compatibility)
 */
function aiCentral_closeUserDialog() {
    if (aiCentral_userModal) {
        aiCentral_userModal.hide();
    }
}

/**
 * Save user
 */
async function aiCentral_saveUser() {
    const formData = new URLSearchParams({
        action: 'updateUserTier',
        user_id: document.getElementById('aicentral-user-id').value,
        tier_code: document.getElementById('aicentral-user-tier').value
    });

    try {
        const response = await fetch('/ai/admin/aicentral_usersCode.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData
        });

        const data = await response.json();
        if (data.success) {
            aiCentral_closeUserDialog();
            aiCentral_loadUsers();
        } else {
            alert('Error: ' + data.error);
        }
    } catch (error) {
        console.error('Error saving user:', error);
        alert('Error saving user');
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
