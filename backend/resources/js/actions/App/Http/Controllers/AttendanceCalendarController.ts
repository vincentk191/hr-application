import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\AttendanceCalendarController::index
 * @see app/Http/Controllers/AttendanceCalendarController.php:55
 * @route '/attendance/calendar'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/attendance/calendar',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AttendanceCalendarController::index
 * @see app/Http/Controllers/AttendanceCalendarController.php:55
 * @route '/attendance/calendar'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceCalendarController::index
 * @see app/Http/Controllers/AttendanceCalendarController.php:55
 * @route '/attendance/calendar'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AttendanceCalendarController::index
 * @see app/Http/Controllers/AttendanceCalendarController.php:55
 * @route '/attendance/calendar'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\AttendanceCalendarController::exportMethod
 * @see app/Http/Controllers/AttendanceCalendarController.php:107
 * @route '/attendance/calendar/export'
 */
export const exportMethod = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportMethod.url(options),
    method: 'get',
})

exportMethod.definition = {
    methods: ["get","head"],
    url: '/attendance/calendar/export',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AttendanceCalendarController::exportMethod
 * @see app/Http/Controllers/AttendanceCalendarController.php:107
 * @route '/attendance/calendar/export'
 */
exportMethod.url = (options?: RouteQueryOptions) => {
    return exportMethod.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceCalendarController::exportMethod
 * @see app/Http/Controllers/AttendanceCalendarController.php:107
 * @route '/attendance/calendar/export'
 */
exportMethod.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportMethod.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AttendanceCalendarController::exportMethod
 * @see app/Http/Controllers/AttendanceCalendarController.php:107
 * @route '/attendance/calendar/export'
 */
exportMethod.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: exportMethod.url(options),
    method: 'head',
})
const AttendanceCalendarController = { index, exportMethod, export: exportMethod }

export default AttendanceCalendarController