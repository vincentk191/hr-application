import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\AttendanceIngestController::store
 * @see app/Http/Controllers/Api/AttendanceIngestController.php:25
 * @route '/api/ingest/attendance'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/ingest/attendance',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\AttendanceIngestController::store
 * @see app/Http/Controllers/Api/AttendanceIngestController.php:25
 * @route '/api/ingest/attendance'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\AttendanceIngestController::store
 * @see app/Http/Controllers/Api/AttendanceIngestController.php:25
 * @route '/api/ingest/attendance'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\AttendanceIngestController::instructions
 * @see app/Http/Controllers/Api/AttendanceIngestController.php:84
 * @route '/api/ingest/attendance/instructions'
 */
export const instructions = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: instructions.url(options),
    method: 'get',
})

instructions.definition = {
    methods: ["get","head"],
    url: '/api/ingest/attendance/instructions',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\AttendanceIngestController::instructions
 * @see app/Http/Controllers/Api/AttendanceIngestController.php:84
 * @route '/api/ingest/attendance/instructions'
 */
instructions.url = (options?: RouteQueryOptions) => {
    return instructions.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\AttendanceIngestController::instructions
 * @see app/Http/Controllers/Api/AttendanceIngestController.php:84
 * @route '/api/ingest/attendance/instructions'
 */
instructions.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: instructions.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Api\AttendanceIngestController::instructions
 * @see app/Http/Controllers/Api/AttendanceIngestController.php:84
 * @route '/api/ingest/attendance/instructions'
 */
instructions.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: instructions.url(options),
    method: 'head',
})
const attendance = {
    store: Object.assign(store, store),
instructions: Object.assign(instructions, instructions),
}

export default attendance