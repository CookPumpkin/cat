<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ImportResource\Pages\FailedImportRows;
use App\Filament\Resources\ImportResource\Pages\Index;
use App\Filament\Resources\ImportResource\Pages\View;
use App\Models\Import;
use Filament\Forms\Form;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Split;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ImportResource extends Resource
{
    protected static ?string $model = Import::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $modelLabel = '导入日志';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationGroup = '日志';

    public static function getRecordSubNavigation(Page $page): array
    {
        $navigation_items = [
            Index::class,
            View::class,
            FailedImportRows::class,
        ];

        return $page->generateNavigationItems($navigation_items);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('completed_at')
                    ->searchable()
                    ->toggleable()
                    ->label('完成时间'),
                Tables\Columns\TextColumn::make('file_name')
                    ->searchable()
                    ->toggleable()
                    ->label('文件'),
                Tables\Columns\TextColumn::make('processed_rows')
                    ->searchable()
                    ->toggleable()
                    ->label('已处理行'),
                Tables\Columns\TextColumn::make('total_rows')
                    ->searchable()
                    ->toggleable()
                    ->label('总行'),
                Tables\Columns\TextColumn::make('successful_rows')
                    ->searchable()
                    ->toggleable()
                    ->label('成功行'),
                Tables\Columns\TextColumn::make('user_id')
                    ->searchable()
                    ->toggleable()
                    ->label('执行用户'),
            ])
            ->filters([
                //
            ])
            ->actions([

            ])
            ->bulkActions([

            ]);
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
                                        TextEntry::make('completed_at')
                                            ->label('完成时间')
                                            ->badge()
                                            ->color('primary'),
                                        TextEntry::make('file_name')
                                            ->label('文件'),
                                        TextEntry::make('file_path')
                                            ->label('文件路径'),
                                        TextEntry::make('importer')
                                            ->label('导入器'),
                                    ]),
                                    Group::make([
                                        TextEntry::make('user_id')
                                            ->label('执行用户'),
                                        TextEntry::make('total_rows')
                                            ->label('总行'),
                                        TextEntry::make('processed_rows')
                                            ->label('已处理行'),
                                        TextEntry::make('successful_rows')
                                            ->label('成功行'),
                                    ]),
                                ]),
                        ]),
                    ]),
            ])->columnSpan(['lg' => 3]),
        ])->columns(3);
    }

    public static function getPages(): array
    {
        return [
            'index' => Index::route('/'),
            'view' => View::route('/{record}'),
            'failed_import_rows' => FailedImportRows::route('/{record}/failed_import_rows'),
        ];
    }

    public static function canEdit(Model $record): bool
    {
        return false;
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
