// Admin Management JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all functionality
    init();
});

function init() {
    // Initialize mobile menu
    initMobileMenu();
    
    // Initialize user removal functionality
    initUserRemoval();
    
    // Initialize logout functionality
    initLogout();
    
    // Show welcome message
    setTimeout(() => {
        showMessage('Welcome to Admin Management!', 'success');
    }, 1000);
}

// Mobile Menu Functionality
function initMobileMenu() {
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    
    if (mobileMenuBtn && mobileMenu) {
        mobileMenuBtn.addEventListener('click', function() {
            mobileMenu.classList.toggle('hidden');
        });
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            if (!mobileMenuBtn.contains(event.target) && !mobileMenu.contains(event.target)) {
                mobileMenu.classList.add('hidden');
            }
        });
    }
}

// User Removal Functionality
function initUserRemoval() {
    // Citizen removal buttons
    const removeCitizenBtns = document.querySelectorAll('.remove-citizen-btn');
    removeCitizenBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const row = this.closest('tr');
            const userName = row.cells[0].textContent; // Full Name column
            handleUserRemoval(row, userName, 'citizen');
        });
    });
    

}

// Handle user removal with confirmation
function handleUserRemoval(row, userName, userType) {
    // Show confirmation dialog
    if (confirm(`Are you sure you want to remove ${userName} (citizen)?`)) {
        // Simulate API call
        const originalText = row.innerHTML;
        row.style.opacity = '0.5';
        row.style.pointerEvents = 'none';
        
        setTimeout(() => {
            // Remove the row with animation
            row.style.transition = 'all 0.3s ease';
            row.style.transform = 'translateX(-100%)';
            row.style.opacity = '0';
            
            setTimeout(() => {
                row.remove();
                showMessage(`${userName} (citizen) has been removed successfully!`, 'success');
                updateStatistics(userType);
            }, 300);
        }, 500);
    }
}

// Update statistics after user removal
function updateStatistics(userType) {
    const statsCards = document.querySelectorAll('.text-4xl.font-bold');
    
    if (userType === 'citizen') {
        // Update Total Citizens count
        const citizensCard = statsCards[0];
        if (citizensCard) {
            let currentCount = parseInt(citizensCard.textContent);
            citizensCard.textContent = Math.max(0, currentCount - 1);
        }
    }
}

// Logout Functionality
function initLogout() {
    const logoutBtn = document.getElementById('logoutBtn');
    const mobileLogoutBtn = document.getElementById('mobileLogoutBtn');
    
    if (logoutBtn) {
        logoutBtn.addEventListener('click', handleLogout);
    }
    
    if (mobileLogoutBtn) {
        mobileLogoutBtn.addEventListener('click', handleLogout);
    }
}

function handleLogout() {
    if (confirm('Are you sure you want to logout?')) {
        showMessage('Logging out...', 'info');
        setTimeout(() => {
            window.location.href = 'index.html';
        }, 1500);
    }
}

// Toast Message System
function showMessage(message, type = 'info') {
    // Remove existing toast if any
    const existingToast = document.querySelector('.toast-message');
    if (existingToast) {
        existingToast.remove();
    }
    
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast-message fixed top-24 right-4 z-50 px-6 py-3 rounded-lg shadow-lg text-white font-medium transition-all duration-300 transform translate-x-full`;
    
    // Set background color based on type
    switch (type) {
        case 'success':
            toast.style.backgroundColor = '#10b981'; // green-500
            break;
        case 'error':
            toast.style.backgroundColor = '#ef4444'; // red-500
            break;
        case 'warning':
            toast.style.backgroundColor = '#f59e0b'; // amber-500
            break;
        default:
            toast.style.backgroundColor = '#3b82f6'; // blue-500
    }
    
    toast.textContent = message;
    
    // Add to page
    document.body.appendChild(toast);
    
    // Animate in
    setTimeout(() => {
        toast.style.transform = 'translateX(0)';
    }, 100);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        toast.style.transform = 'translateX(full)';
        setTimeout(() => {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 300);
    }, 3000);
}

// Smooth scrolling for navigation links
document.addEventListener('DOMContentLoaded', function() {
    const navLinks = document.querySelectorAll('a[href^="#"]');
    
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetId = this.getAttribute('href').substring(1);
            const targetElement = document.getElementById(targetId);
            
            if (targetElement) {
                const headerHeight = 80; // Approximate header height
                const targetPosition = targetElement.offsetTop - headerHeight;
                
                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });
});

// Add hover effects to complaint cards
document.addEventListener('DOMContentLoaded', function() {
    const complaintCards = document.querySelectorAll('#complaints .bg-white.rounded-xl');
    
    complaintCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});

// Table row hover effects
document.addEventListener('DOMContentLoaded', function() {
    const tableRows = document.querySelectorAll('tbody tr');
    
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f9fafb'; // gray-50
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
        });
    });
});

// Keyboard navigation for accessibility
document.addEventListener('keydown', function(e) {
    // Escape key to close mobile menu
    if (e.key === 'Escape') {
        const mobileMenu = document.getElementById('mobileMenu');
        if (mobileMenu && !mobileMenu.classList.contains('hidden')) {
            mobileMenu.classList.add('hidden');
        }
    }
});

// Add loading states to buttons
function addLoadingState(button, text) {
    const originalText = button.innerHTML;
    button.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>${text}`;
    button.disabled = true;
    return originalText;
}

function removeLoadingState(button, originalText) {
    button.innerHTML = originalText;
    button.disabled = false;
}

// Export functions for potential external use
window.AdminManagement = {
    showMessage,
    handleUserRemoval,
    updateStatistics,
    handleLogout
}; 