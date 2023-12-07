<?php

namespace App\Services;

use App\Models\AssetNumberRule;
use App\Models\Flow;
use App\Models\Setting;
use App\Models\Software;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use JetBrains\PhpStorm\ArrayShape;

class SoftwareService
{
    public Software $software;

    public function __construct(?Software $software = null)
    {
        $this->software = $software ?? new Software();
    }

    /**
     * 选单.
     */
    public static function pluckOptions(): Collection
    {
        return Software::query()->get()->mapWithKeys(function (Software $software) {
            $title = '';
            $title .= $software->getAttribute('asset_number');
            $title .= ' | '.$software->brand()->first()?->getAttribute('name') ?? ' | 未知';
            $title .= ' | '.$software->getAttribute('name');
            $title .= ' | '.$software->getAttribute('specification');
            $title .= ' | '.$software->category()->first()?->getAttribute('name') ?? ' | 闲置';
            if ($software->getAttribute('max_license_count') == 0) {
                $title .= ' - 无限制';
            } else {
                if ($software->getAttribute('max_license_count') > $software->usedCount()) {
                    $title .= ' | '.$software->usedCount().'/'.$software->getAttribute('max_license_count').' 已使用';
                } else {
                    $title .= ' | '.$software->usedCount().'/'.$software->getAttribute('max_license_count').' 已使用 | 软件授权数量不足';
                }
            }

            return [$software->getKey() => $title];
        });
    }

    /**
     * 判断是否配置报废流程.
     */
    public static function isSetRetireFlow(): bool
    {
        return Setting::query()
            ->where('custom_key', 'software_retire_flow_id')
            ->count();

    }

    /**
     * 创建设备-软件关联.
     *
     * @throws Exception
     */
    #[ArrayShape([
        'device_id' => 'int',
        'user_id' => 'int',
        'status' => 'int',
    ])]
    public function createHasSoftware(array $data): Model
    {
        if ($this->software->hasSoftware()->where('device_id', $data['device_id'])->count()) {
            throw new Exception('软件已经附加到此设备');
        }

        $max_license_count = $this->software->getAttribute('max_license_count');
        if ($max_license_count != 0 && $this->software->usedCount() >= $max_license_count) {
            throw new Exception('软件授权数量不足');
        }

        return $this->software->hasSoftware()->create($data);
    }

    /**
     * 新增软件.
     *
     * @throws Exception
     */
    #[ArrayShape([
        'asset_number' => 'string',
        'name' => 'string',
        'category_id' => 'int',
        'brand_id' => 'int',
        'sn' => 'string',
        'specification' => 'string',
        'image' => 'string',
        'max_license_count' => 'int',
    ])]
    public function create(array $data): void
    {
        // 开始事务
        DB::beginTransaction();
        try {
            $asset_number = $data['asset_number'];
            $asset_number_rule = AssetNumberRule::query()
                ->where('class_name', Software::class)
                ->first();
            /* @var AssetNumberRule $asset_number_rule */
            if ($asset_number_rule) {
                // 如果绑定了自动生成规则并且启用
                if ($asset_number_rule->getAttribute('is_auto')) {
                    $asset_number_rule_service = new AssetNumberRuleService($asset_number_rule);
                    $asset_number = $asset_number_rule_service->generate();
                    $asset_number_rule_service->addAutoIncrementCount();
                }
            }
            $this->software->setAttribute('asset_number', $asset_number);
            $this->software->setAttribute('name', $data['name']);
            $this->software->setAttribute('category_id', $data['category_id']);
            $this->software->setAttribute('brand_id', $data['brand_id']);
            $this->software->setAttribute('sn', $data['sn'] ?? '无');
            $this->software->setAttribute('specification', $data['specification'] ?? '无');
            $this->software->setAttribute('image', $data['image']);
            $this->software->setAttribute('max_license_count', $data['max_license_count']);
            $this->software->setAttribute('description', $data['description']);
            $this->software->save();
            $this->software->assetNumberTrack()
                ->create(['asset_number' => $asset_number]);
            // 写入事务
            DB::commit();
        } catch (Exception $exception) {
            // 回滚事务
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * 软件报废.
     *
     * @throws Exception
     */
    public function retire(): void
    {
        try {
            DB::beginTransaction();
            $this->software->hasSoftware()->delete();
            $this->software->delete();
            DB::commit();
        } catch (Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * 获取已配置的软件报废流程.
     *
     * @throws Exception
     */
    public function getRetireFlow(): Builder|Model
    {
        $flow_id = Setting::query()
            ->where('custom_key', 'software_retire_flow_id')
            ->value('custom_value');
        if (! $flow_id) {
            throw new Exception('还未配置软件报废流程');
        }
        $flow = Flow::query()->where('id', $flow_id)->first();
        if (! $flow) {
            throw new Exception('未找到已配置的软件报废流程');
        }

        return $flow;
    }
}
