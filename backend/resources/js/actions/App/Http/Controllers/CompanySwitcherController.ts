import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\CompanySwitcherController::update
 * @see app/Http/Controllers/CompanySwitcherController.php:18
 * @route '/active-company'
 */
export const update = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/active-company',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\CompanySwitcherController::update
 * @see app/Http/Controllers/CompanySwitcherController.php:18
 * @route '/active-company'
 */
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CompanySwitcherController::update
 * @see app/Http/Controllers/CompanySwitcherController.php:18
 * @route '/active-company'
 */
update.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(options),
    method: 'put',
})
const CompanySwitcherController = { update }

export default CompanySwitcherController