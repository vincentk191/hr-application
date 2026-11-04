import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\AttendanceImportController::store
 * @see app/Http/Controllers/AttendanceImportController.php:14
 * @route '/attendance/imports'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/attendance/imports',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AttendanceImportController::store
 * @see app/Http/Controllers/AttendanceImportController.php:14
 * @route '/attendance/imports'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceImportController::store
 * @see app/Http/Controllers/AttendanceImportController.php:14
 * @route '/attendance/imports'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})
const imports = {
    store: Object.assign(store, store),
}

export default imports