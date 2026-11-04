import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Filament\Resources\AttendanceAgents\Pages\ManageAttendanceAgents::__invoke
 * @see app/Filament/Resources/AttendanceAgents/Pages/ManageAttendanceAgents.php:7
 * @route '/admin/attendance-agents'
 */
const ManageAttendanceAgents = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ManageAttendanceAgents.url(options),
    method: 'get',
})

ManageAttendanceAgents.definition = {
    methods: ["get","head"],
    url: '/admin/attendance-agents',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Resources\AttendanceAgents\Pages\ManageAttendanceAgents::__invoke
 * @see app/Filament/Resources/AttendanceAgents/Pages/ManageAttendanceAgents.php:7
 * @route '/admin/attendance-agents'
 */
ManageAttendanceAgents.url = (options?: RouteQueryOptions) => {
    return ManageAttendanceAgents.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Resources\AttendanceAgents\Pages\ManageAttendanceAgents::__invoke
 * @see app/Filament/Resources/AttendanceAgents/Pages/ManageAttendanceAgents.php:7
 * @route '/admin/attendance-agents'
 */
ManageAttendanceAgents.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ManageAttendanceAgents.url(options),
    method: 'get',
})
/**
* @see \App\Filament\Resources\AttendanceAgents\Pages\ManageAttendanceAgents::__invoke
 * @see app/Filament/Resources/AttendanceAgents/Pages/ManageAttendanceAgents.php:7
 * @route '/admin/attendance-agents'
 */
ManageAttendanceAgents.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ManageAttendanceAgents.url(options),
    method: 'head',
})
export default ManageAttendanceAgents