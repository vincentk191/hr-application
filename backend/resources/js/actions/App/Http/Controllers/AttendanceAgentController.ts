import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\AttendanceAgentController::syncNow
 * @see app/Http/Controllers/AttendanceAgentController.php:16
 * @route '/attendance/agents/sync-now'
 */
export const syncNow = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: syncNow.url(options),
    method: 'post',
})

syncNow.definition = {
    methods: ["post"],
    url: '/attendance/agents/sync-now',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AttendanceAgentController::syncNow
 * @see app/Http/Controllers/AttendanceAgentController.php:16
 * @route '/attendance/agents/sync-now'
 */
syncNow.url = (options?: RouteQueryOptions) => {
    return syncNow.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceAgentController::syncNow
 * @see app/Http/Controllers/AttendanceAgentController.php:16
 * @route '/attendance/agents/sync-now'
 */
syncNow.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: syncNow.url(options),
    method: 'post',
})
const AttendanceAgentController = { syncNow }

export default AttendanceAgentController