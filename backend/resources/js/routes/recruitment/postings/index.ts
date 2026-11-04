import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\RequisitionController::store
 * @see app/Http/Controllers/RequisitionController.php:268
 * @route '/recruitment/{requisition}/postings'
 */
export const store = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/recruitment/{requisition}/postings',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RequisitionController::store
 * @see app/Http/Controllers/RequisitionController.php:268
 * @route '/recruitment/{requisition}/postings'
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
 * @see app/Http/Controllers/RequisitionController.php:268
 * @route '/recruitment/{requisition}/postings'
 */
store.post = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\RequisitionController::update
 * @see app/Http/Controllers/RequisitionController.php:280
 * @route '/recruitment/{requisition}/postings/{posting}'
 */
export const update = (args: { requisition: number | { id: number }, posting: string | number | { id: string | number } } | [requisition: number | { id: number }, posting: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/recruitment/{requisition}/postings/{posting}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\RequisitionController::update
 * @see app/Http/Controllers/RequisitionController.php:280
 * @route '/recruitment/{requisition}/postings/{posting}'
 */
update.url = (args: { requisition: number | { id: number }, posting: string | number | { id: string | number } } | [requisition: number | { id: number }, posting: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    requisition: args[0],
                    posting: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        requisition: typeof args.requisition === 'object'
                ? args.requisition.id
                : args.requisition,
                                posting: typeof args.posting === 'object'
                ? args.posting.id
                : args.posting,
                }

    return update.definition.url
            .replace('{requisition}', parsedArgs.requisition.toString())
            .replace('{posting}', parsedArgs.posting.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RequisitionController::update
 * @see app/Http/Controllers/RequisitionController.php:280
 * @route '/recruitment/{requisition}/postings/{posting}'
 */
update.put = (args: { requisition: number | { id: number }, posting: string | number | { id: string | number } } | [requisition: number | { id: number }, posting: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\RequisitionController::destroy
 * @see app/Http/Controllers/RequisitionController.php:289
 * @route '/recruitment/{requisition}/postings/{posting}'
 */
export const destroy = (args: { requisition: number | { id: number }, posting: string | number | { id: string | number } } | [requisition: number | { id: number }, posting: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/recruitment/{requisition}/postings/{posting}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\RequisitionController::destroy
 * @see app/Http/Controllers/RequisitionController.php:289
 * @route '/recruitment/{requisition}/postings/{posting}'
 */
destroy.url = (args: { requisition: number | { id: number }, posting: string | number | { id: string | number } } | [requisition: number | { id: number }, posting: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    requisition: args[0],
                    posting: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        requisition: typeof args.requisition === 'object'
                ? args.requisition.id
                : args.requisition,
                                posting: typeof args.posting === 'object'
                ? args.posting.id
                : args.posting,
                }

    return destroy.definition.url
            .replace('{requisition}', parsedArgs.requisition.toString())
            .replace('{posting}', parsedArgs.posting.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RequisitionController::destroy
 * @see app/Http/Controllers/RequisitionController.php:289
 * @route '/recruitment/{requisition}/postings/{posting}'
 */
destroy.delete = (args: { requisition: number | { id: number }, posting: string | number | { id: string | number } } | [requisition: number | { id: number }, posting: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})
const postings = {
    store: Object.assign(store, store),
update: Object.assign(update, update),
destroy: Object.assign(destroy, destroy),
}

export default postings