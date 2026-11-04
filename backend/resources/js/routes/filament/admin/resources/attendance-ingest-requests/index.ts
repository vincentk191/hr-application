import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Filament\Resources\AttendanceIngestRequests\Pages\ManageAttendanceIngestRequests::__invoke
 * @see app/Filament/Resources/AttendanceIngestRequests/Pages/ManageAttendanceIngestRequests.php:7
 * @route '/admin/attendance-ingest-requests'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/attendance-ingest-requests',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Resources\AttendanceIngestRequests\Pages\ManageAttendanceIngestRequests::__invoke
 * @see app/Filament/Resources/AttendanceIngestRequests/Pages/ManageAttendanceIngestRequests.php:7
 * @route '/admin/attendance-ingest-requests'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Resources\AttendanceIngestRequests\Pages\ManageAttendanceIngestRequests::__invoke
 * @see app/Filament/Resources/AttendanceIngestRequests/Pages/ManageAttendanceIngestRequests.php:7
 * @route '/admin/attendance-ingest-requests'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Filament\Resources\AttendanceIngestRequests\Pages\ManageAttendanceIngestRequests::__invoke
 * @see app/Filament/Resources/AttendanceIngestRequests/Pages/ManageAttendanceIngestRequests.php:7
 * @route '/admin/attendance-ingest-requests'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})
const attendanceIngestRequests = {
    index: Object.assign(index, index),
}

export default attendanceIngestRequests