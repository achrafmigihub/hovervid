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
     * Create and inject popup HTML based on error type
     */
    function createPopupHTML(isDisabled = false) {
        const currentDomain = window.location.hostname;
        
        if (isDisabled) {
            // Domain exists but is disabled
            return `
                <div id="slvp-popup-overlay" class="slvp-popup-overlay">
                    <div class="slvp-popup">
                        <div class="slvp-popup-header">
                            <button class="slvp-popup-close" id="slvp-popup-close" aria-label="Close popup">×</button>
                            <h2 class="slvp-popup-title">⚠️ Service Disabled</h2>
                            <p class="slvp-popup-subtitle">Plugin Inactive</p>
                        </div>
                        
                        <div class="slvp-popup-body">
                            <div class="slvp-popup-icon">🚫</div>
                            
                            <div class="slvp-popup-message">
                                <strong>Service Disabled:</strong> Your HoverVid plugin is currently inactive.<br>
                                Your subscription may have expired or your account may be suspended.
                            </div>
                            
                            <div class="slvp-popup-domain">
                                <strong>Current Domain:</strong> ${currentDomain}
                            </div>
                            
                            <div class="slvp-popup-buttons">
                                <a href="${POPUP_CONFIG.apiBaseUrl}/support" class="slvp-popup-button slvp-primary" id="slvp-support-btn" style="background: #10b981; border-color: #10b981;">
                                    📞 Contact Support
                                </a>
                                <a href="${POPUP_CONFIG.loginUrl}" class="slvp-popup-button slvp-secondary" id="slvp-dashboard-btn">
                                    🏠 Go to Dashboard
                                </a>
                            </div>
                        </div>
                        
                        <div class="slvp-popup-footer">
                            Need help? <a href="${POPUP_CONFIG.apiBaseUrl}/support" target="_blank">Contact Support</a>
                        </div>
                    </div>
                </div>
            `;
        } else {
            // Domain not found - original registration popup
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
                                <a href="${POPUP_CONFIG.loginUrl}" class="slvp-popup-button slvp-primary" id="slvp-login-btn" style="background: #10b981; border-color: #10b981;">
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
    }
    
    /**
     * Show the login popup
     */
    function showLoginPopup(isDisabled = false) {
        const popupType = isDisabled ? 'disabled' : 'not_registered';
        console.log(`🔐 Showing ${popupType} popup for domain`);
        
        // Remove existing popup if any
        const existingPopup = document.getElementById('slvp-popup-overlay');
        if (existingPopup) {
            existingPopup.remove();
        }
        
        // Create and inject popup
        const popupHTML = createPopupHTML(isDisabled);
        document.body.insertAdjacentHTML('beforeend', popupHTML);
        
        // Show popup with animation
        const popupOverlay = document.getElementById('slvp-popup-overlay');
        requestAnimationFrame(() => {
            popupOverlay.classList.add('slvp-show');
        });
        
        // Add event listeners
        setupPopupEventListeners(isDisabled);
        
        // Log analytics
        logPopupEvent('shown', popupType);
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
    function setupPopupEventListeners(isDisabled) {
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
        
        if (isDisabled) {
            // Support button tracking
            const supportBtn = document.getElementById('slvp-support-btn');
            if (supportBtn) {
                supportBtn.addEventListener('click', function() {
                    logPopupEvent('support_clicked', 'disabled');
                    // Add current domain as URL parameter
                    const currentDomain = window.location.hostname;
                    const url = new URL(this.href);
                    url.searchParams.set('domain', currentDomain);
                    url.searchParams.set('source', 'plugin');
                    this.href = url.toString();
                });
            }
            
            // Dashboard button tracking
            const dashboardBtn = document.getElementById('slvp-dashboard-btn');
            if (dashboardBtn) {
                dashboardBtn.addEventListener('click', function() {
                    logPopupEvent('dashboard_clicked', 'disabled');
                    // Add current domain as URL parameter
                    const currentDomain = window.location.hostname;
                    const url = new URL(this.href);
                    url.searchParams.set('domain', currentDomain);
                    url.searchParams.set('source', 'plugin');
                    this.href = url.toString();
                });
            }
        } else {
            // Login button tracking
            const loginBtn = document.getElementById('slvp-login-btn');
            if (loginBtn) {
                loginBtn.addEventListener('click', function() {
                    logPopupEvent('login_clicked', 'not_registered');
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
                    logPopupEvent('signup_clicked', 'not_registered');
                    // Add current domain as URL parameter for auto-registration
                    const currentDomain = window.location.hostname;
                    const url = new URL(this.href);
                    url.searchParams.set('domain', currentDomain);
                    url.searchParams.set('source', 'plugin');
                    this.href = url.toString();
                });
            }
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
    function logPopupEvent(action, domain) {
        if (window.gtag) {
            window.gtag('event', 'hovervid_popup', {
                'action': action,
                'domain': domain || window.location.hostname
            });
        }
        
        console.log(`📊 HoverVid Popup Event: ${action} for domain ${domain || window.location.hostname}`);
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
        
        // Show popup ONLY if domain doesn't exist in the database
        // Don't show popup when domain exists but is_verified is false
        if (!domainStatus || !domainStatus.domainExists) {
            console.log('❌ Domain not found in database - showing registration popup');
            
            // Delay popup slightly to ensure page is ready
            setTimeout(() => {
                showLoginPopup(false); // Domain not registered popup
            }, 1000);
            
            return false;
        }
        
        // Check if domain exists but is not active (disabled)
        if (domainStatus.domainExists && !domainStatus.isActive) {
            console.log('⚠️ Domain exists but is disabled - showing support popup');
            
            // Delay popup slightly to ensure page is ready
            setTimeout(() => {
                showLoginPopup(true); // Domain disabled popup
            }, 1000);
            
            return false;
        }
        
        console.log('✅ Domain exists and is active - no popup needed');
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
        config: POPUP_CONFIG,
        showDisabledPopup: () => showLoginPopup(true),
        showRegistrationPopup: () => showLoginPopup(false)
    };
    
    // Auto-initialize
    initLoginPopup();
    
})(); 
