import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\AttendanceDeviceController::index
 * @see app/Http/Controllers/AttendanceDeviceController.php:33
 * @route '/attendance/device'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/attendance/device',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AttendanceDeviceController::index
 * @see app/Http/Controllers/AttendanceDeviceController.php:33
 * @route '/attendance/device'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceDeviceController::index
 * @see app/Http/Controllers/AttendanceDeviceController.php:33
 * @route '/attendance/device'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AttendanceDeviceController::index
 * @see app/Http/Controllers/AttendanceDeviceController.php:33
 * @route '/attendance/device'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})
const AttendanceDeviceController = { index }

export default AttendanceDeviceController