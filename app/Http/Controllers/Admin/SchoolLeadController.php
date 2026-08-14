<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolLead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolLeadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = SchoolLead::latest()->paginate(20);

        return response()->json([
            'data' => $page->items(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }
}
