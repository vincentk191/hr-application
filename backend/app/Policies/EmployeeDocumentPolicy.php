<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\EmployeeDocument;
use App\Models\User;

/**
 * Who may see and change an employee's documents (EMP-DOC-001, EMP-DOC-004).
 *
 * Access is layered. A role's document permissions say what it may do in general; a document
 * marked restricted then needs the extra sensitive-data permission on top, so HR can hold a
 * contract and a disciplinary letter in the same folder and only show one of them.
 *
 * CompanyScope already keeps another company's documents out of reach, and the company check
 * here repeats it for the same reason EmployeePolicy does.
 */
class EmployeeDocumentPolicy
{
    public function view(User $user, EmployeeDocument $document): bool
    {
        if ($document->is_restricted) {
            return $user->can(Permission::ViewSensitiveData->value)
                && $user->canAccessCompany($document->company_id);
        }

        if ($this->isOwner($user, $document)) {
            return true;
        }

        return $user->can(Permission::ViewDocuments->value)
            && $user->canAccessCompany($document->company_id);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageDocuments->value);
    }

    /**
     * Changing a document, filing a new version, or removing it.
     */
    public function update(User $user, EmployeeDocument $document): bool
    {
        return $user->can(Permission::ManageDocuments->value)
            && $user->canAccessCompany($document->company_id)
            && $this->view($user, $document);
    }

    public function delete(User $user, EmployeeDocument $document): bool
    {
        return $this->update($user, $document);
    }

    private function isOwner(User $user, EmployeeDocument $document): bool
    {
        return $document->employee?->user_id !== null
            && $document->employee->user_id === $user->id;
    }
}
