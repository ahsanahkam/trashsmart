// ========================================
// TrashSmart - Admin Dashboard JavaScript
// ========================================

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    
    // ========================================
    // Global Variables
    // ========================================
    
    const mobileMenu = document.getElementById('mobileMenu');
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    
    // ========================================
    // Mobile Menu Functionality
    // ========================================
    
    function initMobileMenu() {
        if (mobileMenuBtn && mobileMenu) {
            mobileMenuBtn.addEventListener('click', function() {
                mobileMenu.classList.toggle('hidden');
                
                // Change icon based on menu state
                const icon = mobileMenuBtn.querySelector('i');
                if (mobileMenu.classList.contains('hidden')) {
                    icon.className = 'fas fa-bars text-xl';
                } else {
                    icon.className = 'fas fa-times text-xl';
                }
            });
            
            // Close mobile menu when clicking on a link
            const mobileLinks = mobileMenu.querySelectorAll('a');
            mobileLinks.forEach(link => {
                link.addEventListener('click', function() {
                    mobileMenu.classList.add('hidden');
                    const icon = mobileMenuBtn.querySelector('i');
                    icon.className = 'fas fa-bars text-xl';
                });
            });
        }
    }
    
    // ========================================
    // Table Action Handlers
    // ========================================
    
    function initTableActions() {
        // Accept button handlers
        const acceptButtons = document.querySelectorAll('.accept-btn');
        acceptButtons.forEach(button => {
            button.addEventListener('click', function() {
                if (!button.disabled) {
                    handleAcceptRequest(this);
                }
            });
        });
        
        // Reject button handlers
        const rejectButtons = document.querySelectorAll('.reject-btn');
        rejectButtons.forEach(button => {
            button.addEventListener('click', function() {
                if (!button.disabled) {
                    handleRejectRequest(this);
                }
            });
        });
        
        // Collect button handlers
        const collectButtons = document.querySelectorAll('.collect-btn');
        collectButtons.forEach(button => {
            button.addEventListener('click', function() {
                if (!button.disabled) {
                    handleCollectRequest(this);
                }
            });
        });
    }
    
    function handleAcceptRequest(button) {
        const row = button.closest('tr');
        const requestId = row.querySelector('td:first-child').textContent;
        const statusCell = row.querySelector('td:nth-child(7) span');
        const actionButtons = row.querySelectorAll('button');
        
        // Show loading state
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Accepting...';
        button.disabled = true;
        
        // Simulate API call
        setTimeout(() => {
            // Update status
            statusCell.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800';
            statusCell.textContent = 'Accepted';
            
            // Update button states
            actionButtons.forEach(btn => {
                if (btn.classList.contains('accept-btn')) {
                    btn.innerHTML = 'Accepted';
                    btn.className = 'bg-gray-400 text-white px-3 py-1 rounded text-xs cursor-not-allowed';
                    btn.disabled = true;
                } else if (btn.classList.contains('reject-btn')) {
                    btn.disabled = true;
                }
            });
            
            showMessage(`Request ${requestId} has been accepted successfully!`, 'success');
            updateStatistics();
        }, 1000);
    }
    
    function handleRejectRequest(button) {
        const row = button.closest('tr');
        const requestId = row.querySelector('td:first-child').textContent;
        const statusCell = row.querySelector('td:nth-child(7) span');
        const actionButtons = row.querySelectorAll('button');
        
        // Show loading state
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Rejecting...';
        button.disabled = true;
        
        // Simulate API call
        setTimeout(() => {
            // Update status
            statusCell.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-brown-100 text-brown-800';
            statusCell.textContent = 'Rejected';
            
            // Update button states
            actionButtons.forEach(btn => {
                if (btn.classList.contains('reject-btn')) {
                    btn.innerHTML = 'Rejected';
                    btn.className = 'bg-gray-400 text-white px-3 py-1 rounded text-xs cursor-not-allowed';
                    btn.disabled = true;
                } else if (btn.classList.contains('accept-btn')) {
                    btn.disabled = true;
                } else if (btn.classList.contains('collect-btn')) {
                    btn.disabled = true;
                }
            });
            
            showMessage(`Request ${requestId} has been rejected.`, 'warning');
            updateStatistics();
        }, 1000);
    }
    
    function handleCollectRequest(button) {
        const row = button.closest('tr');
        const requestId = row.querySelector('td:first-child').textContent;
        const statusCell = row.querySelector('td:nth-child(7) span');
        const actionButtons = row.querySelectorAll('button');
        
        // Show loading state
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Collecting...';
        button.disabled = true;
        
        // Simulate API call
        setTimeout(() => {
            // Update status
            statusCell.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800';
            statusCell.textContent = 'Collected';
            
            // Update button states
            actionButtons.forEach(btn => {
                if (btn.classList.contains('collect-btn')) {
                    btn.innerHTML = 'Collected';
                    btn.className = 'bg-gray-400 text-white px-3 py-1 rounded text-xs cursor-not-allowed';
                    btn.disabled = true;
                } else if (btn.classList.contains('accept-btn')) {
                    btn.disabled = true;
                } else if (btn.classList.contains('reject-btn')) {
                    btn.disabled = true;
                }
            });
            
            showMessage(`Request ${requestId} has been marked as collected!`, 'success');
            updateStatistics();
        }, 1000);
    }
    
    // ========================================
    // Statistics Update
    // ========================================
    
    function updateStatistics() {
        // Count different statuses
        const pendingCount = document.querySelectorAll('td span:contains("Pending")').length;
        const collectedCount = document.querySelectorAll('td span:contains("Collected")').length;
        const rejectedCount = document.querySelectorAll('td span:contains("Rejected")').length;
        const acceptedCount = document.querySelectorAll('td span:contains("Accepted")').length;
        
        // Update statistics cards
        const totalRequests = document.querySelector('.bg-white.rounded-xl:nth-child(1) .text-3xl');
        const pendingRequests = document.querySelector('.bg-white.rounded-xl:nth-child(2) .text-3xl');
        const collectedRequests = document.querySelector('.bg-white.rounded-xl:nth-child(3) .text-3xl');
        const rejectedRequests = document.querySelector('.bg-white.rounded-xl:nth-child(4) .text-3xl');
        
        if (totalRequests) {
            totalRequests.textContent = pendingCount + collectedCount + rejectedCount + acceptedCount;
        }
        if (pendingRequests) {
            pendingRequests.textContent = pendingCount + acceptedCount;
        }
        if (collectedRequests) {
            collectedRequests.textContent = collectedCount;
        }
        if (rejectedRequests) {
            rejectedRequests.textContent = rejectedCount;
        }
    }
    
    // ========================================
    // Logout Functionality
    // ========================================
    
    function initLogout() {
        const logoutBtn = document.getElementById('logoutBtn');
        const mobileLogoutBtn = document.getElementById('mobileLogoutBtn');
        
        if (logoutBtn) {
            logoutBtn.addEventListener('click', function() {
                handleLogout();
            });
        }
        
        if (mobileLogoutBtn) {
            mobileLogoutBtn.addEventListener('click', function() {
                handleLogout();
            });
        }
    }
    
    function handleLogout() {
        showMessage('Logging out...', 'info');
        
        setTimeout(() => {
            window.location.href = 'index.html';
        }, 1500);
    }
    

    
    // ========================================
    // Smooth Scrolling
    // ========================================
    
    function initSmoothScrolling() {
        const links = document.querySelectorAll('a[href^="#"]');
        
        links.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href');
                const targetElement = document.querySelector(targetId);
                
                if (targetElement) {
                    const headerHeight = document.querySelector('header').offsetHeight;
                    const targetPosition = targetElement.offsetTop - headerHeight;
                    
                    window.scrollTo({
                        top: targetPosition,
                        behavior: 'smooth'
                    });
                }
            });
        });
    }
    
    // ========================================
    // Message System
    // ========================================
    
    function showMessage(message, type = 'info') {
        // Remove existing messages
        const existingMessages = document.querySelectorAll('.message-toast');
        existingMessages.forEach(msg => msg.remove());
        
        // Create message element
        const messageDiv = document.createElement('div');
        messageDiv.className = `message-toast fixed top-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg transform translate-x-full transition-transform duration-300 ${getMessageClasses(type)}`;
        messageDiv.innerHTML = `
            <div class="flex items-center">
                <i class="${getMessageIcon(type)} mr-3"></i>
                <span>${message}</span>
                <button class="ml-4 text-lg hover:opacity-70" onclick="this.parentElement.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        // Add to page
        document.body.appendChild(messageDiv);
        
        // Animate in
        setTimeout(() => {
            messageDiv.classList.remove('translate-x-full');
        }, 100);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (messageDiv.parentElement) {
                messageDiv.classList.add('translate-x-full');
                setTimeout(() => {
                    if (messageDiv.parentElement) {
                        messageDiv.remove();
                    }
                }, 300);
            }
        }, 5000);
    }
    
    function getMessageClasses(type) {
        switch (type) {
            case 'success':
                return 'bg-green-100 border border-green-400 text-green-700';
            case 'error':
                return 'bg-red-100 border border-red-400 text-red-700';
            case 'warning':
                return 'bg-yellow-100 border border-yellow-400 text-yellow-700';
            default:
                return 'bg-blue-100 border border-blue-400 text-blue-700';
        }
    }
    
    function getMessageIcon(type) {
        switch (type) {
            case 'success':
                return 'fas fa-check-circle';
            case 'error':
                return 'fas fa-exclamation-circle';
            case 'warning':
                return 'fas fa-exclamation-triangle';
            default:
                return 'fas fa-info-circle';
        }
    }
    
    // ========================================
    // District Filter Functionality
    // ========================================
    
    function initTableFilters() {
        const districtFilter = document.getElementById('districtFilter');
        const clearFilterBtn = document.getElementById('clearFilter');
        const pendingTable = document.querySelector('#pending-requests tbody');
        
        if (districtFilter && clearFilterBtn && pendingTable) {
            // District filter change handler
            districtFilter.addEventListener('change', function() {
                const selectedDistrict = this.value;
                filterTableByDistrict(selectedDistrict);
                updateFilterStatus(selectedDistrict);
            });
            
            // Clear filter button handler
            clearFilterBtn.addEventListener('click', function() {
                districtFilter.value = '';
                filterTableByDistrict('');
                updateFilterStatus('');
                showMessage('Filter cleared. Showing all districts.', 'info');
            });
        }
    }
    
    function filterTableByDistrict(district) {
        const tableRows = document.querySelectorAll('#pending-requests tbody tr');
        let visibleCount = 0;
        
        tableRows.forEach(row => {
            const rowDistrict = row.getAttribute('data-district');
            
            if (!district || district === '' || rowDistrict === district) {
                row.style.display = '';
                row.classList.remove('hidden');
                visibleCount++;
            } else {
                row.style.display = 'none';
                row.classList.add('hidden');
            }
        });
        
        // Update the count display
        updateVisibleCount(visibleCount, tableRows.length);
        
        // Show message about filtering
        if (district && district !== '') {
            showMessage(`Showing ${visibleCount} requests from ${district} district.`, 'info');
        }
    }
    
    function updateFilterStatus(district) {
        const filterContainer = document.querySelector('#districtFilter').closest('.bg-white');
        const statusElement = filterContainer.querySelector('.filter-status');
        
        if (statusElement) {
            statusElement.remove();
        }
        
        if (district && district !== '') {
            const status = document.createElement('div');
            status.className = 'filter-status mt-2 text-sm text-green-600 font-medium';
            status.innerHTML = `<i class="fas fa-filter mr-1"></i>Filtered by: ${district}`;
            filterContainer.appendChild(status);
        }
    }
    
    function updateVisibleCount(visible, total) {
        const countElement = document.querySelector('.visible-count');
        
        if (!countElement) {
            // Create count display if it doesn't exist
            const filterContainer = document.querySelector('#districtFilter').closest('.bg-white');
            const countDiv = document.createElement('div');
            countDiv.className = 'visible-count mt-2 text-sm text-gray-600';
            countDiv.innerHTML = `<i class="fas fa-list mr-1"></i>Showing ${visible} of ${total} requests`;
            filterContainer.appendChild(countDiv);
        } else {
            countElement.innerHTML = `<i class="fas fa-list mr-1"></i>Showing ${visible} of ${total} requests`;
        }
    }
    
    // ========================================
    // Initialize All Functions
    // ========================================
    
    function init() {
        // Initialize all components
        initMobileMenu();
        initTableActions();
        initLogout();
        initSmoothScrolling();
        initTableFilters();
        
        // Show welcome message
        setTimeout(() => {
            showMessage('Welcome to the Admin Dashboard!', 'success');
        }, 1000);
    }
    
    // Start the application
    init();
    
});

// ========================================
// Global Functions (accessible from HTML)
// ========================================

// Function to show messages globally
window.showMessage = function(message, type) {
    // This will be handled by the main script
};

// ========================================
// End of Admin Dashboard JavaScript
// ======================================== 