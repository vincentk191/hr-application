import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Filament\Resources\RequestTypes\Pages\ListRequestTypes::__invoke
 * @see app/Filament/Resources/RequestTypes/Pages/ListRequestTypes.php:7
 * @route '/admin/request-types'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/request-types',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Resources\RequestTypes\Pages\ListRequestTypes::__invoke
 * @see app/Filament/Resources/RequestTypes/Pages/ListRequestTypes.php:7
 * @route '/admin/request-types'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Resources\RequestTypes\Pages\ListRequestTypes::__invoke
 * @see app/Filament/Resources/RequestTypes/Pages/ListRequestTypes.php:7
 * @route '/admin/request-types'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Filament\Resources\RequestTypes\Pages\ListRequestTypes::__invoke
 * @see app/Filament/Resources/RequestTypes/Pages/ListRequestTypes.php:7
 * @route '/admin/request-types'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Filament\Resources\RequestTypes\Pages\CreateRequestType::__invoke
 * @see app/Filament/Resources/RequestTypes/Pages/CreateRequestType.php:7
 * @route '/admin/request-types/create'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/admin/request-types/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Resources\RequestTypes\Pages\CreateRequestType::__invoke
 * @see app/Filament/Resources/RequestTypes/Pages/CreateRequestType.php:7
 * @route '/admin/request-types/create'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Resources\RequestTypes\Pages\CreateRequestType::__invoke
 * @see app/Filament/Resources/RequestTypes/Pages/CreateRequestType.php:7
 * @route '/admin/request-types/create'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Filament\Resources\RequestTypes\Pages\CreateRequestType::__invoke
 * @see app/Filament/Resources/RequestTypes/Pages/CreateRequestType.php:7
 * @route '/admin/request-types/create'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Filament\Resources\RequestTypes\Pages\EditRequestType::__invoke
 * @see app/Filament/Resources/RequestTypes/Pages/EditRequestType.php:7
 * @route '/admin/request-types/{record}/edit'
 */
export const edit = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/admin/request-types/{record}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Resources\RequestTypes\Pages\EditRequestType::__invoke
 * @see app/Filament/Resources/RequestTypes/Pages/EditRequestType.php:7
 * @route '/admin/request-types/{record}/edit'
 */
edit.url = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return edit.definition.url
            .replace('{record}', parsedArgs.record.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Filament\Resources\RequestTypes\Pages\EditRequestType::__invoke
 * @see app/Filament/Resources/RequestTypes/Pages/EditRequestType.php:7
 * @route '/admin/request-types/{record}/edit'
 */
edit.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})
/**
* @see \App\Filament\Resources\RequestTypes\Pages\EditRequestType::__invoke
 * @see app/Filament/Resources/RequestTypes/Pages/EditRequestType.php:7
 * @route '/admin/request-types/{record}/edit'
 */
edit.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})
const requestTypes = {
    index: Object.assign(index, index),
create: Object.assign(create, create),
edit: Object.assign(edit, edit),
}

export default requestTypes