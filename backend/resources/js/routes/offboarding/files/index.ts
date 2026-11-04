import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\OffboardingCaseController::download
 * @see app/Http/Controllers/OffboardingCaseController.php:356
 * @route '/offboarding/files/{file}'
 */
export const download = (args: { file: number | { id: number } } | [file: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})

download.definition = {
    methods: ["get","head"],
    url: '/offboarding/files/{file}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\OffboardingCaseController::download
 * @see app/Http/Controllers/OffboardingCaseController.php:356
 * @route '/offboarding/files/{file}'
 */
download.url = (args: { file: number | { id: number } } | [file: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { file: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { file: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    file: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        file: typeof args.file === 'object'
                ? args.file.id
                : args.file,
                }

    return download.definition.url
            .replace('{file}', parsedArgs.file.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingCaseController::download
 * @see app/Http/Controllers/OffboardingCaseController.php:356
 * @route '/offboarding/files/{file}'
 */
download.get = (args: { file: number | { id: number } } | [file: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\OffboardingCaseController::download
 * @see app/Http/Controllers/OffboardingCaseController.php:356
 * @route '/offboarding/files/{file}'
 */
download.head = (args: { file: number | { id: number } } | [file: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: download.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\OffboardingCaseController::store
 * @see app/Http/Controllers/OffboardingCaseController.php:345
 * @route '/offboarding/{offboardingCase}/files'
 */
export const store = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/offboarding/{offboardingCase}/files',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OffboardingCaseController::store
 * @see app/Http/Controllers/OffboardingCaseController.php:345
 * @route '/offboarding/{offboardingCase}/files'
 */
store.url = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { offboardingCase: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { offboardingCase: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    offboardingCase: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        offboardingCase: typeof args.offboardingCase === 'object'
                ? args.offboardingCase.id
                : args.offboardingCase,
                }

    return store.definition.url
            .replace('{offboardingCase}', parsedArgs.offboardingCase.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingCaseController::store
 * @see app/Http/Controllers/OffboardingCaseController.php:345
 * @route '/offboarding/{offboardingCase}/files'
 */
store.post = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})
const files = {
    download: Object.assign(download, download),
store: Object.assign(store, store),
}

export default files