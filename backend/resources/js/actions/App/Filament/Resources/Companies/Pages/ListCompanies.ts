import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Filament\Resources\Companies\Pages\ListCompanies::__invoke
 * @see app/Filament/Resources/Companies/Pages/ListCompanies.php:7
 * @route '/admin/companies'
 */
const ListCompanies = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ListCompanies.url(options),
    method: 'get',
})

ListCompanies.definition = {
    methods: ["get","head"],
    url: '/admin/companies',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Resources\Companies\Pages\ListCompanies::__invoke
 * @see app/Filament/Resources/Companies/Pages/ListCompanies.php:7
 * @route '/admin/companies'
 */
ListCompanies.url = (options?: RouteQueryOptions) => {
    return ListCompanies.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Resources\Companies\Pages\ListCompanies::__invoke
 * @see app/Filament/Resources/Companies/Pages/ListCompanies.php:7
 * @route '/admin/companies'
 */
ListCompanies.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ListCompanies.url(options),
    method: 'get',
})
/**
* @see \App\Filament\Resources\Companies\Pages\ListCompanies::__invoke
 * @see app/Filament/Resources/Companies/Pages/ListCompanies.php:7
 * @route '/admin/companies'
 */
ListCompanies.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ListCompanies.url(options),
    method: 'head',
})
export default ListCompanies