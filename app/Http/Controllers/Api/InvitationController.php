<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateInvitationRequest;
use App\Http\Resources\InvitationResource;
use App\Models\Invitation;
use App\Models\Team;
use App\Models\User;
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

            // التحقق من وجود طلب انضمام معلق (JoinRequest)
            $existingJoinRequest = \App\Models\JoinRequest::where('team_id', $team->id)
                ->where('user_id', $user->id)
                ->where('status', 'pending')
                ->first();

            if ($existingJoinRequest) {
                return response()->json(['message' => 'يوجد طلب انضمام معلق لهذا المستخدم', 'details' => "Pending join request exists for user ID {$user->id} in team ID {$team->id} with request ID {$existingJoinRequest->id}"], 422);
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

            // مسح cache للمستخدم المرسل والمستلم والفريق مع التعامل مع مفاتيح متعددة الصفحات
            $commonPerPageValues = [10, 20, 50];
            for ($page = 1; $page <= 5; $page++) {
                foreach ($commonPerPageValues as $perPage) {
                    Cache::forget("my_invitations_user_" . auth()->id() . "_page_{$page}_per_{$perPage}");
                    Cache::forget("my_invitations_user_" . $user->id . "_page_{$page}_per_{$perPage}");
                }
            }
            // مسح cache لدعوات الفريق
            $statuses = ['all', 'pending', 'accepted', 'rejected'];
            for ($page = 1; $page <= 5; $page++) {
                foreach ($commonPerPageValues as $perPage) {
                    foreach ($statuses as $status) {
                        Cache::forget("team_invitations_{$team->id}_page_{$page}_per_{$perPage}_status_{$status}");
                    }
                }
            }

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
            
            $page = $request->input('page', 1);
            $perPage = min($request->input('per_page', 10), 50);
            $userId = auth()->id();
            
            // جمع طلبات الانضمام المستلمة من جدول طلبات الانضمام
            $received = \App\Models\JoinRequest::where('user_id', $userId)
                ->with(['team:id,name', 'user:id,name'])
                ->get()
                ->map(function($req) {
                    $req->type = 'received';
                    return $req;
                });
                
            // جمع طلبات الانضمام المرسلة من جدول طلبات الانضمام بناءً على الفرق التي يملكها المستخدم
            $ownedTeams = Team::where('owner_id', $userId)->pluck('id');
            $sent = \App\Models\JoinRequest::whereIn('team_id', $ownedTeams)
                ->with(['team:id,name', 'user:id,name'])
                ->get()
                ->map(function($req) {
                    $req->type = 'sent';
                    return $req;
                });
                
            $allRequests = $sent->merge($received)
                ->sortByDesc('created_at')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->values();
                
            $total = $sent->count() + $received->count();
            
            $requestsData = [
                'requests' => $allRequests,
                'total' => $total,
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => ceil($total / $perPage)
            ];
            
            return response()->json([
                'message' => 'تم جلب جميع طلبات الانضمام بنجاح',
                'data' => $requestsData['requests'],
                'meta' => [
                    'total' => $requestsData['total'],
                    'current_page' => $requestsData['current_page'],
                    'per_page' => $requestsData['per_page'],
                    'last_page' => $requestsData['last_page']
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'حدث خطأ أثناء جلب طلبات الانضمام',
                'error' => $e->getMessage(),
                'details' => 'Exception occurred while fetching join requests. Stack trace: ' . $e->getTraceAsString()
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

            $page = $request->input('page', 1);
            $perPage = min($request->input('per_page', 10), 50);
            $status = $request->input('status');
            
            // جلب طلبات الانضمام للفريق من جدول طلبات الانضمام
            $query = \App\Models\JoinRequest::where('team_id', $team->id)
                ->with(['user:id,name', 'team:id,name'])
                ->orderBy('created_at', 'desc');
            
            if ($status) {
                $query->where('status', $status);
            }
            
            $joinRequests = $query->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            
            $total = \App\Models\JoinRequest::where('team_id', $team->id)
                ->when($status, fn($q) => $q->where('status', $status))
                ->count();
            
            $joinRequestsData = [
                'join_requests' => $joinRequests,
                'total' => $total,
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => ceil($total / $perPage)
            ];
            
            return response()->json([
                'message' => 'تم جلب طلبات انضمام الفريق بنجاح',
                'data' => $joinRequestsData['join_requests'],
                'meta' => [
                    'total' => $joinRequestsData['total'],
                    'current_page' => $joinRequestsData['current_page'],
                    'per_page' => $joinRequestsData['per_page'],
                    'last_page' => $joinRequestsData['last_page']
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'حدث خطأ أثناء جلب طلبات انضمام الفريق',
                'error' => $e->getMessage(),
                'details' => 'Exception occurred while fetching team join requests. Stack trace: ' . $e->getTraceAsString()
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
                // إضافة العضو للفريق
                $team = Team::find($joinRequest->team_id);
                if ($team) {
                    $team->members()->attach($userId);
                    // مسح cache لفريق المالك
                    Cache::forget("my_team_" . $team->owner_id);
                    // مسح cache للمستخدم الذي قبل الطلب
                    Cache::forget("my_team_" . $userId);
                    // مسح cache لعدد أعضاء الفريق
                    Cache::forget("team_" . $team->id . "_members_count");
                    // مسح cache لفريق المستخدم
                    Cache::forget("user_team_" . $userId);
                    Cache::forget("user_team_" . $team->owner_id);
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
