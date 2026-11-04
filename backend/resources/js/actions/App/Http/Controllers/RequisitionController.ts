import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
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
* @see \App\Http\Controllers\RequisitionController::storePosting
 * @see app/Http/Controllers/RequisitionController.php:268
 * @route '/recruitment/{requisition}/postings'
 */
export const storePosting = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storePosting.url(args, options),
    method: 'post',
})

storePosting.definition = {
    methods: ["post"],
    url: '/recruitment/{requisition}/postings',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RequisitionController::storePosting
 * @see app/Http/Controllers/RequisitionController.php:268
 * @route '/recruitment/{requisition}/postings'
 */
storePosting.url = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return storePosting.definition.url
            .replace('{requisition}', parsedArgs.requisition.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RequisitionController::storePosting
 * @see app/Http/Controllers/RequisitionController.php:268
 * @route '/recruitment/{requisition}/postings'
 */
storePosting.post = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storePosting.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\RequisitionController::updatePosting
 * @see app/Http/Controllers/RequisitionController.php:280
 * @route '/recruitment/{requisition}/postings/{posting}'
 */
export const updatePosting = (args: { requisition: number | { id: number }, posting: string | number | { id: string | number } } | [requisition: number | { id: number }, posting: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updatePosting.url(args, options),
    method: 'put',
})

updatePosting.definition = {
    methods: ["put"],
    url: '/recruitment/{requisition}/postings/{posting}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\RequisitionController::updatePosting
 * @see app/Http/Controllers/RequisitionController.php:280
 * @route '/recruitment/{requisition}/postings/{posting}'
 */
updatePosting.url = (args: { requisition: number | { id: number }, posting: string | number | { id: string | number } } | [requisition: number | { id: number }, posting: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    requisition: args[0],
                    posting: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        requisition: typeof args.requisition === 'object'
                ? args.requisition.id
                : args.requisition,
                                posting: typeof args.posting === 'object'
                ? args.posting.id
                : args.posting,
                }

    return updatePosting.definition.url
            .replace('{requisition}', parsedArgs.requisition.toString())
            .replace('{posting}', parsedArgs.posting.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RequisitionController::updatePosting
 * @see app/Http/Controllers/RequisitionController.php:280
 * @route '/recruitment/{requisition}/postings/{posting}'
 */
updatePosting.put = (args: { requisition: number | { id: number }, posting: string | number | { id: string | number } } | [requisition: number | { id: number }, posting: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updatePosting.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\RequisitionController::destroyPosting
 * @see app/Http/Controllers/RequisitionController.php:289
 * @route '/recruitment/{requisition}/postings/{posting}'
 */
export const destroyPosting = (args: { requisition: number | { id: number }, posting: string | number | { id: string | number } } | [requisition: number | { id: number }, posting: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroyPosting.url(args, options),
    method: 'delete',
})

destroyPosting.definition = {
    methods: ["delete"],
    url: '/recruitment/{requisition}/postings/{posting}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\RequisitionController::destroyPosting
 * @see app/Http/Controllers/RequisitionController.php:289
 * @route '/recruitment/{requisition}/postings/{posting}'
 */
destroyPosting.url = (args: { requisition: number | { id: number }, posting: string | number | { id: string | number } } | [requisition: number | { id: number }, posting: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    requisition: args[0],
                    posting: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        requisition: typeof args.requisition === 'object'
                ? args.requisition.id
                : args.requisition,
                                posting: typeof args.posting === 'object'
                ? args.posting.id
                : args.posting,
                }

    return destroyPosting.definition.url
            .replace('{requisition}', parsedArgs.requisition.toString())
            .replace('{posting}', parsedArgs.posting.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RequisitionController::destroyPosting
 * @see app/Http/Controllers/RequisitionController.php:289
 * @route '/recruitment/{requisition}/postings/{posting}'
 */
destroyPosting.delete = (args: { requisition: number | { id: number }, posting: string | number | { id: string | number } } | [requisition: number | { id: number }, posting: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroyPosting.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\RequisitionController::advanceToScreening
 * @see app/Http/Controllers/RequisitionController.php:303
 * @route '/recruitment/{requisition}/screening'
 */
export const advanceToScreening = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: advanceToScreening.url(args, options),
    method: 'post',
})

advanceToScreening.definition = {
    methods: ["post"],
    url: '/recruitment/{requisition}/screening',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RequisitionController::advanceToScreening
 * @see app/Http/Controllers/RequisitionController.php:303
 * @route '/recruitment/{requisition}/screening'
 */
advanceToScreening.url = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return advanceToScreening.definition.url
            .replace('{requisition}', parsedArgs.requisition.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RequisitionController::advanceToScreening
 * @see app/Http/Controllers/RequisitionController.php:303
 * @route '/recruitment/{requisition}/screening'
 */
advanceToScreening.post = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: advanceToScreening.url(args, options),
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

/**
* @see \App\Http\Controllers\RequisitionController::comment
 * @see app/Http/Controllers/RequisitionController.php:337
 * @route '/recruitment/{requisition}/comments'
 */
export const comment = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: comment.url(args, options),
    method: 'post',
})

comment.definition = {
    methods: ["post"],
    url: '/recruitment/{requisition}/comments',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RequisitionController::comment
 * @see app/Http/Controllers/RequisitionController.php:337
 * @route '/recruitment/{requisition}/comments'
 */
comment.url = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return comment.definition.url
            .replace('{requisition}', parsedArgs.requisition.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RequisitionController::comment
 * @see app/Http/Controllers/RequisitionController.php:337
 * @route '/recruitment/{requisition}/comments'
 */
comment.post = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: comment.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\RequisitionController::markCommentsRead
 * @see app/Http/Controllers/RequisitionController.php:353
 * @route '/recruitment/{requisition}/comments/read'
 */
export const markCommentsRead = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: markCommentsRead.url(args, options),
    method: 'post',
})

markCommentsRead.definition = {
    methods: ["post"],
    url: '/recruitment/{requisition}/comments/read',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RequisitionController::markCommentsRead
 * @see app/Http/Controllers/RequisitionController.php:353
 * @route '/recruitment/{requisition}/comments/read'
 */
markCommentsRead.url = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return markCommentsRead.definition.url
            .replace('{requisition}', parsedArgs.requisition.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RequisitionController::markCommentsRead
 * @see app/Http/Controllers/RequisitionController.php:353
 * @route '/recruitment/{requisition}/comments/read'
 */
markCommentsRead.post = (args: { requisition: number | { id: number } } | [requisition: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: markCommentsRead.url(args, options),
    method: 'post',
})
const RequisitionController = { index, create, store, show, edit, update, destroy, decide, salary, advance, storePosting, updatePosting, destroyPosting, advanceToScreening, cancel, comment, markCommentsRead }

export default RequisitionController