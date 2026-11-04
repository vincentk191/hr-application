import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Filament\Resources\AttendanceIngestRequests\Pages\ManageAttendanceIngestRequests::__invoke
 * @see app/Filament/Resources/AttendanceIngestRequests/Pages/ManageAttendanceIngestRequests.php:7
 * @route '/admin/attendance-ingest-requests'
 */
const ManageAttendanceIngestRequests = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ManageAttendanceIngestRequests.url(options),
    method: 'get',
})

ManageAttendanceIngestRequests.definition = {
    methods: ["get","head"],
    url: '/admin/attendance-ingest-requests',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Resources\AttendanceIngestRequests\Pages\ManageAttendanceIngestRequests::__invoke
 * @see app/Filament/Resources/AttendanceIngestRequests/Pages/ManageAttendanceIngestRequests.php:7
 * @route '/admin/attendance-ingest-requests'
 */
ManageAttendanceIngestRequests.url = (options?: RouteQueryOptions) => {
    return ManageAttendanceIngestRequests.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Resources\AttendanceIngestRequests\Pages\ManageAttendanceIngestRequests::__invoke
 * @see app/Filament/Resources/AttendanceIngestRequests/Pages/ManageAttendanceIngestRequests.php:7
 * @route '/admin/attendance-ingest-requests'
 */
ManageAttendanceIngestRequests.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ManageAttendanceIngestRequests.url(options),
    method: 'get',
})
/**
* @see \App\Filament\Resources\AttendanceIngestRequests\Pages\ManageAttendanceIngestRequests::__invoke
 * @see app/Filament/Resources/AttendanceIngestRequests/Pages/ManageAttendanceIngestRequests.php:7
 * @route '/admin/attendance-ingest-requests'
 */
ManageAttendanceIngestRequests.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ManageAttendanceIngestRequests.url(options),
    method: 'head',
})
export default ManageAttendanceIngestRequests