import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\LeaveRequestController::index
 * @see app/Http/Controllers/LeaveRequestController.php:42
 * @route '/leave-requests'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/leave-requests',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\LeaveRequestController::index
 * @see app/Http/Controllers/LeaveRequestController.php:42
 * @route '/leave-requests'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\LeaveRequestController::index
 * @see app/Http/Controllers/LeaveRequestController.php:42
 * @route '/leave-requests'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\LeaveRequestController::index
 * @see app/Http/Controllers/LeaveRequestController.php:42
 * @route '/leave-requests'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\LeaveRequestController::exportMethod
 * @see app/Http/Controllers/LeaveRequestController.php:72
 * @route '/leave-requests/export'
 */
export const exportMethod = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportMethod.url(options),
    method: 'get',
})

exportMethod.definition = {
    methods: ["get","head"],
    url: '/leave-requests/export',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\LeaveRequestController::exportMethod
 * @see app/Http/Controllers/LeaveRequestController.php:72
 * @route '/leave-requests/export'
 */
exportMethod.url = (options?: RouteQueryOptions) => {
    return exportMethod.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\LeaveRequestController::exportMethod
 * @see app/Http/Controllers/LeaveRequestController.php:72
 * @route '/leave-requests/export'
 */
exportMethod.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportMethod.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\LeaveRequestController::exportMethod
 * @see app/Http/Controllers/LeaveRequestController.php:72
 * @route '/leave-requests/export'
 */
exportMethod.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: exportMethod.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\LeaveRequestController::create
 * @see app/Http/Controllers/LeaveRequestController.php:144
 * @route '/leave-requests/create'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/leave-requests/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\LeaveRequestController::create
 * @see app/Http/Controllers/LeaveRequestController.php:144
 * @route '/leave-requests/create'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\LeaveRequestController::create
 * @see app/Http/Controllers/LeaveRequestController.php:144
 * @route '/leave-requests/create'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\LeaveRequestController::create
 * @see app/Http/Controllers/LeaveRequestController.php:144
 * @route '/leave-requests/create'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\LeaveRequestController::store
 * @see app/Http/Controllers/LeaveRequestController.php:208
 * @route '/leave-requests'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/leave-requests',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\LeaveRequestController::store
 * @see app/Http/Controllers/LeaveRequestController.php:208
 * @route '/leave-requests'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\LeaveRequestController::store
 * @see app/Http/Controllers/LeaveRequestController.php:208
 * @route '/leave-requests'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\LeaveRequestController::edit
 * @see app/Http/Controllers/LeaveRequestController.php:235
 * @route '/leave-requests/{leaveRequest}/edit'
 */
export const edit = (args: { leaveRequest: number | { id: number } } | [leaveRequest: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/leave-requests/{leaveRequest}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\LeaveRequestController::edit
 * @see app/Http/Controllers/LeaveRequestController.php:235
 * @route '/leave-requests/{leaveRequest}/edit'
 */
edit.url = (args: { leaveRequest: number | { id: number } } | [leaveRequest: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { leaveRequest: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { leaveRequest: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    leaveRequest: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        leaveRequest: typeof args.leaveRequest === 'object'
                ? args.leaveRequest.id
                : args.leaveRequest,
                }

    return edit.definition.url
            .replace('{leaveRequest}', parsedArgs.leaveRequest.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LeaveRequestController::edit
 * @see app/Http/Controllers/LeaveRequestController.php:235
 * @route '/leave-requests/{leaveRequest}/edit'
 */
edit.get = (args: { leaveRequest: number | { id: number } } | [leaveRequest: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\LeaveRequestController::edit
 * @see app/Http/Controllers/LeaveRequestController.php:235
 * @route '/leave-requests/{leaveRequest}/edit'
 */
edit.head = (args: { leaveRequest: number | { id: number } } | [leaveRequest: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\LeaveRequestController::update
 * @see app/Http/Controllers/LeaveRequestController.php:252
 * @route '/leave-requests/{leaveRequest}'
 */
export const update = (args: { leaveRequest: number | { id: number } } | [leaveRequest: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/leave-requests/{leaveRequest}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\LeaveRequestController::update
 * @see app/Http/Controllers/LeaveRequestController.php:252
 * @route '/leave-requests/{leaveRequest}'
 */
update.url = (args: { leaveRequest: number | { id: number } } | [leaveRequest: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { leaveRequest: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { leaveRequest: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    leaveRequest: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        leaveRequest: typeof args.leaveRequest === 'object'
                ? args.leaveRequest.id
                : args.leaveRequest,
                }

    return update.definition.url
            .replace('{leaveRequest}', parsedArgs.leaveRequest.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LeaveRequestController::update
 * @see app/Http/Controllers/LeaveRequestController.php:252
 * @route '/leave-requests/{leaveRequest}'
 */
update.put = (args: { leaveRequest: number | { id: number } } | [leaveRequest: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})
const LeaveRequestController = { index, exportMethod, create, store, edit, update, export: exportMethod }

export default LeaveRequestController