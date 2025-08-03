// ========================================
// TrashSmart - Citizen Profile JavaScript
// ========================================

document.addEventListener('DOMContentLoaded', function() {
    
    // ========================================
    // Mobile Menu Toggle
    // ========================================
    
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    
    mobileMenuBtn.addEventListener('click', function() {
        mobileMenu.classList.toggle('hidden');
    });
    
    // Close mobile menu when clicking outside
    document.addEventListener('click', function(event) {
        if (!mobileMenuBtn.contains(event.target) && !mobileMenu.contains(event.target)) {
            mobileMenu.classList.add('hidden');
        }
    });
    
    // ========================================
    // Edit Profile Modal
    // ========================================
    
    const editProfileBtn = document.getElementById('editProfileBtn');
    const editProfileModal = document.getElementById('editProfileModal');
    const closeEditProfileModal = document.getElementById('closeEditProfileModal');
    const cancelEditProfile = document.getElementById('cancelEditProfile');
    const editProfileForm = document.getElementById('editProfileForm');
    
    // Open edit profile modal
    editProfileBtn.addEventListener('click', function() {
        editProfileModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    });
    
    // Close edit profile modal
    function closeEditModal() {
        editProfileModal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
    
    closeEditProfileModal.addEventListener('click', closeEditModal);
    cancelEditProfile.addEventListener('click', closeEditModal);
    
    // Close modal when clicking outside
    editProfileModal.addEventListener('click', function(event) {
        if (event.target === editProfileModal) {
            closeEditModal();
        }
    });
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && !editProfileModal.classList.contains('hidden')) {
            closeEditModal();
        }
    });
    
    // ========================================
    // Edit Profile Form Submission
    // ========================================
    
    editProfileForm.addEventListener('submit', function(event) {
        event.preventDefault();
        
        // Get form data
        const formData = new FormData(editProfileForm);
        const data = Object.fromEntries(formData);
        
        // Show loading state
        const submitBtn = editProfileForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
        submitBtn.disabled = true;
        
        // Simulate API call
        setTimeout(function() {
            // Update profile information on the page
            updateProfileDisplay(data);
            
            // Show success message
            showMessage('Profile updated successfully!', 'success');
            
            // Reset button
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
            
            // Close modal
            closeEditModal();
        }, 1500);
    });
    
    // ========================================
    // Update Profile Display
    // ========================================
    
    function updateProfileDisplay(data) {
        // Update name
        const nameElement = document.querySelector('h2');
        nameElement.textContent = `${data.firstName} ${data.lastName}`;
        
        // Update phone
        const phoneElement = document.querySelector('.fa-phone').nextElementSibling;
        phoneElement.textContent = data.phone;
        
        // Update email
        const emailElement = document.querySelector('.fa-envelope').nextElementSibling;
        emailElement.textContent = data.email;
    }
    
    // ========================================
    // Cancel Request Buttons
    // ========================================
    
    const cancelButtons = document.querySelectorAll('.bg-red-600');
    cancelButtons.forEach(button => {
        button.addEventListener('click', function() {
            const card = button.closest('.bg-green-50, .bg-blue-50');
            const requestId = card.querySelector('strong').nextSibling.textContent.trim();
            
            if (confirm(`Are you sure you want to cancel request ${requestId}?`)) {
                // Show loading state
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Cancelling...';
                button.disabled = true;
                
                // Simulate API call
                setTimeout(function() {
                    card.style.opacity = '0.5';
                    card.style.pointerEvents = 'none';
                    button.innerHTML = '<i class="fas fa-check mr-2"></i>Cancelled';
                    button.classList.remove('bg-red-600', 'hover:bg-red-700');
                    button.classList.add('bg-gray-500');
                    
                    showMessage(`Request ${requestId} cancelled successfully!`, 'success');
                }, 1000);
            }
        });
    });
    
    // ========================================
    // Rate Service Buttons
    // ========================================
    
    const rateButtons = document.querySelectorAll('.bg-gray-600');
    rateButtons.forEach(button => {
        button.addEventListener('click', function() {
            const card = button.closest('.bg-white');
            const requestId = card.querySelector('strong').nextSibling.textContent.trim();
            
            // Show rating modal (simplified)
            const rating = prompt(`Rate your service for request ${requestId} (1-5 stars):`);
            if (rating && rating >= 1 && rating <= 5) {
                button.innerHTML = '<i class="fas fa-check mr-2"></i>Rated';
                button.classList.remove('bg-gray-600', 'hover:bg-gray-700');
                button.classList.add('bg-green-500');
                button.disabled = true;
                
                showMessage(`Thank you for rating request ${requestId}!`, 'success');
            }
        });
    });
    
    // ========================================
    // New Request Form
    // ========================================
    
    const newRequestForm = document.getElementById('newRequestForm');
    const pickupDateInput = document.getElementById('pickupDate');
    
    // Set minimum date to today
    const today = new Date().toISOString().split('T')[0];
    pickupDateInput.setAttribute('min', today);
    
    newRequestForm.addEventListener('submit', function(event) {
        event.preventDefault();
        
        // Get form data
        const formData = new FormData(newRequestForm);
        const data = Object.fromEntries(formData);
        
        // Validate form
        if (!data.address || !data.wasteType || !data.weight || !data.pickupDate) {
            showMessage('Please fill in all required fields.', 'error');
            return;
        }
        
        // Validate pickup date
        const selectedDate = new Date(data.pickupDate);
        const todayDate = new Date();
        todayDate.setHours(0, 0, 0, 0);
        
        if (selectedDate < todayDate) {
            showMessage('Pickup date cannot be in the past.', 'error');
            return;
        }
        
        // Show loading state
        const submitBtn = newRequestForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';
        submitBtn.disabled = true;
        
        // Simulate API call
        setTimeout(function() {
            // Generate new request ID
            const requestId = '#REQ' + String(Math.floor(Math.random() * 1000)).padStart(3, '0');
            
            // Show success message
            showMessage(`New request ${requestId} created successfully!`, 'success');
            
            // Reset form
            newRequestForm.reset();
            
            // Reset button
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
            
            // Optionally add new request to pending requests
            // addNewRequestToPending(data, requestId);
        }, 1500);
    });
    
    // ========================================
    // Logout Functionality
    // ========================================
    
    const logoutBtn = document.getElementById('logoutBtn');
    const mobileLogoutBtn = document.getElementById('mobileLogoutBtn');
    
    function logout() {
        if (confirm('Are you sure you want to logout?')) {
            // Show loading state
            logoutBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Logging out...';
            mobileLogoutBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Logging out...';
            
            // Simulate logout process
            setTimeout(function() {
                // Redirect to home page
                window.location.href = 'index.html';
            }, 1000);
        }
    }
    
    logoutBtn.addEventListener('click', logout);
    mobileLogoutBtn.addEventListener('click', logout);
    
    // ========================================
    // Utility Functions
    // ========================================
    
    // Show message function
    function showMessage(message, type = 'info') {
        // Create message element
        const messageDiv = document.createElement('div');
        messageDiv.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm transform transition-all duration-300 translate-x-full`;
        
        // Set message content and styling based on type
        let icon, bgColor, textColor, borderColor;
        
        switch(type) {
            case 'success':
                icon = 'fas fa-check-circle';
                bgColor = 'bg-green-100';
                textColor = 'text-green-800';
                borderColor = 'border-green-400';
                break;
            case 'error':
                icon = 'fas fa-exclamation-circle';
                bgColor = 'bg-red-100';
                textColor = 'text-red-800';
                borderColor = 'border-red-400';
                break;
            case 'warning':
                icon = 'fas fa-exclamation-triangle';
                bgColor = 'bg-yellow-100';
                textColor = 'text-yellow-800';
                borderColor = 'border-yellow-400';
                break;
            default:
                icon = 'fas fa-info-circle';
                bgColor = 'bg-blue-100';
                textColor = 'text-blue-800';
                borderColor = 'border-blue-400';
        }
        
        messageDiv.className += ` ${bgColor} ${textColor} border ${borderColor}`;
        messageDiv.innerHTML = `
            <div class="flex items-center">
                <i class="${icon} mr-2"></i>
                <span>${message}</span>
                <button class="ml-auto text-gray-500 hover:text-gray-700" onclick="this.parentElement.parentElement.remove()">
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
    
    // ========================================
    // Form Validation
    // ========================================
    
    // Email validation
    function validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    // Phone validation
    function validatePhone(phone) {
        const re = /^[\+]?[1-9][\d]{0,15}$/;
        return re.test(phone.replace(/\s/g, ''));
    }
    
    // Add validation to form inputs
    const emailInput = document.getElementById('editEmail');
    const phoneInput = document.getElementById('editPhone');
    
    emailInput.addEventListener('blur', function() {
        if (this.value && !validateEmail(this.value)) {
            this.classList.add('border-red-500');
            showMessage('Please enter a valid email address.', 'error');
        } else {
            this.classList.remove('border-red-500');
        }
    });
    
    phoneInput.addEventListener('blur', function() {
        if (this.value && !validatePhone(this.value)) {
            this.classList.add('border-red-500');
            showMessage('Please enter a valid phone number.', 'error');
        } else {
            this.classList.remove('border-red-500');
        }
    });
    
    // ========================================
    // Smooth Scrolling
    // ========================================
    
    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // ========================================
    // Scroll Effects
    // ========================================
    
    // Change header background on scroll
    window.addEventListener('scroll', function() {
        const header = document.querySelector('header');
        if (window.scrollY > 50) {
            header.classList.add('bg-white/95', 'backdrop-blur-sm');
        } else {
            header.classList.remove('bg-white/95', 'backdrop-blur-sm');
        }
    });
    
    // ========================================
    // Initialize Page
    // ========================================
    
    // Show welcome message
    setTimeout(() => {
        showMessage('Welcome back, Isini!', 'success');
    }, 1000);
}); 