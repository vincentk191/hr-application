import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Filament\Resources\Positions\Pages\ListPositions::__invoke
 * @see app/Filament/Resources/Positions/Pages/ListPositions.php:7
 * @route '/admin/positions'
 */
const ListPositions = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ListPositions.url(options),
    method: 'get',
})

ListPositions.definition = {
    methods: ["get","head"],
    url: '/admin/positions',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Resources\Positions\Pages\ListPositions::__invoke
 * @see app/Filament/Resources/Positions/Pages/ListPositions.php:7
 * @route '/admin/positions'
 */
ListPositions.url = (options?: RouteQueryOptions) => {
    return ListPositions.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Resources\Positions\Pages\ListPositions::__invoke
 * @see app/Filament/Resources/Positions/Pages/ListPositions.php:7
 * @route '/admin/positions'
 */
ListPositions.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ListPositions.url(options),
    method: 'get',
})
/**
* @see \App\Filament\Resources\Positions\Pages\ListPositions::__invoke
 * @see app/Filament/Resources/Positions/Pages/ListPositions.php:7
 * @route '/admin/positions'
 */
ListPositions.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ListPositions.url(options),
    method: 'head',
})
export default ListPositions