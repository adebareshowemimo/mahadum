<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolLead;
use Illuminate\Http\JsonResponse;

class SchoolLeadController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => SchoolLead::latest()->get()]);
    }
}
