import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\EmployeeDocumentController::store
 * @see app/Http/Controllers/EmployeeDocumentController.php:32
 * @route '/employees/{employee}/documents'
 */
export const store = (args: { employee: number | { id: number } } | [employee: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/employees/{employee}/documents',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\EmployeeDocumentController::store
 * @see app/Http/Controllers/EmployeeDocumentController.php:32
 * @route '/employees/{employee}/documents'
 */
store.url = (args: { employee: number | { id: number } } | [employee: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { employee: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { employee: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    employee: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        employee: typeof args.employee === 'object'
                ? args.employee.id
                : args.employee,
                }

    return store.definition.url
            .replace('{employee}', parsedArgs.employee.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\EmployeeDocumentController::store
 * @see app/Http/Controllers/EmployeeDocumentController.php:32
 * @route '/employees/{employee}/documents'
 */
store.post = (args: { employee: number | { id: number } } | [employee: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\EmployeeDocumentController::update
 * @see app/Http/Controllers/EmployeeDocumentController.php:55
 * @route '/documents/{document}'
 */
export const update = (args: { document: number | { id: number } } | [document: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/documents/{document}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\EmployeeDocumentController::update
 * @see app/Http/Controllers/EmployeeDocumentController.php:55
 * @route '/documents/{document}'
 */
update.url = (args: { document: number | { id: number } } | [document: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return update.definition.url
            .replace('{document}', parsedArgs.document.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\EmployeeDocumentController::update
 * @see app/Http/Controllers/EmployeeDocumentController.php:55
 * @route '/documents/{document}'
 */
update.put = (args: { document: number | { id: number } } | [document: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\EmployeeDocumentController::destroy
 * @see app/Http/Controllers/EmployeeDocumentController.php:87
 * @route '/documents/{document}'
 */
export const destroy = (args: { document: number | { id: number } } | [document: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/documents/{document}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\EmployeeDocumentController::destroy
 * @see app/Http/Controllers/EmployeeDocumentController.php:87
 * @route '/documents/{document}'
 */
destroy.url = (args: { document: number | { id: number } } | [document: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return destroy.definition.url
            .replace('{document}', parsedArgs.document.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\EmployeeDocumentController::destroy
 * @see app/Http/Controllers/EmployeeDocumentController.php:87
 * @route '/documents/{document}'
 */
destroy.delete = (args: { document: number | { id: number } } | [document: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\EmployeeDocumentController::storeVersion
 * @see app/Http/Controllers/EmployeeDocumentController.php:72
 * @route '/documents/{document}/versions'
 */
export const storeVersion = (args: { document: number | { id: number } } | [document: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeVersion.url(args, options),
    method: 'post',
})

storeVersion.definition = {
    methods: ["post"],
    url: '/documents/{document}/versions',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\EmployeeDocumentController::storeVersion
 * @see app/Http/Controllers/EmployeeDocumentController.php:72
 * @route '/documents/{document}/versions'
 */
storeVersion.url = (args: { document: number | { id: number } } | [document: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return storeVersion.definition.url
            .replace('{document}', parsedArgs.document.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\EmployeeDocumentController::storeVersion
 * @see app/Http/Controllers/EmployeeDocumentController.php:72
 * @route '/documents/{document}/versions'
 */
storeVersion.post = (args: { document: number | { id: number } } | [document: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeVersion.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\EmployeeDocumentController::download
 * @see app/Http/Controllers/EmployeeDocumentController.php:98
 * @route '/documents/{document}/versions/{version}'
 */
export const download = (args: { document: number | { id: number }, version: number | { id: number } } | [document: number | { id: number }, version: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})

download.definition = {
    methods: ["get","head"],
    url: '/documents/{document}/versions/{version}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\EmployeeDocumentController::download
 * @see app/Http/Controllers/EmployeeDocumentController.php:98
 * @route '/documents/{document}/versions/{version}'
 */
download.url = (args: { document: number | { id: number }, version: number | { id: number } } | [document: number | { id: number }, version: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    document: args[0],
                    version: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        document: typeof args.document === 'object'
                ? args.document.id
                : args.document,
                                version: typeof args.version === 'object'
                ? args.version.id
                : args.version,
                }

    return download.definition.url
            .replace('{document}', parsedArgs.document.toString())
            .replace('{version}', parsedArgs.version.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\EmployeeDocumentController::download
 * @see app/Http/Controllers/EmployeeDocumentController.php:98
 * @route '/documents/{document}/versions/{version}'
 */
download.get = (args: { document: number | { id: number }, version: number | { id: number } } | [document: number | { id: number }, version: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\EmployeeDocumentController::download
 * @see app/Http/Controllers/EmployeeDocumentController.php:98
 * @route '/documents/{document}/versions/{version}'
 */
download.head = (args: { document: number | { id: number }, version: number | { id: number } } | [document: number | { id: number }, version: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: download.url(args, options),
    method: 'head',
})
const EmployeeDocumentController = { store, update, destroy, storeVersion, download }

export default EmployeeDocumentController