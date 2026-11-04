import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Filament\Resources\Companies\Pages\CreateCompany::__invoke
 * @see app/Filament/Resources/Companies/Pages/CreateCompany.php:7
 * @route '/admin/companies/create'
 */
const CreateCompany = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: CreateCompany.url(options),
    method: 'get',
})

CreateCompany.definition = {
    methods: ["get","head"],
    url: '/admin/companies/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Resources\Companies\Pages\CreateCompany::__invoke
 * @see app/Filament/Resources/Companies/Pages/CreateCompany.php:7
 * @route '/admin/companies/create'
 */
CreateCompany.url = (options?: RouteQueryOptions) => {
    return CreateCompany.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Resources\Companies\Pages\CreateCompany::__invoke
 * @see app/Filament/Resources/Companies/Pages/CreateCompany.php:7
 * @route '/admin/companies/create'
 */
CreateCompany.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: CreateCompany.url(options),
    method: 'get',
})
/**
* @see \App\Filament\Resources\Companies\Pages\CreateCompany::__invoke
 * @see app/Filament/Resources/Companies/Pages/CreateCompany.php:7
 * @route '/admin/companies/create'
 */
CreateCompany.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: CreateCompany.url(options),
    method: 'head',
})
export default CreateCompany