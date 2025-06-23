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
        $team = Team::where('owner_id', auth()->id())->first();
        
        if (!$team) {
            return response()->json(['message' => 'أنت لست قائد فريق'], 403);
        }

        $user = User::where('email', $request->email)->first();
        
        // التحقق من أن المستخدم ليس عضواً بالفعل
        if ($team->members()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'المستخدم عضو في الفريق بالفعل'], 422);
        }

        // التحقق من وجود دعوة معلقة
        $existingInvitation = Invitation::where('team_id', $team->id)
            ->where('email', $request->email)
            ->where('status', 'pending')
            ->first();

        if ($existingInvitation) {
            return response()->json(['message' => 'يوجد دعوة معلقة لهذا المستخدم'], 422);
        }

        $invitation = Invitation::create([
            'team_id' => $team->id,
            'inviter_id' => auth()->id(),
            'invitee_id' => $user->id,
            'email' => $request->email,
            'message' => $request->message,
            'token' => Str::random(32),
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        // مسح cache
        Cache::forget("my_invitations_sent_" . auth()->id() . "_*");
        Cache::forget("my_invitations_received_" . $user->id . "_*");

        return response()->json([
            'message' => 'تم إرسال الدعوة بنجاح',
            'invitation' => new InvitationResource($invitation->load('team', 'invitee'))
        ], 201);
    }

    public function myInvitations(Request $request): JsonResponse
    {
        $page = $request->input('page', 1);
        $perPage = min($request->input('per_page', 10), 50);
        
        $cacheKey = "all_my_invitations_" . auth()->id() . "_page_{$page}_per_{$perPage}";
        
        $invitationsData = Cache::remember($cacheKey, 300, function () use ($page, $perPage) {
            // جمع الدعوات المرسلة والمستلمة
            $sent = Invitation::where('inviter_id', auth()->id())
                ->with(['team:id,name', 'invitee:id,name'])
                ->get()
                ->map(function($inv) {
                    $inv->type = 'sent';
                    return $inv;
                });
                
            $received = Invitation::where('email', auth()->user()->email)
                ->with(['team:id,name', 'inviter:id,name'])
                ->get()
                ->map(function($inv) {
                    $inv->type = 'received';
                    return $inv;
                });
                
            $allInvitations = $sent->merge($received)
                ->sortByDesc('created_at')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->values();
                
            $total = $sent->count() + $received->count();
            
            return [
                'invitations' => $allInvitations,
                'total' => $total,
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => ceil($total / $perPage)
            ];
        });
        
        return response()->json([
            'message' => 'تم جلب جميع دعواتي بنجاح',
            'data' => InvitationResource::collection($invitationsData['invitations']),
            'meta' => [
                'total' => $invitationsData['total'],
                'current_page' => $invitationsData['current_page'],
                'per_page' => $invitationsData['per_page'],
                'last_page' => $invitationsData['last_page']
            ]
        ])->setMaxAge(300)->setPublic();
    }

    public function teamInvitations(Request $request): JsonResponse
    {
        $team = Team::where('owner_id', auth()->id())->first();
        
        if (!$team) {
            return response()->json(['message' => 'أنت لست قائد فريق'], 403);
        }

        $page = $request->input('page', 1);
        $perPage = min($request->input('per_page', 10), 50);
        $status = $request->input('status');
        
        $cacheKey = "team_invitations_{$team->id}_page_{$page}_per_{$perPage}_status_{$status}";
        
        $invitationsData = Cache::remember($cacheKey, 300, function () use ($team, $page, $perPage, $status) {
            $query = Invitation::where('team_id', $team->id)
                ->with(['inviter:id,name', 'invitee:id,name'])
                ->orderBy('created_at', 'desc');
            
            if ($status) {
                $query->where('status', $status);
            }
            
            $invitations = $query->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            
            $total = Invitation::where('team_id', $team->id)
                ->when($status, fn($q) => $q->where('status', $status))
                ->count();
            
            return [
                'invitations' => $invitations,
                'total' => $total,
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => ceil($total / $perPage)
            ];
        });
        
        return response()->json([
            'message' => 'تم جلب دعوات الفريق بنجاح',
            'data' => InvitationResource::collection($invitationsData['invitations']),
            'meta' => [
                'total' => $invitationsData['total'],
                'current_page' => $invitationsData['current_page'],
                'per_page' => $invitationsData['per_page'],
                'last_page' => $invitationsData['last_page']
            ]
        ])->setMaxAge(300)->setPublic();
    }

    public function respond(\App\Http\Requests\Api\RespondInvitationRequest $request): JsonResponse
    {
        $invitation = Invitation::findOrFail($request->invitation_id);
        
        if ($invitation->email !== auth()->user()->email) {
            return response()->json(['message' => 'غير مصرح لك بالرد على هذه الدعوة'], 403);
        }
        
        if ($invitation->status !== 'pending') {
            return response()->json(['message' => 'تم الرد على هذه الدعوة مسبقاً'], 400);
        }
        
        if ($invitation->expires_at && $invitation->expires_at->isPast()) {
            return response()->json(['message' => 'هذه الدعوة منتهية الصلاحية'], 400);
        }
        
        $status = $request->action === 'accept' ? 'accepted' : 'rejected';
        $invitation->update(['status' => $status]);
        
        if ($request->action === 'accept') {
            // إضافة العضو للفريق
            $invitation->team->members()->attach(auth()->id());
        }
        
        // مسح cache
        Cache::forget('my_invitations_*');
        Cache::forget('team_invitations_*');
        
        $message = $request->action === 'accept' ? 'تم قبول الدعوة بنجاح' : 'تم رفض الدعوة';
        
        return response()->json(['message' => $message]);
    }

    public function delete(\App\Http\Requests\Api\DeleteInvitationRequest $request): JsonResponse
    {
        $invitation = Invitation::findOrFail($request->invitation_id);
        
        if ($invitation->inviter_id !== auth()->id()) {
            return response()->json(['message' => 'غير مصرح لك بحذف هذه الدعوة'], 403);
        }
        
        if ($invitation->status !== 'pending') {
            return response()->json(['message' => 'لا يمكن حذف دعوة تم الرد عليها'], 400);
        }
        
        $invitation->delete();
        
        // مسح cache
        Cache::forget('my_invitations_*');
        Cache::forget('team_invitations_*');
        
        return response()->json(['message' => 'تم حذف الدعوة بنجاح']);
    }
}