import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Filament\Resources\RequestTypes\Pages\ListRequestTypes::__invoke
 * @see app/Filament/Resources/RequestTypes/Pages/ListRequestTypes.php:7
 * @route '/admin/request-types'
 */
const ListRequestTypes = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ListRequestTypes.url(options),
    method: 'get',
})

ListRequestTypes.definition = {
    methods: ["get","head"],
    url: '/admin/request-types',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Resources\RequestTypes\Pages\ListRequestTypes::__invoke
 * @see app/Filament/Resources/RequestTypes/Pages/ListRequestTypes.php:7
 * @route '/admin/request-types'
 */
ListRequestTypes.url = (options?: RouteQueryOptions) => {
    return ListRequestTypes.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Resources\RequestTypes\Pages\ListRequestTypes::__invoke
 * @see app/Filament/Resources/RequestTypes/Pages/ListRequestTypes.php:7
 * @route '/admin/request-types'
 */
ListRequestTypes.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ListRequestTypes.url(options),
    method: 'get',
})
/**
* @see \App\Filament\Resources\RequestTypes\Pages\ListRequestTypes::__invoke
 * @see app/Filament/Resources/RequestTypes/Pages/ListRequestTypes.php:7
 * @route '/admin/request-types'
 */
ListRequestTypes.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ListRequestTypes.url(options),
    method: 'head',
})
export default ListRequestTypes