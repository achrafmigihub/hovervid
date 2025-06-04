import { createFetch } from '@vueuse/core'
import { destr } from 'destr'

export const useApi = createFetch({
  baseUrl: (import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000') + '/api',
  fetchOptions: {
    headers: {
      Accept: 'application/json',
    },
  },
  options: {
    refetch: true,
    async beforeFetch({ options }) {
      const accessToken = localStorage.getItem('accessToken')
      if (accessToken) {
        options.headers = {
          ...options.headers,
          Authorization: `Bearer ${accessToken}`,
        }
      }
      
      return { options }
    },
    afterFetch(ctx) {
      const { data, response } = ctx

      // Parse data if it's JSON
      let parsedData = null
      try {
        parsedData = destr(data) || {}
        
        // Make sure we have an object, not null or undefined
        if (parsedData === null || parsedData === undefined) {
          parsedData = {}
          console.warn('API returned null or undefined data, using empty object instead')
        }
        
        // Log the response status for debugging
        console.log(`API Response [${response.status}]:`, response.url, parsedData)
      }
      catch (error) {
        console.error('Error parsing API response:', error)
        parsedData = {}
      }
      
      return { data: parsedData, response }
    },
    onFetchError(ctx) {
      console.error('API fetch error:', ctx.error, ctx.response?.status, ctx.response?.url)
      
      // Return a default empty object to prevent errors
      return { 
        data: {}, 
        response: ctx.response 
      }
    },
  },
})
