import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\AccountRegistrationController::store
 * @see app/Http/Controllers/AccountRegistrationController.php:24
 * @route '/register'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/register',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AccountRegistrationController::store
 * @see app/Http/Controllers/AccountRegistrationController.php:24
 * @route '/register'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AccountRegistrationController::store
 * @see app/Http/Controllers/AccountRegistrationController.php:24
 * @route '/register'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})
const register = {
    store: Object.assign(store, store),
}

export default register