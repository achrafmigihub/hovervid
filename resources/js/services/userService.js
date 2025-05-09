/**
 * User Service
 * This service handles fetching user data from the public API
 * without relying on authentication state
 */

// Helper function to build query params
const buildQueryParams = (params = {}) => {
  const queryParams = new URLSearchParams();
  
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      queryParams.append(key, value);
    }
  });
  
  return queryParams.toString();
};

/**
 * Fetch users with optional filtering, pagination, and sorting
 * 
 * @param {Object} options - Query parameters
 * @returns {Promise<Object>} - The users data
 */
export const fetchUsers = async (options = {}) => {
  try {
    const queryString = buildQueryParams({
      q: options.search,
      role: options.role,
      status: options.status,
      sortBy: options.sortBy,
      orderBy: options.orderBy,
      itemsPerPage: options.itemsPerPage,
      page: options.page,
    });
    
    const response = await fetch(`/api/public/users?${queryString}`);
    
    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.message || 'Failed to fetch users');
    }
    
    return await response.json();
  } catch (error) {
    console.error('Error fetching users:', error);
    throw error;
  }
};

/**
 * Fetch a user by ID
 * 
 * @param {string|number} id - The user ID
 * @returns {Promise<Object>} - The user data
 */
export const fetchUserById = async (id) => {
  try {
    const response = await fetch(`/api/public/users/${id}`);
    
    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.message || 'Failed to fetch user');
    }
    
    return await response.json();
  } catch (error) {
    console.error(`Error fetching user with ID ${id}:`, error);
    throw error;
  }
}; 