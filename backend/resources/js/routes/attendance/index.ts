import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
import calendarFa95d0 from './calendar'
import issues1dfb2e from './issues'
import agents from './agents'
import imports from './imports'
import rekaps from './rekaps'
import enrolMaps from './enrol-maps'
/**
* @see \App\Http\Controllers\AttendanceCalendarController::calendar
 * @see app/Http/Controllers/AttendanceCalendarController.php:55
 * @route '/attendance/calendar'
 */
export const calendar = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: calendar.url(options),
    method: 'get',
})

calendar.definition = {
    methods: ["get","head"],
    url: '/attendance/calendar',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AttendanceCalendarController::calendar
 * @see app/Http/Controllers/AttendanceCalendarController.php:55
 * @route '/attendance/calendar'
 */
calendar.url = (options?: RouteQueryOptions) => {
    return calendar.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceCalendarController::calendar
 * @see app/Http/Controllers/AttendanceCalendarController.php:55
 * @route '/attendance/calendar'
 */
calendar.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: calendar.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AttendanceCalendarController::calendar
 * @see app/Http/Controllers/AttendanceCalendarController.php:55
 * @route '/attendance/calendar'
 */
calendar.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: calendar.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\AttendanceRecapController::recap
 * @see app/Http/Controllers/AttendanceRecapController.php:38
 * @route '/attendance/recap'
 */
export const recap = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: recap.url(options),
    method: 'get',
})

recap.definition = {
    methods: ["get","head"],
    url: '/attendance/recap',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AttendanceRecapController::recap
 * @see app/Http/Controllers/AttendanceRecapController.php:38
 * @route '/attendance/recap'
 */
recap.url = (options?: RouteQueryOptions) => {
    return recap.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceRecapController::recap
 * @see app/Http/Controllers/AttendanceRecapController.php:38
 * @route '/attendance/recap'
 */
recap.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: recap.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AttendanceRecapController::recap
 * @see app/Http/Controllers/AttendanceRecapController.php:38
 * @route '/attendance/recap'
 */
recap.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: recap.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\AttendanceIssueController::issues
 * @see app/Http/Controllers/AttendanceIssueController.php:40
 * @route '/attendance/issues'
 */
export const issues = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: issues.url(options),
    method: 'get',
})

issues.definition = {
    methods: ["get","head"],
    url: '/attendance/issues',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AttendanceIssueController::issues
 * @see app/Http/Controllers/AttendanceIssueController.php:40
 * @route '/attendance/issues'
 */
issues.url = (options?: RouteQueryOptions) => {
    return issues.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceIssueController::issues
 * @see app/Http/Controllers/AttendanceIssueController.php:40
 * @route '/attendance/issues'
 */
issues.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: issues.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AttendanceIssueController::issues
 * @see app/Http/Controllers/AttendanceIssueController.php:40
 * @route '/attendance/issues'
 */
issues.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: issues.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\AttendanceDeviceController::device
 * @see app/Http/Controllers/AttendanceDeviceController.php:33
 * @route '/attendance/device'
 */
export const device = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: device.url(options),
    method: 'get',
})

device.definition = {
    methods: ["get","head"],
    url: '/attendance/device',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AttendanceDeviceController::device
 * @see app/Http/Controllers/AttendanceDeviceController.php:33
 * @route '/attendance/device'
 */
device.url = (options?: RouteQueryOptions) => {
    return device.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendanceDeviceController::device
 * @see app/Http/Controllers/AttendanceDeviceController.php:33
 * @route '/attendance/device'
 */
device.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: device.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AttendanceDeviceController::device
 * @see app/Http/Controllers/AttendanceDeviceController.php:33
 * @route '/attendance/device'
 */
device.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: device.url(options),
    method: 'head',
})
const attendance = {
    calendar: Object.assign(calendar, calendarFa95d0),
recap: Object.assign(recap, recap),
issues: Object.assign(issues, issues1dfb2e),
agents: Object.assign(agents, agents),
device: Object.assign(device, device),
imports: Object.assign(imports, imports),
rekaps: Object.assign(rekaps, rekaps),
enrolMaps: Object.assign(enrolMaps, enrolMaps),
}

export default attendance