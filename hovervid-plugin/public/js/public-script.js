document.addEventListener('DOMContentLoaded', function() {
    // Debug code - print what's coming from PHP
    console.log('Debug: WordPress variables received:', window.slvp_vars);
    
    // =============================================
    // DOM Elements
    // =============================================
    const player = document.getElementById('slvp-player-container');
    const toggleButton = document.querySelector('.slvp-toggle-button');
    const video = document.getElementById('slvp-video-player');
    const closeBtn = document.querySelector('.slvp-close-btn');
    const plusBtn = document.querySelector('.slvp-plus-btn');
    const minusBtn = document.querySelector('.slvp-minus-btn');
    const playButton = player.querySelector('.slvp-play-button');
    
    // =============================================
    // Configuration
    // =============================================
    const config = {
        dimensions: {
            min: { width: 300, height: 400 },
            max: { width: 400, height: 700 },
            default: { 
                width: 300,
                height: Math.min(window.innerHeight * 0.5, 500)
            },
            margin: 20,
            edgeThreshold: 100
        },
        // Add domain status configuration from wp_localize_script
        domainStatus: {
            // Get domain status directly from PHP's wp_localize_script vars
            isActive: !!(window.slvp_vars && window.slvp_vars.is_domain_active === '1'),
            isForced: !!(window.slvp_vars && window.slvp_vars.is_forced_active === '1'),
            domainExists: !!(window.slvp_vars && window.slvp_vars.domain_exists === '1'),
            licenseMessage: window.slvp_vars ? window.slvp_vars.license_message : 'Your subscription or license has expired. Please contact support to renew your access.',
            domain: window.slvp_vars ? window.slvp_vars.domain : window.location.hostname
        },
        api: {
            ajaxUrl: window.slvp_vars ? window.slvp_vars.ajax_url : '',
            nonce: window.slvp_vars ? window.slvp_vars.ajax_nonce : ''
        }
    };
    
    // =============================================
    // State Management
    // =============================================
    const state = {
        isPluginActive: false,
        currentTranslation: null,
        drag: {
            isDragging: false,
            startX: 0,
            startY: 0,
            initialX: 0,
            initialY: 0
        },
        initialized: false
    };

    // =============================================
    // Initialization
    // =============================================
    function init() {
        if (state.initialized) return;
        
        player.classList.remove('slvp-visible');
        toggleButton.style.display = 'block';
        setPlayerSize(config.dimensions.default.width, config.dimensions.default.height);
        initDraggable();
        setInitialPosition();
        
        // Check if domain is authorized
        if (!config.domainStatus.domainExists) {
            console.log('UNAUTHORIZED DOMAIN - Plugin will be disabled');
            // Disable the plugin functionality
            config.domainStatus.isActive = false;
            // Show error on button hover via tooltip
            toggleButton.setAttribute('title', 'HoverVid Plugin Error: This domain is not authorized to use the HoverVid plugin. Please contact the plugin provider.');
            // Force disable button
            toggleButton.classList.add('slvp-inactive');
            toggleButton.setAttribute('disabled', 'disabled');
            
            // Setup event listeners
            setupEventListeners();
            state.initialized = true;
            return;
        }
        
        // Apply initial domain status - STRICT is_verified checking
        updateToggleButtonState();
        
        // ALWAYS check domain status via API for real-time tracking
        checkDomainStatusViaAPI();
        
        // Setup event listeners and continue initialization
        setupEventListeners();
        initElementorCompatibility();
        
        // Setup periodic checking to continuously monitor is_verified status
        setupPeriodicDomainCheck();
        
        state.initialized = true;
    }

    function setInitialPosition() {
        Object.assign(player.style, {
            right: config.dimensions.margin + 'px',
            bottom: config.dimensions.margin + 'px',
            position: 'fixed'
        });
    }

    // =============================================
    // Event Listeners Setup
    // =============================================
    function setupEventListeners() {
        // Video Events
        setupVideoEvents();
        
        // Toggle Button Events - Always set up the click handler
        toggleButton.addEventListener('click', handleToggleClick);
        
        // Control Button Events
        closeBtn.addEventListener('click', handleCloseClick);
        plusBtn.addEventListener('click', () => resizePlayer(true));
        minusBtn.addEventListener('click', () => resizePlayer(false));
        
        // Global Events
        document.addEventListener('click', handleGlobalClick);
        document.addEventListener('keyup', handleKeyPress);
        window.addEventListener('resize', handleWindowResize);
        
        // Hover Events
        setupHoverEvents();
    }

    function setupVideoEvents() {
        video.addEventListener('loadedmetadata', function() {
            setPlayerDimensions(this);
        });
        
        if (playButton) {
            video.addEventListener('ended', () => playButton.classList.add('visible'));
            video.addEventListener('play', () => playButton.classList.remove('visible'));
            playButton.addEventListener('click', () => {
                video.play();
                playButton.classList.remove('visible');
            });
        }
    }

    function setupHoverEvents() {
        // Use event delegation for hover events
        document.addEventListener('mouseover', function(e) {
            const wrapper = e.target.closest('.slvp-text-wrapper');
            if (wrapper && state.isPluginActive) {
                wrapper.classList.add('slvp-hover');
            }
        });

        document.addEventListener('mouseout', function(e) {
            const wrapper = e.target.closest('.slvp-text-wrapper');
            if (wrapper) {
                wrapper.classList.remove('slvp-hover');
            }
        });
    }

    // =============================================
    // Event Handlers
    // =============================================
    function handleToggleClick() {
        console.log('Toggle button clicked - checking current status first...');
        
        // Always check current status before processing click
        checkDomainStatusViaAPI(() => {
            // Now process the click with fresh status
            processToggleClick();
        });
    }
    
    function processToggleClick() {
        console.log('Processing toggle click with domain status:', config.domainStatus);
        
        // Check if domain exists in database
        if (!config.domainStatus.domainExists) {
            // Display unauthorized message
            console.log('❌ Domain not in database - showing unauthorized message');
            alert('HoverVid Plugin Error: This domain is not authorized to use the HoverVid plugin. Please contact the plugin provider to authorize your domain.');
            return;
        }
        
        // Check if domain is verified (active) - STRICT CHECK
        if (!config.domainStatus.isActive) {
            // Domain exists but is not verified - show subscription/license message
            console.log('❌ Domain is_verified = false - showing license expired message');
            alert(config.domainStatus.licenseMessage || 'Your subscription or license has expired. Please contact support to renew your access.');
            return;
        }
        
        // Only proceed if domain is verified
        console.log('✅ Domain is verified - allowing plugin functionality');
        state.isPluginActive = !state.isPluginActive;
        document.body.classList.toggle('slvp-active', state.isPluginActive);
        toggleButton.classList.toggle('slvp-active', state.isPluginActive);
        
        if (!state.isPluginActive) {
            resetTranslation();
        } else {
            showWelcomeVideo();
        }
    }

    function handleCloseClick(e) {
        e.preventDefault();
        
        // Block functionality if not verified
        if (!config.domainStatus.isActive) {
            console.log('❌ Close button blocked - domain not verified');
            return;
        }
        
        resetTranslation();
    }

    function handleGlobalClick(e) {
        // Block ALL functionality if domain is not verified
        if (!config.domainStatus.isActive) {
            console.log('❌ Global click blocked - domain not verified');
            return;
        }
        
        const translationIcon = e.target.closest('.slvp-translate-icon');
        if (!translationIcon) return;
        
        e.preventDefault();
        e.stopPropagation();
        
        const wrapper = translationIcon.closest('.slvp-text-wrapper');
        if (wrapper && !wrapper.classList.contains('slvp-processing')) {
            handleTranslationClick(translationIcon);
        }
    }

    function handleKeyPress(e) {
        if (e.key === "Escape" && player.classList.contains('slvp-visible')) {
            resetTranslation();
        }
    }

    function handleWindowResize() {
        if (video.videoWidth) {
            setPlayerDimensions(video);
        }
    }
    
    // =============================================
    // License Status Handling
    // =============================================
    function initInactiveLicenseTooltip() {
        // Tooltip already created in PHP via title attribute
        // Add a visual indicator for the inactive state if needed
        toggleButton.classList.add('slvp-inactive');
    }

    // =============================================
    // Elementor Compatibility
    // =============================================
    function initElementorCompatibility() {
        if (window.elementorFrontend) {
            elementorFrontend.hooks.addAction('frontend/element_ready/global', initializePlugin);
        }

        if (window.elementor) {
            elementor.on('preview:loaded', initializePlugin);
        }

        // Handle dynamic content updates
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length) {
                    initializePlugin();
                }
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    // =============================================
    // Translation Handling
    // =============================================
    function handleTranslationClick(icon) {
        if (!state.isPluginActive) return;
        
        const wrapper = icon.closest('.slvp-text-wrapper');
        if (!wrapper || wrapper.classList.contains('slvp-processing')) return;
        
        updateTranslationState(wrapper);
        sendTranslationRequest(wrapper.getAttribute('data-slvp-hash'), wrapper);
    }

    function updateTranslationState(wrapper) {
        if (state.currentTranslation) {
            state.currentTranslation.classList.remove('slvp-current-translation', 'slvp-processing');
            state.currentTranslation.style.pointerEvents = 'auto';
        }
        
        state.currentTranslation = wrapper;
        wrapper.classList.add('slvp-current-translation', 'slvp-processing');
        wrapper.style.pointerEvents = 'none';
        
        setTimeout(() => {
            if (wrapper === state.currentTranslation) {
                wrapper.style.pointerEvents = 'auto';
            }
        }, 1000);
    }

    // =============================================
    // Player Controls
    // =============================================
    function setPlayerSize(width, height) {
        const maxWidth = window.innerWidth - (config.dimensions.margin * 2);
        const maxHeight = window.innerHeight - (config.dimensions.margin * 2);
        
        width = Math.min(Math.max(width, config.dimensions.min.width), Math.min(maxWidth, config.dimensions.max.width));
        height = Math.min(Math.max(height, config.dimensions.min.height), Math.min(maxHeight, config.dimensions.max.height));
        
        Object.assign(player.style, {
            width: width + 'px',
            height: (height + 60) + 'px'
        });
        
        const videoFrame = player.querySelector('.slvp-video-frame');
        if (videoFrame) {
            videoFrame.style.height = height + 'px';
            videoFrame.style.width = width + 'px';
        }
    }

    function resizePlayer(isIncrease) {
        // Store current state
        const current = {
            width: parseInt(player.style.width),
            height: parseInt(player.style.height) - 60,
            left: parseInt(player.style.left) || 0,
            top: parseInt(player.style.top) || 0,
            right: parseInt(player.style.right) || 0,
            bottom: parseInt(player.style.bottom) || 0
        };

        // Calculate size changes
        const change = {
            width: isIncrease ? 30 : -30,
            height: isIncrease ? 50 : -50
        };

        // Get current position and viewport dimensions
        const rect = player.getBoundingClientRect();
        const viewport = {
            width: window.innerWidth,
            height: window.innerHeight
        };

        // Calculate distances from edges
        const distances = {
            left: rect.left,
            right: viewport.width - rect.right,
            top: rect.top,
            bottom: viewport.height - rect.bottom
        };

        // Determine which edges are closest
        const isNearLeft = distances.left < 100;
        const isNearRight = distances.right < 100;
        const isNearTop = distances.top < 100;
        const isNearBottom = distances.bottom < 100;

        // Check if frame is centered (not near any edges)
        const isCentered = !isNearLeft && !isNearRight && !isNearTop && !isNearBottom;

        // Calculate new size with constraints
        const newSize = {
            width: Math.max(config.dimensions.min.width, 
                   Math.min(current.width + change.width, config.dimensions.max.width)),
            height: Math.max(config.dimensions.min.height, 
                    Math.min(current.height + change.height, config.dimensions.max.height))
        };

        // Apply changes
        requestAnimationFrame(() => {
            // Update size first
            player.style.width = newSize.width + 'px';
            player.style.height = (newSize.height + 60) + 'px';

            if (isCentered) {
                // Centered resize - resize from all corners
                const widthDiff = newSize.width - current.width;
                const heightDiff = newSize.height - current.height;

                // Calculate new position to maintain center
                const newLeft = current.left - (widthDiff / 2);
                const newTop = current.top - (heightDiff / 2);

                // Apply new position with margin check
                player.style.left = Math.max(10, newLeft) + 'px';
                player.style.top = Math.max(10, newTop) + 'px';
                player.style.right = 'auto';
                player.style.bottom = 'auto';
            } else {
                // Handle horizontal position based on nearest edge
                if (isNearLeft) {
                    // If near left edge, resize from right and maintain 10px margin
                    player.style.left = '10px';
                    player.style.right = 'auto';
                } else if (isNearRight) {
                    // If near right edge, resize from left and maintain 10px margin
                    player.style.right = '10px';
                    player.style.left = 'auto';
                } else {
                    // If not near either edge, maintain current position with margin check
                    if (current.left > 0) {
                        player.style.left = Math.max(10, current.left) + 'px';
                        player.style.right = 'auto';
                    } else {
                        player.style.right = Math.max(10, current.right) + 'px';
                        player.style.left = 'auto';
                    }
                }

                // Handle vertical position based on nearest edge
                if (isNearTop) {
                    // If near top edge, resize from bottom and maintain 10px margin
                    player.style.top = '10px';
                    player.style.bottom = 'auto';
                } else if (isNearBottom) {
                    // If near bottom edge, resize from top and maintain 10px margin
                    player.style.bottom = '10px';
                    player.style.top = 'auto';
                } else {
                    // If not near either edge, maintain current position with margin check
                    if (current.top > 0) {
                        player.style.top = Math.max(10, current.top) + 'px';
                        player.style.bottom = 'auto';
                    } else {
                        player.style.bottom = Math.max(10, current.bottom) + 'px';
                        player.style.top = 'auto';
                    }
                }
            }

            // Ensure video frame and video are visible
            const videoFrame = player.querySelector('.slvp-video-frame');
            if (videoFrame) {
                videoFrame.style.width = newSize.width + 'px';
                videoFrame.style.height = newSize.height + 'px';
                videoFrame.style.display = 'block';
                videoFrame.style.visibility = 'visible';
                videoFrame.style.opacity = '1';
                
                // Ensure video is visible
                const video = videoFrame.querySelector('video');
                if (video) {
                    video.style.width = '100%';
                    video.style.height = '100%';
                    video.style.display = 'block';
                    video.style.visibility = 'visible';
                    video.style.opacity = '1';
                }
            }

            // Position buttons in header line
            const buttons = player.querySelectorAll('.slvp-close-btn, .slvp-plus-btn, .slvp-minus-btn');
            buttons.forEach(button => {
                button.style.display = 'block';
                button.style.opacity = '1';
                button.style.visibility = 'visible';
                button.style.position = 'absolute';
                button.style.zIndex = '1000';
                button.style.top = '10px';
            });

            // Position buttons in header line
            const closeBtn = player.querySelector('.slvp-close-btn');
            const plusBtn = player.querySelector('.slvp-plus-btn');
            const minusBtn = player.querySelector('.slvp-minus-btn');

            if (closeBtn) {
                closeBtn.style.right = '10px';
            }
            if (plusBtn) {
                plusBtn.style.right = '50px';
            }
            if (minusBtn) {
                minusBtn.style.right = '90px';
            }

            // Force a reflow to ensure visibility
            player.offsetHeight;
        });
    }

    function calculateNewPosition(current, newSize, distances, isCentered) {
        const position = { ...current };
        const sizeDiff = {
            width: newSize.width - current.width,
            height: newSize.height - current.height
        };

        if (isCentered.horizontal && isCentered.vertical) {
            // Center-anchored resize
            position.left = current.left - (sizeDiff.width / 2);
            position.top = current.top - (sizeDiff.height / 2);
        } else {
            // Edge-anchored resize
            if (distances.left < distances.right) {
                // Left edge is closer
                position.left = current.left;
            } else {
                // Right edge is closer
                position.right = current.right;
            }

            if (distances.top < distances.bottom) {
                // Top edge is closer
                position.top = current.top;
            } else {
                // Bottom edge is closer
                position.bottom = current.bottom;
            }
        }

        return position;
    }

    // =============================================
    // Drag Functionality
    // =============================================
    function initDraggable() {
        const header = player.querySelector('.slvp-player-header');
        
        // Mouse events
        header.addEventListener('mousedown', startDragging);
        document.addEventListener('mousemove', drag);
        document.addEventListener('mouseup', stopDragging);
        
       
        header.addEventListener('touchstart', startDragging, { passive: false });
        document.addEventListener('touchmove', drag, { passive: false });
        document.addEventListener('touchend', stopDragging);
    }

    function startDragging(e) {
        if (!player.classList.contains('slvp-visible')) return;
        
        const target = e.target;
        if (target.closest('.slvp-close-btn, .slvp-plus-btn, .slvp-minus-btn, video')) return;
        
        e.preventDefault(); 
        
        player.style.transition = 'none';
        player.classList.add('slvp-dragging');
        
        state.drag.isDragging = true;
        const touch = e.type === 'touchstart' ? e.touches[0] : e;
        
        state.drag.startX = touch.clientX;
        state.drag.startY = touch.clientY;
        
        const rect = player.getBoundingClientRect();
        state.drag.initialX = rect.left;
        state.drag.initialY = rect.top;
    }

    function drag(e) {
        if (!state.drag.isDragging) return;
        
        e.preventDefault(); 
        
        const touch = e.type === 'touchmove' ? e.touches[0] : e;
        const newPosition = calculateDragPosition(touch);
        
        requestAnimationFrame(() => {
            Object.assign(player.style, {
                left: newPosition.left + 'px',
                top: newPosition.top + 'px',
                position: 'fixed'
            });
        });
    }

    function calculateDragPosition(touch) {
        const deltaX = touch.clientX - state.drag.startX;
        const deltaY = touch.clientY - state.drag.startY;
        
        let newX = state.drag.initialX + deltaX;
        let newY = state.drag.initialY + deltaY;
        
        // Ensure 10px margin from edges
        const minX = 10;
        const minY = 10;
        const maxX = window.innerWidth - player.offsetWidth - 10;
        const maxY = window.innerHeight - player.offsetHeight - 10;
        
        return {
            left: Math.max(minX, Math.min(newX, maxX)),
            top: Math.max(minY, Math.min(newY, maxY))
        };
    }

    function stopDragging() {
        state.drag.isDragging = false;
        player.classList.remove('slvp-dragging');
        player.style.transition = 'all 0.2s ease';
    }

    // =============================================
    // Video Loading
    // =============================================
    function loadVideo(url) {
        video.pause();
        video.innerHTML = `<source src="${url}" type="video/mp4">`;
        
        video.onloadedmetadata = function() {
            const videoSize = calculateVideoSize(this.videoWidth, this.videoHeight);
            setPlayerSize(videoSize.width, videoSize.height);
            
            // Ensure video fills the frame
            this.style.width = '100%';
            this.style.height = '100%';
            this.style.objectFit = 'fill';
            
            // Only set initial position if the player is not already positioned
            // This ensures the player stays where the user dragged it
            if (!player.style.left && !player.style.top) {
                Object.assign(player.style, {
                    right: config.dimensions.margin + 'px',
                    bottom: config.dimensions.margin + 'px',
                    left: 'auto',
                    top: 'auto'
                });
            }
            
            const videoFrame = player.querySelector('.slvp-video-frame');
            if (videoFrame) {
                videoFrame.style.width = '100%';
                videoFrame.style.height = '100%';
            }
            
            player.classList.add('slvp-visible');
            toggleButton.style.display = 'none';
            this.play();
        };
        
        video.load();
    }

    function calculateVideoSize(width, height) {
        // Set initial height to 50% of viewport height
        const initialHeight = window.innerHeight * 0.5;
        const aspectRatio = width / height;
        
        // Calculate width based on initial height and aspect ratio
        let newWidth = initialHeight * aspectRatio;
        let newHeight = initialHeight;
        
        // If width exceeds max width, scale down
        if (newWidth > config.dimensions.max.width) {
            newWidth = config.dimensions.max.width;
            newHeight = newWidth / aspectRatio;
        }
        
        // Ensure dimensions are within min/max bounds
        newWidth = Math.max(config.dimensions.min.width, Math.min(newWidth, config.dimensions.max.width));
        newHeight = Math.max(config.dimensions.min.height, Math.min(newHeight, config.dimensions.max.height));
        
        return {
            width: Math.round(newWidth),
            height: Math.round(newHeight)
        };
    }

    function sendTranslationRequest(textHash, wrapper) {
        fetch(window.slvp_vars.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'slvp_get_video',
                text_hash: textHash,
                security: window.slvp_vars.ajax_nonce
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.video_url) {
                loadVideo(data.data.video_url);
            } else {
                handleError(wrapper);
            }
        })
        .catch(() => handleError(wrapper))
        .finally(() => {
            wrapper.classList.remove('slvp-processing');
            // Re-enable pointer events
            wrapper.style.pointerEvents = 'auto';
            
            // Recreate the icon if it was removed
            const icon = wrapper.querySelector('.slvp-translate-icon');
            if (!icon || !icon.innerHTML.trim()) {
                const newIcon = document.createElement('span');
                newIcon.className = 'slvp-translate-icon';
                const img = document.createElement('img');
                img.src = window.slvp_vars.plugin_url + 'assets/hovervid-icon.svg';
                img.alt = 'Translate';
                newIcon.appendChild(img);
                newIcon.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    handleTranslationClick(newIcon);
                });
                
                if (icon) {
                    wrapper.replaceChild(newIcon, icon);
                } else {
                    wrapper.appendChild(newIcon);
                }
            }
        });
    }

    function handleError(wrapper) {
        wrapper.classList.remove('slvp-processing');
        wrapper.classList.remove('slvp-current-translation');
        console.error('Failed to load video translation');
        
        const icon = wrapper.querySelector('.slvp-translate-icon');
        if (icon) {
            icon.style.background = '#dc2626';
            setTimeout(() => {
                icon.style.background = '';
            }, 2000);
        }
    }

    function resetTranslation() {
        if (state.currentTranslation) {
            state.currentTranslation.classList.remove('slvp-current-translation');
            state.currentTranslation.style.pointerEvents = 'auto';
        }
        state.currentTranslation = null;
        state.isPluginActive = false;
        
        // Reset all text wrapper styles
        document.querySelectorAll('.slvp-text-wrapper').forEach(el => {
            el.classList.remove('slvp-current-translation', 'slvp-processing', 'slvp-hover', 'slvp-mobile-active');
            el.style.pointerEvents = 'auto';
        });
        
        player.classList.remove('slvp-visible');
        toggleButton.style.display = 'block';
        toggleButton.classList.remove('slvp-active');
        document.body.classList.remove('slvp-active');
        video.pause();
    }

    // =============================================
    // Move setPlayerDimensions inside the DOMContentLoaded scope
    // =============================================
    function setPlayerDimensions(video) {
        // Get video's natural dimensions
        const originalWidth = video.videoWidth;
        const originalHeight = video.videoHeight;
        
        if (originalWidth && originalHeight) {
            // Calculate maximum dimensions based on viewport
            const maxWidth = window.innerWidth * 0.9;
            const maxHeight = window.innerHeight * 0.9;
            const initialHeight = window.innerHeight * 0.5; // 50% of viewport height
            const aspectRatio = originalWidth / originalHeight;
            
            // For vertical videos (height > width)
            if (originalHeight > originalWidth) {
                // Start with height based on viewport
                let height = initialHeight; // Changed from maxHeight to initialHeight
                let width = height * aspectRatio;
                
                // If width is too large, scale down
                if (width > maxWidth) {
                    width = maxWidth;
                    height = width / aspectRatio;
                }
                
                // Apply dimensions
                const headerHeight = 60;
                const videoFrame = player.querySelector('.slvp-video-frame');
                
                // Set container dimensions first
                player.style.width = `${width}px`;
                player.style.height = `${height + headerHeight}px`;
                
                // Set video frame dimensions
                if (videoFrame) {
                    videoFrame.style.width = `${width}px`;
                    videoFrame.style.height = `${height}px`;
                    
                    // Ensure video fills frame
                    const videoElement = videoFrame.querySelector('video');
                    if (videoElement) {
                        videoElement.style.width = '100%';
                        videoElement.style.height = '100%';
                        videoElement.style.objectFit = 'fill';
                    }
                }
            } else {
                // For horizontal videos
                let width = originalWidth;
                let height = originalHeight;
                
                // First set to initial height (50% of viewport)
                height = initialHeight;
                width = height * aspectRatio;
                
                // Scale down if needed while maintaining aspect ratio
                const scaleRatio = Math.min(
                    maxWidth / width,
                    maxHeight / height
                );
                
                if (scaleRatio < 1) {
                    width *= scaleRatio;
                    height *= scaleRatio;
                }
                
                // Apply dimensions
                const headerHeight = 60;
                
                // Set video frame dimensions
                const videoFrame = player.querySelector('.slvp-video-frame');
                if (videoFrame) {
                    videoFrame.style.width = `${width}px`;
                    videoFrame.style.height = `${height}px`;
                    
                    // Ensure video fills frame
                    videoFrame.style.overflow = 'hidden';
                }
                
                // Set player container dimensions
                player.style.width = `${width}px`;
                player.style.height = `${height + headerHeight}px`;
                
                // Set video element dimensions
                video.style.width = '100%';
                video.style.height = '100%';
                video.style.objectFit = 'fill';
            }
        }
    }

    // =============================================
    // Plugin Initialization
    // =============================================
    function addToggleButton() {
        if (document.querySelector('.slvp-toggle-button')) return;
        
        const button = document.createElement('button');
        button.className = 'slvp-toggle-button';
        button.setAttribute('aria-label', 'Toggle Sign Language Video');
        
        const img = document.createElement('img');
        img.src = window.slvp_vars.plugin_url + 'assets/hovervid-icon.svg';
        img.alt = 'Sign Language Video';
        button.appendChild(img);
        
        document.body.appendChild(button);
    }

    function addPlayerContainer() {
        if (document.getElementById('slvp-player-container')) return;
        
        const container = document.createElement('div');
        container.id = 'slvp-player-container';
        
        const header = document.createElement('div');
        header.className = 'slvp-player-header';
        
        const closeBtn = document.createElement('button');
        closeBtn.className = 'slvp-control-btn slvp-close-btn';
        closeBtn.innerHTML = '&times;';
        closeBtn.setAttribute('aria-label', 'Close');
        
        const plusBtn = document.createElement('button');
        plusBtn.className = 'slvp-control-btn slvp-plus-btn';
        plusBtn.innerHTML = '+';
        plusBtn.setAttribute('aria-label', 'Increase Size');
        
        const minusBtn = document.createElement('button');
        minusBtn.className = 'slvp-control-btn slvp-minus-btn';
        minusBtn.innerHTML = '&minus;';
        minusBtn.setAttribute('aria-label', 'Decrease Size');
        
        const controls = document.createElement('div');
        controls.className = 'slvp-player-controls';
        controls.appendChild(minusBtn);
        controls.appendChild(plusBtn);
        controls.appendChild(closeBtn);
        
        header.appendChild(controls);
        
        const videoFrame = document.createElement('div');
        videoFrame.className = 'slvp-video-frame';
        
        const video = document.createElement('video');
        video.id = 'slvp-video-player';
        video.setAttribute('playsinline', '');
        video.setAttribute('controls', '');
        
        const playButton = document.createElement('button');
        playButton.className = 'slvp-play-button';
        playButton.setAttribute('aria-label', 'Play');
        
        videoFrame.appendChild(video);
        videoFrame.appendChild(playButton);
        
        container.appendChild(header);
        container.appendChild(videoFrame);
        
        document.body.appendChild(container);
    }

    function initializePlugin() {
        addToggleButton();
        addPlayerContainer();
        setupEventListeners();
    }

    // =============================================
    // Video Management
    // =============================================
    function showWelcomeVideo() {
        player.classList.add('slvp-visible');
        toggleButton.style.display = 'none';
        
        video.innerHTML = `<source src="${window.slvp_vars.plugin_url}assets/welcome-vid.mp4" type="video/mp4">`;
        video.load();
        
        video.play().catch(error => {
            console.log('Video autoplay failed:', error);
        });
    }

    // =============================================
    // Initialize the plugin
    // =============================================
    init();

    /**
     * Check domain status via API
     * This ensures we get real-time status from the database
     * 
     * @param {Function} callback Function to call after status check completes
     */
    function checkDomainStatusViaAPI(callback) {
        const timestamp = new Date().toISOString();
        console.log(`[${timestamp}] Checking domain status via API...`);
        console.log('API details:', config.api);
        console.log('Current domain status:', config.domainStatus);
        
        // Skip for domains that don't exist in database
        if (!config.domainStatus.domainExists) {
            console.log('Domain is not in the database, skipping API check');
            if (callback) callback();
            return;
        }
        
        // Skip for forced domains from PHP
        if (config.domainStatus.isForced) {
            console.log('Domain is force enabled from PHP, skipping API check');
            if (callback) callback();
            return;
        }
        
        // Check if we're on a known active domain - if so, skip API check
        const currentDomain = window.location.hostname;
        const knownActiveDomains = ['sign-language-video-plugin.local'];
        
        if (knownActiveDomains.includes(currentDomain)) {
            console.log('This is a known active domain, skipping API check');
            // Ensure it's active and proceed with callback
            config.domainStatus.isActive = true;
            if (callback) callback();
            return;
        }
        
        // Skip if we don't have API details
        if (!config.api.ajaxUrl) {
            console.log('No AJAX URL available, using PHP value');
            // Fallback to wp_localize_script value if API check not possible
            config.domainStatus.isActive = typeof window.slvp_vars !== 'undefined' && window.slvp_vars.is_domain_active === '1';
            console.log('Default value set:', config.domainStatus.isActive);
            if (callback) callback();
            return;
        }
        
        // Store previous status to detect changes
        const previousStatus = config.domainStatus.isActive;
        
        const formData = new FormData();
        formData.append('action', 'slvp_check_domain');
        formData.append('security', config.api.nonce);
        formData.append('timestamp', timestamp); // Add timestamp to prevent caching
        
        console.log(`[${timestamp}] Sending fetch request to:`, config.api.ajaxUrl);
        fetch(config.api.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData,
            cache: 'no-cache', // Prevent caching
            headers: {
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache',
                'Expires': '0'
            }
        })
        .then(response => {
            console.log(`[${timestamp}] Received response:`, response);
            return response.json();
        })
        .then(data => {
            const responseTime = new Date().toISOString();
            console.log(`[${responseTime}] Received data:`, data);
            
            if (data.success) {
                console.log('API reported success. is_active from API:', data.data.is_active);
                console.log('API reported domain_exists:', data.data.domain_exists);
                
                // Strictly check for boolean true
                config.domainStatus.isActive = data.data.is_active === true;
                config.domainStatus.domainExists = data.data.domain_exists === true;
                
                // Set appropriate message based on status
                if (data.data.message) {
                    config.domainStatus.licenseMessage = data.data.message;
                } else {
                    // Fallback messages based on status
                    if (config.domainStatus.domainExists && !config.domainStatus.isActive) {
                        config.domainStatus.licenseMessage = 'Your subscription or license has expired. Please contact support to renew your access.';
                    } else if (!config.domainStatus.domainExists) {
                        config.domainStatus.licenseMessage = 'This domain is not authorized to use the HoverVid plugin.';
                    } else {
                        config.domainStatus.licenseMessage = 'Domain is verified and active.';
                    }
                }
                
                console.log(`[${responseTime}] Domain status after API update:`, config.domainStatus);
                
                // Check if status changed
                if (previousStatus !== config.domainStatus.isActive) {
                    const statusChange = config.domainStatus.isActive ? 'DISABLED → ENABLED' : 'ENABLED → DISABLED';
                    console.log(`[${responseTime}] ⚠️ STATUS CHANGED: ${statusChange}`);
                    
                    // Show notification about status change
                    if (config.domainStatus.isActive) {
                        console.log('✅ Plugin has been ENABLED');
                    } else {
                        console.log('❌ Plugin has been DISABLED');
                        // If plugin was active, reset it
                        if (state.isPluginActive) {
                            resetTranslation();
                        }
                    }
                }
                
                // If the domain doesn't exist, disable completely
                if (!config.domainStatus.domainExists) {
                    // Disable the plugin functionality
                    config.domainStatus.isActive = false;
                    // Show error on button hover via tooltip
                    toggleButton.setAttribute('title', 'HoverVid Plugin Error: This domain is not authorized to use the HoverVid plugin. Please contact the plugin provider.');
                    // Force disable button
                    toggleButton.classList.add('slvp-inactive');
                    toggleButton.setAttribute('disabled', 'disabled');
                } else {
                    // Update toggle button state based on domain status
                    updateToggleButtonState();
                }
            } else {
                // API error - fallback to wp_localize_script value
                console.log('API reported error. Falling back to default value');
                config.domainStatus.isActive = typeof window.slvp_vars !== 'undefined' && window.slvp_vars.is_domain_active === '1';
                console.log('Error message:', data.data ? data.data.message : 'Unknown error');
                console.log('Domain status after fallback:', config.domainStatus);
                
                updateToggleButtonState();
            }
            
            if (callback) callback();
        })
        .catch(error => {
            const errorTime = new Date().toISOString();
            // Error - fallback to wp_localize_script value
            console.log(`[${errorTime}] Error during fetch:`, error);
            config.domainStatus.isActive = typeof window.slvp_vars !== 'undefined' && window.slvp_vars.is_domain_active === '1';
            console.log('Domain status after error fallback:', config.domainStatus);
            
            updateToggleButtonState();
            
            if (callback) callback();
        });
    }

    /**
     * Update toggle button state based on domain status
     */
    function updateToggleButtonState() {
        console.log('Updating toggle button state...');
        console.log('Domain status from config:', config.domainStatus);
        
        if (config.domainStatus.isActive) {
            console.log('✅ Domain is VERIFIED - Enabling button');
            toggleButton.classList.remove('slvp-inactive');
            toggleButton.removeAttribute('disabled');
            toggleButton.setAttribute('title', 'Toggle HoverVid Player');
        } else {
            console.log('❌ Domain is NOT VERIFIED - Disabling button');
            toggleButton.classList.add('slvp-inactive');
            toggleButton.setAttribute('disabled', 'disabled');
            toggleButton.setAttribute('title', config.domainStatus.licenseMessage);
        }
    }

    /**
     * Force enable toggle button for known active domains
     * This is a failsafe measure to ensure functionality
     */
    function forceEnableToggleButton() {
        if (toggleButton) {
            toggleButton.classList.remove('slvp-inactive');
            toggleButton.removeAttribute('disabled');
            config.domainStatus.isActive = true;
            console.log('Toggle button forcibly enabled');
        }
    }

    // =============================================
    // Periodic Domain Status Checking
    // =============================================
    function setupPeriodicDomainCheck() {
        // Skip periodic checking for forced domains or unauthorized domains
        if (config.domainStatus.isForced || !config.domainStatus.domainExists) {
            console.log('Skipping periodic domain check - forced domain or unauthorized');
            return;
        }
        
        console.log('Setting up periodic domain status checking every 30 seconds...');
        
        // Check every 30 seconds
        setInterval(() => {
            console.log('Periodic domain status check...', new Date().toISOString());
            checkDomainStatusViaAPI((updatedStatus) => {
                // Log any status changes
                console.log('Periodic check completed. Status:', config.domainStatus.isActive ? 'ACTIVE' : 'INACTIVE');
            });
        }, 30000); // 30 seconds
        
        // Also check when the window regains focus (user switches back to tab)
        window.addEventListener('focus', () => {
            console.log('Window focus regained - checking domain status...');
            checkDomainStatusViaAPI();
        });
        
        // Check when page becomes visible (if user was on another tab)
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                console.log('Page became visible - checking domain status...');
                checkDomainStatusViaAPI();
            }
        });
    }
});

