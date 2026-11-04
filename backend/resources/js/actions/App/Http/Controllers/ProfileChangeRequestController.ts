import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\ProfileChangeRequestController::create
 * @see app/Http/Controllers/ProfileChangeRequestController.php:47
 * @route '/employees/{employee}/changes'
 */
export const create = (args: { employee: number | { id: number } } | [employee: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(args, options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/employees/{employee}/changes',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ProfileChangeRequestController::create
 * @see app/Http/Controllers/ProfileChangeRequestController.php:47
 * @route '/employees/{employee}/changes'
 */
create.url = (args: { employee: number | { id: number } } | [employee: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return create.definition.url
            .replace('{employee}', parsedArgs.employee.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProfileChangeRequestController::create
 * @see app/Http/Controllers/ProfileChangeRequestController.php:47
 * @route '/employees/{employee}/changes'
 */
create.get = (args: { employee: number | { id: number } } | [employee: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ProfileChangeRequestController::create
 * @see app/Http/Controllers/ProfileChangeRequestController.php:47
 * @route '/employees/{employee}/changes'
 */
create.head = (args: { employee: number | { id: number } } | [employee: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ProfileChangeRequestController::store
 * @see app/Http/Controllers/ProfileChangeRequestController.php:61
 * @route '/employees/{employee}/changes'
 */
export const store = (args: { employee: number | { id: number } } | [employee: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/employees/{employee}/changes',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ProfileChangeRequestController::store
 * @see app/Http/Controllers/ProfileChangeRequestController.php:61
 * @route '/employees/{employee}/changes'
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
* @see \App\Http\Controllers\ProfileChangeRequestController::store
 * @see app/Http/Controllers/ProfileChangeRequestController.php:61
 * @route '/employees/{employee}/changes'
 */
store.post = (args: { employee: number | { id: number } } | [employee: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\ProfileChangeRequestController::index
 * @see app/Http/Controllers/ProfileChangeRequestController.php:28
 * @route '/profile-changes'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/profile-changes',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ProfileChangeRequestController::index
 * @see app/Http/Controllers/ProfileChangeRequestController.php:28
 * @route '/profile-changes'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProfileChangeRequestController::index
 * @see app/Http/Controllers/ProfileChangeRequestController.php:28
 * @route '/profile-changes'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ProfileChangeRequestController::index
 * @see app/Http/Controllers/ProfileChangeRequestController.php:28
 * @route '/profile-changes'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ProfileChangeRequestController::decide
 * @see app/Http/Controllers/ProfileChangeRequestController.php:84
 * @route '/profile-changes/{profileChange}/decision'
 */
export const decide = (args: { profileChange: number | { id: number } } | [profileChange: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: decide.url(args, options),
    method: 'put',
})

decide.definition = {
    methods: ["put"],
    url: '/profile-changes/{profileChange}/decision',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\ProfileChangeRequestController::decide
 * @see app/Http/Controllers/ProfileChangeRequestController.php:84
 * @route '/profile-changes/{profileChange}/decision'
 */
decide.url = (args: { profileChange: number | { id: number } } | [profileChange: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { profileChange: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { profileChange: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    profileChange: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        profileChange: typeof args.profileChange === 'object'
                ? args.profileChange.id
                : args.profileChange,
                }

    return decide.definition.url
            .replace('{profileChange}', parsedArgs.profileChange.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProfileChangeRequestController::decide
 * @see app/Http/Controllers/ProfileChangeRequestController.php:84
 * @route '/profile-changes/{profileChange}/decision'
 */
decide.put = (args: { profileChange: number | { id: number } } | [profileChange: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: decide.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\ProfileChangeRequestController::destroy
 * @see app/Http/Controllers/ProfileChangeRequestController.php:73
 * @route '/profile-changes/{profileChange}'
 */
export const destroy = (args: { profileChange: number | { id: number } } | [profileChange: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/profile-changes/{profileChange}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\ProfileChangeRequestController::destroy
 * @see app/Http/Controllers/ProfileChangeRequestController.php:73
 * @route '/profile-changes/{profileChange}'
 */
destroy.url = (args: { profileChange: number | { id: number } } | [profileChange: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { profileChange: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { profileChange: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    profileChange: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        profileChange: typeof args.profileChange === 'object'
                ? args.profileChange.id
                : args.profileChange,
                }

    return destroy.definition.url
            .replace('{profileChange}', parsedArgs.profileChange.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProfileChangeRequestController::destroy
 * @see app/Http/Controllers/ProfileChangeRequestController.php:73
 * @route '/profile-changes/{profileChange}'
 */
destroy.delete = (args: { profileChange: number | { id: number } } | [profileChange: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})
const ProfileChangeRequestController = { create, store, index, decide, destroy }

export default ProfileChangeRequestController