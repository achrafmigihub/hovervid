/**
 * Browser fingerprinting utility for tracking session security
 * This generates a unique hash based on browser characteristics
 */

/**
 * Generate a simple hash from a string
 * @param {string} str - The string to hash
 * @returns {string} - The hashed string
 */
function simpleHash(str) {
  let hash = 0
  if (!str || str.length === 0) return hash.toString(16)
  
  for (let i = 0; i < str.length; i++) {
    const char = str.charCodeAt(i)
    hash = ((hash << 5) - hash) + char
    hash = hash & hash // Convert to 32bit integer
  }
  
  return hash.toString(16)
}

/**
 * Get browser fingerprint using various characteristics
 * @returns {Object} Object containing hash and components
 */
export const getBrowserFingerprint = async () => {
  const components = {}
  
  // Collect browser data
  const nav = window.navigator
  
  // Basic browser info
  components.userAgent = nav.userAgent
  components.language = nav.language
  components.languages = Array.from(nav.languages || []).join(',')
  components.platform = nav.platform
  components.hardwareConcurrency = nav.hardwareConcurrency || 'unknown'
  components.deviceMemory = nav.deviceMemory || 'unknown'
  
  // Screen properties
  const screen = window.screen
  components.screenWidth = screen.width
  components.screenHeight = screen.height
  components.screenDepth = screen.colorDepth
  components.screenAvailWidth = screen.availWidth
  components.screenAvailHeight = screen.availHeight
  components.pixelRatio = window.devicePixelRatio || 'unknown'
  
  // Time zone
  components.timezone = Intl.DateTimeFormat().resolvedOptions().timeZone
  components.timezoneOffset = new Date().getTimezoneOffset()
  
  // Browser capabilities
  components.touchPoints = nav.maxTouchPoints || 0
  components.doNotTrack = nav.doNotTrack || 'unknown'
  
  // Feature detection
  components.cookiesEnabled = navigator.cookieEnabled
  components.localStorage = !!window.localStorage
  components.sessionStorage = !!window.sessionStorage
  components.indexedDB = !!window.indexedDB
  
  // Canvas fingerprinting (simple version)
  try {
    const canvas = document.createElement('canvas')
    const ctx = canvas.getContext('2d')
    canvas.width = 200
    canvas.height = 50
    
    ctx.textBaseline = 'top'
    ctx.font = '16px Arial'
    ctx.fillStyle = '#F60'
    ctx.fillRect(125, 1, 62, 20)
    ctx.fillStyle = '#069'
    ctx.fillText('Fingerprint', 2, 15)
    ctx.fillStyle = 'rgba(102, 204, 0, 0.7)'
    ctx.fillText('Fingerprint', 4, 17)
    
    const canvasData = canvas.toDataURL()
    components.canvasFingerprint = simpleHash(canvasData)
  } catch (e) {
    components.canvasFingerprint = 'not_supported'
  }
  
  // WebGL fingerprinting
  try {
    const canvas = document.createElement('canvas')
    const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl')
    
    if (gl) {
      const debugInfo = gl.getExtension('WEBGL_debug_renderer_info')
      if (debugInfo) {
        components.webglVendor = gl.getParameter(debugInfo.UNMASKED_VENDOR_WEBGL)
        components.webglRenderer = gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL)
      }
      
      components.webglVersion = gl.getParameter(gl.VERSION)
      components.webglShadingLanguageVersion = gl.getParameter(gl.SHADING_LANGUAGE_VERSION)
      components.webglVendor = components.webglVendor || gl.getParameter(gl.VENDOR)
      components.webglRenderer = components.webglRenderer || gl.getParameter(gl.RENDERER)
    }
  } catch (e) {
    components.webgl = 'not_supported'
  }
  
  // Generate combined hash from all components
  const componentsStr = JSON.stringify(components)
  const hash = simpleHash(componentsStr)
  
  return {
    hash,
    components,
    timestamp: new Date().toISOString()
  }
}

/**
 * Compare current fingerprint with stored one to detect changes
 * @param {Object} current - Current fingerprint
 * @param {Object} stored - Stored fingerprint
 * @returns {Object} - Comparison results with a suspicion score
 */
export const compareFingerprints = (current, stored) => {
  if (!current || !stored) return { match: false, score: 1, changes: ['missing_fingerprint'] }
  
  const changes = []
  let suspicionScore = 0
  
  // Critical components that shouldn't change (high impact on score)
  const criticalComponents = [
    'userAgent',
    'platform',
    'screenWidth',
    'screenHeight',
    'screenDepth',
    'webglVendor',
    'webglRenderer'
  ]
  
  // Semi-critical components (medium impact)
  const semiCriticalComponents = [
    'language',
    'hardwareConcurrency',
    'timezone',
    'pixelRatio',
    'canvasFingerprint'
  ]
  
  // Check critical components
  criticalComponents.forEach(comp => {
    if (current.components[comp] !== stored.components[comp]) {
      changes.push(comp)
      suspicionScore += 0.2 // High impact
    }
  })
  
  // Check semi-critical components
  semiCriticalComponents.forEach(comp => {
    if (current.components[comp] !== stored.components[comp]) {
      changes.push(comp)
      suspicionScore += 0.1 // Medium impact
    }
  })
  
  // Final match determination
  const match = suspicionScore < 0.3 // If less than 30% suspicious, consider it a match
  
  return {
    match,
    score: suspicionScore,
    changes
  }
}

export default {
  getBrowserFingerprint,
  compareFingerprints
} 
