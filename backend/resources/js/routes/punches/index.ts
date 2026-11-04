import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\AttendancePunchController::store
 * @see app/Http/Controllers/AttendancePunchController.php:20
 * @route '/employees/{employee}/punches'
 */
export const store = (args: { employee: number | { id: number } } | [employee: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/employees/{employee}/punches',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AttendancePunchController::store
 * @see app/Http/Controllers/AttendancePunchController.php:20
 * @route '/employees/{employee}/punches'
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
* @see \App\Http\Controllers\AttendancePunchController::store
 * @see app/Http/Controllers/AttendancePunchController.php:20
 * @route '/employees/{employee}/punches'
 */
store.post = (args: { employee: number | { id: number } } | [employee: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AttendancePunchController::update
 * @see app/Http/Controllers/AttendancePunchController.php:29
 * @route '/punches/{punch}'
 */
export const update = (args: { punch: number | { id: number } } | [punch: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/punches/{punch}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\AttendancePunchController::update
 * @see app/Http/Controllers/AttendancePunchController.php:29
 * @route '/punches/{punch}'
 */
update.url = (args: { punch: number | { id: number } } | [punch: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { punch: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { punch: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    punch: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        punch: typeof args.punch === 'object'
                ? args.punch.id
                : args.punch,
                }

    return update.definition.url
            .replace('{punch}', parsedArgs.punch.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendancePunchController::update
 * @see app/Http/Controllers/AttendancePunchController.php:29
 * @route '/punches/{punch}'
 */
update.put = (args: { punch: number | { id: number } } | [punch: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\AttendancePunchController::destroy
 * @see app/Http/Controllers/AttendancePunchController.php:38
 * @route '/punches/{punch}'
 */
export const destroy = (args: { punch: number | { id: number } } | [punch: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/punches/{punch}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\AttendancePunchController::destroy
 * @see app/Http/Controllers/AttendancePunchController.php:38
 * @route '/punches/{punch}'
 */
destroy.url = (args: { punch: number | { id: number } } | [punch: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { punch: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { punch: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    punch: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        punch: typeof args.punch === 'object'
                ? args.punch.id
                : args.punch,
                }

    return destroy.definition.url
            .replace('{punch}', parsedArgs.punch.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendancePunchController::destroy
 * @see app/Http/Controllers/AttendancePunchController.php:38
 * @route '/punches/{punch}'
 */
destroy.delete = (args: { punch: number | { id: number } } | [punch: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})
const punches = {
    store: Object.assign(store, store),
update: Object.assign(update, update),
destroy: Object.assign(destroy, destroy),
}

export default punches