// ========================================
// TrashSmart - Main JavaScript File (Updated for PHP)
// ========================================

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    
    // ========================================
    // Global Variables
    // ========================================
    
    const loginModal = document.getElementById('loginModal');
    const signupModal = document.getElementById('signupModal');
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
            const mobileLinks = mobileMenu.querySelectorAll('a, button');
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
    // Modal Management
    // ========================================
    
    function initModalHandling() {
        // Login Modal Triggers
        const loginTriggers = document.querySelectorAll('#loginBtn, #mobileLoginBtn, #heroLoginBtn');
        loginTriggers.forEach(trigger => {
            if (trigger) {
                trigger.addEventListener('click', function(e) {
                    e.preventDefault();
                    openModal(loginModal);
                });
            }
        });
        
        // Signup Modal Triggers
        const signupTriggers = document.querySelectorAll('#signupBtn, #mobileSignupBtn, #heroSignupBtn');
        signupTriggers.forEach(trigger => {
            if (trigger) {
                trigger.addEventListener('click', function(e) {
                    e.preventDefault();
                    openModal(signupModal);
                });
            }
        });
        
        // Close Modal Buttons
        const closeLoginBtn = document.getElementById('closeLoginModal');
        const closeSignupBtn = document.getElementById('closeSignupModal');
        
        if (closeLoginBtn) {
            closeLoginBtn.addEventListener('click', function() {
                closeModal(loginModal);
            });
        }
        
        if (closeSignupBtn) {
            closeSignupBtn.addEventListener('click', function() {
                closeModal(signupModal);
            });
        }
        
        // Close modals when clicking outside
        [loginModal, signupModal].forEach(modal => {
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        closeModal(modal);
                    }
                });
            }
        });
        
        // Close modals with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal(loginModal);
                closeModal(signupModal);
            }
        });
    }
    
    function openModal(modal) {
        if (modal) {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            
            // Focus on the first input
            const firstInput = modal.querySelector('input:not([type="hidden"])');
            if (firstInput) {
                setTimeout(() => firstInput.focus(), 100);
            }
        }
    }
    
    function closeModal(modal) {
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }
    }
    
    // ========================================
    // Modal Switching Functions
    // ========================================
    
    function initModalSwitching() {
        // Switch from Login to Signup
        const switchToSignup = document.getElementById('switchToSignup');
        if (switchToSignup) {
            switchToSignup.addEventListener('click', function(e) {
                e.preventDefault();
                closeModal(loginModal);
                openModal(signupModal);
            });
        }
        
        // Switch from Signup to Login
        const switchToLogin = document.getElementById('switchToLogin');
        if (switchToLogin) {
            switchToLogin.addEventListener('click', function(e) {
                e.preventDefault();
                closeModal(signupModal);
                openModal(loginModal);
            });
        }
    }
    
    // ========================================
    // Form Validation (Client-side)
    // ========================================
    
    function initFormValidation() {
        // Login Form Validation
        const loginForm = document.querySelector('#loginModal form');
        if (loginForm) {
            loginForm.addEventListener('submit', function(e) {
                const email = document.getElementById('loginEmail').value.trim();
                const password = document.getElementById('loginPassword').value.trim();
                
                if (!email || !password) {
                    e.preventDefault();
                    showMessage('Please fill in all fields', 'error');
                    return;
                }
                
                if (!isValidEmail(email)) {
                    e.preventDefault();
                    showMessage('Please enter a valid email address', 'error');
                    return;
                }
                
                // Show loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Logging in...';
                submitBtn.disabled = true;
            });
        }
        
        // Registration Form Validation
        const registerForm = document.querySelector('#signupModal form');
        if (registerForm) {
            registerForm.addEventListener('submit', function(e) {
                const formData = new FormData(this);
                const data = Object.fromEntries(formData);
                
                // Check required fields
                const requiredFields = ['firstName', 'lastName', 'dateOfBirth', 'district', 'address', 'nearestTown', 'phone', 'email', 'password', 'confirmPassword'];
                
                for (let field of requiredFields) {
                    if (!data[field] || !data[field].trim()) {
                        e.preventDefault();
                        showMessage('Please fill in all fields', 'error');
                        return;
                    }
                }
                
                // Email validation
                if (!isValidEmail(data.email)) {
                    e.preventDefault();
                    showMessage('Please enter a valid email address', 'error');
                    return;
                }
                
                // Password validation
                if (data.password.length < 6) {
                    e.preventDefault();
                    showMessage('Password must be at least 6 characters long', 'error');
                    return;
                }
                
                // Password confirmation
                if (data.password !== data.confirmPassword) {
                    e.preventDefault();
                    showMessage('Passwords do not match', 'error');
                    return;
                }
                
                // Phone validation (basic)
                if (!/^[+]?[\d\s-()]+$/.test(data.phone)) {
                    e.preventDefault();
                    showMessage('Please enter a valid phone number', 'error');
                    return;
                }
                
                // Show loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Registering...';
                submitBtn.disabled = true;
            });
        }
        
        // Contact Form Validation
        const contactForm = document.getElementById('contactForm');
        if (contactForm) {
            contactForm.addEventListener('submit', function(e) {
                const name = document.getElementById('contactName').value.trim();
                const email = document.getElementById('contactEmail').value.trim();
                const message = document.getElementById('contactMessage').value.trim();
                
                if (!name || !email || !message) {
                    e.preventDefault();
                    showMessage('Please fill in all fields', 'error');
                    return;
                }
                
                if (!isValidEmail(email)) {
                    e.preventDefault();
                    showMessage('Please enter a valid email address', 'error');
                    return;
                }
                
                // Show loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';
                submitBtn.disabled = true;
            });
        }
    }
    
    // ========================================
    // Utility Functions
    // ========================================
    
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    function showMessage(message, type = 'info') {
        // Create message element
        const messageDiv = document.createElement('div');
        messageDiv.className = `fixed top-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg max-w-sm ${
            type === 'success' ? 'bg-green-100 border border-green-400 text-green-700' :
            type === 'error' ? 'bg-red-100 border border-red-400 text-red-700' :
            'bg-blue-100 border border-blue-400 text-blue-700'
        }`;
        
        messageDiv.innerHTML = `
            <div class="flex items-center">
                <i class="fas ${
                    type === 'success' ? 'fa-check-circle' :
                    type === 'error' ? 'fa-exclamation-circle' :
                    'fa-info-circle'
                } mr-2"></i>
                <span>${message}</span>
                <button class="ml-4 text-current opacity-50 hover:opacity-75" onclick="this.parentElement.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        document.body.appendChild(messageDiv);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (messageDiv.parentElement) {
                messageDiv.remove();
            }
        }, 5000);
    }
    
    // ========================================
    // Smooth Scrolling for Navigation Links
    // ========================================
    
    function initSmoothScrolling() {
        const navLinks = document.querySelectorAll('a[href^="#"]');
        
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                
                // Skip if it's just "#" or empty
                if (href === '#' || href === '') {
                    e.preventDefault();
                    return;
                }
                
                const targetId = href.substring(1);
                const targetElement = document.getElementById(targetId);
                
                if (targetElement) {
                    e.preventDefault();
                    
                    // Close mobile menu if open
                    if (mobileMenu && !mobileMenu.classList.contains('hidden')) {
                        mobileMenu.classList.add('hidden');
                        const icon = mobileMenuBtn.querySelector('i');
                        icon.className = 'fas fa-bars text-xl';
                    }
                    
                    // Calculate offset for fixed header
                    const headerOffset = 80; // Adjust based on your header height
                    const elementPosition = targetElement.offsetTop;
                    const offsetPosition = elementPosition - headerOffset;
                    
                    window.scrollTo({
                        top: offsetPosition,
                        behavior: 'smooth'
                    });
                }
            });
        });
    }
    
    // ========================================
    // Navbar Scroll Effect
    // ========================================
    
    function initNavbarScrollEffect() {
        const header = document.querySelector('header');
        
        if (header) {
            window.addEventListener('scroll', function() {
                if (window.scrollY > 100) {
                    header.classList.add('bg-white', 'shadow-lg');
                    header.classList.remove('bg-transparent');
                } else {
                    header.classList.add('bg-white', 'shadow-md');
                    header.classList.remove('shadow-lg');
                }
            });
        }
    }
    
    // ========================================
    // Animation on Scroll (Simple)
    // ========================================
    
    function initScrollAnimations() {
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);
        
        // Observe elements for animation
        const animatedElements = document.querySelectorAll('.feature-card, .tip-card, .stat-card');
        animatedElements.forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });
    }
    
    // ========================================
    // Initialize All Functions
    // ========================================
    
    // Initialize all functionality
    initMobileMenu();
    initModalHandling();
    initModalSwitching();
    initFormValidation();
    initSmoothScrolling();
    initNavbarScrollEffect();
    initScrollAnimations();
});

// ========================================
// Global utility functions
// ========================================

// Function to refresh page after successful registration/login
function redirectToDashboard(userType) {
    if (userType === 'admin') {
        window.location.href = 'admin-dashboard.php';
    } else {
        window.location.href = 'citizen-profile.php';
    }
}

// Function to show loading state on buttons
function showButtonLoading(button, loadingText) {
    const originalText = button.innerHTML;
    button.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>${loadingText}`;
    button.disabled = true;
    
    return function() {
        button.innerHTML = originalText;
        button.disabled = false;
    };
}
