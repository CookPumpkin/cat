<?php

namespace App\Filament\Resources\InventoryResource\Pages;

use App\Filament\Resources\InventoryResource;
use Filament\Resources\Pages\ListRecords;

class Index extends ListRecords
{
    protected static string $resource = InventoryResource::class;

    protected static ?string $navigationIcon = 'heroicon-m-arrow-uturn-left';

    public static function getNavigationLabel(): string
    {
        return '返回列表';
    }
}
