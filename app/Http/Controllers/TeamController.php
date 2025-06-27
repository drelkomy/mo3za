<?php

namespace App\Http\Controllers;

use App\Http\Resources\TeamMemberResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class TeamController extends Controller
{
    public function memberStats(): JsonResponse
    {
        $members = Cache::remember('team_members_stats', now()->addMinutes(10), function () {
            return User::with(['tasks' => function ($query) {
                $query->select('id', 'user_id', 'title', 'status', 'progress', 'due_date', 'created_at', 'stages_count', 'completed_stages');
            }])
            ->where('user_type', 'member')
            ->get(['id', 'name', 'email', 'avatar_url', 'completion_percentage_margin']);
        });

        return response()->json([
            'message' => 'تم جلب إحصائيات مهام أعضاء الفريق بنجاح',
            'data' => [
                'members' => TeamMemberResource::collection($members)
            ]
        ]);
    }
}