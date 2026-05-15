/**
 * AI Central System - Common JavaScript Functions
 */

/**
 * Make AJAX request to backend
 * @param {string} url Backend URL
 * @param {object} data Data to send
 * @param {function} successCallback Success callback
 * @param {function} errorCallback Error callback
 */
function aiAjax(url, data, successCallback, errorCallback) {
    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (successCallback) successCallback(data);
        } else {
            if (errorCallback) errorCallback(data);
            else console.error('Error:', data.error || data.message);
        }
    })
    .catch(error => {
        console.error('Network error:', error);
        if (errorCallback) errorCallback({ error: error.message });
    });
}

/**
 * Show notification message
 * @param {string} message Message to display
 * @param {string} type Type: success, error, warning, info
 */
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        background: ${type === 'success' ? '#4CAF50' : type === 'error' ? '#f44336' : type === 'warning' ? '#ff9800' : '#2196F3'};
        color: white;
        border-radius: 4px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        z-index: 10000;
        animation: slideIn 0.3s ease-out;
    `;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

/**
 * Format number as currency
 * @param {number} amount Amount to format
 * @param {string} currency Currency code (default: USD)
 * @return {string} Formatted currency
 */
function formatCurrency(amount, currency = 'USD') {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency
    }).format(amount);
}

/**
 * Format number with commas
 * @param {number} number Number to format
 * @return {string} Formatted number
 */
function formatNumber(number) {
    return new Intl.NumberFormat('en-US').format(number);
}

/**
 * Confirm dialog
 * @param {string} message Message to display
 * @param {function} callback Callback if confirmed
 */
function confirmDialog(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

/**
 * Custom Alert Modal - Replaces standard JavaScript alerts
 * Shows a centered, styled modal alert
 * @param {string} message The message to display
 * @param {string} title The title of the alert (default: "Alert")
 * @param {string} type The type: success, error, warning, info (default: info)
 */
function showAlert(message, title = 'Alert', type = 'info') {
    // Remove any existing alert modal
    const existingAlert = document.getElementById('ai-custom-alert');
    if (existingAlert) {
        existingAlert.remove();
    }

    // Determine icon and colors based on type
    let icon, bgColor, borderColor;
    switch(type) {
        case 'success':
            icon = 'bi-check-circle-fill';
            bgColor = '#d4edda';
            borderColor = '#28a745';
            break;
        case 'error':
            icon = 'bi-exclamation-triangle-fill';
            bgColor = '#f8d7da';
            borderColor = '#dc3545';
            break;
        case 'warning':
            icon = 'bi-exclamation-circle-fill';
            bgColor = '#fff3cd';
            borderColor = '#ffc107';
            break;
        default:
            icon = 'bi-info-circle-fill';
            bgColor = '#d1ecf1';
            borderColor = '#17a2b8';
    }

    // Create overlay
    const overlay = document.createElement('div');
    overlay.id = 'ai-custom-alert';
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        animation: fadeIn 0.2s ease-out;
    `;

    // Create alert box
    const alertBox = document.createElement('div');
    alertBox.style.cssText = `
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        max-width: 500px;
        width: 90%;
        animation: slideDown 0.3s ease-out;
        border-top: 4px solid ${borderColor};
    `;

    // Create header
    const header = document.createElement('div');
    header.style.cssText = `
        background: ${bgColor};
        padding: 15px 20px;
        border-radius: 4px 4px 0 0;
        display: flex;
        align-items: center;
        gap: 10px;
    `;
    header.innerHTML = `
        <i class="bi ${icon}" style="font-size: 24px; color: ${borderColor};"></i>
        <h5 style="margin: 0; color: #333;">${title}</h5>
    `;

    // Create body
    const body = document.createElement('div');
    body.style.cssText = `
        padding: 20px;
        color: #333;
        line-height: 1.6;
    `;
    body.textContent = message;

    // Create footer
    const footer = document.createElement('div');
    footer.style.cssText = `
        padding: 15px 20px;
        text-align: right;
        border-top: 1px solid #dee2e6;
    `;

    const okButton = document.createElement('button');
    okButton.textContent = 'OK';
    okButton.className = 'btn btn-primary';
    okButton.style.cssText = `
        padding: 8px 24px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
    `;
    okButton.onclick = function() {
        overlay.style.animation = 'fadeOut 0.2s ease-out';
        setTimeout(() => overlay.remove(), 200);
    };

    footer.appendChild(okButton);

    // Assemble
    alertBox.appendChild(header);
    alertBox.appendChild(body);
    alertBox.appendChild(footer);
    overlay.appendChild(alertBox);

    // Add CSS animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        @keyframes slideDown {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    `;
    document.head.appendChild(style);

    // Add to page
    document.body.appendChild(overlay);

    // Focus the OK button
    okButton.focus();

    // Allow Enter or Escape to close
    const handleKeyPress = function(e) {
        if (e.key === 'Enter' || e.key === 'Escape') {
            okButton.click();
            document.removeEventListener('keydown', handleKeyPress);
        }
    };
    document.addEventListener('keydown', handleKeyPress);
}
