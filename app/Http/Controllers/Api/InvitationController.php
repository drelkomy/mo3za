<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateInvitationRequest;
use App\Http\Resources\InvitationResource;
use App\Models\Invitation;
use App\Models\Team;
use App\Models\User;
use App\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class InvitationController extends Controller
{


    public function send(CreateInvitationRequest $request): JsonResponse
    {
        try {
            // Check authentication
            if (!auth()->check()) {
                return response()->json(['message' => 'غير مصادق', 'details' => 'No authenticated user found. Please ensure you are logged in or have provided valid API credentials.'], 401);
            }
            
            $team = Team::where('owner_id', auth()->id())->first();
            
            if (!$team) {
                return response()->json(['message' => 'أنت لست قائد فريق', 'details' => 'No team found for the authenticated user as owner. User ID: ' . auth()->id()], 403);
            }

            $email = $request->input('email');
            if (empty($email)) {
                return response()->json(['message' => 'البريد الإلكتروني مفقود', 'details' => 'Email field is required but was not provided in the request.'], 422);
            }
            
            $user = User::where('email', $email)->first();
            
            if (!$user) {
                return response()->json(['message' => 'المستخدم غير موجود', 'details' => "No user found with email: $email"], 404);
            }
            
            // التحقق من أن المستخدم ليس عضواً بالفعل
            if ($team->members()->where('user_id', $user->id)->exists()) {
                return response()->json(['message' => 'المستخدم عضو في الفريق بالفعل', 'details' => "User with ID {$user->id} is already a member of team ID {$team->id}"], 422);
            }

            // التحقق من وجود طلبات انضمام سابقة (JoinRequest) بأي حالة
            $existingJoinRequest = \App\Models\JoinRequest::where('team_id', $team->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existingJoinRequest) {
                if ($existingJoinRequest->status === 'pending') {
                    return response()->json(['message' => 'يوجد طلب انضمام معلق لهذا المستخدم', 'details' => "Pending join request exists for user ID {$user->id} in team ID {$team->id} with request ID {$existingJoinRequest->id}"], 422);
                } else {
                    // حذف الطلبات السابقة (accepted أو rejected) لتجنب انتهاك القيد الفريد
                    $existingJoinRequest->delete();
                }
            }

            // إنشاء طلب انضمام (JoinRequest) كبيانات أساسية
            $joinRequestData = [
                'team_id' => $team->id,
                'user_id' => $user->id,
                'status' => 'pending',
                'message' => $request->input('message') ?? 'طلب انضمام من خلال API',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            
            $joinRequest = \App\Models\JoinRequest::create($joinRequestData);

            if (!$joinRequest) {
                return response()->json(['message' => 'فشل في إنشاء طلب الانضمام', 'details' => 'Failed to create join request in database. Data provided: ' . json_encode($joinRequestData)], 500);
            }

            // إنشاء دعوة (Invitation) للحفاظ على السجل في API وإرسال البريد الإلكتروني إذا لزم الأمر
            $invitationData = [
                'team_id' => $team->id,
                'sender_id' => auth()->id(),
                'invitee_id' => $user->id,
                'email' => $email,
                'message' => $request->input('message'),
                'token' => Str::random(32),
                'status' => 'pending',
                'expires_at' => now()->addDays(7),
            ];
            
            $invitation = Invitation::create($invitationData);

            if (!$invitation) {
                // إذا فشل إنشاء الدعوة، نحذف طلب الانضمام للحفاظ على الاتساق
                $joinRequest->delete();
                return response()->json(['message' => 'فشل في إنشاء الدعوة', 'details' => 'Failed to create invitation in database. Data provided: ' . json_encode($invitationData)], 500);
            }

            // مسح كاش الفريق
            CacheService::clearTeamCache(auth()->id(), $user->id, false);

            return response()->json([
                'message' => 'تم إرسال طلب الانضمام بنجاح',
                'join_request' => $joinRequest
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'حدث خطأ أثناء إرسال طلب الانضمام',
                'error' => $e->getMessage(),
                'details' => 'Exception occurred during join request creation process. Stack trace: ' . $e->getTraceAsString()
            ], 500);
        }
    }

    public function myInvitations(Request $request): JsonResponse
    {
        try {
            // Check authentication
            if (!auth()->check()) {
                return response()->json(['message' => 'غير مصادق', 'details' => 'No authenticated user found. Please ensure you are logged in or have provided valid API credentials.'], 401);
            }
            
            $userId = auth()->id();
            
            // جمع طلبات الانضمام المستلمة من جدول طلبات الانضمام
            $received = \App\Models\JoinRequest::where('user_id', $userId)
                ->with(['team:id,name', 'user:id,name,avatar_url'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($req) {
                    $req->type = 'received';
                    if ($req->user && !empty($req->user->avatar_url) && !str_starts_with($req->user->avatar_url, 'http')) {
                        $req->user->avatar_url = 'https://www.moezez.com/storage/' . $req->user->avatar_url;
                    }
                    return $req;
                });
                
            $total = \App\Models\JoinRequest::where('user_id', $userId)->count();
            
            return response()->json([
                'message' => 'تم جلب طلبات الانضمام المستلمة بنجاح',
                'data' => $received,
                'meta' => [
                    'total' => $total
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'حدث خطأ أثناء جلب طلبات الانضمام المستلمة',
                'error' => $e->getMessage(),
                'details' => 'Exception occurred while fetching received join requests. Stack trace: ' . $e->getTraceAsString()
            ], 500);
        }
    }

    public function teamInvitations(Request $request): JsonResponse
    {
        try {
            // Check authentication
            if (!auth()->check()) {
                return response()->json(['message' => 'غير مصادق', 'details' => 'No authenticated user found. Please ensure you are logged in or have provided valid API credentials.'], 401);
            }
            
            $team = Team::where('owner_id', auth()->id())->first();
            
            if (!$team) {
                return response()->json(['message' => 'أنت لست قائد فريق', 'details' => 'No team found for the authenticated user as owner. User ID: ' . auth()->id()], 403);
            }

            $status = $request->input('status');
            
            // جلب طلبات الانضمام المرسلة من قبل الفريق من جدول طلبات الانضمام
            $query = \App\Models\JoinRequest::where('team_id', $team->id)
                ->with(['user:id,name,avatar_url', 'team:id,name'])
                ->orderBy('created_at', 'desc');
            
            if ($status) {
                $query->where('status', $status);
            }
            
            $joinRequests = $query->get()
                ->map(function($req) {
                    $req->type = 'sent';
                    if ($req->user && !empty($req->user->avatar_url) && !str_starts_with($req->user->avatar_url, 'http')) {
                        $req->user->avatar_url = 'https://www.moezez.com/storage/' . $req->user->avatar_url;
                    }
                    return $req;
                });
            
            $total = \App\Models\JoinRequest::where('team_id', $team->id)
                ->when($status, fn($q) => $q->where('status', $status))
                ->count();
            
            return response()->json([
                'message' => 'تم جلب طلبات الانضمام المرسلة من الفريق بنجاح',
                'data' => $joinRequests,
                'meta' => [
                    'total' => $total
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'حدث خطأ أثناء جلب طلبات الانضمام المرسلة من الفريق',
                'error' => $e->getMessage(),
                'details' => 'Exception occurred while fetching team sent join requests. Stack trace: ' . $e->getTraceAsString()
            ], 500);
        }
    }

    public function respond(\App\Http\Requests\Api\RespondInvitationRequest $request): JsonResponse
    {
        try {
            // Check authentication
            if (!auth()->check()) {
                return response()->json(['message' => 'غير مصادق', 'details' => 'No authenticated user found. Please ensure you are logged in or have provided valid API credentials.'], 401);
            }
            
            $userId = auth()->id();
            // First, try to find a JoinRequest with the provided ID
            $joinRequest = \App\Models\JoinRequest::find($request->invitation_id);
            
            if (!$joinRequest) {
                // If not found by direct ID, try to find any JoinRequest for the user with matching team
                $joinRequest = \App\Models\JoinRequest::where('user_id', $userId)
                    ->where('status', 'pending')
                    ->first();
                
                if (!$joinRequest) {
                    return response()->json([
                        'message' => 'طلب الانضمام غير موجود',
                        'errors' => ['invitation_id' => ['طلب الانضمام غير موجود']]
                    ], 404);
                }
            }
            
            // التحقق من أن المستخدم هو المستلم لطلب الانضمام
            if ($joinRequest->user_id !== $userId) {
                return response()->json(['message' => 'غير مصرح لك بالرد على هذا الطلب'], 403);
            }
            
            if ($joinRequest->status !== 'pending') {
                return response()->json(['message' => 'تم الرد على هذا الطلب مسبقاً'], 400);
            }
            
            $status = $request->action === 'accept' ? 'accepted' : 'rejected';
            
            // التحقق من وجود طلب انضمام آخر بنفس الحالة لتجنب انتهاك القيد الفريد
            $existingRequest = \App\Models\JoinRequest::where('user_id', $joinRequest->user_id)
                ->where('team_id', $joinRequest->team_id)
                ->where('status', $status)
                ->first();
                
            if ($existingRequest && $existingRequest->id !== $joinRequest->id) {
                // إذا وجد طلب آخر بنفس الحالة، نحذف الطلب القديم أو نتعامل معه
                $existingRequest->delete();
            }
            
            // تحديث طلب الانضمام كعملية أساسية
            $joinRequest->update(['status' => $status]);
            
            // تحديث الدعوة المقابلة إذا وجدت للحفاظ على الاتساق
            $invitation = Invitation::where('team_id', $joinRequest->team_id)
                ->where('status', 'pending')
                ->first();
                
            if ($invitation) {
                $invitation->update(['status' => $status]);
            }
            
            if ($request->action === 'accept') {
                // إضافة العضو للفريق فقط إذا لم يكن عضوًا بالفعل
                $team = Team::find($joinRequest->team_id);
                if ($team) {
                    if (!$team->members()->where('user_id', $userId)->exists()) {
                        $team->members()->attach($userId);
                    }
                    // مسح كاش الفريق
                    CacheService::clearTeamCache($team->owner_id, $userId, true); // قبول عضو = حالة حرجة
                }
            }
            
            // تم إزالة مسح cache بناءً على طلب المستخدم
            
            $message = $request->action === 'accept' ? 'تم قبول طلب الانضمام بنجاح' : 'تم رفض طلب الانضمام';
            
            return response()->json(['message' => $message]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'حدث خطأ أثناء الرد على طلب الانضمام',
                'error' => $e->getMessage(),
                'details' => 'Exception occurred while responding to join request. Stack trace: ' . $e->getTraceAsString()
            ], 500);
        }
    }

    public function delete(\App\Http\Requests\Api\DeleteInvitationRequest $request): JsonResponse
    {
        try {
            // First, try to find a JoinRequest with the provided ID
            $joinRequest = \App\Models\JoinRequest::find($request->invitation_id);
            
            if (!$joinRequest) {
                // If not found by direct ID, try to find a JoinRequest based on team ownership
                $team = Team::where('owner_id', auth()->id())->first();
                if ($team) {
                    $joinRequest = \App\Models\JoinRequest::where('team_id', $team->id)
                        ->where('status', 'pending')
                        ->first();
                }
                
                if (!$joinRequest) {
                    return response()->json([
                        'message' => 'طلب الانضمام غير موجود',
                        'errors' => ['invitation_id' => ['طلب الانضمام غير موجود']]
                    ], 404);
                }
            }
            
            // Check if the authenticated user is the team owner
            $team = Team::find($joinRequest->team_id);
            if ($team && $team->owner_id !== auth()->id()) {
                return response()->json(['message' => 'غير مصرح لك بحذف هذا الطلب'], 403);
            }
            
            if ($joinRequest->status !== 'pending') {
                return response()->json(['message' => 'لا يمكن حذف طلب تم الرد عليه', 'details' => 'The join request status is ' . $joinRequest->status . ', deletion is only allowed for pending requests.'], 400);
            }
            
            // Delete the JoinRequest
            $joinRequest->delete();
            
            // Try to find and delete a corresponding Invitation if it exists
            $invitation = Invitation::where('team_id', $joinRequest->team_id)
                ->where('status', 'pending')
                ->first();
            
            if ($invitation) {
                $invitation->delete();
            }
            
            // تم إزالة مسح cache بناءً على طلب المستخدم
            
            return response()->json(['message' => 'تم حذف طلب الانضمام بنجاح']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'حدث خطأ أثناء حذف طلب الانضمام',
                'error' => $e->getMessage(),
                'details' => 'Exception occurred while deleting join request. Stack trace: ' . $e->getTraceAsString()
            ], 500);
        }
    }
}
