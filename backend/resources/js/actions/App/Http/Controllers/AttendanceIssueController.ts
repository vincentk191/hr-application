import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\AttendanceIssueController::index
 * @see app/Http/Controllers/AttendanceIssueController.php:40
 * @route '/attendance/issues'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/attendance/issues',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AttendanceIssueController::index
 * @see app/Http/Controllers/AttendanceIssueController.php:40
 * @route '/attendance/issues'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceIssueController::index
 * @see app/Http/Controllers/AttendanceIssueController.php:40
 * @route '/attendance/issues'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AttendanceIssueController::index
 * @see app/Http/Controllers/AttendanceIssueController.php:40
 * @route '/attendance/issues'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\AttendanceIssueController::resolve
 * @see app/Http/Controllers/AttendanceIssueController.php:100
 * @route '/attendance/issues/resolve'
 */
export const resolve = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resolve.url(options),
    method: 'post',
})

resolve.definition = {
    methods: ["post"],
    url: '/attendance/issues/resolve',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AttendanceIssueController::resolve
 * @see app/Http/Controllers/AttendanceIssueController.php:100
 * @route '/attendance/issues/resolve'
 */
resolve.url = (options?: RouteQueryOptions) => {
    return resolve.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceIssueController::resolve
 * @see app/Http/Controllers/AttendanceIssueController.php:100
 * @route '/attendance/issues/resolve'
 */
resolve.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resolve.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AttendanceIssueController::reopen
 * @see app/Http/Controllers/AttendanceIssueController.php:140
 * @route '/attendance/issues/reopen'
 */
export const reopen = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reopen.url(options),
    method: 'post',
})

reopen.definition = {
    methods: ["post"],
    url: '/attendance/issues/reopen',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AttendanceIssueController::reopen
 * @see app/Http/Controllers/AttendanceIssueController.php:140
 * @route '/attendance/issues/reopen'
 */
reopen.url = (options?: RouteQueryOptions) => {
    return reopen.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceIssueController::reopen
 * @see app/Http/Controllers/AttendanceIssueController.php:140
 * @route '/attendance/issues/reopen'
 */
reopen.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reopen.url(options),
    method: 'post',
})
const AttendanceIssueController = { index, resolve, reopen }

export default AttendanceIssueController