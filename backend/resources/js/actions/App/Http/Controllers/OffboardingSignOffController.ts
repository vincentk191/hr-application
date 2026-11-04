import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
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
* @see \App\Http\Controllers\OffboardingSignOffController::reassign
 * @see app/Http/Controllers/OffboardingSignOffController.php:104
 * @route '/offboarding/{offboardingCase}/reports'
 */
export const reassign = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reassign.url(args, options),
    method: 'post',
})

reassign.definition = {
    methods: ["post"],
    url: '/offboarding/{offboardingCase}/reports',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OffboardingSignOffController::reassign
 * @see app/Http/Controllers/OffboardingSignOffController.php:104
 * @route '/offboarding/{offboardingCase}/reports'
 */
reassign.url = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return reassign.definition.url
            .replace('{offboardingCase}', parsedArgs.offboardingCase.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OffboardingSignOffController::reassign
 * @see app/Http/Controllers/OffboardingSignOffController.php:104
 * @route '/offboarding/{offboardingCase}/reports'
 */
reassign.post = (args: { offboardingCase: number | { id: number } } | [offboardingCase: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reassign.url(args, options),
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
const OffboardingSignOffController = { sendBack, interview, lastDay, cancel, close, reassign, beritaAcara }

export default OffboardingSignOffController