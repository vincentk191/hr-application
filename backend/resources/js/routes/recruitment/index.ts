import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
import postings from './postings'
import comments from './comments'
/**
* @see \App\Http\Controllers\RequisitionController::index
 * @see app/Http/Controllers/RequisitionController.php:56
 * @route '/recruitment'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/recruitment',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\RequisitionController::index
 * @see app/Http/Controllers/RequisitionController.php:56
 * @route '/recruitment'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\RequisitionController::index
 * @see app/Http/Controllers/RequisitionController.php:56
 * @route '/recruitment'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\RequisitionController::index
 * @see app/Http/Controllers/RequisitionController.php:56
 * @route '/recruitment'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\RequisitionController::create
 * @see app/Http/Controllers/RequisitionController.php:106
 * @route '/recruitment/create'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/recruitment/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\RequisitionController::create
 * @see app/Http/Controllers/RequisitionController.php:106
 * @route '/recruitment/create'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\RequisitionController::create
 * @see app/Http/Controllers/RequisitionController.php:106
 * @route '/recruitment/create'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\RequisitionController::create
 * @see app/Http/Controllers/RequisitionController.php:106
 * @route '/recruitment/create'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\RequisitionController::store
 * @see app/Http/Controllers/RequisitionController.php:116
 * @route '/recruitment'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/recruitment',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RequisitionController::store
 * @see app/Http/Controllers/RequisitionController.php:116
 * @route '/recruitment'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\RequisitionController::store
 * @see app/Http/Controllers/RequisitionController.php:116
 * @route '/recruitment'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\RequisitionController::show
 * @see app/Http/Controllers/RequisitionController.php:195
 * @route '/recruitment/{requisition}'
 */
export const show = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/recruitment/{requisition}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\RequisitionController::show
 * @see app/Http/Controllers/RequisitionController.php:195
 * @route '/recruitment/{requisition}'
 */
show.url = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { requisition: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { requisition: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    requisition: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        requisition: typeof args.requisition === 'object'
                ? args.requisition.id
                : args.requisition,
                }

    return show.definition.url
            .replace('{requisition}', parsedArgs.requisition.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RequisitionController::show
 * @see app/Http/Controllers/RequisitionController.php:195
 * @route '/recruitment/{requisition}'
 */
show.get = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\RequisitionController::show
 * @see app/Http/Controllers/RequisitionController.php:195
 * @route '/recruitment/{requisition}'
 */
show.head = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\RequisitionController::edit
 * @see app/Http/Controllers/RequisitionController.php:133
 * @route '/recruitment/{requisition}/edit'
 */
export const edit = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/recruitment/{requisition}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\RequisitionController::edit
 * @see app/Http/Controllers/RequisitionController.php:133
 * @route '/recruitment/{requisition}/edit'
 */
edit.url = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { requisition: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { requisition: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    requisition: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        requisition: typeof args.requisition === 'object'
                ? args.requisition.id
                : args.requisition,
                }

    return edit.definition.url
            .replace('{requisition}', parsedArgs.requisition.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RequisitionController::edit
 * @see app/Http/Controllers/RequisitionController.php:133
 * @route '/recruitment/{requisition}/edit'
 */
edit.get = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\RequisitionController::edit
 * @see app/Http/Controllers/RequisitionController.php:133
 * @route '/recruitment/{requisition}/edit'
 */
edit.head = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\RequisitionController::update
 * @see app/Http/Controllers/RequisitionController.php:149
 * @route '/recruitment/{requisition}'
 */
export const update = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/recruitment/{requisition}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\RequisitionController::update
 * @see app/Http/Controllers/RequisitionController.php:149
 * @route '/recruitment/{requisition}'
 */
update.url = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { requisition: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { requisition: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    requisition: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        requisition: typeof args.requisition === 'object'
                ? args.requisition.id
                : args.requisition,
                }

    return update.definition.url
            .replace('{requisition}', parsedArgs.requisition.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RequisitionController::update
 * @see app/Http/Controllers/RequisitionController.php:149
 * @route '/recruitment/{requisition}'
 */
update.put = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\RequisitionController::destroy
 * @see app/Http/Controllers/RequisitionController.php:179
 * @route '/recruitment/{requisition}'
 */
export const destroy = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/recruitment/{requisition}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\RequisitionController::destroy
 * @see app/Http/Controllers/RequisitionController.php:179
 * @route '/recruitment/{requisition}'
 */
destroy.url = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { requisition: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { requisition: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    requisition: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        requisition: typeof args.requisition === 'object'
                ? args.requisition.id
                : args.requisition,
                }

    return destroy.definition.url
            .replace('{requisition}', parsedArgs.requisition.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RequisitionController::destroy
 * @see app/Http/Controllers/RequisitionController.php:179
 * @route '/recruitment/{requisition}'
 */
destroy.delete = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\RequisitionController::decide
 * @see app/Http/Controllers/RequisitionController.php:210
 * @route '/recruitment/{requisition}/decision'
 */
export const decide = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: decide.url(args, options),
    method: 'put',
})

decide.definition = {
    methods: ["put"],
    url: '/recruitment/{requisition}/decision',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\RequisitionController::decide
 * @see app/Http/Controllers/RequisitionController.php:210
 * @route '/recruitment/{requisition}/decision'
 */
decide.url = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { requisition: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { requisition: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    requisition: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        requisition: typeof args.requisition === 'object'
                ? args.requisition.id
                : args.requisition,
                }

    return decide.definition.url
            .replace('{requisition}', parsedArgs.requisition.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RequisitionController::decide
 * @see app/Http/Controllers/RequisitionController.php:210
 * @route '/recruitment/{requisition}/decision'
 */
decide.put = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: decide.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\RequisitionController::salary
 * @see app/Http/Controllers/RequisitionController.php:234
 * @route '/recruitment/{requisition}/salary'
 */
export const salary = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: salary.url(args, options),
    method: 'put',
})

salary.definition = {
    methods: ["put"],
    url: '/recruitment/{requisition}/salary',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\RequisitionController::salary
 * @see app/Http/Controllers/RequisitionController.php:234
 * @route '/recruitment/{requisition}/salary'
 */
salary.url = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { requisition: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { requisition: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    requisition: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        requisition: typeof args.requisition === 'object'
                ? args.requisition.id
                : args.requisition,
                }

    return salary.definition.url
            .replace('{requisition}', parsedArgs.requisition.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RequisitionController::salary
 * @see app/Http/Controllers/RequisitionController.php:234
 * @route '/recruitment/{requisition}/salary'
 */
salary.put = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: salary.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\RequisitionController::advance
 * @see app/Http/Controllers/RequisitionController.php:250
 * @route '/recruitment/{requisition}/advance'
 */
export const advance = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: advance.url(args, options),
    method: 'post',
})

advance.definition = {
    methods: ["post"],
    url: '/recruitment/{requisition}/advance',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RequisitionController::advance
 * @see app/Http/Controllers/RequisitionController.php:250
 * @route '/recruitment/{requisition}/advance'
 */
advance.url = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { requisition: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { requisition: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    requisition: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        requisition: typeof args.requisition === 'object'
                ? args.requisition.id
                : args.requisition,
                }

    return advance.definition.url
            .replace('{requisition}', parsedArgs.requisition.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RequisitionController::advance
 * @see app/Http/Controllers/RequisitionController.php:250
 * @route '/recruitment/{requisition}/advance'
 */
advance.post = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: advance.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\RequisitionController::screening
 * @see app/Http/Controllers/RequisitionController.php:303
 * @route '/recruitment/{requisition}/screening'
 */
export const screening = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: screening.url(args, options),
    method: 'post',
})

screening.definition = {
    methods: ["post"],
    url: '/recruitment/{requisition}/screening',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RequisitionController::screening
 * @see app/Http/Controllers/RequisitionController.php:303
 * @route '/recruitment/{requisition}/screening'
 */
screening.url = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { requisition: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { requisition: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    requisition: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        requisition: typeof args.requisition === 'object'
                ? args.requisition.id
                : args.requisition,
                }

    return screening.definition.url
            .replace('{requisition}', parsedArgs.requisition.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RequisitionController::screening
 * @see app/Http/Controllers/RequisitionController.php:303
 * @route '/recruitment/{requisition}/screening'
 */
screening.post = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: screening.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\RequisitionController::cancel
 * @see app/Http/Controllers/RequisitionController.php:321
 * @route '/recruitment/{requisition}/cancel'
 */
export const cancel = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cancel.url(args, options),
    method: 'post',
})

cancel.definition = {
    methods: ["post"],
    url: '/recruitment/{requisition}/cancel',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RequisitionController::cancel
 * @see app/Http/Controllers/RequisitionController.php:321
 * @route '/recruitment/{requisition}/cancel'
 */
cancel.url = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { requisition: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { requisition: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    requisition: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        requisition: typeof args.requisition === 'object'
                ? args.requisition.id
                : args.requisition,
                }

    return cancel.definition.url
            .replace('{requisition}', parsedArgs.requisition.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RequisitionController::cancel
 * @see app/Http/Controllers/RequisitionController.php:321
 * @route '/recruitment/{requisition}/cancel'
 */
cancel.post = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cancel.url(args, options),
    method: 'post',
})
const recruitment = {
    index: Object.assign(index, index),
create: Object.assign(create, create),
store: Object.assign(store, store),
show: Object.assign(show, show),
edit: Object.assign(edit, edit),
update: Object.assign(update, update),
destroy: Object.assign(destroy, destroy),
decide: Object.assign(decide, decide),
salary: Object.assign(salary, salary),
advance: Object.assign(advance, advance),
postings: Object.assign(postings, postings),
screening: Object.assign(screening, screening),
cancel: Object.assign(cancel, cancel),
comments: Object.assign(comments, comments),
}

export default recruitment