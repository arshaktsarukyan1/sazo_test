<?php

namespace App\Http\Controllers\Public;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class HealthController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }
}
