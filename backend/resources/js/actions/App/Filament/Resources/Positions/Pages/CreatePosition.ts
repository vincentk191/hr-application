import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Filament\Resources\Positions\Pages\CreatePosition::__invoke
 * @see app/Filament/Resources/Positions/Pages/CreatePosition.php:7
 * @route '/admin/positions/create'
 */
const CreatePosition = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: CreatePosition.url(options),
    method: 'get',
})

CreatePosition.definition = {
    methods: ["get","head"],
    url: '/admin/positions/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Resources\Positions\Pages\CreatePosition::__invoke
 * @see app/Filament/Resources/Positions/Pages/CreatePosition.php:7
 * @route '/admin/positions/create'
 */
CreatePosition.url = (options?: RouteQueryOptions) => {
    return CreatePosition.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Resources\Positions\Pages\CreatePosition::__invoke
 * @see app/Filament/Resources/Positions/Pages/CreatePosition.php:7
 * @route '/admin/positions/create'
 */
CreatePosition.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: CreatePosition.url(options),
    method: 'get',
})
/**
* @see \App\Filament\Resources\Positions\Pages\CreatePosition::__invoke
 * @see app/Filament/Resources/Positions/Pages/CreatePosition.php:7
 * @route '/admin/positions/create'
 */
CreatePosition.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: CreatePosition.url(options),
    method: 'head',
})
export default CreatePosition