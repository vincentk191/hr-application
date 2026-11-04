<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Annual leave entitlement
    |--------------------------------------------------------------------------
    |
    | The working days of annual leave each employee has per calendar year, for the "Sisa cuti" figure on the
    | dashboard. The remaining balance is this less the approved days of the request type whose code is
    | `annual_type_code`. Nothing else is deducted, and no carry-over is modelled. 12 is the statutory minimum
    | in Indonesia; it is one number for everyone because the data holds no per-person entitlement.
    |
    */

    'annual_entitlement_days' => (int) env('LEAVE_ANNUAL_DAYS', 12),

    'annual_type_code' => 'annual',

];
