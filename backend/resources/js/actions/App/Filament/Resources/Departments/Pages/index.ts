import ListDepartments from './ListDepartments'
import CreateDepartment from './CreateDepartment'
import EditDepartment from './EditDepartment'
const Pages = {
    ListDepartments: Object.assign(ListDepartments, ListDepartments),
CreateDepartment: Object.assign(CreateDepartment, CreateDepartment),
EditDepartment: Object.assign(EditDepartment, EditDepartment),
}

export default Pages