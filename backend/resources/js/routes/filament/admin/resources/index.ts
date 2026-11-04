import activityLogs from './activity-logs'
import attendanceAgents from './attendance-agents'
import attendanceIngestRequests from './attendance-ingest-requests'
import branches from './branches'
import companies from './companies'
import departments from './departments'
import offboardingItems from './offboarding-items'
import positions from './positions'
import requestTypes from './request-types'
import roles from './roles'
import users from './users'
const resources = {
    activityLogs: Object.assign(activityLogs, activityLogs),
attendanceAgents: Object.assign(attendanceAgents, attendanceAgents),
attendanceIngestRequests: Object.assign(attendanceIngestRequests, attendanceIngestRequests),
branches: Object.assign(branches, branches),
companies: Object.assign(companies, companies),
departments: Object.assign(departments, departments),
offboardingItems: Object.assign(offboardingItems, offboardingItems),
positions: Object.assign(positions, positions),
requestTypes: Object.assign(requestTypes, requestTypes),
roles: Object.assign(roles, roles),
users: Object.assign(users, users),
}

export default resources