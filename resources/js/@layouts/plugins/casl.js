import { ability } from '@/plugins/casl/ability'

/**
 * Returns ability result if ACL is configured or else just return true
 * We should allow passing string | undefined to can because for admin ability we omit defining action & subject
 *
 * Useful if you don't know if ACL is configured or not
 * Used in @core files to handle absence of ACL without errors
 *
 * @param {string} action CASL Actions // https://casl.js.org/v4/en/guide/intro#basics
 * @param {string} subject CASL Subject // https://casl.js.org/v4/en/guide/intro#basics
 */
export const can = (action, subject) => {
  // Use our local ability instance directly
  return ability.can(action, subject)
}

/**
 * Check if user can view item based on it's ability
 * Based on item's action and subject & Hide group if all of it's children are hidden
 * @param {object} item navigation object item
 */
export const canViewNavMenuGroup = item => {
  // Check conditional visibility first
  if (typeof item.conditionalVisible === 'function' && !item.conditionalVisible()) {
    return false
  }
  
  const hasAnyVisibleChild = item.children.some(i => {
    // Check child's conditional visibility
    if (typeof i.conditionalVisible === 'function' && !i.conditionalVisible()) {
      return false
    }
    return can(i.action, i.subject)
  })

  // If subject and action is defined in item => Return based on children visibility (Hide group if no child is visible)
  // Else check for ability using provided subject and action along with checking if has any visible child
  if (!(item.action && item.subject))
    return hasAnyVisibleChild
  
  return can(item.action, item.subject) && hasAnyVisibleChild
}

export const canNavigate = to => {
  // Use our local ability instance instead of useAbility()
  console.log('CASL canNavigate check for:', to.path, 'Current abilities:', ability.rules)

  // Get the most specific route (last one in the matched array)
  const targetRoute = to.matched[to.matched.length - 1]

  // If the target route has specific permissions, check those first
  if (targetRoute?.meta?.action && targetRoute?.meta?.subject) {
    const canAccess = ability.can(targetRoute.meta.action, targetRoute.meta.subject)
    console.log(`CASL check: ${targetRoute.meta.action} ${targetRoute.meta.subject} = ${canAccess}`)
    return canAccess
  }

  // If no specific permissions, fall back to checking if any parent route allows access
  const hasAccess = to.matched.some(route => {
    if (route.meta.action && route.meta.subject) {
      return ability.can(route.meta.action, route.meta.subject)
    }
    return true // If no specific permissions required, allow access
  })
  
  console.log('CASL fallback check result:', hasAccess)
  return hasAccess
}
