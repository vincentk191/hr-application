import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Filament\Pages\ManageWorkingHours::__invoke
 * @see app/Filament/Pages/ManageWorkingHours.php:7
 * @route '/admin/manage-working-hours'
 */
const ManageWorkingHours = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ManageWorkingHours.url(options),
    method: 'get',
})

ManageWorkingHours.definition = {
    methods: ["get","head"],
    url: '/admin/manage-working-hours',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Pages\ManageWorkingHours::__invoke
 * @see app/Filament/Pages/ManageWorkingHours.php:7
 * @route '/admin/manage-working-hours'
 */
ManageWorkingHours.url = (options?: RouteQueryOptions) => {
    return ManageWorkingHours.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Pages\ManageWorkingHours::__invoke
 * @see app/Filament/Pages/ManageWorkingHours.php:7
 * @route '/admin/manage-working-hours'
 */
ManageWorkingHours.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ManageWorkingHours.url(options),
    method: 'get',
})
/**
* @see \App\Filament\Pages\ManageWorkingHours::__invoke
 * @see app/Filament/Pages/ManageWorkingHours.php:7
 * @route '/admin/manage-working-hours'
 */
ManageWorkingHours.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ManageWorkingHours.url(options),
    method: 'head',
})
export default ManageWorkingHours