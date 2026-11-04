import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\EmployeeAccountController::activate
 * @see app/Http/Controllers/EmployeeAccountController.php:16
 * @route '/employees/{employee}/activation'
 */
export const activate = (args: { employee: number | { id: number } } | [employee: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: activate.url(args, options),
    method: 'post',
})

activate.definition = {
    methods: ["post"],
    url: '/employees/{employee}/activation',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\EmployeeAccountController::activate
 * @see app/Http/Controllers/EmployeeAccountController.php:16
 * @route '/employees/{employee}/activation'
 */
activate.url = (args: { employee: number | { id: number } } | [employee: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return activate.definition.url
            .replace('{employee}', parsedArgs.employee.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\EmployeeAccountController::activate
 * @see app/Http/Controllers/EmployeeAccountController.php:16
 * @route '/employees/{employee}/activation'
 */
activate.post = (args: { employee: number | { id: number } } | [employee: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: activate.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\EmployeeAccountController::reject
 * @see app/Http/Controllers/EmployeeAccountController.php:27
 * @route '/employees/{employee}/activation'
 */
export const reject = (args: { employee: number | { id: number } } | [employee: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: reject.url(args, options),
    method: 'delete',
})

reject.definition = {
    methods: ["delete"],
    url: '/employees/{employee}/activation',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\EmployeeAccountController::reject
 * @see app/Http/Controllers/EmployeeAccountController.php:27
 * @route '/employees/{employee}/activation'
 */
reject.url = (args: { employee: number | { id: number } } | [employee: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return reject.definition.url
            .replace('{employee}', parsedArgs.employee.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\EmployeeAccountController::reject
 * @see app/Http/Controllers/EmployeeAccountController.php:27
 * @route '/employees/{employee}/activation'
 */
reject.delete = (args: { employee: number | { id: number } } | [employee: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: reject.url(args, options),
    method: 'delete',
})
const activation = {
    activate: Object.assign(activate, activate),
reject: Object.assign(reject, reject),
}

export default activation