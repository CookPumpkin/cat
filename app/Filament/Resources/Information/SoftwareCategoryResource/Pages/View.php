<?php

namespace App\Filament\Resources\Information\SoftwareCategoryResource\Pages;

use App\Filament\Resources\Information\SoftwareCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class View extends ViewRecord
{
    protected static string $resource = SoftwareCategoryResource::class;

    protected function getActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
