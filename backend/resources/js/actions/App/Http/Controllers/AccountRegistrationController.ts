import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\AccountRegistrationController::create
 * @see app/Http/Controllers/AccountRegistrationController.php:17
 * @route '/register'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/register',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AccountRegistrationController::create
 * @see app/Http/Controllers/AccountRegistrationController.php:17
 * @route '/register'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AccountRegistrationController::create
 * @see app/Http/Controllers/AccountRegistrationController.php:17
 * @route '/register'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AccountRegistrationController::create
 * @see app/Http/Controllers/AccountRegistrationController.php:17
 * @route '/register'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

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
const AccountRegistrationController = { create, store }

export default AccountRegistrationController