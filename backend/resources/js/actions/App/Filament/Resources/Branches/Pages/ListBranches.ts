import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Filament\Resources\Branches\Pages\ListBranches::__invoke
 * @see app/Filament/Resources/Branches/Pages/ListBranches.php:7
 * @route '/admin/branches'
 */
const ListBranches = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ListBranches.url(options),
    method: 'get',
})

ListBranches.definition = {
    methods: ["get","head"],
    url: '/admin/branches',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Resources\Branches\Pages\ListBranches::__invoke
 * @see app/Filament/Resources/Branches/Pages/ListBranches.php:7
 * @route '/admin/branches'
 */
ListBranches.url = (options?: RouteQueryOptions) => {
    return ListBranches.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Resources\Branches\Pages\ListBranches::__invoke
 * @see app/Filament/Resources/Branches/Pages/ListBranches.php:7
 * @route '/admin/branches'
 */
ListBranches.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ListBranches.url(options),
    method: 'get',
})
/**
* @see \App\Filament\Resources\Branches\Pages\ListBranches::__invoke
 * @see app/Filament/Resources/Branches/Pages/ListBranches.php:7
 * @route '/admin/branches'
 */
ListBranches.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ListBranches.url(options),
    method: 'head',
})
export default ListBranches