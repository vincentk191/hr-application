import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Filament\Resources\RequestTypes\Pages\EditRequestType::__invoke
 * @see app/Filament/Resources/RequestTypes/Pages/EditRequestType.php:7
 * @route '/admin/request-types/{record}/edit'
 */
const EditRequestType = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: EditRequestType.url(args, options),
    method: 'get',
})

EditRequestType.definition = {
    methods: ["get","head"],
    url: '/admin/request-types/{record}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Resources\RequestTypes\Pages\EditRequestType::__invoke
 * @see app/Filament/Resources/RequestTypes/Pages/EditRequestType.php:7
 * @route '/admin/request-types/{record}/edit'
 */
EditRequestType.url = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return EditRequestType.definition.url
            .replace('{record}', parsedArgs.record.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Filament\Resources\RequestTypes\Pages\EditRequestType::__invoke
 * @see app/Filament/Resources/RequestTypes/Pages/EditRequestType.php:7
 * @route '/admin/request-types/{record}/edit'
 */
EditRequestType.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: EditRequestType.url(args, options),
    method: 'get',
})
/**
* @see \App\Filament\Resources\RequestTypes\Pages\EditRequestType::__invoke
 * @see app/Filament/Resources/RequestTypes/Pages/EditRequestType.php:7
 * @route '/admin/request-types/{record}/edit'
 */
EditRequestType.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: EditRequestType.url(args, options),
    method: 'head',
})
export default EditRequestType