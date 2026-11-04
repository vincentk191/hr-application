import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Filament\Resources\Positions\Pages\EditPosition::__invoke
 * @see app/Filament/Resources/Positions/Pages/EditPosition.php:7
 * @route '/admin/positions/{record}/edit'
 */
const EditPosition = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: EditPosition.url(args, options),
    method: 'get',
})

EditPosition.definition = {
    methods: ["get","head"],
    url: '/admin/positions/{record}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Resources\Positions\Pages\EditPosition::__invoke
 * @see app/Filament/Resources/Positions/Pages/EditPosition.php:7
 * @route '/admin/positions/{record}/edit'
 */
EditPosition.url = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return EditPosition.definition.url
            .replace('{record}', parsedArgs.record.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Filament\Resources\Positions\Pages\EditPosition::__invoke
 * @see app/Filament/Resources/Positions/Pages/EditPosition.php:7
 * @route '/admin/positions/{record}/edit'
 */
EditPosition.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: EditPosition.url(args, options),
    method: 'get',
})
/**
* @see \App\Filament\Resources\Positions\Pages\EditPosition::__invoke
 * @see app/Filament/Resources/Positions/Pages/EditPosition.php:7
 * @route '/admin/positions/{record}/edit'
 */
EditPosition.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: EditPosition.url(args, options),
    method: 'head',
})
export default EditPosition