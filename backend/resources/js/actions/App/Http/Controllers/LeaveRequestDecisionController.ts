import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\LeaveRequestDecisionController::update
 * @see app/Http/Controllers/LeaveRequestDecisionController.php:25
 * @route '/leave-requests/{leaveRequest}/decision'
 */
export const update = (args: { leaveRequest: number | { id: number } } | [leaveRequest: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/leave-requests/{leaveRequest}/decision',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\LeaveRequestDecisionController::update
 * @see app/Http/Controllers/LeaveRequestDecisionController.php:25
 * @route '/leave-requests/{leaveRequest}/decision'
 */
update.url = (args: { leaveRequest: number | { id: number } } | [leaveRequest: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { leaveRequest: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { leaveRequest: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    leaveRequest: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        leaveRequest: typeof args.leaveRequest === 'object'
                ? args.leaveRequest.id
                : args.leaveRequest,
                }

    return update.definition.url
            .replace('{leaveRequest}', parsedArgs.leaveRequest.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LeaveRequestDecisionController::update
 * @see app/Http/Controllers/LeaveRequestDecisionController.php:25
 * @route '/leave-requests/{leaveRequest}/decision'
 */
update.put = (args: { leaveRequest: number | { id: number } } | [leaveRequest: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})
const LeaveRequestDecisionController = { update }

export default LeaveRequestDecisionController