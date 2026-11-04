import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
import files from './files'
import checklist from './checklist'
import comments from './comments'
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
* @see \App\Http\Controllers\OffboardingChecklistController::proceed
 * @see app/Http/Controllers/OffboardingChecklistController.php:183
 * @route '/offboarding/{offboardingCase}/proceed'
 */
export const proceed = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: proceed.url(args, options),
    method: 'post',
})

proceed.definition = {
    methods: ["post"],
    url: '/offboarding/{offboardingCase}/proceed',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OffboardingChecklistController::proceed
 * @see app/Http/Controllers/OffboardingChecklistController.php:183
 * @route '/offboarding/{offboardingCase}/proceed'
 */
proceed.url = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return proceed.definition.url
            .replace('{offboardingCase}', parsedArgs.offboardingCase.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingChecklistController::proceed
 * @see app/Http/Controllers/OffboardingChecklistController.php:183
 * @route '/offboarding/{offboardingCase}/proceed'
 */
proceed.post = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: proceed.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OffboardingSignOffController::sendBack
 * @see app/Http/Controllers/OffboardingSignOffController.php:27
 * @route '/offboarding/{offboardingCase}/send-back'
 */
export const sendBack = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: sendBack.url(args, options),
    method: 'post',
})

sendBack.definition = {
    methods: ["post"],
    url: '/offboarding/{offboardingCase}/send-back',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OffboardingSignOffController::sendBack
 * @see app/Http/Controllers/OffboardingSignOffController.php:27
 * @route '/offboarding/{offboardingCase}/send-back'
 */
sendBack.url = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return sendBack.definition.url
            .replace('{offboardingCase}', parsedArgs.offboardingCase.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingSignOffController::sendBack
 * @see app/Http/Controllers/OffboardingSignOffController.php:27
 * @route '/offboarding/{offboardingCase}/send-back'
 */
sendBack.post = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: sendBack.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OffboardingSignOffController::interview
 * @see app/Http/Controllers/OffboardingSignOffController.php:40
 * @route '/offboarding/{offboardingCase}/exit-interview'
 */
export const interview = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: interview.url(args, options),
    method: 'put',
})

interview.definition = {
    methods: ["put"],
    url: '/offboarding/{offboardingCase}/exit-interview',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\OffboardingSignOffController::interview
 * @see app/Http/Controllers/OffboardingSignOffController.php:40
 * @route '/offboarding/{offboardingCase}/exit-interview'
 */
interview.url = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return interview.definition.url
            .replace('{offboardingCase}', parsedArgs.offboardingCase.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingSignOffController::interview
 * @see app/Http/Controllers/OffboardingSignOffController.php:40
 * @route '/offboarding/{offboardingCase}/exit-interview'
 */
interview.put = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: interview.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\OffboardingSignOffController::lastDay
 * @see app/Http/Controllers/OffboardingSignOffController.php:54
 * @route '/offboarding/{offboardingCase}/last-day'
 */
export const lastDay = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: lastDay.url(args, options),
    method: 'put',
})

lastDay.definition = {
    methods: ["put"],
    url: '/offboarding/{offboardingCase}/last-day',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\OffboardingSignOffController::lastDay
 * @see app/Http/Controllers/OffboardingSignOffController.php:54
 * @route '/offboarding/{offboardingCase}/last-day'
 */
lastDay.url = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return lastDay.definition.url
            .replace('{offboardingCase}', parsedArgs.offboardingCase.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingSignOffController::lastDay
 * @see app/Http/Controllers/OffboardingSignOffController.php:54
 * @route '/offboarding/{offboardingCase}/last-day'
 */
lastDay.put = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: lastDay.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\OffboardingSignOffController::cancel
 * @see app/Http/Controllers/OffboardingSignOffController.php:70
 * @route '/offboarding/{offboardingCase}/cancel'
 */
export const cancel = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cancel.url(args, options),
    method: 'post',
})

cancel.definition = {
    methods: ["post"],
    url: '/offboarding/{offboardingCase}/cancel',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OffboardingSignOffController::cancel
 * @see app/Http/Controllers/OffboardingSignOffController.php:70
 * @route '/offboarding/{offboardingCase}/cancel'
 */
cancel.url = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return cancel.definition.url
            .replace('{offboardingCase}', parsedArgs.offboardingCase.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingSignOffController::cancel
 * @see app/Http/Controllers/OffboardingSignOffController.php:70
 * @route '/offboarding/{offboardingCase}/cancel'
 */
cancel.post = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cancel.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OffboardingSignOffController::close
 * @see app/Http/Controllers/OffboardingSignOffController.php:86
 * @route '/offboarding/{offboardingCase}/close'
 */
export const close = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: close.url(args, options),
    method: 'post',
})

close.definition = {
    methods: ["post"],
    url: '/offboarding/{offboardingCase}/close',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OffboardingSignOffController::close
 * @see app/Http/Controllers/OffboardingSignOffController.php:86
 * @route '/offboarding/{offboardingCase}/close'
 */
close.url = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return close.definition.url
            .replace('{offboardingCase}', parsedArgs.offboardingCase.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingSignOffController::close
 * @see app/Http/Controllers/OffboardingSignOffController.php:86
 * @route '/offboarding/{offboardingCase}/close'
 */
close.post = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: close.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OffboardingSignOffController::reports
 * @see app/Http/Controllers/OffboardingSignOffController.php:104
 * @route '/offboarding/{offboardingCase}/reports'
 */
export const reports = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reports.url(args, options),
    method: 'post',
})

reports.definition = {
    methods: ["post"],
    url: '/offboarding/{offboardingCase}/reports',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OffboardingSignOffController::reports
 * @see app/Http/Controllers/OffboardingSignOffController.php:104
 * @route '/offboarding/{offboardingCase}/reports'
 */
reports.url = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return reports.definition.url
            .replace('{offboardingCase}', parsedArgs.offboardingCase.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingSignOffController::reports
 * @see app/Http/Controllers/OffboardingSignOffController.php:104
 * @route '/offboarding/{offboardingCase}/reports'
 */
reports.post = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reports.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OffboardingSignOffController::beritaAcara
 * @see app/Http/Controllers/OffboardingSignOffController.php:120
 * @route '/offboarding/{offboardingCase}/berita-acara'
 */
export const beritaAcara = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: beritaAcara.url(args, options),
    method: 'get',
})

beritaAcara.definition = {
    methods: ["get","head"],
    url: '/offboarding/{offboardingCase}/berita-acara',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\OffboardingSignOffController::beritaAcara
 * @see app/Http/Controllers/OffboardingSignOffController.php:120
 * @route '/offboarding/{offboardingCase}/berita-acara'
 */
beritaAcara.url = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return beritaAcara.definition.url
            .replace('{offboardingCase}', parsedArgs.offboardingCase.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingSignOffController::beritaAcara
 * @see app/Http/Controllers/OffboardingSignOffController.php:120
 * @route '/offboarding/{offboardingCase}/berita-acara'
 */
beritaAcara.get = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: beritaAcara.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\OffboardingSignOffController::beritaAcara
 * @see app/Http/Controllers/OffboardingSignOffController.php:120
 * @route '/offboarding/{offboardingCase}/berita-acara'
 */
beritaAcara.head = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: beritaAcara.url(args, options),
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
const offboarding = {
    index: Object.assign(index, index),
create: Object.assign(create, create),
store: Object.assign(store, store),
files: Object.assign(files, files),
checklist: Object.assign(checklist, checklist),
proceed: Object.assign(proceed, proceed),
sendBack: Object.assign(sendBack, sendBack),
interview: Object.assign(interview, interview),
lastDay: Object.assign(lastDay, lastDay),
cancel: Object.assign(cancel, cancel),
close: Object.assign(close, close),
reports: Object.assign(reports, reports),
beritaAcara: Object.assign(beritaAcara, beritaAcara),
show: Object.assign(show, show),
edit: Object.assign(edit, edit),
update: Object.assign(update, update),
destroy: Object.assign(destroy, destroy),
submit: Object.assign(submit, submit),
decide: Object.assign(decide, decide),
comments: Object.assign(comments, comments),
}

export default offboarding