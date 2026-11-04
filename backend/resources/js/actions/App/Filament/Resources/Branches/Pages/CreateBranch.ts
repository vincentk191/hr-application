import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Filament\Resources\Branches\Pages\CreateBranch::__invoke
 * @see app/Filament/Resources/Branches/Pages/CreateBranch.php:7
 * @route '/admin/branches/create'
 */
const CreateBranch = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: CreateBranch.url(options),
    method: 'get',
})

CreateBranch.definition = {
    methods: ["get","head"],
    url: '/admin/branches/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Resources\Branches\Pages\CreateBranch::__invoke
 * @see app/Filament/Resources/Branches/Pages/CreateBranch.php:7
 * @route '/admin/branches/create'
 */
CreateBranch.url = (options?: RouteQueryOptions) => {
    return CreateBranch.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Resources\Branches\Pages\CreateBranch::__invoke
 * @see app/Filament/Resources/Branches/Pages/CreateBranch.php:7
 * @route '/admin/branches/create'
 */
CreateBranch.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: CreateBranch.url(options),
    method: 'get',
})
/**
* @see \App\Filament\Resources\Branches\Pages\CreateBranch::__invoke
 * @see app/Filament/Resources/Branches/Pages/CreateBranch.php:7
 * @route '/admin/branches/create'
 */
CreateBranch.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: CreateBranch.url(options),
    method: 'head',
})
export default CreateBranch