import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\OffboardingCaseController::index
 * @see app/Http/Controllers/OffboardingCaseController.php:48
 * @route '/offboarding'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/offboarding',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\OffboardingCaseController::index
 * @see app/Http/Controllers/OffboardingCaseController.php:48
 * @route '/offboarding'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingCaseController::index
 * @see app/Http/Controllers/OffboardingCaseController.php:48
 * @route '/offboarding'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\OffboardingCaseController::index
 * @see app/Http/Controllers/OffboardingCaseController.php:48
 * @route '/offboarding'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\OffboardingCaseController::create
 * @see app/Http/Controllers/OffboardingCaseController.php:146
 * @route '/offboarding/create'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/offboarding/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\OffboardingCaseController::create
 * @see app/Http/Controllers/OffboardingCaseController.php:146
 * @route '/offboarding/create'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingCaseController::create
 * @see app/Http/Controllers/OffboardingCaseController.php:146
 * @route '/offboarding/create'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\OffboardingCaseController::create
 * @see app/Http/Controllers/OffboardingCaseController.php:146
 * @route '/offboarding/create'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\OffboardingCaseController::store
 * @see app/Http/Controllers/OffboardingCaseController.php:156
 * @route '/offboarding'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/offboarding',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OffboardingCaseController::store
 * @see app/Http/Controllers/OffboardingCaseController.php:156
 * @route '/offboarding'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingCaseController::store
 * @see app/Http/Controllers/OffboardingCaseController.php:156
 * @route '/offboarding'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OffboardingCaseController::downloadFile
 * @see app/Http/Controllers/OffboardingCaseController.php:356
 * @route '/offboarding/files/{file}'
 */
export const downloadFile = (args: { file: number | { id: number } } | [file: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: downloadFile.url(args, options),
    method: 'get',
})

downloadFile.definition = {
    methods: ["get","head"],
    url: '/offboarding/files/{file}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\OffboardingCaseController::downloadFile
 * @see app/Http/Controllers/OffboardingCaseController.php:356
 * @route '/offboarding/files/{file}'
 */
downloadFile.url = (args: { file: number | { id: number } } | [file: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return downloadFile.definition.url
            .replace('{file}', parsedArgs.file.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingCaseController::downloadFile
 * @see app/Http/Controllers/OffboardingCaseController.php:356
 * @route '/offboarding/files/{file}'
 */
downloadFile.get = (args: { file: number | { id: number } } | [file: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: downloadFile.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\OffboardingCaseController::downloadFile
 * @see app/Http/Controllers/OffboardingCaseController.php:356
 * @route '/offboarding/files/{file}'
 */
downloadFile.head = (args: { file: number | { id: number } } | [file: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: downloadFile.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\OffboardingCaseController::show
 * @see app/Http/Controllers/OffboardingCaseController.php:255
 * @route '/offboarding/{offboardingCase}'
 */
export const show = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/offboarding/{offboardingCase}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\OffboardingCaseController::show
 * @see app/Http/Controllers/OffboardingCaseController.php:255
 * @route '/offboarding/{offboardingCase}'
 */
show.url = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{offboardingCase}', parsedArgs.offboardingCase.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingCaseController::show
 * @see app/Http/Controllers/OffboardingCaseController.php:255
 * @route '/offboarding/{offboardingCase}'
 */
show.get = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\OffboardingCaseController::show
 * @see app/Http/Controllers/OffboardingCaseController.php:255
 * @route '/offboarding/{offboardingCase}'
 */
show.head = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\OffboardingCaseController::edit
 * @see app/Http/Controllers/OffboardingCaseController.php:189
 * @route '/offboarding/{offboardingCase}/edit'
 */
export const edit = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/offboarding/{offboardingCase}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\OffboardingCaseController::edit
 * @see app/Http/Controllers/OffboardingCaseController.php:189
 * @route '/offboarding/{offboardingCase}/edit'
 */
edit.url = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return edit.definition.url
            .replace('{offboardingCase}', parsedArgs.offboardingCase.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingCaseController::edit
 * @see app/Http/Controllers/OffboardingCaseController.php:189
 * @route '/offboarding/{offboardingCase}/edit'
 */
edit.get = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\OffboardingCaseController::edit
 * @see app/Http/Controllers/OffboardingCaseController.php:189
 * @route '/offboarding/{offboardingCase}/edit'
 */
edit.head = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\OffboardingCaseController::update
 * @see app/Http/Controllers/OffboardingCaseController.php:205
 * @route '/offboarding/{offboardingCase}'
 */
export const update = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/offboarding/{offboardingCase}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\OffboardingCaseController::update
 * @see app/Http/Controllers/OffboardingCaseController.php:205
 * @route '/offboarding/{offboardingCase}'
 */
update.url = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return update.definition.url
            .replace('{offboardingCase}', parsedArgs.offboardingCase.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingCaseController::update
 * @see app/Http/Controllers/OffboardingCaseController.php:205
 * @route '/offboarding/{offboardingCase}'
 */
update.put = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\OffboardingCaseController::destroy
 * @see app/Http/Controllers/OffboardingCaseController.php:225
 * @route '/offboarding/{offboardingCase}'
 */
export const destroy = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/offboarding/{offboardingCase}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\OffboardingCaseController::destroy
 * @see app/Http/Controllers/OffboardingCaseController.php:225
 * @route '/offboarding/{offboardingCase}'
 */
destroy.url = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return destroy.definition.url
            .replace('{offboardingCase}', parsedArgs.offboardingCase.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingCaseController::destroy
 * @see app/Http/Controllers/OffboardingCaseController.php:225
 * @route '/offboarding/{offboardingCase}'
 */
destroy.delete = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\OffboardingCaseController::submit
 * @see app/Http/Controllers/OffboardingCaseController.php:241
 * @route '/offboarding/{offboardingCase}/submit'
 */
export const submit = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: submit.url(args, options),
    method: 'post',
})

submit.definition = {
    methods: ["post"],
    url: '/offboarding/{offboardingCase}/submit',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OffboardingCaseController::submit
 * @see app/Http/Controllers/OffboardingCaseController.php:241
 * @route '/offboarding/{offboardingCase}/submit'
 */
submit.url = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return submit.definition.url
            .replace('{offboardingCase}', parsedArgs.offboardingCase.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingCaseController::submit
 * @see app/Http/Controllers/OffboardingCaseController.php:241
 * @route '/offboarding/{offboardingCase}/submit'
 */
submit.post = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: submit.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OffboardingCaseController::decide
 * @see app/Http/Controllers/OffboardingCaseController.php:277
 * @route '/offboarding/{offboardingCase}/decision'
 */
export const decide = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: decide.url(args, options),
    method: 'put',
})

decide.definition = {
    methods: ["put"],
    url: '/offboarding/{offboardingCase}/decision',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\OffboardingCaseController::decide
 * @see app/Http/Controllers/OffboardingCaseController.php:277
 * @route '/offboarding/{offboardingCase}/decision'
 */
decide.url = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return decide.definition.url
            .replace('{offboardingCase}', parsedArgs.offboardingCase.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingCaseController::decide
 * @see app/Http/Controllers/OffboardingCaseController.php:277
 * @route '/offboarding/{offboardingCase}/decision'
 */
decide.put = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: decide.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\OffboardingCaseController::comment
 * @see app/Http/Controllers/OffboardingCaseController.php:295
 * @route '/offboarding/{offboardingCase}/comments'
 */
export const comment = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: comment.url(args, options),
    method: 'post',
})

comment.definition = {
    methods: ["post"],
    url: '/offboarding/{offboardingCase}/comments',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OffboardingCaseController::comment
 * @see app/Http/Controllers/OffboardingCaseController.php:295
 * @route '/offboarding/{offboardingCase}/comments'
 */
comment.url = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return comment.definition.url
            .replace('{offboardingCase}', parsedArgs.offboardingCase.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingCaseController::comment
 * @see app/Http/Controllers/OffboardingCaseController.php:295
 * @route '/offboarding/{offboardingCase}/comments'
 */
comment.post = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: comment.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OffboardingCaseController::markCommentsRead
 * @see app/Http/Controllers/OffboardingCaseController.php:311
 * @route '/offboarding/{offboardingCase}/comments/read'
 */
export const markCommentsRead = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: markCommentsRead.url(args, options),
    method: 'post',
})

markCommentsRead.definition = {
    methods: ["post"],
    url: '/offboarding/{offboardingCase}/comments/read',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OffboardingCaseController::markCommentsRead
 * @see app/Http/Controllers/OffboardingCaseController.php:311
 * @route '/offboarding/{offboardingCase}/comments/read'
 */
markCommentsRead.url = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return markCommentsRead.definition.url
            .replace('{offboardingCase}', parsedArgs.offboardingCase.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingCaseController::markCommentsRead
 * @see app/Http/Controllers/OffboardingCaseController.php:311
 * @route '/offboarding/{offboardingCase}/comments/read'
 */
markCommentsRead.post = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: markCommentsRead.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OffboardingCaseController::addFiles
 * @see app/Http/Controllers/OffboardingCaseController.php:345
 * @route '/offboarding/{offboardingCase}/files'
 */
export const addFiles = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: addFiles.url(args, options),
    method: 'post',
})

addFiles.definition = {
    methods: ["post"],
    url: '/offboarding/{offboardingCase}/files',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OffboardingCaseController::addFiles
 * @see app/Http/Controllers/OffboardingCaseController.php:345
 * @route '/offboarding/{offboardingCase}/files'
 */
addFiles.url = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return addFiles.definition.url
            .replace('{offboardingCase}', parsedArgs.offboardingCase.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingCaseController::addFiles
 * @see app/Http/Controllers/OffboardingCaseController.php:345
 * @route '/offboarding/{offboardingCase}/files'
 */
addFiles.post = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: addFiles.url(args, options),
    method: 'post',
})
const OffboardingCaseController = { index, create, store, downloadFile, show, edit, update, destroy, submit, decide, comment, markCommentsRead, addFiles }

export default OffboardingCaseController