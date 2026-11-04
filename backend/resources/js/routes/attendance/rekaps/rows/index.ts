import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\AttendanceRekapController::update
 * @see app/Http/Controllers/AttendanceRekapController.php:92
 * @route '/attendance/rekaps/{rekap}/rows/{row}'
 */
export const update = (args: { rekap: string | number, row: string | number } | [rekap: string | number, row: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/attendance/rekaps/{rekap}/rows/{row}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\AttendanceRekapController::update
 * @see app/Http/Controllers/AttendanceRekapController.php:92
 * @route '/attendance/rekaps/{rekap}/rows/{row}'
 */
update.url = (args: { rekap: string | number, row: string | number } | [rekap: string | number, row: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    rekap: args[0],
                    row: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rekap: args.rekap,
                                row: args.row,
                }

    return update.definition.url
            .replace('{rekap}', parsedArgs.rekap.toString())
            .replace('{row}', parsedArgs.row.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceRekapController::update
 * @see app/Http/Controllers/AttendanceRekapController.php:92
 * @route '/attendance/rekaps/{rekap}/rows/{row}'
 */
update.put = (args: { rekap: string | number, row: string | number } | [rekap: string | number, row: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})
const rows = {
    update: Object.assign(update, update),
}

export default rows