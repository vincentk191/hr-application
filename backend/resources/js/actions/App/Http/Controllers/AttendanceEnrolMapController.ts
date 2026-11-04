import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\AttendanceEnrolMapController::store
 * @see app/Http/Controllers/AttendanceEnrolMapController.php:15
 * @route '/attendance/enrol-maps'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/attendance/enrol-maps',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AttendanceEnrolMapController::store
 * @see app/Http/Controllers/AttendanceEnrolMapController.php:15
 * @route '/attendance/enrol-maps'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceEnrolMapController::store
 * @see app/Http/Controllers/AttendanceEnrolMapController.php:15
 * @route '/attendance/enrol-maps'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AttendanceEnrolMapController::destroy
 * @see app/Http/Controllers/AttendanceEnrolMapController.php:41
 * @route '/attendance/enrol-maps/{enrolMap}'
 */
export const destroy = (args: { enrolMap: string | number } | [enrolMap: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/attendance/enrol-maps/{enrolMap}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\AttendanceEnrolMapController::destroy
 * @see app/Http/Controllers/AttendanceEnrolMapController.php:41
 * @route '/attendance/enrol-maps/{enrolMap}'
 */
destroy.url = (args: { enrolMap: string | number } | [enrolMap: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { enrolMap: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    enrolMap: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        enrolMap: args.enrolMap,
                }

    return destroy.definition.url
            .replace('{enrolMap}', parsedArgs.enrolMap.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceEnrolMapController::destroy
 * @see app/Http/Controllers/AttendanceEnrolMapController.php:41
 * @route '/attendance/enrol-maps/{enrolMap}'
 */
destroy.delete = (args: { enrolMap: string | number } | [enrolMap: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})
const AttendanceEnrolMapController = { store, destroy }

export default AttendanceEnrolMapController