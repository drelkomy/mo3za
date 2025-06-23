<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateTeamRequest;
use App\Http\Requests\Api\RemoveMemberRequest;
use App\Http\Requests\Api\UpdateTeamNameRequest;
use App\Http\Resources\TeamDetailResource;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Models\User;
use App\Models\TeamMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class TeamController extends Controller
{
    public function create(CreateTeamRequest $request): JsonResponse
    {
        // إنشاء الفريق
        $team = Team::create([
            'name' => $request->input('name'),
            'owner_id' => auth()->id()
        ]);
        
        // تحميل العلاقات للريسورس
        $team->load('owner');
        
        return response()->json([
            'message' => 'تم إنشاء الفريق بنجاح',
            'team' => new TeamDetailResource($team)
        ], 201);
    }
    
    public function myTeam(Request $request): JsonResponse
    {
        $cacheKey = 'my_owned_team_' . auth()->id();
        
        $team = Cache::remember($cacheKey, 300, function () {
            return Team::where('owner_id', auth()->id())
                ->with(['members:id,name,email', 'owner:id,name,email'])
                ->first();
        });
        
        if (!$team) {
            return response()->json([
                'message' => 'أنت لست مالك فريق - لا تملك فريقاً خاصاً بك',
                'data' => null,
                'is_owner' => false
            ]);
        }
        
        // تطبيق pagination على الأعضاء
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 10);
        
        $membersCacheKey = 'team_members_' . $team->id . '_page_' . $page . '_' . $perPage;
        
        $membersData = Cache::remember($membersCacheKey, 300, function () use ($team, $page, $perPage) {
            $members = $team->members()
                ->select(['users.id', 'users.name', 'users.email'])
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
                
            $total = $team->members()->count();
            
            return [
                'members' => $members,
                'total' => $total,
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => ceil($total / $perPage)
            ];
        });
        
        // تحميل العلاقات للريسورس
        $team->load('owner');
        
        $teamData = new TeamDetailResource($team);
        $teamData->additional['members_meta'] = [
            'total' => $membersData['total'],
            'current_page' => $membersData['current_page'],
            'per_page' => $membersData['per_page'],
            'last_page' => $membersData['last_page']
        ];
        
        return response()->json([
            'message' => 'تم جلب فريقك الذي تملكه بنجاح',
            'data' => $teamData
        ])->setMaxAge(300)->setPublic();
    }
    
    public function updateName(UpdateTeamNameRequest $request): JsonResponse
    {
        $team = Team::where('owner_id', auth()->id())->first();
        
        if (!$team) {
            return response()->json([
                'message' => 'أنت لست مالك فريق - لا يمكنك تعديل الاسم'
            ], 403);
        }
        
        $team->update(['name' => $request->input('name')]);
        
        // تنظيف cache
        Cache::forget('my_owned_team_' . auth()->id());
        
        // تحميل العلاقات للريسورس
        $team = $team->fresh();
        $team->load('owner');
        
        return response()->json([
            'message' => 'تم تعديل اسم الفريق بنجاح',
            'team' => new TeamDetailResource($team)
        ]);
    }
    
    public function removeMember(RemoveMemberRequest $request): JsonResponse
    {
        $team = Team::where('owner_id', auth()->id())->first();
        
        if (!$team) {
            return response()->json([
                'message' => 'أنت لست مالك فريق - لا يمكنك حذف أعضاء'
            ], 403);
        }
        
        // فحص إضافي للتأكد من الملكية
        if ($team->owner_id !== auth()->id()) {
            return response()->json([
                'message' => 'أنت لست مالك هذا الفريق - لا يمكنك حذف أعضاء'
            ], 403);
        }
        
        $memberId = $request->input('member_id');
        
        // التحقق من وجود العضو في الفريق
        if (!$team->members()->where('user_id', $memberId)->exists()) {
            return response()->json([
                'message' => 'العضو غير موجود في فريقك'
            ], 404);
        }
        
        // إزالة العضو
        $team->members()->detach($memberId);
        
        // تنظيف cache
        Cache::forget('my_owned_team_' . auth()->id());
        Cache::forget('my_owned_team_' . $memberId);
        
        return response()->json([
            'message' => 'تم إزالة العضو من فريقك بنجاح'
        ]);
    }
}
