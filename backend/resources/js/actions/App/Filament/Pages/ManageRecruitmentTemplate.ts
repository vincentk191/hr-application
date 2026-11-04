import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Filament\Pages\ManageRecruitmentTemplate::__invoke
 * @see app/Filament/Pages/ManageRecruitmentTemplate.php:7
 * @route '/admin/manage-recruitment-template'
 */
const ManageRecruitmentTemplate = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ManageRecruitmentTemplate.url(options),
    method: 'get',
})

ManageRecruitmentTemplate.definition = {
    methods: ["get","head"],
    url: '/admin/manage-recruitment-template',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Pages\ManageRecruitmentTemplate::__invoke
 * @see app/Filament/Pages/ManageRecruitmentTemplate.php:7
 * @route '/admin/manage-recruitment-template'
 */
ManageRecruitmentTemplate.url = (options?: RouteQueryOptions) => {
    return ManageRecruitmentTemplate.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Pages\ManageRecruitmentTemplate::__invoke
 * @see app/Filament/Pages/ManageRecruitmentTemplate.php:7
 * @route '/admin/manage-recruitment-template'
 */
ManageRecruitmentTemplate.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ManageRecruitmentTemplate.url(options),
    method: 'get',
})
/**
* @see \App\Filament\Pages\ManageRecruitmentTemplate::__invoke
 * @see app/Filament/Pages/ManageRecruitmentTemplate.php:7
 * @route '/admin/manage-recruitment-template'
 */
ManageRecruitmentTemplate.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ManageRecruitmentTemplate.url(options),
    method: 'head',
})
export default ManageRecruitmentTemplate