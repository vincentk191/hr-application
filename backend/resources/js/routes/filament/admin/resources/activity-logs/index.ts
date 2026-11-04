import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs::__invoke
 * @see app/Filament/Resources/ActivityLogs/Pages/ListActivityLogs.php:7
 * @route '/admin/activity-logs'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/activity-logs',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs::__invoke
 * @see app/Filament/Resources/ActivityLogs/Pages/ListActivityLogs.php:7
 * @route '/admin/activity-logs'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs::__invoke
 * @see app/Filament/Resources/ActivityLogs/Pages/ListActivityLogs.php:7
 * @route '/admin/activity-logs'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs::__invoke
 * @see app/Filament/Resources/ActivityLogs/Pages/ListActivityLogs.php:7
 * @route '/admin/activity-logs'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})
const activityLogs = {
    index: Object.assign(index, index),
}

export default activityLogs