/**
 * HoverVid Login Popup Handler
 * Shows popup when domain is not verified and provides login redirect
 */

(function() {
    'use strict';
    
    // Configuration
    const POPUP_CONFIG = {
        apiBaseUrl: window.slvp_vars?.api_base_url || 'http://localhost:8000',
        loginUrl: window.slvp_vars?.login_url || 'http://localhost:8000/login',
        signupUrl: window.slvp_vars?.signup_url || 'http://localhost:8000/register'
    };
    
    /**
     * Create and inject popup HTML
     */
    function createPopupHTML() {
        const currentDomain = window.location.hostname;
        
        return `
            <div id="slvp-popup-overlay" class="slvp-popup-overlay">
                <div class="slvp-popup">
                    <div class="slvp-popup-header">
                        <button class="slvp-popup-close" id="slvp-popup-close" aria-label="Close popup">×</button>
                        <h2 class="slvp-popup-title">🔐 Domain Not Registered</h2>
                        <p class="slvp-popup-subtitle">Authentication Required</p>
                    </div>
                    
                    <div class="slvp-popup-body">
                        <div class="slvp-popup-icon">🚫</div>
                        
                        <div class="slvp-popup-message">
                            <strong>Access Denied:</strong> Your domain is not registered with HoverVid.<br>
                            To use this plugin, you need to have an active account and register your domain.
                        </div>
                        
                        <div class="slvp-popup-domain">
                            <strong>Current Domain:</strong> ${currentDomain}
                        </div>
                        
                        <div class="slvp-popup-buttons">
                            <a href="${POPUP_CONFIG.loginUrl}" class="slvp-popup-button slvp-primary" id="slvp-login-btn">
                                🔑 Login to Dashboard
                            </a>
                            <a href="${POPUP_CONFIG.signupUrl}" class="slvp-popup-button slvp-secondary" id="slvp-signup-btn">
                                📝 Create Account
                            </a>
                        </div>
                    </div>
                    
                    <div class="slvp-popup-footer">
                        Need help? <a href="${POPUP_CONFIG.apiBaseUrl}/support" target="_blank">Contact Support</a>
                    </div>
                </div>
            </div>
        `;
    }
    
    /**
     * Show the login popup
     */
    function showLoginPopup() {
        console.log('🔐 Showing login popup for unregistered domain');
        
        // Remove existing popup if any
        const existingPopup = document.getElementById('slvp-popup-overlay');
        if (existingPopup) {
            existingPopup.remove();
        }
        
        // Create and inject popup
        const popupHTML = createPopupHTML();
        document.body.insertAdjacentHTML('beforeend', popupHTML);
        
        // Show popup with animation
        const popupOverlay = document.getElementById('slvp-popup-overlay');
        requestAnimationFrame(() => {
            popupOverlay.classList.add('slvp-show');
        });
        
        // Add event listeners
        setupPopupEventListeners();
        
        // Log analytics
        logPopupEvent('shown');
    }
    
    /**
     * Hide the login popup
     */
    function hideLoginPopup() {
        const popupOverlay = document.getElementById('slvp-popup-overlay');
        if (popupOverlay) {
            popupOverlay.classList.remove('slvp-show');
            setTimeout(() => {
                popupOverlay.remove();
            }, 300);
        }
        
        logPopupEvent('hidden');
    }
    
    /**
     * Setup event listeners for popup interactions
     */
    function setupPopupEventListeners() {
        // Close button
        const closeBtn = document.getElementById('slvp-popup-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', hideLoginPopup);
        }
        
        // Close on overlay click
        const overlay = document.getElementById('slvp-popup-overlay');
        if (overlay) {
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) {
                    hideLoginPopup();
                }
            });
        }
        
        // Login button tracking
        const loginBtn = document.getElementById('slvp-login-btn');
        if (loginBtn) {
            loginBtn.addEventListener('click', function() {
                logPopupEvent('login_clicked');
                // Add current domain as URL parameter for auto-registration
                const currentDomain = window.location.hostname;
                const url = new URL(this.href);
                url.searchParams.set('domain', currentDomain);
                url.searchParams.set('source', 'plugin');
                this.href = url.toString();
            });
        }
        
        // Signup button tracking
        const signupBtn = document.getElementById('slvp-signup-btn');
        if (signupBtn) {
            signupBtn.addEventListener('click', function() {
                logPopupEvent('signup_clicked');
                // Add current domain as URL parameter for auto-registration
                const currentDomain = window.location.hostname;
                const url = new URL(this.href);
                url.searchParams.set('domain', currentDomain);
                url.searchParams.set('source', 'plugin');
                this.href = url.toString();
            });
        }
        
        // ESC key to close
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideLoginPopup();
            }
        });
    }
    
    /**
     * Log popup events for analytics
     */
    function logPopupEvent(action) {
        if (window.gtag) {
            window.gtag('event', 'hovervid_popup', {
                'action': action,
                'domain': window.location.hostname
            });
        }
        
        console.log(`📊 HoverVid Popup Event: ${action} for domain ${window.location.hostname}`);
    }
    
    /**
     * Check if domain needs authentication popup
     */
    function checkDomainStatus() {
        // Get domain status from global variables (set by domain verifier)
        const domainStatus = window.slvp_vars ? {
            isActive: window.slvp_vars.is_domain_active === '1',
            domainExists: window.slvp_vars.domain_exists === '1',
            domain: window.slvp_vars.domain,
            message: window.slvp_vars.license_message
        } : null;
        
        console.log('🔍 Checking domain status:', domainStatus);
        
        // Show popup if domain is not verified
        if (!domainStatus || !domainStatus.isActive || !domainStatus.domainExists) {
            console.log('❌ Domain not verified - showing login popup');
            
            // Delay popup slightly to ensure page is ready
            setTimeout(() => {
                showLoginPopup();
            }, 1000);
            
            return false;
        }
        
        console.log('✅ Domain verified - no popup needed');
        return true;
    }
    
    /**
     * Initialize popup system
     */
    function initLoginPopup() {
        console.log('🚀 Initializing HoverVid login popup system');
        
        // Check domain status when page loads
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', checkDomainStatus);
        } else {
            checkDomainStatus();
        }
    }
    
    // Public API
    window.slvpLoginPopup = {
        show: showLoginPopup,
        hide: hideLoginPopup,
        checkStatus: checkDomainStatus,
        config: POPUP_CONFIG
    };
    
    // Auto-initialize
    initLoginPopup();
    
})(); 
