import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\OffboardingChecklistController::remove
 * @see app/Http/Controllers/OffboardingChecklistController.php:71
 * @route '/offboarding/checklist/items/{item}'
 */
export const remove = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: remove.url(args, options),
    method: 'delete',
})

remove.definition = {
    methods: ["delete"],
    url: '/offboarding/checklist/items/{item}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\OffboardingChecklistController::remove
 * @see app/Http/Controllers/OffboardingChecklistController.php:71
 * @route '/offboarding/checklist/items/{item}'
 */
remove.url = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { item: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { item: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    item: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        item: typeof args.item === 'object'
                ? args.item.id
                : args.item,
                }

    return remove.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingChecklistController::remove
 * @see app/Http/Controllers/OffboardingChecklistController.php:71
 * @route '/offboarding/checklist/items/{item}'
 */
remove.delete = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: remove.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\OffboardingChecklistController::done
 * @see app/Http/Controllers/OffboardingChecklistController.php:146
 * @route '/offboarding/checklist/items/{item}/done'
 */
export const done = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: done.url(args, options),
    method: 'put',
})

done.definition = {
    methods: ["put"],
    url: '/offboarding/checklist/items/{item}/done',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\OffboardingChecklistController::done
 * @see app/Http/Controllers/OffboardingChecklistController.php:146
 * @route '/offboarding/checklist/items/{item}/done'
 */
done.url = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { item: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { item: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    item: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        item: typeof args.item === 'object'
                ? args.item.id
                : args.item,
                }

    return done.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingChecklistController::done
 * @see app/Http/Controllers/OffboardingChecklistController.php:146
 * @route '/offboarding/checklist/items/{item}/done'
 */
done.put = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: done.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\OffboardingChecklistController::reopen
 * @see app/Http/Controllers/OffboardingChecklistController.php:157
 * @route '/offboarding/checklist/items/{item}/reopen'
 */
export const reopen = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: reopen.url(args, options),
    method: 'put',
})

reopen.definition = {
    methods: ["put"],
    url: '/offboarding/checklist/items/{item}/reopen',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\OffboardingChecklistController::reopen
 * @see app/Http/Controllers/OffboardingChecklistController.php:157
 * @route '/offboarding/checklist/items/{item}/reopen'
 */
reopen.url = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { item: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { item: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    item: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        item: typeof args.item === 'object'
                ? args.item.id
                : args.item,
                }

    return reopen.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingChecklistController::reopen
 * @see app/Http/Controllers/OffboardingChecklistController.php:157
 * @route '/offboarding/checklist/items/{item}/reopen'
 */
reopen.put = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: reopen.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\OffboardingChecklistController::select
 * @see app/Http/Controllers/OffboardingChecklistController.php:34
 * @route '/offboarding/{offboardingCase}/checklist/{group}/items'
 */
export const select = (args: { offboardingCase: number | { id: number }, group: number | { id: number } } | [offboardingCase: number | { id: number }, group: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: select.url(args, options),
    method: 'post',
})

select.definition = {
    methods: ["post"],
    url: '/offboarding/{offboardingCase}/checklist/{group}/items',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OffboardingChecklistController::select
 * @see app/Http/Controllers/OffboardingChecklistController.php:34
 * @route '/offboarding/{offboardingCase}/checklist/{group}/items'
 */
select.url = (args: { offboardingCase: number | { id: number }, group: number | { id: number } } | [offboardingCase: number | { id: number }, group: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    offboardingCase: args[0],
                    group: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        offboardingCase: typeof args.offboardingCase === 'object'
                ? args.offboardingCase.id
                : args.offboardingCase,
                                group: typeof args.group === 'object'
                ? args.group.id
                : args.group,
                }

    return select.definition.url
            .replace('{offboardingCase}', parsedArgs.offboardingCase.toString())
            .replace('{group}', parsedArgs.group.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingChecklistController::select
 * @see app/Http/Controllers/OffboardingChecklistController.php:34
 * @route '/offboarding/{offboardingCase}/checklist/{group}/items'
 */
select.post = (args: { offboardingCase: number | { id: number }, group: number | { id: number } } | [offboardingCase: number | { id: number }, group: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: select.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OffboardingChecklistController::adhoc
 * @see app/Http/Controllers/OffboardingChecklistController.php:54
 * @route '/offboarding/{offboardingCase}/checklist/{group}/adhoc'
 */
export const adhoc = (args: { offboardingCase: number | { id: number }, group: number | { id: number } } | [offboardingCase: number | { id: number }, group: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: adhoc.url(args, options),
    method: 'post',
})

adhoc.definition = {
    methods: ["post"],
    url: '/offboarding/{offboardingCase}/checklist/{group}/adhoc',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OffboardingChecklistController::adhoc
 * @see app/Http/Controllers/OffboardingChecklistController.php:54
 * @route '/offboarding/{offboardingCase}/checklist/{group}/adhoc'
 */
adhoc.url = (args: { offboardingCase: number | { id: number }, group: number | { id: number } } | [offboardingCase: number | { id: number }, group: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    offboardingCase: args[0],
                    group: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        offboardingCase: typeof args.offboardingCase === 'object'
                ? args.offboardingCase.id
                : args.offboardingCase,
                                group: typeof args.group === 'object'
                ? args.group.id
                : args.group,
                }

    return adhoc.definition.url
            .replace('{offboardingCase}', parsedArgs.offboardingCase.toString())
            .replace('{group}', parsedArgs.group.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingChecklistController::adhoc
 * @see app/Http/Controllers/OffboardingChecklistController.php:54
 * @route '/offboarding/{offboardingCase}/checklist/{group}/adhoc'
 */
adhoc.post = (args: { offboardingCase: number | { id: number }, group: number | { id: number } } | [offboardingCase: number | { id: number }, group: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: adhoc.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OffboardingChecklistController::confirm
 * @see app/Http/Controllers/OffboardingChecklistController.php:83
 * @route '/offboarding/{offboardingCase}/checklist/{group}/confirm'
 */
export const confirm = (args: { offboardingCase: number | { id: number }, group: number | { id: number } } | [offboardingCase: number | { id: number }, group: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: confirm.url(args, options),
    method: 'post',
})

confirm.definition = {
    methods: ["post"],
    url: '/offboarding/{offboardingCase}/checklist/{group}/confirm',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OffboardingChecklistController::confirm
 * @see app/Http/Controllers/OffboardingChecklistController.php:83
 * @route '/offboarding/{offboardingCase}/checklist/{group}/confirm'
 */
confirm.url = (args: { offboardingCase: number | { id: number }, group: number | { id: number } } | [offboardingCase: number | { id: number }, group: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    offboardingCase: args[0],
                    group: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        offboardingCase: typeof args.offboardingCase === 'object'
                ? args.offboardingCase.id
                : args.offboardingCase,
                                group: typeof args.group === 'object'
                ? args.group.id
                : args.group,
                }

    return confirm.definition.url
            .replace('{offboardingCase}', parsedArgs.offboardingCase.toString())
            .replace('{group}', parsedArgs.group.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingChecklistController::confirm
 * @see app/Http/Controllers/OffboardingChecklistController.php:83
 * @route '/offboarding/{offboardingCase}/checklist/{group}/confirm'
 */
confirm.post = (args: { offboardingCase: number | { id: number }, group: number | { id: number } } | [offboardingCase: number | { id: number }, group: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: confirm.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OffboardingChecklistController::involvement
 * @see app/Http/Controllers/OffboardingChecklistController.php:98
 * @route '/offboarding/{offboardingCase}/checklist/{group}/involvement'
 */
export const involvement = (args: { offboardingCase: number | { id: number }, group: number | { id: number } } | [offboardingCase: number | { id: number }, group: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: involvement.url(args, options),
    method: 'put',
})

involvement.definition = {
    methods: ["put"],
    url: '/offboarding/{offboardingCase}/checklist/{group}/involvement',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\OffboardingChecklistController::involvement
 * @see app/Http/Controllers/OffboardingChecklistController.php:98
 * @route '/offboarding/{offboardingCase}/checklist/{group}/involvement'
 */
involvement.url = (args: { offboardingCase: number | { id: number }, group: number | { id: number } } | [offboardingCase: number | { id: number }, group: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    offboardingCase: args[0],
                    group: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        offboardingCase: typeof args.offboardingCase === 'object'
                ? args.offboardingCase.id
                : args.offboardingCase,
                                group: typeof args.group === 'object'
                ? args.group.id
                : args.group,
                }

    return involvement.definition.url
            .replace('{offboardingCase}', parsedArgs.offboardingCase.toString())
            .replace('{group}', parsedArgs.group.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingChecklistController::involvement
 * @see app/Http/Controllers/OffboardingChecklistController.php:98
 * @route '/offboarding/{offboardingCase}/checklist/{group}/involvement'
 */
involvement.put = (args: { offboardingCase: number | { id: number }, group: number | { id: number } } | [offboardingCase: number | { id: number }, group: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: involvement.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\OffboardingChecklistController::approve
 * @see app/Http/Controllers/OffboardingChecklistController.php:169
 * @route '/offboarding/{offboardingCase}/checklist/approve'
 */
export const approve = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: approve.url(args, options),
    method: 'post',
})

approve.definition = {
    methods: ["post"],
    url: '/offboarding/{offboardingCase}/checklist/approve',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OffboardingChecklistController::approve
 * @see app/Http/Controllers/OffboardingChecklistController.php:169
 * @route '/offboarding/{offboardingCase}/checklist/approve'
 */
approve.url = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return approve.definition.url
            .replace('{offboardingCase}', parsedArgs.offboardingCase.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingChecklistController::approve
 * @see app/Http/Controllers/OffboardingChecklistController.php:169
 * @route '/offboarding/{offboardingCase}/checklist/approve'
 */
approve.post = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: approve.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OffboardingChecklistController::refresh
 * @see app/Http/Controllers/OffboardingChecklistController.php:112
 * @route '/offboarding/{offboardingCase}/checklist/refresh'
 */
export const refresh = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: refresh.url(args, options),
    method: 'post',
})

refresh.definition = {
    methods: ["post"],
    url: '/offboarding/{offboardingCase}/checklist/refresh',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OffboardingChecklistController::refresh
 * @see app/Http/Controllers/OffboardingChecklistController.php:112
 * @route '/offboarding/{offboardingCase}/checklist/refresh'
 */
refresh.url = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return refresh.definition.url
            .replace('{offboardingCase}', parsedArgs.offboardingCase.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingChecklistController::refresh
 * @see app/Http/Controllers/OffboardingChecklistController.php:112
 * @route '/offboarding/{offboardingCase}/checklist/refresh'
 */
refresh.post = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: refresh.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OffboardingChecklistController::owner
 * @see app/Http/Controllers/OffboardingChecklistController.php:129
 * @route '/offboarding/{offboardingCase}/checklist/{group}/owner'
 */
export const owner = (args: { offboardingCase: number | { id: number }, group: number | { id: number } } | [offboardingCase: number | { id: number }, group: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: owner.url(args, options),
    method: 'put',
})

owner.definition = {
    methods: ["put"],
    url: '/offboarding/{offboardingCase}/checklist/{group}/owner',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\OffboardingChecklistController::owner
 * @see app/Http/Controllers/OffboardingChecklistController.php:129
 * @route '/offboarding/{offboardingCase}/checklist/{group}/owner'
 */
owner.url = (args: { offboardingCase: number | { id: number }, group: number | { id: number } } | [offboardingCase: number | { id: number }, group: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    offboardingCase: args[0],
                    group: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        offboardingCase: typeof args.offboardingCase === 'object'
                ? args.offboardingCase.id
                : args.offboardingCase,
                                group: typeof args.group === 'object'
                ? args.group.id
                : args.group,
                }

    return owner.definition.url
            .replace('{offboardingCase}', parsedArgs.offboardingCase.toString())
            .replace('{group}', parsedArgs.group.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingChecklistController::owner
 * @see app/Http/Controllers/OffboardingChecklistController.php:129
 * @route '/offboarding/{offboardingCase}/checklist/{group}/owner'
 */
owner.put = (args: { offboardingCase: number | { id: number }, group: number | { id: number } } | [offboardingCase: number | { id: number }, group: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: owner.url(args, options),
    method: 'put',
})
const checklist = {
    remove: Object.assign(remove, remove),
done: Object.assign(done, done),
reopen: Object.assign(reopen, reopen),
select: Object.assign(select, select),
adhoc: Object.assign(adhoc, adhoc),
confirm: Object.assign(confirm, confirm),
involvement: Object.assign(involvement, involvement),
approve: Object.assign(approve, approve),
refresh: Object.assign(refresh, refresh),
owner: Object.assign(owner, owner),
}

export default checklist