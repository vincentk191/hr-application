import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Filament\Pages\ManageBackups::__invoke
 * @see app/Filament/Pages/ManageBackups.php:7
 * @route '/admin/manage-backups'
 */
const ManageBackups = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ManageBackups.url(options),
    method: 'get',
})

ManageBackups.definition = {
    methods: ["get","head"],
    url: '/admin/manage-backups',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Pages\ManageBackups::__invoke
 * @see app/Filament/Pages/ManageBackups.php:7
 * @route '/admin/manage-backups'
 */
ManageBackups.url = (options?: RouteQueryOptions) => {
    return ManageBackups.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Pages\ManageBackups::__invoke
 * @see app/Filament/Pages/ManageBackups.php:7
 * @route '/admin/manage-backups'
 */
ManageBackups.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ManageBackups.url(options),
    method: 'get',
})
/**
* @see \App\Filament\Pages\ManageBackups::__invoke
 * @see app/Filament/Pages/ManageBackups.php:7
 * @route '/admin/manage-backups'
 */
ManageBackups.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ManageBackups.url(options),
    method: 'head',
})
export default ManageBackups