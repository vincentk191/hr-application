import Api from './Api'
import AccountRegistrationController from './AccountRegistrationController'
import DashboardController from './DashboardController'
import CompanySwitcherController from './CompanySwitcherController'
import OffboardingCaseController from './OffboardingCaseController'
import OffboardingChecklistController from './OffboardingChecklistController'
import OffboardingSignOffController from './OffboardingSignOffController'
import RequisitionController from './RequisitionController'
import ProfileChangeRequestController from './ProfileChangeRequestController'
import EmployeeController from './EmployeeController'
import EmployeeImportController from './EmployeeImportController'
import EmployeeAccountController from './EmployeeAccountController'
import EmployeePhotoController from './EmployeePhotoController'
import EmployeeDocumentController from './EmployeeDocumentController'
import AttendanceCalendarController from './AttendanceCalendarController'
import CalendarEventController from './CalendarEventController'
import AttendanceRecapController from './AttendanceRecapController'
import AttendanceIssueController from './AttendanceIssueController'
import AttendanceAgentController from './AttendanceAgentController'
import AttendanceDeviceController from './AttendanceDeviceController'
import AttendanceImportController from './AttendanceImportController'
import AttendanceRekapController from './AttendanceRekapController'
import AttendanceEnrolMapController from './AttendanceEnrolMapController'
import AttendancePunchController from './AttendancePunchController'
import SearchController from './SearchController'
import NotificationController from './NotificationController'
import LeaveRequestController from './LeaveRequestController'
import LeaveAttachmentController from './LeaveAttachmentController'
import LeaveRequestDecisionController from './LeaveRequestDecisionController'
import Settings from './Settings'
const Controllers = {
    Api: Object.assign(Api, Api),
AccountRegistrationController: Object.assign(AccountRegistrationController, AccountRegistrationController),
DashboardController: Object.assign(DashboardController, DashboardController),
CompanySwitcherController: Object.assign(CompanySwitcherController, CompanySwitcherController),
OffboardingCaseController: Object.assign(OffboardingCaseController, OffboardingCaseController),
OffboardingChecklistController: Object.assign(OffboardingChecklistController, OffboardingChecklistController),
OffboardingSignOffController: Object.assign(OffboardingSignOffController, OffboardingSignOffController),
RequisitionController: Object.assign(RequisitionController, RequisitionController),
ProfileChangeRequestController: Object.assign(ProfileChangeRequestController, ProfileChangeRequestController),
EmployeeController: Object.assign(EmployeeController, EmployeeController),
EmployeeImportController: Object.assign(EmployeeImportController, EmployeeImportController),
EmployeeAccountController: Object.assign(EmployeeAccountController, EmployeeAccountController),
EmployeePhotoController: Object.assign(EmployeePhotoController, EmployeePhotoController),
EmployeeDocumentController: Object.assign(EmployeeDocumentController, EmployeeDocumentController),
AttendanceCalendarController: Object.assign(AttendanceCalendarController, AttendanceCalendarController),
CalendarEventController: Object.assign(CalendarEventController, CalendarEventController),
AttendanceRecapController: Object.assign(AttendanceRecapController, AttendanceRecapController),
AttendanceIssueController: Object.assign(AttendanceIssueController, AttendanceIssueController),
AttendanceAgentController: Object.assign(AttendanceAgentController, AttendanceAgentController),
AttendanceDeviceController: Object.assign(AttendanceDeviceController, AttendanceDeviceController),
AttendanceImportController: Object.assign(AttendanceImportController, AttendanceImportController),
AttendanceRekapController: Object.assign(AttendanceRekapController, AttendanceRekapController),
AttendanceEnrolMapController: Object.assign(AttendanceEnrolMapController, AttendanceEnrolMapController),
AttendancePunchController: Object.assign(AttendancePunchController, AttendancePunchController),
SearchController: Object.assign(SearchController, SearchController),
NotificationController: Object.assign(NotificationController, NotificationController),
LeaveRequestController: Object.assign(LeaveRequestController, LeaveRequestController),
LeaveAttachmentController: Object.assign(LeaveAttachmentController, LeaveAttachmentController),
LeaveRequestDecisionController: Object.assign(LeaveRequestDecisionController, LeaveRequestDecisionController),
Settings: Object.assign(Settings, Settings),
}

export default Controllers