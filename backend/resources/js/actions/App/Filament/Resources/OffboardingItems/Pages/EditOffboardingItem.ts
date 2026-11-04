import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Filament\Resources\OffboardingItems\Pages\EditOffboardingItem::__invoke
 * @see app/Filament/Resources/OffboardingItems/Pages/EditOffboardingItem.php:7
 * @route '/admin/offboarding-items/{record}/edit'
 */
const EditOffboardingItem = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: EditOffboardingItem.url(args, options),
    method: 'get',
})

EditOffboardingItem.definition = {
    methods: ["get","head"],
    url: '/admin/offboarding-items/{record}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Resources\OffboardingItems\Pages\EditOffboardingItem::__invoke
 * @see app/Filament/Resources/OffboardingItems/Pages/EditOffboardingItem.php:7
 * @route '/admin/offboarding-items/{record}/edit'
 */
EditOffboardingItem.url = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return EditOffboardingItem.definition.url
            .replace('{record}', parsedArgs.record.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Filament\Resources\OffboardingItems\Pages\EditOffboardingItem::__invoke
 * @see app/Filament/Resources/OffboardingItems/Pages/EditOffboardingItem.php:7
 * @route '/admin/offboarding-items/{record}/edit'
 */
EditOffboardingItem.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: EditOffboardingItem.url(args, options),
    method: 'get',
})
/**
* @see \App\Filament\Resources\OffboardingItems\Pages\EditOffboardingItem::__invoke
 * @see app/Filament/Resources/OffboardingItems/Pages/EditOffboardingItem.php:7
 * @route '/admin/offboarding-items/{record}/edit'
 */
EditOffboardingItem.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: EditOffboardingItem.url(args, options),
    method: 'head',
})
export default EditOffboardingItem