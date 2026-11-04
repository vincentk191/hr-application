import ListPositions from './ListPositions'
import CreatePosition from './CreatePosition'
import EditPosition from './EditPosition'
const Pages = {
    ListPositions: Object.assign(ListPositions, ListPositions),
CreatePosition: Object.assign(CreatePosition, CreatePosition),
EditPosition: Object.assign(EditPosition, EditPosition),
}

export default Pages