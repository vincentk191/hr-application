import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\NotificationController::index
 * @see app/Http/Controllers/NotificationController.php:19
 * @route '/notifications'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/notifications',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\NotificationController::index
 * @see app/Http/Controllers/NotificationController.php:19
 * @route '/notifications'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\NotificationController::index
 * @see app/Http/Controllers/NotificationController.php:19
 * @route '/notifications'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\NotificationController::index
 * @see app/Http/Controllers/NotificationController.php:19
 * @route '/notifications'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\NotificationController::readAll
 * @see app/Http/Controllers/NotificationController.php:54
 * @route '/notifications/read-all'
 */
export const readAll = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: readAll.url(options),
    method: 'post',
})

readAll.definition = {
    methods: ["post"],
    url: '/notifications/read-all',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\NotificationController::readAll
 * @see app/Http/Controllers/NotificationController.php:54
 * @route '/notifications/read-all'
 */
readAll.url = (options?: RouteQueryOptions) => {
    return readAll.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\NotificationController::readAll
 * @see app/Http/Controllers/NotificationController.php:54
 * @route '/notifications/read-all'
 */
readAll.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: readAll.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\NotificationController::open
 * @see app/Http/Controllers/NotificationController.php:43
 * @route '/notifications/{notification}'
 */
export const open = (args: { notification: string | number } | [notification: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: open.url(args, options),
    method: 'get',
})

open.definition = {
    methods: ["get","head"],
    url: '/notifications/{notification}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\NotificationController::open
 * @see app/Http/Controllers/NotificationController.php:43
 * @route '/notifications/{notification}'
 */
open.url = (args: { notification: string | number } | [notification: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { notification: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    notification: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        notification: args.notification,
                }

    return open.definition.url
            .replace('{notification}', parsedArgs.notification.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\NotificationController::open
 * @see app/Http/Controllers/NotificationController.php:43
 * @route '/notifications/{notification}'
 */
open.get = (args: { notification: string | number } | [notification: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: open.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\NotificationController::open
 * @see app/Http/Controllers/NotificationController.php:43
 * @route '/notifications/{notification}'
 */
open.head = (args: { notification: string | number } | [notification: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: open.url(args, options),
    method: 'head',
})
const notifications = {
    index: Object.assign(index, index),
readAll: Object.assign(readAll, readAll),
open: Object.assign(open, open),
}

export default notifications