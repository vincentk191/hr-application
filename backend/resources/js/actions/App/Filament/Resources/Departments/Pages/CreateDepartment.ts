import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Filament\Resources\Departments\Pages\CreateDepartment::__invoke
 * @see app/Filament/Resources/Departments/Pages/CreateDepartment.php:7
 * @route '/admin/departments/create'
 */
const CreateDepartment = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: CreateDepartment.url(options),
    method: 'get',
})

CreateDepartment.definition = {
    methods: ["get","head"],
    url: '/admin/departments/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Resources\Departments\Pages\CreateDepartment::__invoke
 * @see app/Filament/Resources/Departments/Pages/CreateDepartment.php:7
 * @route '/admin/departments/create'
 */
CreateDepartment.url = (options?: RouteQueryOptions) => {
    return CreateDepartment.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Resources\Departments\Pages\CreateDepartment::__invoke
 * @see app/Filament/Resources/Departments/Pages/CreateDepartment.php:7
 * @route '/admin/departments/create'
 */
CreateDepartment.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: CreateDepartment.url(options),
    method: 'get',
})
/**
* @see \App\Filament\Resources\Departments\Pages\CreateDepartment::__invoke
 * @see app/Filament/Resources/Departments/Pages/CreateDepartment.php:7
 * @route '/admin/departments/create'
 */
CreateDepartment.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: CreateDepartment.url(options),
    method: 'head',
})
export default CreateDepartment