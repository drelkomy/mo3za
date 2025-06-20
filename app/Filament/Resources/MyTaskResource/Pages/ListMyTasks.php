<?php

namespace App\Filament\Resources\MyTaskResource\Pages;

use App\Filament\Resources\MyTaskResource;
use Filament\Resources\Pages\ListRecords;

class ListMyTasks extends ListRecords
{
    protected static string $resource = MyTaskResource::class;
}