import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Filament\Resources\Departments\Pages\EditDepartment::__invoke
 * @see app/Filament/Resources/Departments/Pages/EditDepartment.php:7
 * @route '/admin/departments/{record}/edit'
 */
const EditDepartment = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: EditDepartment.url(args, options),
    method: 'get',
})

EditDepartment.definition = {
    methods: ["get","head"],
    url: '/admin/departments/{record}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Resources\Departments\Pages\EditDepartment::__invoke
 * @see app/Filament/Resources/Departments/Pages/EditDepartment.php:7
 * @route '/admin/departments/{record}/edit'
 */
EditDepartment.url = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { record: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    record: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        record: args.record,
                }

    return EditDepartment.definition.url
            .replace('{record}', parsedArgs.record.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Filament\Resources\Departments\Pages\EditDepartment::__invoke
 * @see app/Filament/Resources/Departments/Pages/EditDepartment.php:7
 * @route '/admin/departments/{record}/edit'
 */
EditDepartment.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: EditDepartment.url(args, options),
    method: 'get',
})
/**
* @see \App\Filament\Resources\Departments\Pages\EditDepartment::__invoke
 * @see app/Filament/Resources/Departments/Pages/EditDepartment.php:7
 * @route '/admin/departments/{record}/edit'
 */
EditDepartment.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: EditDepartment.url(args, options),
    method: 'head',
})
export default EditDepartment