<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSchoolLeadRequest;
use App\Models\SchoolLead;
use Illuminate\Http\JsonResponse;

/**
 * Public (unauthenticated) "Get Quote" capture from the pricing page's School
 * banner. No account or Organization is created — just a contact record for
 * manual sales follow-up (see Admin\SchoolLeadController for the admin list).
 */
class SchoolLeadController extends Controller
{
    public function store(StoreSchoolLeadRequest $request): JsonResponse
    {
        $lead = SchoolLead::create($request->validated());

        return response()->json(['data' => ['id' => $lead->id]], 201);
    }
}
