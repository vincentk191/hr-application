import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Filament\Resources\Departments\Pages\ListDepartments::__invoke
 * @see app/Filament/Resources/Departments/Pages/ListDepartments.php:7
 * @route '/admin/departments'
 */
const ListDepartments = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ListDepartments.url(options),
    method: 'get',
})

ListDepartments.definition = {
    methods: ["get","head"],
    url: '/admin/departments',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Resources\Departments\Pages\ListDepartments::__invoke
 * @see app/Filament/Resources/Departments/Pages/ListDepartments.php:7
 * @route '/admin/departments'
 */
ListDepartments.url = (options?: RouteQueryOptions) => {
    return ListDepartments.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Resources\Departments\Pages\ListDepartments::__invoke
 * @see app/Filament/Resources/Departments/Pages/ListDepartments.php:7
 * @route '/admin/departments'
 */
ListDepartments.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ListDepartments.url(options),
    method: 'get',
})
/**
* @see \App\Filament\Resources\Departments\Pages\ListDepartments::__invoke
 * @see app/Filament/Resources/Departments/Pages/ListDepartments.php:7
 * @route '/admin/departments'
 */
ListDepartments.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ListDepartments.url(options),
    method: 'head',
})
export default ListDepartments