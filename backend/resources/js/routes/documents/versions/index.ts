import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\EmployeeDocumentController::store
 * @see app/Http/Controllers/EmployeeDocumentController.php:72
 * @route '/documents/{document}/versions'
 */
export const store = (args: { document: number | { id: number } } | [document: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/documents/{document}/versions',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\EmployeeDocumentController::store
 * @see app/Http/Controllers/EmployeeDocumentController.php:72
 * @route '/documents/{document}/versions'
 */
store.url = (args: { document: number | { id: number } } | [document: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { document: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { document: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    document: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        document: typeof args.document === 'object'
                ? args.document.id
                : args.document,
                }

    return store.definition.url
            .replace('{document}', parsedArgs.document.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\EmployeeDocumentController::store
 * @see app/Http/Controllers/EmployeeDocumentController.php:72
 * @route '/documents/{document}/versions'
 */
store.post = (args: { document: number | { id: number } } | [document: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})
const versions = {
    store: Object.assign(store, store),
}

export default versions