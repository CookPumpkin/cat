<?php

namespace App\Filament\Resources;

use App\Enums\AssetEnum;
use App\Filament\Actions\PartAction;
use App\Filament\Forms\PartForm;
use App\Filament\Imports\PartImporter;
use App\Filament\Resources\PartResource\Pages\Edit;
use App\Filament\Resources\PartResource\Pages\HasPart;
use App\Filament\Resources\PartResource\Pages\Index;
use App\Filament\Resources\PartResource\Pages\View;
use App\Models\Part;
use App\Services\BrandService;
use App\Services\PartCategoryService;
use App\Services\PartService;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms\Form;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Split;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ImportAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;

class PartResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Part::class;

    protected static ?string $navigationIcon = 'heroicon-m-cpu-chip';

    protected static ?string $modelLabel = '配件';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationGroup = '资产';

    protected static ?string $recordTitleAttribute = 'asset_number';

    public static function getRecordSubNavigation(Page $page): array
    {
        $navigation_items = [
            Index::class,
            View::class,
            Edit::class,
            HasPart::class,
        ];
        $part_service = $page->getWidgetData()['record']->service();
        $can_update_part = auth()->user()->can('update_part');
        if ($part_service->isRetired() || ! $can_update_part) {
            unset($navigation_items[2]);
        }

        return $page->generateNavigationItems($navigation_items);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /* @var Part $record */
        return [
            '设备' => $record->devices()->value('asset_number'),
            '用户' => $record->devices()->first()?->users()->value('name'),
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): string
    {
        return self::getUrl('view', ['record' => $record]);
    }

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
            'import',
            'export',
            'retire',
            'force_retire',
            'set_auto_asset_number_rule',
            'reset_auto_asset_number_rule',
            'set_retire_flow',
            'create_has_part',
            'delete_has_part',
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('images')
                    ->circular()
                    ->toggleable()
                    ->defaultImageUrl(('/images/default.jpg'))
                    ->label('照片'),
                Tables\Columns\TextColumn::make('asset_number')
                    ->searchable()
                    ->toggleable()
                    ->sortable()
                    ->label('资产编号'),
                Tables\Columns\TextColumn::make('brand.name')
                    ->searchable()
                    ->toggleable()
                    ->sortable()
                    ->label('品牌'),
                Tables\Columns\TextColumn::make('category.name')
                    ->searchable()
                    ->toggleable()
                    ->sortable()
                    ->label('分类'),
                Tables\Columns\TextColumn::make('sn')
                    ->searchable()
                    ->toggleable()
                    ->sortable()
                    ->label('序列号'),
                Tables\Columns\TextColumn::make('specification')
                    ->searchable()
                    ->toggleable()
                    ->sortable()
                    ->label('规格'),
                Tables\Columns\TextColumn::make('status')
                    ->toggleable()
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(function ($state) {
                        return AssetEnum::statusText($state);
                    })
                    ->color(function ($state) {
                        return AssetEnum::statusColor($state);
                    })
                    ->label('状态'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->multiple()
                    ->options(PartCategoryService::pluckOptions())
                    ->label('分类'),
                Tables\Filters\SelectFilter::make('brand_id')
                    ->multiple()
                    ->options(BrandService::pluckOptions())
                    ->label('品牌'),
                Tables\Filters\SelectFilter::make('status')
                    ->multiple()
                    ->options(AssetEnum::allStatusText())
                    ->label('状态'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    // 流程报废
                    PartAction::retire()
                        ->visible(function () {
                            $can = auth()->user()->can('retire_part');

                            return $can && PartService::isSetRetireFlow();
                        }),
                    // 强制报废
                    PartAction::forceRetire()
                        ->visible(function () {
                            return auth()->user()->can('force_retire_part');
                        }),
                ]),
            ])
            ->bulkActions([

            ])
            ->headerActions([
                // 导入
                ImportAction::make()
                    ->importer(PartImporter::class)
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->label('导入')
                    ->visible(function () {
                        return auth()->user()->can('import_part');
                    }),
                // 导出
                ExportAction::make()
                    ->label('导出')
                    ->visible(function () {
                        return auth()->user()->can('export_part');
                    }),
                // 创建
                PartAction::create()->visible(function () {
                    return auth()->user()->can('create_part');
                }),
                Tables\Actions\ActionGroup::make([
                    // 前往配件分类
                    PartAction::toCategories(),
                    // 配置资产编号自动生成
                    PartAction::setAssetNumberRule()
                        ->visible(function () {
                            return auth()->user()->can('set_auto_asset_number_rule_part');
                        }),
                    // 重置资产编号配置流程
                    PartAction::resetAssetNumberRule()
                        ->visible(function () {
                            return auth()->user()->can('reset_auto_asset_number_rule_part');
                        }),
                    // 配置配件报废流程
                    PartAction::setRetireFlow()
                        ->visible(function () {
                            return auth()->user()->can('set_retire_flow_part');
                        }),
                ])
                    ->label('高级')
                    ->icon('heroicon-m-cog-8-tooth')
                    ->button(),
            ])
            ->heading('配件');
    }

    public static function form(Form $form): Form
    {
        return $form->schema(PartForm::createOrEdit());
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Group::make()->schema([
                Section::make()
                    ->schema([
                        Split::make([
                            Grid::make()
                                ->schema([
                                    Group::make([
                                        TextEntry::make('asset_number')
                                            ->badge()
                                            ->color('primary')
                                            ->hint(function (Part $part) {
                                                return AssetEnum::statusText($part->getAttribute('status'));
                                            })
                                            ->hintColor(function (Part $part) {
                                                return AssetEnum::statusColor($part->getAttribute('status'));
                                            })
                                            ->label('资产编号'),
                                        TextEntry::make('category.name')
                                            ->label('分类'),
                                    ]),
                                    Group::make([
                                        TextEntry::make('sn')
                                            ->label('序列号'),
                                        TextEntry::make('brand.name')
                                            ->label('品牌'),
                                        TextEntry::make('specification')
                                            ->label('规格'),
                                    ]),
                                ]),
                        ]),
                    ]),
                Section::make()->schema([
                    TextEntry::make('description')
                        ->label('说明'),
                ]),
                Section::make()->schema([
                    RepeatableEntry::make('additional')
                        ->schema([
                            TextEntry::make('name')
                                ->columnSpan(1)
                                ->hiddenLabel(),
                            TextEntry::make('text')
                                ->columnSpan(1)
                                ->hiddenLabel(),
                        ])
                        ->grid()
                        ->columns()
                        ->label('额外信息'),
                ]),
            ])->columnSpan(['lg' => 2]),
            Group::make()->schema([
                Section::make()
                    ->schema([
                        ImageEntry::make('image')
                            ->disk('public')
                            ->height(300)
                            ->defaultImageUrl(('/images/default.jpg'))
                            ->label('照片'),
                    ]),
            ])->columnSpan(['lg' => 1]),
        ])->columns(3);
    }

    public static function getPages(): array
    {
        return [
            'index' => Index::route('/'),
            'edit' => Edit::route('/{record}/edit'),
            'view' => View::route('/{record}'),
            'parts' => HasPart::route('{record}/has_parts'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
