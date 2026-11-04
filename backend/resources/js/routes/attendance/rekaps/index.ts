import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
import rows from './rows'
/**
* @see \App\Http\Controllers\AttendanceRekapController::store
 * @see app/Http/Controllers/AttendanceRekapController.php:32
 * @route '/attendance/rekaps'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/attendance/rekaps',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AttendanceRekapController::store
 * @see app/Http/Controllers/AttendanceRekapController.php:32
 * @route '/attendance/rekaps'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceRekapController::store
 * @see app/Http/Controllers/AttendanceRekapController.php:32
 * @route '/attendance/rekaps'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AttendanceRekapController::show
 * @see app/Http/Controllers/AttendanceRekapController.php:45
 * @route '/attendance/rekaps/{rekap}'
 */
export const show = (args: { rekap: string | number } | [rekap: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/attendance/rekaps/{rekap}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AttendanceRekapController::show
 * @see app/Http/Controllers/AttendanceRekapController.php:45
 * @route '/attendance/rekaps/{rekap}'
 */
show.url = (args: { rekap: string | number } | [rekap: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { rekap: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    rekap: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rekap: args.rekap,
                }

    return show.definition.url
            .replace('{rekap}', parsedArgs.rekap.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceRekapController::show
 * @see app/Http/Controllers/AttendanceRekapController.php:45
 * @route '/attendance/rekaps/{rekap}'
 */
show.get = (args: { rekap: string | number } | [rekap: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AttendanceRekapController::show
 * @see app/Http/Controllers/AttendanceRekapController.php:45
 * @route '/attendance/rekaps/{rekap}'
 */
show.head = (args: { rekap: string | number } | [rekap: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\AttendanceRekapController::confirm
 * @see app/Http/Controllers/AttendanceRekapController.php:132
 * @route '/attendance/rekaps/{rekap}/confirm'
 */
export const confirm = (args: { rekap: string | number } | [rekap: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: confirm.url(args, options),
    method: 'post',
})

confirm.definition = {
    methods: ["post"],
    url: '/attendance/rekaps/{rekap}/confirm',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AttendanceRekapController::confirm
 * @see app/Http/Controllers/AttendanceRekapController.php:132
 * @route '/attendance/rekaps/{rekap}/confirm'
 */
confirm.url = (args: { rekap: string | number } | [rekap: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { rekap: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    rekap: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rekap: args.rekap,
                }

    return confirm.definition.url
            .replace('{rekap}', parsedArgs.rekap.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceRekapController::confirm
 * @see app/Http/Controllers/AttendanceRekapController.php:132
 * @route '/attendance/rekaps/{rekap}/confirm'
 */
confirm.post = (args: { rekap: string | number } | [rekap: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: confirm.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AttendanceRekapController::destroy
 * @see app/Http/Controllers/AttendanceRekapController.php:147
 * @route '/attendance/rekaps/{rekap}'
 */
export const destroy = (args: { rekap: string | number } | [rekap: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/attendance/rekaps/{rekap}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\AttendanceRekapController::destroy
 * @see app/Http/Controllers/AttendanceRekapController.php:147
 * @route '/attendance/rekaps/{rekap}'
 */
destroy.url = (args: { rekap: string | number } | [rekap: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { rekap: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    rekap: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rekap: args.rekap,
                }

    return destroy.definition.url
            .replace('{rekap}', parsedArgs.rekap.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceRekapController::destroy
 * @see app/Http/Controllers/AttendanceRekapController.php:147
 * @route '/attendance/rekaps/{rekap}'
 */
destroy.delete = (args: { rekap: string | number } | [rekap: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})
const rekaps = {
    store: Object.assign(store, store),
show: Object.assign(show, show),
rows: Object.assign(rows, rows),
confirm: Object.assign(confirm, confirm),
destroy: Object.assign(destroy, destroy),
}

export default rekaps