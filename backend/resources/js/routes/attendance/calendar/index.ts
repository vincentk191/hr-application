import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
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
const calendar = {
    export: Object.assign(exportMethod, exportMethod),
}

export default calendar