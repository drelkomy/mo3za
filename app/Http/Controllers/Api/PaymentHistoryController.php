<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PaymentHistoryController extends Controller
{
    public function index(\Illuminate\Http\Request $request): JsonResponse
    {
        $cacheKey = 'user_payments_all_' . auth()->id() . '_v2';
        
        $paymentsData = Cache::remember($cacheKey, 300, function () {
            $query = auth()->user()->payments()
                ->with('package:id,name,price')
                // تم إزالة الشرط لعرض جميع حالات المدفوعات بما في ذلك "pending"
                ->select(['id', 'package_id', 'amount', 'status', 'created_at'])
                ->orderBy('created_at', 'desc');
                
            $total = $query->count();
            $payments = $query->get();
                
            return [
                'data' => $payments,
                'total' => $total
            ];
        });

        return response()->json([
            'message' => 'تم جلب تاريخ المدفوعات بنجاح',
            'data' => PaymentResource::collection($paymentsData['data']),
            'meta' => [
                'total' => $paymentsData['total']
            ]
        ]);
    }
}
