<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'price_formatted' => $this->price == 0 ? 'مجاني' : number_format($this->price, 2) . ' ريال',
            'duration_days' => $this->duration_days,
            'duration_text' => $this->getDurationText(),
            'max_tasks' => $this->max_tasks,
            'max_tasks_text' => $this->max_tasks == 0 ? 'غير محدود' : $this->max_tasks . ' مهمة',
            'max_team_members' => $this->max_team_members,
            'max_team_members_text' => $this->max_team_members == 0 ? 'غير محدود' : $this->max_team_members . ' عضو',
            'features' => $this->features ? json_decode($this->features, true) : [],
            'is_trial' => $this->is_trial,
            'is_popular' => $this->is_popular ?? false,
            'badge' => $this->getBadge(),
        ];
    }
    
    private function getDurationText(): string
    {
        if ($this->duration_days == 30) {
            return 'شهر واحد';
        } elseif ($this->duration_days == 90) {
            return '3 أشهر';
        } elseif ($this->duration_days == 365) {
            return 'سنة واحدة';
        } elseif ($this->duration_days == 7) {
            return 'أسبوع واحد';
        }
        
        return $this->duration_days . ' يوم';
    }
    
    private function getBadge(): ?string
    {
        if ($this->is_trial) {
            return 'تجريبي';
        }
        
        if ($this->is_popular ?? false) {
            return 'الأكثر شعبية';
        }
        
        return null;
    }
}