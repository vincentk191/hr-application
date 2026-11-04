import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
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
* @see \App\Http\Controllers\AttendanceRekapController::linkRow
 * @see app/Http/Controllers/AttendanceRekapController.php:92
 * @route '/attendance/rekaps/{rekap}/rows/{row}'
 */
export const linkRow = (args: { rekap: string | number, row: string | number } | [rekap: string | number, row: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: linkRow.url(args, options),
    method: 'put',
})

linkRow.definition = {
    methods: ["put"],
    url: '/attendance/rekaps/{rekap}/rows/{row}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\AttendanceRekapController::linkRow
 * @see app/Http/Controllers/AttendanceRekapController.php:92
 * @route '/attendance/rekaps/{rekap}/rows/{row}'
 */
linkRow.url = (args: { rekap: string | number, row: string | number } | [rekap: string | number, row: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    rekap: args[0],
                    row: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rekap: args.rekap,
                                row: args.row,
                }

    return linkRow.definition.url
            .replace('{rekap}', parsedArgs.rekap.toString())
            .replace('{row}', parsedArgs.row.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceRekapController::linkRow
 * @see app/Http/Controllers/AttendanceRekapController.php:92
 * @route '/attendance/rekaps/{rekap}/rows/{row}'
 */
linkRow.put = (args: { rekap: string | number, row: string | number } | [rekap: string | number, row: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: linkRow.url(args, options),
    method: 'put',
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
const AttendanceRekapController = { store, show, linkRow, confirm, destroy }

export default AttendanceRekapController