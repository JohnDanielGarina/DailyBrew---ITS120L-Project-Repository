/**
 * DailyBrew - Authentication JavaScript
 * Handles frontend authentication functionality
 */

// Check if user is authenticated
async function checkAuth() {
    try {
        const response = await fetch('php/auth.php?action=check');
        const result = await response.json();
        return result.data.authenticated;
    } catch (error) {
        console.error('Auth check failed:', error);
        return false;
    }
}

// Get current user info
async function getUser() {
    try {
        const response = await fetch('php/auth.php?action=getUser');
        const result = await response.json();
        return result.data;
    } catch (error) {
        console.error('Get user failed:', error);
        return null;
    }
}

// Login function
async function login(firstName, lastName, password) {
    try {
        const response = await fetch('php/auth.php?action=login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                first_name: firstName,
                last_name: lastName,
                password: password
            })
        });
        
        const result = await response.json();
        
        if (result.error) {
            return { success: false, message: result.message };
        }
        
        return { success: true, data: result.data };
    } catch (error) {
        console.error('Login failed:', error);
        return { success: false, message: 'Network error. Please try again.' };
    }
}

// Signup function
async function signup(firstName, lastName, password, confirmPassword) {
    try {
        const response = await fetch('php/auth.php?action=signup', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                first_name: firstName,
                last_name: lastName,
                password: password,
                confirm_password: confirmPassword
            })
        });
        
        const result = await response.json();
        
        if (result.error) {
            return { success: false, message: result.message };
        }
        
        return { success: true, data: result.data };
    } catch (error) {
        console.error('Signup failed:', error);
        return { success: false, message: 'Network error. Please try again.' };
    }
}

// Logout function
async function logout() {
    try {
        await fetch('php/auth.php?action=logout', {
            method: 'POST'
        });
        window.location.href = 'index.html';
    } catch (error) {
        console.error('Logout failed:', error);
        window.location.href = 'index.html';
    }
}

// Validate form inputs
function validateLoginForm(firstName, lastName, password) {
    const errors = [];
    
    if (!firstName || firstName.trim().length === 0) {
        errors.push('First name is required');
    }
    
    if (!lastName || lastName.trim().length === 0) {
        errors.push('Last name is required');
    }
    
    if (!password || password.length < 6) {
        errors.push('Password must be at least 6 characters');
    }
    
    return errors;
}

function validateSignupForm(firstName, lastName, password, confirmPassword) {
    const errors = [];
    
    if (!firstName || firstName.trim().length === 0) {
        errors.push('First name is required');
    }
    
    if (!lastName || lastName.trim().length === 0) {
        errors.push('Last name is required');
    }
    
    if (!password || password.length < 6) {
        errors.push('Password must be at least 6 characters');
    }
    
    if (password !== confirmPassword) {
        errors.push('Passwords do not match');
    }
    
    return errors;
}

// Show toast notification
function showToast(message, type = 'info') {
    const container = document('toastContainer.getElementById');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <i class="toast-icon fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        <span class="toast-message">${message}</span>
        <button class="toast-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 5000);
}

// Show/hide loading
function showLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.classList.add('active');
    }
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.classList.remove('active');
    }
}

