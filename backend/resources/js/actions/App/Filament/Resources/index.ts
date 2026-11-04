import ActivityLogs from './ActivityLogs'
import AttendanceAgents from './AttendanceAgents'
import AttendanceIngestRequests from './AttendanceIngestRequests'
import Branches from './Branches'
import Companies from './Companies'
import Departments from './Departments'
import OffboardingItems from './OffboardingItems'
import Positions from './Positions'
import RequestTypes from './RequestTypes'
import Roles from './Roles'
import Users from './Users'
const Resources = {
    ActivityLogs: Object.assign(ActivityLogs, ActivityLogs),
AttendanceAgents: Object.assign(AttendanceAgents, AttendanceAgents),
AttendanceIngestRequests: Object.assign(AttendanceIngestRequests, AttendanceIngestRequests),
Branches: Object.assign(Branches, Branches),
Companies: Object.assign(Companies, Companies),
Departments: Object.assign(Departments, Departments),
OffboardingItems: Object.assign(OffboardingItems, OffboardingItems),
Positions: Object.assign(Positions, Positions),
RequestTypes: Object.assign(RequestTypes, RequestTypes),
Roles: Object.assign(Roles, Roles),
Users: Object.assign(Users, Users),
}

export default Resources