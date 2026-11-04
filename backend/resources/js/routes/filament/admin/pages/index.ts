import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Filament\Pages\ManageBackups::__invoke
 * @see app/Filament/Pages/ManageBackups.php:7
 * @route '/admin/manage-backups'
 */
export const manageBackups = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: manageBackups.url(options),
    method: 'get',
})

manageBackups.definition = {
    methods: ["get","head"],
    url: '/admin/manage-backups',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Pages\ManageBackups::__invoke
 * @see app/Filament/Pages/ManageBackups.php:7
 * @route '/admin/manage-backups'
 */
manageBackups.url = (options?: RouteQueryOptions) => {
    return manageBackups.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Pages\ManageBackups::__invoke
 * @see app/Filament/Pages/ManageBackups.php:7
 * @route '/admin/manage-backups'
 */
manageBackups.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: manageBackups.url(options),
    method: 'get',
})
/**
* @see \App\Filament\Pages\ManageBackups::__invoke
 * @see app/Filament/Pages/ManageBackups.php:7
 * @route '/admin/manage-backups'
 */
manageBackups.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: manageBackups.url(options),
    method: 'head',
})

/**
* @see \App\Filament\Pages\ManageRecruitmentTemplate::__invoke
 * @see app/Filament/Pages/ManageRecruitmentTemplate.php:7
 * @route '/admin/manage-recruitment-template'
 */
export const manageRecruitmentTemplate = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: manageRecruitmentTemplate.url(options),
    method: 'get',
})

manageRecruitmentTemplate.definition = {
    methods: ["get","head"],
    url: '/admin/manage-recruitment-template',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Pages\ManageRecruitmentTemplate::__invoke
 * @see app/Filament/Pages/ManageRecruitmentTemplate.php:7
 * @route '/admin/manage-recruitment-template'
 */
manageRecruitmentTemplate.url = (options?: RouteQueryOptions) => {
    return manageRecruitmentTemplate.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Pages\ManageRecruitmentTemplate::__invoke
 * @see app/Filament/Pages/ManageRecruitmentTemplate.php:7
 * @route '/admin/manage-recruitment-template'
 */
manageRecruitmentTemplate.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: manageRecruitmentTemplate.url(options),
    method: 'get',
})
/**
* @see \App\Filament\Pages\ManageRecruitmentTemplate::__invoke
 * @see app/Filament/Pages/ManageRecruitmentTemplate.php:7
 * @route '/admin/manage-recruitment-template'
 */
manageRecruitmentTemplate.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: manageRecruitmentTemplate.url(options),
    method: 'head',
})

/**
* @see \App\Filament\Pages\ManageWorkingHours::__invoke
 * @see app/Filament/Pages/ManageWorkingHours.php:7
 * @route '/admin/manage-working-hours'
 */
export const manageWorkingHours = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: manageWorkingHours.url(options),
    method: 'get',
})

manageWorkingHours.definition = {
    methods: ["get","head"],
    url: '/admin/manage-working-hours',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Pages\ManageWorkingHours::__invoke
 * @see app/Filament/Pages/ManageWorkingHours.php:7
 * @route '/admin/manage-working-hours'
 */
manageWorkingHours.url = (options?: RouteQueryOptions) => {
    return manageWorkingHours.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Pages\ManageWorkingHours::__invoke
 * @see app/Filament/Pages/ManageWorkingHours.php:7
 * @route '/admin/manage-working-hours'
 */
manageWorkingHours.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: manageWorkingHours.url(options),
    method: 'get',
})
/**
* @see \App\Filament\Pages\ManageWorkingHours::__invoke
 * @see app/Filament/Pages/ManageWorkingHours.php:7
 * @route '/admin/manage-working-hours'
 */
manageWorkingHours.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: manageWorkingHours.url(options),
    method: 'head',
})

/**
* @see \Filament\Pages\Dashboard::__invoke
 * @see vendor/filament/filament/src/Pages/Dashboard.php:7
 * @route '/admin'
 */
export const dashboard = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
})

dashboard.definition = {
    methods: ["get","head"],
    url: '/admin',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Filament\Pages\Dashboard::__invoke
 * @see vendor/filament/filament/src/Pages/Dashboard.php:7
 * @route '/admin'
 */
dashboard.url = (options?: RouteQueryOptions) => {
    return dashboard.definition.url + queryParams(options)
}

/**
* @see \Filament\Pages\Dashboard::__invoke
 * @see vendor/filament/filament/src/Pages/Dashboard.php:7
 * @route '/admin'
 */
dashboard.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
})
/**
* @see \Filament\Pages\Dashboard::__invoke
 * @see vendor/filament/filament/src/Pages/Dashboard.php:7
 * @route '/admin'
 */
dashboard.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: dashboard.url(options),
    method: 'head',
})
const pages = {
    manageBackups: Object.assign(manageBackups, manageBackups),
manageRecruitmentTemplate: Object.assign(manageRecruitmentTemplate, manageRecruitmentTemplate),
manageWorkingHours: Object.assign(manageWorkingHours, manageWorkingHours),
dashboard: Object.assign(dashboard, dashboard),
}

export default pages