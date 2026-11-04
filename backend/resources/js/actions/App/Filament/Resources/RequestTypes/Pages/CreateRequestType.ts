import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Filament\Resources\RequestTypes\Pages\CreateRequestType::__invoke
 * @see app/Filament/Resources/RequestTypes/Pages/CreateRequestType.php:7
 * @route '/admin/request-types/create'
 */
const CreateRequestType = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: CreateRequestType.url(options),
    method: 'get',
})

CreateRequestType.definition = {
    methods: ["get","head"],
    url: '/admin/request-types/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Resources\RequestTypes\Pages\CreateRequestType::__invoke
 * @see app/Filament/Resources/RequestTypes/Pages/CreateRequestType.php:7
 * @route '/admin/request-types/create'
 */
CreateRequestType.url = (options?: RouteQueryOptions) => {
    return CreateRequestType.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Resources\RequestTypes\Pages\CreateRequestType::__invoke
 * @see app/Filament/Resources/RequestTypes/Pages/CreateRequestType.php:7
 * @route '/admin/request-types/create'
 */
CreateRequestType.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: CreateRequestType.url(options),
    method: 'get',
})
/**
* @see \App\Filament\Resources\RequestTypes\Pages\CreateRequestType::__invoke
 * @see app/Filament/Resources/RequestTypes/Pages/CreateRequestType.php:7
 * @route '/admin/request-types/create'
 */
CreateRequestType.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: CreateRequestType.url(options),
    method: 'head',
})
export default CreateRequestType