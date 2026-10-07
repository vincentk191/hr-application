<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetActiveCompany;
use App\Http\Requests\UpdateActiveCompanyRequest;
use Illuminate\Http\JsonResponse;

class ActiveCompanyController extends Controller
{
    /**
     * Change the active company. Same Form Request, same session key as the web switcher, so
     * the lock on pinned accounts is enforced by the same code in both front ends.
     */
    public function update(UpdateActiveCompanyRequest $request): JsonResponse
    {
        $request->session()->put(SetActiveCompany::SESSION_KEY, $request->selection());

        return response()->json(['data' => ['selected' => $request->selection()]]);
    }
}
