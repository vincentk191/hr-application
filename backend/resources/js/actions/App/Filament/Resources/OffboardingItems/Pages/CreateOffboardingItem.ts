import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Filament\Resources\OffboardingItems\Pages\CreateOffboardingItem::__invoke
 * @see app/Filament/Resources/OffboardingItems/Pages/CreateOffboardingItem.php:7
 * @route '/admin/offboarding-items/create'
 */
const CreateOffboardingItem = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: CreateOffboardingItem.url(options),
    method: 'get',
})

CreateOffboardingItem.definition = {
    methods: ["get","head"],
    url: '/admin/offboarding-items/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Resources\OffboardingItems\Pages\CreateOffboardingItem::__invoke
 * @see app/Filament/Resources/OffboardingItems/Pages/CreateOffboardingItem.php:7
 * @route '/admin/offboarding-items/create'
 */
CreateOffboardingItem.url = (options?: RouteQueryOptions) => {
    return CreateOffboardingItem.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Resources\OffboardingItems\Pages\CreateOffboardingItem::__invoke
 * @see app/Filament/Resources/OffboardingItems/Pages/CreateOffboardingItem.php:7
 * @route '/admin/offboarding-items/create'
 */
CreateOffboardingItem.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: CreateOffboardingItem.url(options),
    method: 'get',
})
/**
* @see \App\Filament\Resources\OffboardingItems\Pages\CreateOffboardingItem::__invoke
 * @see app/Filament/Resources/OffboardingItems/Pages/CreateOffboardingItem.php:7
 * @route '/admin/offboarding-items/create'
 */
CreateOffboardingItem.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: CreateOffboardingItem.url(options),
    method: 'head',
})
export default CreateOffboardingItem