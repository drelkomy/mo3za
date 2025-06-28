<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentHistoryController extends Controller
{
    public function index(\Illuminate\Http\Request $request): JsonResponse
    {
        $query = auth()->user()->payments()
            ->with('package:id,name,price')
            ->select(['id', 'package_id', 'amount', 'status', 'created_at'])
            ->orderBy('created_at', 'desc');
            
        $total = $query->count();
        $payments = $query->get();

        return response()->json([
            'message' => 'تم جلب تاريخ المدفوعات بنجاح',
            'data' => PaymentResource::collection($payments),
            'meta' => [
                'total' => $total
            ]
        ]);
    }
}