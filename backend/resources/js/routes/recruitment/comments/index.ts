import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\RequisitionController::store
 * @see app/Http/Controllers/RequisitionController.php:337
 * @route '/recruitment/{requisition}/comments'
 */
export const store = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/recruitment/{requisition}/comments',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RequisitionController::store
 * @see app/Http/Controllers/RequisitionController.php:337
 * @route '/recruitment/{requisition}/comments'
 */
store.url = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { requisition: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { requisition: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    requisition: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        requisition: typeof args.requisition === 'object'
                ? args.requisition.id
                : args.requisition,
                }

    return store.definition.url
            .replace('{requisition}', parsedArgs.requisition.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RequisitionController::store
 * @see app/Http/Controllers/RequisitionController.php:337
 * @route '/recruitment/{requisition}/comments'
 */
store.post = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\RequisitionController::read
 * @see app/Http/Controllers/RequisitionController.php:353
 * @route '/recruitment/{requisition}/comments/read'
 */
export const read = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: read.url(args, options),
    method: 'post',
})

read.definition = {
    methods: ["post"],
    url: '/recruitment/{requisition}/comments/read',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RequisitionController::read
 * @see app/Http/Controllers/RequisitionController.php:353
 * @route '/recruitment/{requisition}/comments/read'
 */
read.url = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { requisition: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { requisition: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    requisition: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        requisition: typeof args.requisition === 'object'
                ? args.requisition.id
                : args.requisition,
                }

    return read.definition.url
            .replace('{requisition}', parsedArgs.requisition.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RequisitionController::read
 * @see app/Http/Controllers/RequisitionController.php:353
 * @route '/recruitment/{requisition}/comments/read'
 */
read.post = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: read.url(args, options),
    method: 'post',
})
const comments = {
    store: Object.assign(store, store),
read: Object.assign(read, read),
}

export default comments