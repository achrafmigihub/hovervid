import initMSW from '@/plugins/fake-api'

export default function (app) {
  // Only initialize MSW in development mode
  if (import.meta.env.MODE === 'development' || import.meta.env.VITE_USE_MOCK_API === 'true') {
    initMSW()
  }
} 