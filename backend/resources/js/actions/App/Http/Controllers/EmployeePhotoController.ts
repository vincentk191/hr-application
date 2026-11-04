import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\EmployeePhotoController::show
 * @see app/Http/Controllers/EmployeePhotoController.php:16
 * @route '/employees/{employee}/photo'
 */
export const show = (args: { employee: number | { id: number } } | [employee: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/employees/{employee}/photo',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\EmployeePhotoController::show
 * @see app/Http/Controllers/EmployeePhotoController.php:16
 * @route '/employees/{employee}/photo'
 */
show.url = (args: { employee: number | { id: number } } | [employee: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{employee}', parsedArgs.employee.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\EmployeePhotoController::show
 * @see app/Http/Controllers/EmployeePhotoController.php:16
 * @route '/employees/{employee}/photo'
 */
show.get = (args: { employee: number | { id: number } } | [employee: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\EmployeePhotoController::show
 * @see app/Http/Controllers/EmployeePhotoController.php:16
 * @route '/employees/{employee}/photo'
 */
show.head = (args: { employee: number | { id: number } } | [employee: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\EmployeePhotoController::change
 * @see app/Http/Controllers/EmployeePhotoController.php:26
 * @route '/profile-changes/{profileChange}/photo/{side}'
 */
export const change = (args: { profileChange: number | { id: number }, side: string | number } | [profileChange: number | { id: number }, side: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: change.url(args, options),
    method: 'get',
})

change.definition = {
    methods: ["get","head"],
    url: '/profile-changes/{profileChange}/photo/{side}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\EmployeePhotoController::change
 * @see app/Http/Controllers/EmployeePhotoController.php:26
 * @route '/profile-changes/{profileChange}/photo/{side}'
 */
change.url = (args: { profileChange: number | { id: number }, side: string | number } | [profileChange: number | { id: number }, side: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    profileChange: args[0],
                    side: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        profileChange: typeof args.profileChange === 'object'
                ? args.profileChange.id
                : args.profileChange,
                                side: args.side,
                }

    return change.definition.url
            .replace('{profileChange}', parsedArgs.profileChange.toString())
            .replace('{side}', parsedArgs.side.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\EmployeePhotoController::change
 * @see app/Http/Controllers/EmployeePhotoController.php:26
 * @route '/profile-changes/{profileChange}/photo/{side}'
 */
change.get = (args: { profileChange: number | { id: number }, side: string | number } | [profileChange: number | { id: number }, side: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: change.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\EmployeePhotoController::change
 * @see app/Http/Controllers/EmployeePhotoController.php:26
 * @route '/profile-changes/{profileChange}/photo/{side}'
 */
change.head = (args: { profileChange: number | { id: number }, side: string | number } | [profileChange: number | { id: number }, side: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: change.url(args, options),
    method: 'head',
})
const EmployeePhotoController = { show, change }

export default EmployeePhotoController