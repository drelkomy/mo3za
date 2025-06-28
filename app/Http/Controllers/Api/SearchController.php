<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SearchRequest;
use App\Http\Resources\SearchResultResource;
use App\Models\Task;
use App\Models\TaskStage;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{


    public function search(SearchRequest $request)
    {
        try {
            $query = $request->validated()['query'];
            $type = $request->validated()['type'] ?? 'all';
            $userId = $request->user()->id;
            
            $cacheKey = "search_{$userId}_{$type}_" . md5($query);
            
            $results = Cache::remember($cacheKey, 900, function () use ($query, $type, $userId) {
                return $this->performSearch($query, $type, $userId);
            });

            return response()->json([
                'status' => 'success',
                'data' => new SearchResultResource($results)
            ])->setMaxAge(900)->setPublic();
        } catch (\Exception $e) {
            \Log::error('خطأ في البحث: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'حدث خطأ أثناء البحث. يرجى المحاولة مرة أخرى.'
            ], 500);
        }
    }

    private function performSearch(string $query, string $type, int $userId): array
    {
        $results = ['total_results' => 0];

        if ($type === 'all' || $type === 'task') {
            $results['tasks'] = $this->searchTasks($query, $userId);
            $results['total_results'] += count($results['tasks']);
        }

        if ($type === 'all' || $type === 'stage') {
            $results['stages'] = $this->searchStages($query, $userId);
            $results['total_results'] += count($results['stages']);
        }

        if ($type === 'all' || $type === 'member') {
            $results['members'] = $this->searchMembers($query, $userId);
            $results['total_results'] += count($results['members']);
        }

        return $results;
    }

    private function searchTasks(string $query, int $userId)
    {
        return Task::where(function ($q) use ($userId) {
                $q->where('creator_id', $userId)
                  ->orWhere('receiver_id', $userId);
            })
            ->where('title', 'LIKE', "%{$query}%")
            ->with(['creator:id,name,avatar_url'])
            ->select('id', 'title', 'status', 'progress', 'due_date', 'created_at', 'creator_id')
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();
    }

    private function searchStages(string $query, int $userId)
    {
        return TaskStage::whereHas('task', function ($q) use ($userId) {
                $q->where('creator_id', $userId)
                  ->orWhere('receiver_id', $userId);
            })
            ->where('title', 'LIKE', "%{$query}%")
            ->select('id', 'title', 'status', 'stage_number')
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();
    }

    private function searchMembers(string $query, int $userId)
    {
        return User::where(function ($q) use ($userId) {
                $q->whereHas('receivedTasks', function ($subQ) use ($userId) {
                    $subQ->where('creator_id', $userId);
                })
                ->orWhereHas('createdTasks', function ($subQ) use ($userId) {
                    $subQ->where('receiver_id', $userId);
                })
                ->orWhereHas('teams', function ($subQ) use ($userId) {
                    $subQ->where('owner_id', $userId);
                })
                ->orWhereHas('ownedTeams', function ($subQ) use ($userId) {
                    $subQ->whereHas('members', function ($memberQ) use ($userId) {
                        $memberQ->where('user_id', $userId);
                    });
                });
            })
            ->where('name', 'LIKE', "%{$query}%")
            ->select('id', 'name', 'avatar_url')
            ->limit(5)
            ->get();
    }
}