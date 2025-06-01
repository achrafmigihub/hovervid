document.addEventListener('DOMContentLoaded', function() {
    // Check domain verification status before doing ANY text scanning
    console.log('Text Scanner: Checking domain verification status...');
    
    // Get domain status from global variables (from centralized verifier)
    const domainStatus = window.slvp_vars ? {
        isActive: window.slvp_vars.is_domain_active === true,
        domainExists: window.slvp_vars.domain_exists === true,
        domain: window.slvp_vars.domain,
        message: window.slvp_vars.license_message
    } : null;
    
    // If domain is not verified, completely disable text scanning
    if (!domainStatus || !domainStatus.isActive || !domainStatus.domainExists) {
        console.log('❌ Text Scanner: Domain not verified - ALL text scanning disabled');
        console.log('Domain status:', domainStatus);
        console.log('Reason: Domain verification failed');
        if (domainStatus) {
            console.log('- Domain:', domainStatus.domain);
            console.log('- Is Active:', domainStatus.isActive);
            console.log('- Domain Exists:', domainStatus.domainExists);
            console.log('- Message:', domainStatus.message);
        }
        return; // Exit completely - no text processing at all
    }
    
    console.log('✅ Text Scanner: Domain verified - initializing text scanning');
    console.log('Domain:', domainStatus.domain);
    console.log('Message:', domainStatus.message);
    
    // Continue with original text scanner code only if domain is verified
    const toggleButton = document.querySelector('.slvp-toggle-button');
    const player = document.getElementById('slvp-player-container');
    const video = document.getElementById('slvp-video-player');

    const PERFORMANCE_SETTINGS = {
        batchSize: 50,
        batchDelay: 100,
        maxElements: 500,
        throttleDelay: 250
    };

    function fullReset() {
        if (player) {
            player.classList.remove('slvp-visible');
        }
        if (video) {
            video.pause();
        }
        if (toggleButton) {
            toggleButton.style.display = 'block';
            toggleButton.classList.remove('slvp-active');
        }

        document.querySelectorAll('.slvp-text-wrapper').forEach(el => {
            el.classList.remove('slvp-current-translation', 'slvp-processing', 'slvp-hover', 'slvp-mobile-active');
        });

        document.body.classList.remove('slvp-active');
        state.isPluginActive = false;
        currentTranslation = null;
        console.clear();
    }

    const state = {
        isPluginActive: false,
        currentTranslation: null,
        processingQueue: [],
        isProcessing: false,
        processedCount: 0
    };

    let worker = null;
    let tesseractInitialized = false;
    
    async function initTesseract() {
        if (tesseractInitialized) {
            return true;
        }
        
        try {
            if (typeof Tesseract === 'undefined') {
                console.error('Tesseract.js is not loaded. Please check your internet connection and try again.');
                return false;
            }
            
            console.log('Initializing Tesseract worker...');
            
            try {
                worker = await Tesseract.createWorker({
                    logger: m => console.log(m),
                    errorHandler: e => console.error('Tesseract error:', e)
                });
            } catch (error) {
                console.error('Failed to create Tesseract worker:', error);
                return false;
            }
            
            try {
                await worker.loadLanguage('eng');
            } catch (error) {
                console.error('Failed to load language:', error);
                return false;
            }
            
            try {
                await worker.initialize('eng');
                console.log('Tesseract worker initialized successfully');
                tesseractInitialized = true;
                return true;
            } catch (error) {
                console.error('Failed to initialize Tesseract:', error);
                return false;
            }
        } catch (error) {
            console.error('Error initializing Tesseract:', error);
            return false;
        }
    }

    async function processImagesForText() {
        if (!worker) {
            const initialized = await initTesseract();
            if (!initialized) {
                console.error('Could not initialize Tesseract, skipping image processing');
                return;
            }
        }
        
        const images = document.querySelectorAll('img:not([data-slvp-processed])');
        console.log(`Found ${images.length} images to process`);
        
        const maxImagesToProcess = 10;
        const imagesToProcess = Array.from(images).slice(0, maxImagesToProcess);
        
        if (imagesToProcess.length === 0) {
            console.log('No images to process');
            return;
        }
        
        for (const img of imagesToProcess) {
            try {
                img.setAttribute('data-slvp-processed', 'true');
                
                if (img.width < 50 || img.height < 50) {
                    console.log('Skipping small image:', img.src);
                    continue;
                }
                
                if (!img.src || img.src.startsWith('data:')) {
                    console.log('Skipping image without valid src or data URL:', img.src);
                    continue;
                }
                
                console.log('Processing image:', img.src);
                
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                
                canvas.width = img.width;
                canvas.height = img.height;
                
                const imgElement = new Image();
                imgElement.crossOrigin = 'anonymous';
                
                await new Promise((resolve, reject) => {
                    imgElement.onload = resolve;
                    imgElement.onerror = () => {
                        console.error('Failed to load image:', img.src);
                        reject(new Error('Failed to load image'));
                    };
                    imgElement.src = img.src;
                });
                
                ctx.drawImage(imgElement, 0, 0);
                
                const imageData = canvas.toDataURL('image/png');
                
                const { data: { text } } = await worker.recognize(imageData);
                
                if (text.trim()) {
                    console.log('Found text in image:', text.trim());
                    const wrapper = document.createElement('div');
                    wrapper.className = 'slvp-text-wrapper slvp-image-text';
                    wrapper.setAttribute('data-slvp-processed', 'true');
                    
                    const contentHash = generateContentHash(text);
                    const context = getElementContext(img);
                    
                    wrapper.setAttribute('data-slvp-fingerprint', JSON.stringify({
                        url: window.location.href,
                        context: context,
                        content_hash: contentHash,
                        text: text
                    }));
                    
                    wrapper.setAttribute('data-slvp-hash', contentHash);
                    
                    const icon = document.createElement('span');
                    icon.className = 'slvp-translate-icon';
                    const iconImg = document.createElement('img');
                    iconImg.src = slvp_vars.plugin_url + 'assets/hovervid-icon.svg';
                    iconImg.alt = 'Translate';
                    icon.appendChild(iconImg);
                    icon.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        sendTranslationRequest(contentHash, wrapper);
                    });
                    
                    wrapper.appendChild(icon);
                    img.parentNode.insertBefore(wrapper, img.nextSibling);
                } else {
                    console.log('No text found in image:', img.src);
                }
            } catch (error) {
                console.error('Error processing image:', error);
                continue;
            }
        }
        
        if (images.length > maxImagesToProcess) {
            console.log(`Skipped ${images.length - maxImagesToProcess} additional images to prevent performance issues`);
        }
    }

    window.addEventListener('load', function() {
        setTimeout(() => {
            const imageProcessingEnabled = true;
            
            if (imageProcessingEnabled) {
                processImagesForText().catch(error => {
                    console.error('Error during image processing:', error);
                    window.slvpScanner = window.slvpScanner || {};
                    window.slvpScanner.imageProcessingEnabled = false;
                });
            }
        }, 1000);
    });

    window.addEventListener('beforeunload', function() {
        if (worker) {
            console.log('Terminating Tesseract worker');
            worker.terminate();
        }
    });

    function generateContentHash(text) {
        return text.split('').reduce((a,b) => {
            a = ((a << 5) - a) + b.charCodeAt(0);
            return a & a;
        }, 0).toString(16);
    }

    function getElementContext(element) {
        const context = [];
        let current = element;
        let depth = 0;

        while (current && depth < 3) {
            if (current.tagName) {
                let contextInfo = current.tagName.toLowerCase();
                
                if (current.id) {
                    contextInfo += '#' + current.id;
                } else if (current.getAttribute('role')) {
                    contextInfo += '[' + current.getAttribute('role') + ']';
                } else if (current.className) {
                    contextInfo += '.' + current.className.split(' ')[0];
                }

                const elementTypes = {
                    'H1': '(heading)', 'H2': '(heading)', 'H3': '(heading)',
                    'H4': '(heading)', 'H5': '(heading)', 'H6': '(heading)',
                    'P': '(text)', 'A': '(link)', 'BUTTON': '(button)'
                };
                
                if (elementTypes[current.tagName]) {
                    contextInfo += elementTypes[current.tagName];
                }

                context.push(contextInfo);
            }
            current = current.parentElement;
            depth++;
        }

        return context.reverse().join(' → ');
    }

    function isVideoPlayerElement(element) {
        return element && element.closest && element.closest('#slvp-player-container') !== null;
    }

    function isExcluded(element) {
        // Skip if element is null or undefined
        if (!element) return true;
        
        // Handle text nodes
        if (element.nodeType === 3) {
            // For text nodes, check their parent
            const parent = element.parentNode;
            if (!parent) return true;
            
            // Skip if parent is a script or style tag
            if (parent.tagName === 'SCRIPT' || parent.tagName === 'STYLE') return true;
            
            // Skip if parent is inside a script or style tag
            if (parent.closest && parent.closest('script, style')) return true;
            
            // Skip if parent is a template or has template-related attributes
            if (parent.tagName === 'TEMPLATE' || 
                (parent.hasAttribute && parent.hasAttribute('data-template')) || 
                (parent.closest && parent.closest('[data-template]'))) return true;
                
            // Skip Elementor template code and modal elements
            if (parent.closest && (
                parent.closest('.elementor-templates-modal') || 
                parent.closest('.elementor-template-library') ||
                parent.closest('[data-elementor-template]') ||
                parent.closest('.elementor-templates-modal__header__logo-area') ||
                parent.closest('.elementor-templates-modal__header__menu-area') ||
                parent.closest('.elementor-templates-modal__header__items-area') ||
                parent.closest('.elementor-templates-modal__header__close') ||
                parent.closest('.elementor-templates-modal__header__logo__icon-wrapper') ||
                parent.closest('.elementor-templates-modal__header__logo__title')
            )) return true;
            
            // Skip if parent contains template syntax
            if (parent.textContent && (
                parent.textContent.includes('{{{') || 
                parent.textContent.includes('{{#') || 
                parent.textContent.includes('$e.components')
            )) return true;
            
            // Skip if parent is hidden
            if (parent.offsetParent === null) return true;
            
            // Skip if parent is inside a hidden parent
            if (parent.closest && parent.closest('[style*="display: none"], [style*="visibility: hidden"]')) return true;
            
            // Skip if parent is inside an iframe
            if (parent.closest && parent.closest('iframe')) return true;
            
            // Skip if parent is inside a video player
            if (isVideoPlayerElement(parent)) return true;
            
            // Skip if parent is inside the plugin's own elements
            if (parent.closest && parent.closest('.slvp-text-wrapper, .slvp-player-container, .slvp-toggle-button')) return true;
            
            return false;
        }
        
        // Handle element nodes
        if (element.nodeType === 1) {
            // Skip if element is a script or style tag
            if (element.tagName === 'SCRIPT' || element.tagName === 'STYLE') return true;
            
            // Skip if element is inside a script or style tag
            if (element.closest('script, style')) return true;
            
            // Skip if element is a template or has template-related attributes
            if (element.tagName === 'TEMPLATE' || 
                element.hasAttribute('data-template') || 
                element.closest('[data-template]')) return true;
                
            // Skip Elementor template code and modal elements
            if (element.closest('.elementor-templates-modal') || 
                element.closest('.elementor-template-library') ||
                element.closest('[data-elementor-template]') ||
                element.classList.contains('elementor-templates-modal__header__logo-area') ||
                element.classList.contains('elementor-templates-modal__header__menu-area') ||
                element.classList.contains('elementor-templates-modal__header__items-area') ||
                element.classList.contains('elementor-templates-modal__header__close') ||
                element.classList.contains('elementor-templates-modal__header__logo__icon-wrapper') ||
                element.classList.contains('elementor-templates-modal__header__logo__title')) return true;
                
            // Skip if element contains template syntax
            if (element.textContent.includes('{{{') || 
                element.textContent.includes('{{#') || 
                element.textContent.includes('$e.components')) return true;
            
            // Skip if element is hidden
            if (element.offsetParent === null) return true;
            
            // Skip if element is inside a hidden parent
            if (element.closest('[style*="display: none"], [style*="visibility: hidden"]')) return true;
            
            // Skip if element is inside an iframe
            if (element.closest('iframe')) return true;
            
            // Skip if element is inside a video player
            if (isVideoPlayerElement(element)) return true;
            
            // Skip if element is inside the plugin's own elements
            if (element.closest('.slvp-text-wrapper, .slvp-player-container, .slvp-toggle-button')) return true;
        }
        
        return false;
    }

    function isTextElement(element) {
        const textElements = ['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6'];
        return element && textElements.includes(element.tagName);
    }

    function getAllTextFromElement(element) {
        let text = '';
        const walker = document.createTreeWalker(
            element,
            NodeFilter.SHOW_TEXT,
            null,
            false
        );

        while (node = walker.nextNode()) {
            text += node.nodeValue.trim() + ' ';
        }
        return text.trim();
    }

    function wrapTextNode(node) {
        const wrapper = document.createElement('span');
        wrapper.className = 'slvp-text-wrapper';
        wrapper.setAttribute('data-slvp-processed', 'true');
        
        const textContent = node.nodeValue.trim();
        const contentHash = generateContentHash(textContent);
        const context = getElementContext(node.parentNode);
        
        wrapper.setAttribute('data-slvp-fingerprint', JSON.stringify({
            url: window.location.href,
            context: context,
            content_hash: contentHash,
            text: textContent
        }));
        wrapper.setAttribute('data-slvp-hash', contentHash);
        
        const parent = node.parentNode;
        if (parent.tagName === 'A' || parent.closest('a')) {
            wrapper.setAttribute('data-is-link', 'true');
            
            // Add hover class to parent link when hovering over the wrapper
            wrapper.addEventListener('mouseenter', function() {
                if (document.body.classList.contains('slvp-active')) {
                    const link = parent.tagName === 'A' ? parent : parent.closest('a');
                    if (link) {
                        link.classList.add('slvp-hover');
                    }
                }
            });
            
            wrapper.addEventListener('mouseleave', function() {
                const link = parent.tagName === 'A' ? parent : parent.closest('a');
                if (link) {
                    link.classList.remove('slvp-hover');
                }
            });
            
            wrapper.addEventListener('click', function(e) {
                if (!document.body.classList.contains('slvp-active')) {
                    return;
                }
                // Only prevent navigation if clicking the icon
                if (e.target.classList.contains('slvp-translate-icon') || 
                    e.target.closest('.slvp-translate-icon')) {
                    e.preventDefault();
                    e.stopPropagation();
                    sendTranslationRequest(contentHash, wrapper);
                }
            }, true);
        } else if (parent.tagName === 'BUTTON' || parent.getAttribute('role') === 'button' || parent.closest('button')) {
            wrapper.setAttribute('data-is-button', 'true');
        }
        
        wrapper.appendChild(document.createTextNode(node.nodeValue));
        
        const icon = document.createElement('span');
        icon.className = 'slvp-translate-icon';
        const img = document.createElement('img');
        img.src = slvp_vars.plugin_url + 'assets/hovervid-icon.svg';
        img.alt = 'Translate';
        icon.appendChild(img);
        icon.addEventListener('click', function(e) {
            if (!document.body.classList.contains('slvp-active')) return;
            
            e.preventDefault();
            e.stopPropagation();
            
            console.clear();
            console.log('🔍 Text Fingerprint:');
            console.log('----------------------------');
            const data = JSON.parse(wrapper.getAttribute('data-slvp-fingerprint'));
            console.log(`Text content: "${data.text}"`);
            console.log(`Location: ${data.context}`);
            console.log(`Hash: ${data.content_hash}`);
            console.log('----------------------------');
            
            sendTranslationRequest(contentHash, wrapper);
        }, true);
        
        wrapper.appendChild(icon);
        node.parentNode.replaceChild(wrapper, node);
    }

    function processTextNodes(element) {
        if (isExcluded(element)) return;
        
        if (state.processedCount >= PERFORMANCE_SETTINGS.maxElements) {
            console.log(`Reached maximum element limit (${PERFORMANCE_SETTINGS.maxElements}). Stopping processing.`);
            return;
        }

        if (element.nodeType === 1 && isTextElement(element) && !element.hasAttribute('data-slvp-processed')) {
            const fullText = getAllTextFromElement(element);
            if (fullText.length > 0) {
                state.processedCount++;
                const wrapper = document.createElement('span');
                wrapper.className = 'slvp-text-wrapper';
                wrapper.setAttribute('data-slvp-processed', 'true');
                
                const contentHash = generateContentHash(fullText);
                const context = getElementContext(element);
                
                const fingerprintData = {
                    url: window.location.href,
                    context: context,
                    content_hash: contentHash,
                    text: fullText
                };
                
                wrapper.setAttribute('data-slvp-fingerprint', JSON.stringify(fingerprintData));
                wrapper.setAttribute('data-slvp-hash', contentHash);
                
                wrapper.innerHTML = element.innerHTML;
                
                const icon = document.createElement('span');
                icon.className = 'slvp-translate-icon';
                const img = document.createElement('img');
                img.src = slvp_vars.plugin_url + 'assets/hovervid-icon.svg';
                img.alt = 'Translate';
                icon.appendChild(img);
                icon.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    console.clear();
                    console.log('🔍 Text Fingerprint:');
                    console.log('----------------------------');
                    const data = JSON.parse(wrapper.getAttribute('data-slvp-fingerprint'));
                    console.log(`Text content: "${data.text}"`);
                    console.log(`Location: ${data.context}`);
                    console.log(`Hash: ${data.content_hash}`);
                    console.log('----------------------------');
                    
                    sendTranslationRequest(contentHash, wrapper);
                }, true);
                
                wrapper.appendChild(icon);
                element.innerHTML = '';
                element.appendChild(wrapper);
                return;
            }
        }

        if (element.nodeType === 3) {
            const trimmedText = element.nodeValue.trim();
            if (trimmedText.length > 0 && trimmedText !== '.') {
                if (!element.parentNode.hasAttribute('data-slvp-processed') && 
                    !isVideoPlayerElement(element.parentNode)) {
                    state.processedCount++;
                    wrapTextNode(element);
                }
            }
        }
        
        for (let child of element.childNodes) {
            if (!isExcluded(child)) {
                processTextNodes(child);
            }
        }
    }

    function processBatch(elements) {
        if (elements.length === 0 || state.processedCount >= PERFORMANCE_SETTINGS.maxElements) {
            state.isProcessing = false;
            if (state.processingQueue.length > 0) {
                setTimeout(() => {
                    const nextBatch = state.processingQueue.shift();
                    processBatch(nextBatch);
                }, PERFORMANCE_SETTINGS.batchDelay);
            }
            return;
        }

        const batch = elements.splice(0, PERFORMANCE_SETTINGS.batchSize);
        batch.forEach(element => {
            processTextNodes(element);
        });

        if (elements.length > 0) {
            setTimeout(() => {
                processBatch(elements);
            }, PERFORMANCE_SETTINGS.batchDelay);
        } else {
            state.isProcessing = false;
            if (state.processingQueue.length > 0) {
                setTimeout(() => {
                    const nextBatch = state.processingQueue.shift();
                    processBatch(nextBatch);
                }, PERFORMANCE_SETTINGS.batchDelay);
            }
        }
    }

    function throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    function scanFingerprints() {
        console.clear();
        console.log('🔍 Current Page Text Content:');
        console.log('----------------------------');
        
        const bodyTextElements = document.querySelectorAll('.slvp-text-wrapper');
        console.log(`Found ${bodyTextElements.length} text elements to scan`);
        
        const maxDisplayElements = 200;
        const elementsToDisplay = Array.from(bodyTextElements).slice(0, maxDisplayElements);
        
        if (elementsToDisplay.length === 0) {
            console.log('No text elements found to scan.');
            return;
        }
        
        const elementsByPosition = [];
        const uniqueContent = new Set();
        
        elementsToDisplay.forEach((element, index) => {
            try {
                if (element.closest('#wpadminbar')) {
                    return;
                }

                const fingerprintData = JSON.parse(element.getAttribute('data-slvp-fingerprint'));
                
                let textContent = '';
                
                if (element.childNodes.length > 0) {
                    for (let i = 0; i < element.childNodes.length; i++) {
                        const node = element.childNodes[i];
                        if (node.nodeType === 3) {
                            textContent = node.nodeValue;
                            break;
                        }
                    }
                }
                
                if (!textContent) {
                    textContent = element.textContent;
                }
                
                textContent = textContent.trim();
                
                if (!textContent) {
                    return;
                }
                
                if (textContent.includes('function') || 
                    textContent.includes('var ') || 
                    textContent.includes('let ') || 
                    textContent.includes('const ') ||
                    textContent.includes('return') ||
                    textContent.includes('document.') ||
                    textContent.includes('window.') ||
                    textContent.includes('addEventListener') ||
                    textContent.includes('querySelector') ||
                    textContent.includes('getElementById') ||
                    textContent.includes('classList') ||
                    textContent.includes('setAttribute') ||
                    textContent.includes('appendChild') ||
                    textContent.includes('createElement')) {
                    return;
                }
                
                if (uniqueContent.has(textContent)) {
                    return;
                }
                
                uniqueContent.add(textContent);
                
                const rect = element.getBoundingClientRect();
                const position = rect.top + window.scrollY;
                
                elementsByPosition.push({
                    text: textContent,
                    hash: fingerprintData.content_hash,
                    context: fingerprintData.context || 'Unknown',
                    position: position,
                    index: index + 1
                });
                
            } catch (e) {
                console.error('Error processing element:', e);
                return;
            }
        });
        
        elementsByPosition.sort((a, b) => a.position - b.position);
        
        const elementsByContext = {};
        
        elementsByPosition.forEach(item => {
            if (!elementsByContext[item.context]) {
                elementsByContext[item.context] = [];
            }
            
            elementsByContext[item.context].push(item);
        });
        
        Object.keys(elementsByContext).forEach(context => {
            console.log(`%c${context}:`, 'font-weight: bold; color: #4a90e2;');
            
            elementsByContext[context].forEach(item => {
                console.log(`[${item.index}] Text: "${item.text}"`);
                console.log(`    Hash: ${item.hash}`);
                console.log('   ---------------');
            });
            
            console.log('');
        });
        
        if (bodyTextElements.length > maxDisplayElements) {
            console.log(`%c... and ${bodyTextElements.length - maxDisplayElements} more elements not shown`, 'color: #999; font-style: italic;');
        }
    }

    let currentTranslation = null;

    function deactivateAllTranslations() {
        document.querySelectorAll('.slvp-text-wrapper').forEach(el => {
            el.classList.remove('slvp-current-translation', 'slvp-processing');
        });
        currentTranslation = null;
    }

    function sendTranslationRequest(textHash, wrapper) {
        deactivateAllTranslations();
        currentTranslation = wrapper;
        wrapper.classList.add('slvp-processing');
        
        fetch(slvp_vars.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'slvp_get_video',
                text_hash: textHash,
                security: slvp_vars.ajax_nonce
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.video_url) {
                loadVideo(data.data.video_url);
                deactivateAllTranslations();
                wrapper.classList.add('slvp-current-translation');
                
                const fingerprintData = JSON.parse(wrapper.getAttribute('data-slvp-fingerprint'));
                console.clear();
                console.log('🔍 Current Text Fingerprint:');
                console.log('----------------------------');
                console.log(`Text content: "${fingerprintData.text}"`);
                console.log(`Location: ${fingerprintData.context}`);
                console.log(`Hash: ${fingerprintData.content_hash}`);
                console.log('----------------------------');
            } else {
                handleError(wrapper);
                currentTranslation = null;
            }
        })
        .catch(() => {
            handleError(wrapper);
            currentTranslation = null;
        })
        .finally(() => {
            wrapper.classList.remove('slvp-processing');
            wrapper.querySelector('.slvp-translate-icon').innerHTML = '';
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

    function loadVideo(videoUrl) {
        const player = document.getElementById('slvp-player-container');
        const video = document.getElementById('slvp-video-player');
        const toggleButton = document.querySelector('.slvp-toggle-button');

        if (video && player) {
            video.innerHTML = `<source src="${videoUrl}" type="video/mp4">`;
            
            player.classList.add('slvp-visible');
            toggleButton.style.display = 'none';

            video.load();
            video.play().catch(error => {
                console.log('Video autoplay failed:', error);
            });

            // Remove any existing event listeners
            video.removeEventListener('ended', null);
            video.removeEventListener('click', null);

            // Add event listener for video end
            video.addEventListener('ended', function() {
                video.pause();
                if (currentTranslation) {
                    currentTranslation.classList.remove('slvp-current-translation');
                    currentTranslation = null;
                }
                
                // Show play button to allow replay
                const playButton = player.querySelector('.slvp-play-button');
                if (playButton) {
                    playButton.classList.add('visible');
                }
            });

            // Add click event listener to replay video
            video.addEventListener('click', function() {
                if (video.paused) {
                    video.currentTime = 0;
                    video.play();
                    const playButton = player.querySelector('.slvp-play-button');
                    if (playButton) {
                        playButton.classList.remove('visible');
                    }
                }
            });

            // Add click event listener to play button
            const playButton = player.querySelector('.slvp-play-button');
            if (playButton) {
                playButton.addEventListener('click', function() {
                    video.currentTime = 0;
                    video.play();
                    playButton.classList.remove('visible');
                });
            }
        } else {
            console.error('Video player elements not found');
        }
    }

    function initializeScanner() {
        console.clear();
        console.log('Initializing text scanner...');
        
        state.processedCount = 0;
        
        const textElements = Array.from(document.querySelectorAll('body > *:not(header):not(footer):not(nav):not(aside)'));
        
        if (textElements.length > 0) {
            requestAnimationFrame(() => {
                processBatch(textElements);
            });
        }
        
        const progressInterval = setInterval(() => {
            if (!state.isProcessing && state.processedCount > 0) {
                console.log(`Processed ${state.processedCount} elements`);
                clearInterval(progressInterval);
                
                console.log('%c 🔍 Text Fingerprint Scan Results:', 'background: #4a90e2; color: white; padding: 2px 5px; border-radius: 3px;');
                scanFingerprints();
            }
        }, 1000);
    }

    new MutationObserver(throttle((mutations) => {
        const addedNodes = [];
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType === 1 && !isExcluded(node)) {
                    addedNodes.push(node);
                }
            });
        });
        
        if (addedNodes.length > 0) {
            requestAnimationFrame(() => {
                processBatch(addedNodes);
            });
        }
    }, PERFORMANCE_SETTINGS.throttleDelay)).observe(document.body, { childList: true, subtree: true });

    let lastUrl = location.href;
    new MutationObserver(throttle(() => {
        if (location.href !== lastUrl) {
            lastUrl = location.href;
            setTimeout(() => {
                state.processedCount = 0;
                initializeScanner();
            }, 100);
        }
    }, PERFORMANCE_SETTINGS.throttleDelay)).observe(document.body, { childList: true, subtree: true });

    window.slvpScanner = { 
        scan: scanFingerprints,
        reinitialize: initializeScanner
    };

    toggleButton.addEventListener('click', function() {
        state.isPluginActive = !state.isPluginActive;
        document.body.classList.toggle('slvp-active', state.isPluginActive);
        this.classList.toggle('slvp-active', state.isPluginActive);
        
        if (!state.isPluginActive) {
            const player = document.getElementById('slvp-player-container');
            player.classList.remove('slvp-visible');
            video.pause();
            
            document.querySelectorAll('.slvp-text-wrapper').forEach(el => {
                el.classList.remove('slvp-current-translation', 'slvp-processing', 'slvp-hover');
            });
            
            currentTranslation = null;
            console.clear();
        }
    });
    
    setTimeout(() => {
        initializeScanner();
        setTimeout(combineTextWithNearbyLinks, 1000);
    }, 500);

    function combineTextWithNearbyLinks() {
        const textWrappers = document.querySelectorAll('.slvp-text-wrapper:not(.slvp-combined)');
        const links = document.querySelectorAll('a:not([data-slvp-processed])');
        
        if (textWrappers.length === 0 || links.length === 0) return;
        
        const processedElements = new Set();
        
        textWrappers.forEach(wrapper => {
            if (processedElements.has(wrapper) || wrapper.classList.contains('slvp-combined')) return;
            
            const textElement = findParentTextElement(wrapper);
            if (!textElement) return;
            
            const wrapperStyle = window.getComputedStyle(wrapper);
            const wrapperFontSize = wrapperStyle.fontSize;
            const wrapperFontFamily = wrapperStyle.fontFamily;
            const wrapperFontWeight = wrapperStyle.fontWeight;
            
            const linksInElement = Array.from(links).filter(link => 
                textElement.contains(link) && !link.hasAttribute('data-slvp-processed')
            );
            
            const textWrappersInElement = Array.from(textWrappers).filter(textWrapper => 
                textElement.contains(textWrapper) && 
                !processedElements.has(textWrapper) && 
                !textWrapper.classList.contains('slvp-combined') &&
                textWrapper !== wrapper
            );
            
            const elementsToCombine = [wrapper];
            processedElements.add(wrapper);
            
            let foundMore = true;
            while (foundMore) {
                foundMore = false;
                
                const currentElements = [...elementsToCombine];
                
                linksInElement.forEach(link => {
                    if (link.hasAttribute('data-slvp-processed')) return;
                    
                    const linkStyle = window.getComputedStyle(link);
                    const linkFontSize = linkStyle.fontSize;
                    const linkFontFamily = linkStyle.fontFamily;
                    const linkFontWeight = linkStyle.fontWeight;
                    
                    const hasSameFontProperties = 
                        linkFontSize === wrapperFontSize && 
                        linkFontFamily === wrapperFontFamily && 
                        linkFontWeight === wrapperFontWeight;
                    
                    for (const element of currentElements) {
                        if (areElementsNearby(element, link, 4) && hasSameFontProperties) {
                            link.setAttribute('data-slvp-processed', 'true');
                            
                            const linkIcons = link.querySelectorAll('.slvp-translate-icon');
                            linkIcons.forEach(icon => icon.remove());
                            
                            elementsToCombine.push(link);
                            processedElements.add(link);
                            foundMore = true;
                            break;
                        }
                    }
                });
                
                if (foundMore) {
                    const newlyAddedElements = elementsToCombine.slice(currentElements.length);
                    
                    textWrappersInElement.forEach(otherWrapper => {
                        if (processedElements.has(otherWrapper) || otherWrapper.classList.contains('slvp-combined')) return;
                        
                        const otherWrapperStyle = window.getComputedStyle(otherWrapper);
                        const otherWrapperFontSize = otherWrapperStyle.fontSize;
                        const otherWrapperFontFamily = otherWrapperStyle.fontFamily;
                        const otherWrapperFontWeight = otherWrapperStyle.fontWeight;
                        
                        const hasSameFontProperties = 
                            otherWrapperFontSize === wrapperFontSize && 
                            otherWrapperFontFamily === wrapperFontFamily && 
                            otherWrapperFontWeight === wrapperFontWeight;
                        
                        for (const element of newlyAddedElements) {
                            if (areElementsNearby(element, otherWrapper, 4) && hasSameFontProperties) {
                                elementsToCombine.push(otherWrapper);
                                processedElements.add(otherWrapper);
                                foundMore = true;
                                break;
                            }
                        }
                    });
                }
            }
            
            if (elementsToCombine.length > 1) {
                const baseWrapper = elementsToCombine[0];
                
                baseWrapper.classList.add('slvp-combined');
                
                const baseText = baseWrapper.textContent.trim();
                
                const contentHash = generateContentHash(baseText);
                const context = getElementContext(baseWrapper);
                
                const fingerprintData = {
                    url: window.location.href,
                    context: context,
                    content_hash: contentHash,
                    text: baseText
                };
                
                baseWrapper.setAttribute('data-slvp-fingerprint', JSON.stringify(fingerprintData));
                baseWrapper.setAttribute('data-slvp-hash', contentHash);
                
                for (let i = 1; i < elementsToCombine.length; i++) {
                    const element = elementsToCombine[i];
                    
                    if (element.tagName === 'A') {
                        const linkContainer = document.createElement('span');
                        linkContainer.className = 'slvp-link-part';
                        
                        const linkClone = element.cloneNode(true);
                        
                        const clonedIcons = linkClone.querySelectorAll('.slvp-translate-icon');
                        clonedIcons.forEach(icon => icon.remove());
                        
                        linkContainer.appendChild(linkClone);
                        
                        baseWrapper.appendChild(document.createTextNode('\u00A0'));
                        baseWrapper.appendChild(linkContainer);
                        
                        element.style.display = 'none';
                    } 
                    else if (element.classList.contains('slvp-text-wrapper')) {
                        const textContent = element.textContent.trim();
                        
                        baseWrapper.appendChild(document.createTextNode('\u00A0' + textContent));
                        
                        element.style.display = 'none';
                    }
                }
            }
        });
    }
    
    function findParentTextElement(element) {
        let parent = element.parentElement;
        
        const textElements = ['p', 'div', 'li', 'td', 'th', 'article', 'section', 'aside', 'header', 'footer', 'nav'];
        
        while (parent && parent !== document.body) {
            if (textElements.includes(parent.tagName.toLowerCase())) {
                return parent;
            }
            parent = parent.parentElement;
        }
        
        return element.parentElement;
    }

    function areElementsNearby(element1, element2, maxDistance) {
        const rect1 = element1.getBoundingClientRect();
        const rect2 = element2.getBoundingClientRect();
        
        return (
            Math.abs(rect1.bottom - rect2.top) <= maxDistance ||
            Math.abs(rect1.top - rect2.bottom) <= maxDistance ||
            Math.abs(rect1.right - rect2.left) <= maxDistance ||
            Math.abs(rect1.left - rect2.right) <= maxDistance
        );
    }

    const style = document.createElement('style');
    style.textContent = `
        .slvp-text-wrapper {
            position: relative;
            padding: 0;
            margin: 0;
            display: inline-block;
        }
        
        .slvp-combined {
            position: relative;
            display: inline-block;
            padding: 0;
            margin: 0;
        }
        
        .slvp-content-wrapper {
            display: inline-block;
        }
        
        .slvp-text-part {
            padding: 0 !important;
            margin: 0 !important;
            pointer-events: none !important;
        }
        
        .slvp-link-part {
            pointer-events: auto !important;
            cursor: pointer;
            outline: none !important;
            box-shadow: none !important;
        }
        
        .slvp-link-part a {
            pointer-events: auto !important;
            cursor: pointer;
            outline: none !important;
            box-shadow: none !important;
        }
        
        .slvp-translate-icon {
            display: none;
        }
        
        body.slvp-active .slvp-combined:hover > .slvp-translate-icon {
            display: inline-block !important;
        }
        
        .slvp-combined .slvp-translate-icon {
            position: absolute;
            right: -30px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
        }
        
        .slvp-combined .slvp-text-wrapper,
        .slvp-combined .slvp-link-part,
        .slvp-combined .slvp-link-part a {
            padding: 0 !important;
            margin: 0 !important;
            outline: none !important;
            box-shadow: none !important;
        }
        
        .slvp-combined .slvp-text-wrapper .slvp-translate-icon,
        .slvp-combined .slvp-link-part .slvp-translate-icon,
        .slvp-combined .slvp-link-part a .slvp-translate-icon {
            display: none !important;
        }
        
        body.slvp-active .slvp-text-wrapper:hover > .slvp-translate-icon {
            display: inline-block !important;
        }
        
        .slvp-text-wrapper .slvp-translate-icon {
            position: absolute;
            right: -30px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
        }
        
        a .slvp-translate-icon {
            display: none !important;
        }
        
        a:not(.slvp-combined) .slvp-translate-icon {
            display: none !important;
        }
        
        .slvp-combined a .slvp-translate-icon {
            display: none !important;
        }
        
        body:not(.slvp-active) .slvp-text-wrapper:hover > .slvp-translate-icon,
        body:not(.slvp-active) .slvp-combined:hover > .slvp-translate-icon {
            display: none !important;
        }
        
        .slvp-combined * .slvp-translate-icon {
            display: none !important;
        }
    `;
    
    document.head.appendChild(style);
}); 
