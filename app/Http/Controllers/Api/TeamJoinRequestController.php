<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\JoinTeamRequest;
use App\Http\Resources\JoinRequestResource;
use App\Http\Resources\JoinRequestCollection;
use App\Models\JoinRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;

class TeamJoinRequestController extends Controller
{
    public function store(JoinTeamRequest $request): JsonResponse
    {
        try {
            $key = 'join-request:' . auth()->id();
            if (RateLimiter::tooManyAttempts($key, 5)) {
                return response()->json(['message' => 'عدد كبير من الطلبات. يرجى المحاولة لاحقاً.'], 429);
            }
            
            // فحص عدم دعوة نفسه
            if ($request->email === auth()->user()->email) {
                return response()->json(['message' => 'لا يمكنك دعوة نفسك'], 422);
            }
            
            $invitedUser = User::where('email', $request->email)->first();
            $team = auth()->user()->ownedTeams()->first();
            
            // إنشاء فريق تلقائي إذا لم يكن موجود
            if (!$team) {
                $team = auth()->user()->ownedTeams()->create([
                    'name' => auth()->user()->name . ' Team',
                    'owner_id' => auth()->id()
                ]);
            }
            
            // فحص وجود طلب سابق
            $existingRequest = JoinRequest::where('user_id', $invitedUser->id)
                ->where('team_id', $team->id)
                ->where('status', 'pending')
                ->first();
                
            if ($existingRequest) {
                return response()->json(['message' => 'يوجد طلب انضمام معلق بالفعل'], 422);
            }
            
            $joinRequest = JoinRequest::create([
                'user_id' => $invitedUser->id,
                'team_id' => $team->id,
                'status' => 'pending',
            ]);
            
            Cache::forget('sent_invitations_' . auth()->id());
            Cache::forget('received_requests_' . $invitedUser->id);
            
            RateLimiter::hit($key, 60);
            
            return response()->json([
                'message' => 'تم إرسال طلب الانضمام بنجاح',
                'data' => new JoinRequestResource($joinRequest->load(['team:id,name', 'user:id,name,email']))
            ], 201);
            
        } catch (\Exception $e) {
            \Log::error('Join request error: ' . $e->getMessage());
            return response()->json(['message' => 'حدث خطأ أثناء إرسال الدعوة'], 500);
        }
    }

    public function index(): JsonResponse
    {
        $cacheKey = 'join_requests_' . auth()->id();
        
        $requests = Cache::remember($cacheKey, 300, function () {
            return JoinRequest::where('user_id', auth()->id())
                ->with(['team:id,name', 'user:id,name,email'])
                ->select(['id', 'user_id', 'team_id', 'status', 'created_at', 'updated_at'])
                ->latest()
                ->get();
        });

        return response()->json([
            'message' => 'تم جلب طلبات الانضمام بنجاح',
            'data' => new JoinRequestCollection($requests)
        ])->setMaxAge(300)->setPublic();
    }

    public function update(Request $request, JoinRequest $joinRequest): JsonResponse
    {
        // التحقق من أن المستخدم الحالي هو المدعو
        if ($joinRequest->user_id !== auth()->id()) {
            return response()->json(['message' => 'غير مصرح لك'], 403);
        }

        if ($joinRequest->status !== 'pending') {
            return response()->json(['message' => 'لا يمكن تعديل هذا الطلب'], 400);
        }

        $status = $request->input('status');
        if (!in_array($status, ['accepted', 'rejected'])) {
            return response()->json(['message' => 'حالة غير صحيحة'], 400);
        }

        $joinRequest->update(['status' => $status]);
        
        // تنظيف cache عند تحديث الطلب
        Cache::forget('join_requests_' . $joinRequest->user_id);
        Cache::forget('received_requests_' . $joinRequest->team->owner_id);

        if ($status === 'accepted') {
            $joinRequest->team->members()->attach($joinRequest->user_id);
            $message = 'تم قبول الطلب وإضافة العضو للفريق';
        } else {
            $message = 'تم رفض الطلب';
        }

        return response()->json([
            'message' => $message,
            'data' => new JoinRequestResource($joinRequest->load(['team:id,name', 'user:id,name,email']))
        ]);
    }

    public function destroy(JoinRequest $joinRequest): JsonResponse
    {
        // المرسل يمكنه حذف طلبه
        if ($joinRequest->user_id !== auth()->id()) {
            return response()->json(['message' => 'غير مصرح لك'], 403);
        }
        
        // تنظيف cache قبل الحذف
        Cache::forget('join_requests_' . $joinRequest->user_id);
        Cache::forget('received_requests_' . $joinRequest->team->owner_id);

        $joinRequest->delete();

        return response()->json(['message' => 'تم حذف الطلب بنجاح']);
    }

    public function received(): JsonResponse
    {
        $cacheKey = 'received_requests_' . auth()->id();
        
        $requests = Cache::remember($cacheKey, 300, function () {
            return JoinRequest::where('user_id', auth()->id())
                ->with([
                    'team:id,name,owner_id', 
                    'team.owner:id,name,email',
                    'user:id,name,email'
                ])
                ->select(['id', 'user_id', 'team_id', 'status', 'created_at', 'updated_at'])
                ->latest()
                ->get();
        });

        return response()->json([
            'message' => 'تم جلب الطلبات المرسلة إليك بنجاح',
            'data' => new JoinRequestCollection($requests)
        ])->setMaxAge(300)->setPublic();
    }
}