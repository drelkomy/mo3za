<?php

namespace App\Filament\Resources\AssignedTaskResource\Pages;

use App\Filament\Resources\AssignedTaskResource;
use Filament\Resources\Pages\ListRecords;

class ListAssignedTasks extends ListRecords
{
    protected static string $resource = AssignedTaskResource::class;
}