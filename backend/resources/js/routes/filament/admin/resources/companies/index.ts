import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Filament\Resources\Companies\Pages\ListCompanies::__invoke
 * @see app/Filament/Resources/Companies/Pages/ListCompanies.php:7
 * @route '/admin/companies'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/companies',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Resources\Companies\Pages\ListCompanies::__invoke
 * @see app/Filament/Resources/Companies/Pages/ListCompanies.php:7
 * @route '/admin/companies'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Resources\Companies\Pages\ListCompanies::__invoke
 * @see app/Filament/Resources/Companies/Pages/ListCompanies.php:7
 * @route '/admin/companies'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Filament\Resources\Companies\Pages\ListCompanies::__invoke
 * @see app/Filament/Resources/Companies/Pages/ListCompanies.php:7
 * @route '/admin/companies'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Filament\Resources\Companies\Pages\CreateCompany::__invoke
 * @see app/Filament/Resources/Companies/Pages/CreateCompany.php:7
 * @route '/admin/companies/create'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/admin/companies/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Resources\Companies\Pages\CreateCompany::__invoke
 * @see app/Filament/Resources/Companies/Pages/CreateCompany.php:7
 * @route '/admin/companies/create'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Resources\Companies\Pages\CreateCompany::__invoke
 * @see app/Filament/Resources/Companies/Pages/CreateCompany.php:7
 * @route '/admin/companies/create'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Filament\Resources\Companies\Pages\CreateCompany::__invoke
 * @see app/Filament/Resources/Companies/Pages/CreateCompany.php:7
 * @route '/admin/companies/create'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Filament\Resources\Companies\Pages\EditCompany::__invoke
 * @see app/Filament/Resources/Companies/Pages/EditCompany.php:7
 * @route '/admin/companies/{record}/edit'
 */
export const edit = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/admin/companies/{record}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Resources\Companies\Pages\EditCompany::__invoke
 * @see app/Filament/Resources/Companies/Pages/EditCompany.php:7
 * @route '/admin/companies/{record}/edit'
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
* @see \App\Filament\Resources\Companies\Pages\EditCompany::__invoke
 * @see app/Filament/Resources/Companies/Pages/EditCompany.php:7
 * @route '/admin/companies/{record}/edit'
 */
edit.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})
/**
* @see \App\Filament\Resources\Companies\Pages\EditCompany::__invoke
 * @see app/Filament/Resources/Companies/Pages/EditCompany.php:7
 * @route '/admin/companies/{record}/edit'
 */
edit.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})
const companies = {
    index: Object.assign(index, index),
create: Object.assign(create, create),
edit: Object.assign(edit, edit),
}

export default companies