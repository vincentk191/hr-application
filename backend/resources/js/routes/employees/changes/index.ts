import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\ProfileChangeRequestController::create
 * @see app/Http/Controllers/ProfileChangeRequestController.php:47
 * @route '/employees/{employee}/changes'
 */
export const create = (args: { employee: number | { id: number } } | [employee: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(args, options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/employees/{employee}/changes',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ProfileChangeRequestController::create
 * @see app/Http/Controllers/ProfileChangeRequestController.php:47
 * @route '/employees/{employee}/changes'
 */
create.url = (args: { employee: number | { id: number } } | [employee: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { employee: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { employee: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    employee: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        employee: typeof args.employee === 'object'
                ? args.employee.id
                : args.employee,
                }

    return create.definition.url
            .replace('{employee}', parsedArgs.employee.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProfileChangeRequestController::create
 * @see app/Http/Controllers/ProfileChangeRequestController.php:47
 * @route '/employees/{employee}/changes'
 */
create.get = (args: { employee: number | { id: number } } | [employee: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ProfileChangeRequestController::create
 * @see app/Http/Controllers/ProfileChangeRequestController.php:47
 * @route '/employees/{employee}/changes'
 */
create.head = (args: { employee: number | { id: number } } | [employee: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ProfileChangeRequestController::store
 * @see app/Http/Controllers/ProfileChangeRequestController.php:61
 * @route '/employees/{employee}/changes'
 */
export const store = (args: { employee: number | { id: number } } | [employee: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/employees/{employee}/changes',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ProfileChangeRequestController::store
 * @see app/Http/Controllers/ProfileChangeRequestController.php:61
 * @route '/employees/{employee}/changes'
 */
store.url = (args: { employee: number | { id: number } } | [employee: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { employee: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { employee: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    employee: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        employee: typeof args.employee === 'object'
                ? args.employee.id
                : args.employee,
                }

    return store.definition.url
            .replace('{employee}', parsedArgs.employee.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProfileChangeRequestController::store
 * @see app/Http/Controllers/ProfileChangeRequestController.php:61
 * @route '/employees/{employee}/changes'
 */
store.post = (args: { employee: number | { id: number } } | [employee: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})
const changes = {
    create: Object.assign(create, create),
store: Object.assign(store, store),
}

export default changes