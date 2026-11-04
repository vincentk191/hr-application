import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\CalendarEventController::store
 * @see app/Http/Controllers/CalendarEventController.php:21
 * @route '/calendar-events'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/calendar-events',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CalendarEventController::store
 * @see app/Http/Controllers/CalendarEventController.php:21
 * @route '/calendar-events'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CalendarEventController::store
 * @see app/Http/Controllers/CalendarEventController.php:21
 * @route '/calendar-events'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\CalendarEventController::destroy
 * @see app/Http/Controllers/CalendarEventController.php:56
 * @route '/calendar-events/{calendarEvent}'
 */
export const destroy = (args: { calendarEvent: number | { id: number } } | [calendarEvent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/calendar-events/{calendarEvent}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\CalendarEventController::destroy
 * @see app/Http/Controllers/CalendarEventController.php:56
 * @route '/calendar-events/{calendarEvent}'
 */
destroy.url = (args: { calendarEvent: number | { id: number } } | [calendarEvent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { calendarEvent: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { calendarEvent: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    calendarEvent: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        calendarEvent: typeof args.calendarEvent === 'object'
                ? args.calendarEvent.id
                : args.calendarEvent,
                }

    return destroy.definition.url
            .replace('{calendarEvent}', parsedArgs.calendarEvent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CalendarEventController::destroy
 * @see app/Http/Controllers/CalendarEventController.php:56
 * @route '/calendar-events/{calendarEvent}'
 */
destroy.delete = (args: { calendarEvent: number | { id: number } } | [calendarEvent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})
const calendarEvents = {
    store: Object.assign(store, store),
destroy: Object.assign(destroy, destroy),
}

export default calendarEvents