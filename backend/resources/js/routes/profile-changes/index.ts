import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
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

/**
* @see \App\Http\Controllers\EmployeePhotoController::photo
 * @see app/Http/Controllers/EmployeePhotoController.php:26
 * @route '/profile-changes/{profileChange}/photo/{side}'
 */
export const photo = (args: { profileChange: number | { id: number }, side: string | number } | [profileChange: number | { id: number }, side: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: photo.url(args, options),
    method: 'get',
})

photo.definition = {
    methods: ["get","head"],
    url: '/profile-changes/{profileChange}/photo/{side}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\EmployeePhotoController::photo
 * @see app/Http/Controllers/EmployeePhotoController.php:26
 * @route '/profile-changes/{profileChange}/photo/{side}'
 */
photo.url = (args: { profileChange: number | { id: number }, side: string | number } | [profileChange: number | { id: number }, side: string | number ], options?: RouteQueryOptions) => {
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

    return photo.definition.url
            .replace('{profileChange}', parsedArgs.profileChange.toString())
            .replace('{side}', parsedArgs.side.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\EmployeePhotoController::photo
 * @see app/Http/Controllers/EmployeePhotoController.php:26
 * @route '/profile-changes/{profileChange}/photo/{side}'
 */
photo.get = (args: { profileChange: number | { id: number }, side: string | number } | [profileChange: number | { id: number }, side: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: photo.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\EmployeePhotoController::photo
 * @see app/Http/Controllers/EmployeePhotoController.php:26
 * @route '/profile-changes/{profileChange}/photo/{side}'
 */
photo.head = (args: { profileChange: number | { id: number }, side: string | number } | [profileChange: number | { id: number }, side: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: photo.url(args, options),
    method: 'head',
})
const profileChanges = {
    index: Object.assign(index, index),
decide: Object.assign(decide, decide),
destroy: Object.assign(destroy, destroy),
photo: Object.assign(photo, photo),
}

export default profileChanges