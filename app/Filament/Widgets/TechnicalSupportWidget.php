<?php

namespace App\Filament\Widgets;

use App\Models\FinancialDetail;
use Filament\Widgets\Widget;

class TechnicalSupportWidget extends Widget
{
    protected static string $view = 'filament.widgets.technical-support-widget';
    protected int | string | array $columnSpan = 'full';

    public ?FinancialDetail $financialData;

    public function mount(): void
    {
        $this->financialData = FinancialDetail::latest()->first();
    }
}
