import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Filament\Resources\OffboardingItems\Pages\ListOffboardingItems::__invoke
 * @see app/Filament/Resources/OffboardingItems/Pages/ListOffboardingItems.php:7
 * @route '/admin/offboarding-items'
 */
const ListOffboardingItems = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ListOffboardingItems.url(options),
    method: 'get',
})

ListOffboardingItems.definition = {
    methods: ["get","head"],
    url: '/admin/offboarding-items',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Resources\OffboardingItems\Pages\ListOffboardingItems::__invoke
 * @see app/Filament/Resources/OffboardingItems/Pages/ListOffboardingItems.php:7
 * @route '/admin/offboarding-items'
 */
ListOffboardingItems.url = (options?: RouteQueryOptions) => {
    return ListOffboardingItems.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Resources\OffboardingItems\Pages\ListOffboardingItems::__invoke
 * @see app/Filament/Resources/OffboardingItems/Pages/ListOffboardingItems.php:7
 * @route '/admin/offboarding-items'
 */
ListOffboardingItems.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ListOffboardingItems.url(options),
    method: 'get',
})
/**
* @see \App\Filament\Resources\OffboardingItems\Pages\ListOffboardingItems::__invoke
 * @see app/Filament/Resources/OffboardingItems/Pages/ListOffboardingItems.php:7
 * @route '/admin/offboarding-items'
 */
ListOffboardingItems.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ListOffboardingItems.url(options),
    method: 'head',
})
export default ListOffboardingItems