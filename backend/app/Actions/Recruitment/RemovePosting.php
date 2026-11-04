<?php

namespace App\Actions\Recruitment;

use App\Models\RequisitionPosting;

/**
 * HR takes back a posting recorded by mistake (REC-PST-002).
 */
class RemovePosting
{
    public function handle(RequisitionPosting $posting): void
    {
        $posting->delete();
    }
}
