import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\OffboardingCaseController::store
 * @see app/Http/Controllers/OffboardingCaseController.php:295
 * @route '/offboarding/{offboardingCase}/comments'
 */
export const store = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/offboarding/{offboardingCase}/comments',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OffboardingCaseController::store
 * @see app/Http/Controllers/OffboardingCaseController.php:295
 * @route '/offboarding/{offboardingCase}/comments'
 */
store.url = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { offboardingCase: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { offboardingCase: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    offboardingCase: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        offboardingCase: typeof args.offboardingCase === 'object'
                ? args.offboardingCase.id
                : args.offboardingCase,
                }

    return store.definition.url
            .replace('{offboardingCase}', parsedArgs.offboardingCase.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingCaseController::store
 * @see app/Http/Controllers/OffboardingCaseController.php:295
 * @route '/offboarding/{offboardingCase}/comments'
 */
store.post = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OffboardingCaseController::read
 * @see app/Http/Controllers/OffboardingCaseController.php:311
 * @route '/offboarding/{offboardingCase}/comments/read'
 */
export const read = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: read.url(args, options),
    method: 'post',
})

read.definition = {
    methods: ["post"],
    url: '/offboarding/{offboardingCase}/comments/read',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OffboardingCaseController::read
 * @see app/Http/Controllers/OffboardingCaseController.php:311
 * @route '/offboarding/{offboardingCase}/comments/read'
 */
read.url = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { offboardingCase: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { offboardingCase: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    offboardingCase: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        offboardingCase: typeof args.offboardingCase === 'object'
                ? args.offboardingCase.id
                : args.offboardingCase,
                }

    return read.definition.url
            .replace('{offboardingCase}', parsedArgs.offboardingCase.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingCaseController::read
 * @see app/Http/Controllers/OffboardingCaseController.php:311
 * @route '/offboarding/{offboardingCase}/comments/read'
 */
read.post = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: read.url(args, options),
    method: 'post',
})
const comments = {
    store: Object.assign(store, store),
read: Object.assign(read, read),
}

export default comments