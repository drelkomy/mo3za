<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateTeamRequest;
use App\Http\Requests\Api\RemoveMemberRequest;
use App\Http\Requests\Api\UpdateTeamNameRequest;
use App\Http\Resources\TeamDetailResource;
use App\Http\Resources\TaskResource;
use App\Http\Resources\RewardResource;
use App\Models\Team;
use App\Models\Task;
use App\Models\Reward;
use App\Models\User;
use App\Models\TeamMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class TeamController extends Controller
{
    public function create(CreateTeamRequest $request): JsonResponse
    {
        $team = Team::create([
            'name' => $request->input('name'),
            'owner_id' => auth()->id()
        ]);
        
        $team->load('owner');
        
        return response()->json([
            'message' => 'تم إنشاء الفريق بنجاح',
            'team' => new TeamDetailResource($team)
        ], 201);
    }
    
    public function myTeam(\App\Http\Requests\Api\MyTeamRequest $request): JsonResponse
    {
        $cacheKey = 'my_team_' . auth()->id();
        
        $team = Cache::remember($cacheKey, 300, function () {
            return Team::where('owner_id', auth()->id())
                ->with([
                    'owner:id,name,email,avatar_url',
                    'members:id,name,email,avatar_url',
                    'tasks' => function($query) {
                        $query->select('id', 'team_id', 'title', 'status', 'receiver_id', 'progress')
                              ->with('receiver:id,name');
                    }
                ])
                ->select('id', 'name', 'owner_id', 'created_at')
                ->first();
        });
            
        if (!$team) {
            return response()->json([
                'message' => 'لا تملك فريقاً',
                'data' => [
                    'is_owner' => false
                ]
            ]);
        }
        
        return response()->json([
            'message' => 'تم جلب فريقك بنجاح',
            'data' => new \App\Http\Resources\MyTeamResource($team)
        ]);
    }
    
    public function updateName(UpdateTeamNameRequest $request): JsonResponse
    {
        $team = Team::where('owner_id', auth()->id())->firstOrFail();
        
        $team->update(['name' => $request->input('name')]);
        
        // حذف الكاش
        Cache::forget('my_team_' . auth()->id());
        
        $team = Team::where('id', $team->id)->with('owner:id,name,email,avatar_url')->first();
        
        return response()->json([
            'message' => 'تم تعديل اسم الفريق بنجاح',
            'team' => new TeamDetailResource($team)
        ]);
    }
    
    public function removeMember(RemoveMemberRequest $request): JsonResponse
    {
        $team = Team::with('members')->where('owner_id', auth()->id())->firstOrFail();
        
        $memberId = $request->input('member_id');
        
        if (!$team->members->contains('id', $memberId)) {
            return response()->json([
                'message' => 'العضو غير موجود في فريقك'
            ], 404);
        }
        
        $team->members()->detach($memberId);
        
        // حذف الكاش
        Cache::forget('my_team_' . auth()->id());
        Cache::forget('my_team_' . $memberId);
        
        return response()->json([
            'message' => 'تم إزالة العضو من فريقك بنجاح'
        ]);
    }
    
    /**
     * عرض مهام الفريق
     */
    public function getTeamTasks(\App\Http\Requests\Api\TeamTasksRequest $request): JsonResponse
    {
        // التحقق من وجود فريق للمستخدم
        $team = $this->getUserTeam();
        if (!$team) {
            return response()->json([
                'message' => 'لا تملك فريقاً',
                'data' => null
            ], 404);
        }
        
        $page = $request->input('page', 1);
        $perPage = min($request->input('per_page', 10), 50);
        $status = $request->input('status');
        
        $cacheKey = "team_tasks_{$team->id}_page_{$page}_per_{$perPage}_status_{$status}";
        
        $tasksData = Cache::remember($cacheKey, 300, function () use ($team, $page, $perPage, $status) {
            $query = Task::where('team_id', $team->id)
                ->with([
                    'receiver:id,name,email,avatar_url',
                    'creator:id,name,email',
                    'stages' => function($q) {
                        $q->orderBy('stage_number');
                    }
                ])
                ->select('id', 'title', 'description', 'status', 'progress', 'receiver_id', 'creator_id', 'team_id', 'created_at', 'due_date')
                ->withCount('stages');
            
            if ($status) {
                $query->where('status', $status);
            }
            
            $tasks = $query->orderBy('created_at', 'desc')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            
            $total = Task::where('team_id', $team->id)
                ->when($status, fn($q) => $q->where('status', $status))
                ->count();
                
            $statusCounts = Task::where('team_id', $team->id)
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();
            
            return [
                'tasks' => $tasks,
                'total' => $total,
                'status_counts' => $statusCounts,
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => ceil($total / $perPage)
            ];
        });
        
        return response()->json([
            'message' => 'تم جلب مهام الفريق بنجاح',
            'data' => TaskResource::collection($tasksData['tasks']),
            'meta' => [
                'total' => $tasksData['total'],
                'status_counts' => $tasksData['status_counts'],
                'current_page' => $tasksData['current_page'],
                'per_page' => $tasksData['per_page'],
                'last_page' => $tasksData['last_page']
            ]
        ])->setMaxAge(300)->setPublic();
    }
    
    /**
     * عرض مكافآت الفريق
     */
    public function getTeamRewards(\App\Http\Requests\Api\TeamRewardsRequest $request): JsonResponse
    {
        // التحقق من وجود فريق للمستخدم
        $team = $this->getUserTeam();
        if (!$team) {
            return response()->json([
                'message' => 'لا تملك فريقاً',
                'data' => null
            ], 404);
        }
        
        $page = $request->input('page', 1);
        $perPage = min($request->input('per_page', 10), 50);
        $status = $request->input('status');
        
        $cacheKey = "team_rewards_{$team->id}_page_{$page}_per_{$perPage}_status_{$status}";
        
        $rewardsData = Cache::remember($cacheKey, 300, function () use ($team, $page, $perPage, $status) {
            // الحصول على معرفات أعضاء الفريق
            $teamMemberIds = $team->members()->pluck('user_id')->push($team->owner_id)->toArray();
            
            $query = Reward::whereIn('receiver_id', $teamMemberIds)
                ->with([
                    'receiver:id,name,email,avatar_url',
                    'task:id,title,team_id',
                    'giver:id,name,email'
                ])
                ->select('id', 'amount', 'notes', 'status', 'receiver_id', 'giver_id', 'task_id', 'created_at');
            
            if ($status) {
                $query->where('status', $status);
            }
            
            $rewards = $query->orderBy('created_at', 'desc')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            
            $total = Reward::whereIn('receiver_id', $teamMemberIds)
                ->when($status, fn($q) => $q->where('status', $status))
                ->count();
                
            $totalAmount = Reward::whereIn('receiver_id', $teamMemberIds)
                ->when($status, fn($q) => $q->where('status', $status))
                ->sum('amount');
                
            $userStats = Reward::whereIn('receiver_id', $teamMemberIds)
                ->select('receiver_id', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(*) as count'))
                ->groupBy('receiver_id')
                ->get()
                ->keyBy('receiver_id')
                ->toArray();
                
            $statusCounts = Reward::whereIn('receiver_id', $teamMemberIds)
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();
            
            return [
                'rewards' => $rewards,
                'total' => $total,
                'total_amount' => $totalAmount,
                'user_stats' => $userStats,
                'status_counts' => $statusCounts,
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => ceil($total / $perPage)
            ];
        });
        
        return response()->json([
            'message' => 'تم جلب مكافآت الفريق بنجاح',
            'data' => RewardResource::collection($rewardsData['rewards']),
            'meta' => [
                'total' => $rewardsData['total'],
                'total_amount' => $rewardsData['total_amount'],
                'user_stats' => $rewardsData['user_stats'],
                'status_counts' => $rewardsData['status_counts'] ?? [],
                'current_page' => $rewardsData['current_page'],
                'per_page' => $rewardsData['per_page'],
                'last_page' => $rewardsData['last_page']
            ]
        ])->setMaxAge(300)->setPublic();
    }
    
    /**
     * الحصول على فريق المستخدم (سواء كان مالكاً أو عضواً)
     */
    private function getUserTeam(): ?Team
    {
        $userId = auth()->id();
        $cacheKey = "user_team_{$userId}";
        
        return Cache::remember($cacheKey, 300, function () use ($userId) {
            // البحث عن فريق يملكه المستخدم
            $ownedTeam = Team::where('owner_id', $userId)->first();
            if ($ownedTeam) {
                return $ownedTeam;
            }
            
            // البحث عن فريق ينتمي إليه المستخدم
            $memberTeam = Team::whereHas('members', function($query) use ($userId) {
                $query->where('user_id', $userId);
            })->first();
            
            return $memberTeam;
        });
    }
}