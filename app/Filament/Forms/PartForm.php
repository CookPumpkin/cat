<?php

namespace App\Filament\Forms;

use App\Models\Part;
use App\Services\AssetNumberRuleService;
use App\Services\PartService;
use App\Services\RoleService;
use Awcodes\Shout\Components\Shout;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Ramsey\Uuid\Uuid;

class PartForm
{
    /**
     * 创建或编辑.
     */
    public static function createOrEdit(): array
    {
        return [
            Hidden::make('creator_id')
                ->default(auth()->id()),
            TextInput::make('asset_number')
                ->maxLength(255)
                ->label(__('cat/part.asset_number'))
                ->required(function () {
                    return ! AssetNumberRuleService::isAuto(Part::class);
                })
                ->readOnly(function () {
                    return AssetNumberRuleService::isAuto(Part::class);
                })
                ->hintAction(
                    Action::make(__('cat/part.action.asset_number.create_helper'))
                        ->icon('heroicon-o-arrow-path-rounded-square')
                        ->visible(function () {
                            return AssetNumberRuleService::isAuto(Part::class);
                        })
                ),
            Select::make('category_id')
                ->relationship('category', 'name')
                ->label(__('cat/part.category_id'))
                ->searchable()
                ->preload()
                ->createOptionForm(PartCategoryForm::createOrEdit())
                ->required(),
            Select::make('brand_id')
                ->relationship('brand', 'name')
                ->label(__('cat/part.brand_id'))
                ->searchable()
                ->preload()
                ->createOptionForm(BrandForm::createOrEdit())
                ->required(),
            TextInput::make('sn')
                ->maxLength(255)
                ->label(__('cat/part.sn')),
            TextInput::make('specification')
                ->maxLength(255)
                ->label(__('cat/part.specification')),
            FileUpload::make('image')
                ->label(__('cat/part.image'))
                ->directory('parts')
                ->getUploadedFileNameForStorageUsing(
                    function (TemporaryUploadedFile $file) {
                        return Uuid::uuid4().'.'.$file->getClientOriginalExtension();
                    }
                ),
            Textarea::make('description')
                ->label(__('cat/part.description')),
            Repeater::make('additional')
                ->schema([
                    TextInput::make('name')
                        ->label(__('cat/part.additional.name')),
                    TextInput::make('text')
                        ->label(__('cat/part.additional.text')),
                ])
                ->defaultItems(0)
                ->label(__('cat/part.additional')),
        ];
    }

    /**
     * 配置配件废弃流程.
     */
    public static function setRetireFlow(): array
    {
        return [
            Hidden::make('creator_id')
                ->default(auth()->id()),
            Hidden::make('name')
                ->default('part_retire_flow'),
            Hidden::make('slug')
                ->default('retire_flow'),
            Hidden::make('model_name')
                ->default(Part::class),
            Shout::make('')
                ->color('info')
                ->content(__('cat/part.form.set_retire_flow_helper')),
            Repeater::make('nodes')
                ->simple(
                    Select::make('role_id')
                        ->options(RoleService::pluckOptions())
                        ->required()
                        ->label(__('cat/flow.role_id')),
                )
                ->default(function () {
                    return PartService::getRetireFlow()
                        ?->nodes
                        ->pluck('role_id')
                        ->toArray() ?? [];
                })
                ->addActionLabel(__('cat/part.action.add_node'))
                ->hiddenLabel(),
        ];
    }

    /**
     * 配置资产编号自动生成规则.
     */
    public static function setAssetNumberRule(): array
    {
        return [
            Select::make('asset_number_rule_id')
                ->label(__('cat/part.asset_number_rule_id'))
                ->options(AssetNumberRuleService::pluckOptions())
                ->required()
                ->default(AssetNumberRuleService::getAutoRule(Part::class)?->getAttribute('id')),
            Checkbox::make('is_auto')
                ->label(__('cat/part.is_auto'))
                ->default(AssetNumberRuleService::getAutoRule(Part::class)?->getAttribute('is_auto')),
        ];
    }

    /**
     * 流程废弃.
     */
    public static function retire(): array
    {
        return [
            Hidden::make('applicant_id')
                ->default(auth()->id()),
            Hidden::make('creator_id')
                ->default(auth()->id()),
            Shout::make('')
                ->color('danger')
                ->content(__('cat/part.form.retire_helper')),
            TextInput::make('comment')
                ->label(__('cat/part.form.retire_comment'))
                ->required(),
        ];
    }
}
