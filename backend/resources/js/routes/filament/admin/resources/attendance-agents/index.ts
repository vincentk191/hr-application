import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Filament\Resources\AttendanceAgents\Pages\ManageAttendanceAgents::__invoke
 * @see app/Filament/Resources/AttendanceAgents/Pages/ManageAttendanceAgents.php:7
 * @route '/admin/attendance-agents'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/attendance-agents',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Resources\AttendanceAgents\Pages\ManageAttendanceAgents::__invoke
 * @see app/Filament/Resources/AttendanceAgents/Pages/ManageAttendanceAgents.php:7
 * @route '/admin/attendance-agents'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Resources\AttendanceAgents\Pages\ManageAttendanceAgents::__invoke
 * @see app/Filament/Resources/AttendanceAgents/Pages/ManageAttendanceAgents.php:7
 * @route '/admin/attendance-agents'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Filament\Resources\AttendanceAgents\Pages\ManageAttendanceAgents::__invoke
 * @see app/Filament/Resources/AttendanceAgents/Pages/ManageAttendanceAgents.php:7
 * @route '/admin/attendance-agents'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})
const attendanceAgents = {
    index: Object.assign(index, index),
}

export default attendanceAgents