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
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 10);
        
        $cacheKey = 'user_payments_' . auth()->id() . '_page_' . $page . '_' . $perPage;
        
        $paymentsData = Cache::remember($cacheKey, 300, function () use ($page, $perPage) {
            $query = auth()->user()->subscriptions()
                ->with('package:id,name,price')
                ->where('status', '!=', 'pending')
                ->select(['id', 'package_id', 'price_paid', 'status', 'created_at'])
                ->orderBy('created_at', 'desc');
                
            $total = $query->count();
            $payments = $query->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
                
            return [
                'data' => $payments,
                'total' => $total,
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => ceil($total / $perPage)
            ];
        });

        return response()->json([
            'message' => 'تم جلب تاريخ المدفوعات بنجاح',
            'data' => PaymentResource::collection($paymentsData['data']),
            'meta' => [
                'total' => $paymentsData['total'],
                'current_page' => $paymentsData['current_page'],
                'per_page' => $paymentsData['per_page'],
                'last_page' => $paymentsData['last_page']
            ]
        ]);
    }
}