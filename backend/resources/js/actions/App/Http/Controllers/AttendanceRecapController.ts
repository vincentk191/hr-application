import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\AttendanceRecapController::index
 * @see app/Http/Controllers/AttendanceRecapController.php:38
 * @route '/attendance/recap'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/attendance/recap',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AttendanceRecapController::index
 * @see app/Http/Controllers/AttendanceRecapController.php:38
 * @route '/attendance/recap'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceRecapController::index
 * @see app/Http/Controllers/AttendanceRecapController.php:38
 * @route '/attendance/recap'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AttendanceRecapController::index
 * @see app/Http/Controllers/AttendanceRecapController.php:38
 * @route '/attendance/recap'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})
const AttendanceRecapController = { index }

export default AttendanceRecapController