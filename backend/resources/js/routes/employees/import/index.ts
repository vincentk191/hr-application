import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\EmployeeImportController::store
 * @see app/Http/Controllers/EmployeeImportController.php:40
 * @route '/employees/import'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/employees/import',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\EmployeeImportController::store
 * @see app/Http/Controllers/EmployeeImportController.php:40
 * @route '/employees/import'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\EmployeeImportController::store
 * @see app/Http/Controllers/EmployeeImportController.php:40
 * @route '/employees/import'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\EmployeeImportController::template
 * @see app/Http/Controllers/EmployeeImportController.php:64
 * @route '/employees/import/template'
 */
export const template = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: template.url(options),
    method: 'get',
})

template.definition = {
    methods: ["get","head"],
    url: '/employees/import/template',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\EmployeeImportController::template
 * @see app/Http/Controllers/EmployeeImportController.php:64
 * @route '/employees/import/template'
 */
template.url = (options?: RouteQueryOptions) => {
    return template.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\EmployeeImportController::template
 * @see app/Http/Controllers/EmployeeImportController.php:64
 * @route '/employees/import/template'
 */
template.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: template.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\EmployeeImportController::template
 * @see app/Http/Controllers/EmployeeImportController.php:64
 * @route '/employees/import/template'
 */
template.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: template.url(options),
    method: 'head',
})
const importMethod = {
    store: Object.assign(store, store),
template: Object.assign(template, template),
}

export default importMethod