export const paginationMeta = (options, total) => {
  // Handle edge cases
  const safeTotal = typeof total === 'number' && !isNaN(total) ? total : 0
  
  // If no items, return simple message
  if (safeTotal === 0) {
    return 'No items to display'
  }
  
  // Calculate start and end with validation
  const safePage = Math.max(1, options.page || 1)
  const safeItemsPerPage = Math.max(1, options.itemsPerPage || 10)
  
  const start = (safePage - 1) * safeItemsPerPage + 1
  const end = Math.min(safePage * safeItemsPerPage, safeTotal)
  
  return `Showing ${start} to ${end} of ${safeTotal} entries`
}
