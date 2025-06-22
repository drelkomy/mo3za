<?php

namespace App\Filament\Widgets;

use App\Models\Task;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MemberTaskActivityChartWidget extends ChartWidget
{
    protected static ?string $heading = 'نشاط إنشاء المهام لكل عضو';

    protected static ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        // نحصل على عدد المهام التي أنشأها كل مستخدم
        $results = Task::query()
            ->select('creator_id', DB::raw('COUNT(*) as tasks_count'))
            ->groupBy('creator_id')
            ->with('creator:id,name')
            ->get();

        $labels = [];
        $data   = [];

        foreach ($results as $row) {
            /** @var User|null $creator */
            $creator = $row->creator;
            if (!$creator) {
                continue;
            }
            $labels[] = $creator->name;
            $data[]   = $row->tasks_count;
        }

        return [
            'datasets' => [
                [
                    'label' => 'عدد المهام المنشأة',
                    'data'  => $data,
                    'backgroundColor' => '#006e82',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    public static function canView(): bool
    {
        return Auth::check() && Auth::user()->hasRole('admin');
    }
}
