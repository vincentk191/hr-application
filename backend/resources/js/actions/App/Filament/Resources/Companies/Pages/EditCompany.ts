import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Filament\Resources\Companies\Pages\EditCompany::__invoke
 * @see app/Filament/Resources/Companies/Pages/EditCompany.php:7
 * @route '/admin/companies/{record}/edit'
 */
const EditCompany = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: EditCompany.url(args, options),
    method: 'get',
})

EditCompany.definition = {
    methods: ["get","head"],
    url: '/admin/companies/{record}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Resources\Companies\Pages\EditCompany::__invoke
 * @see app/Filament/Resources/Companies/Pages/EditCompany.php:7
 * @route '/admin/companies/{record}/edit'
 */
EditCompany.url = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return EditCompany.definition.url
            .replace('{record}', parsedArgs.record.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Filament\Resources\Companies\Pages\EditCompany::__invoke
 * @see app/Filament/Resources/Companies/Pages/EditCompany.php:7
 * @route '/admin/companies/{record}/edit'
 */
EditCompany.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: EditCompany.url(args, options),
    method: 'get',
})
/**
* @see \App\Filament\Resources\Companies\Pages\EditCompany::__invoke
 * @see app/Filament/Resources/Companies/Pages/EditCompany.php:7
 * @route '/admin/companies/{record}/edit'
 */
EditCompany.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: EditCompany.url(args, options),
    method: 'head',
})
export default EditCompany