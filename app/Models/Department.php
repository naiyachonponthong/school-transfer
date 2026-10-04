<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/** หน่วยงานของโรงเรียนในโครงสร้างองค์กร */
class Department extends Model
{
    public const KINDS = ['executive' => 'ผู้บริหาร', 'division' => 'ฝ่าย', 'group' => 'กลุ่มสาระ', 'unit' => 'งาน'];

    /** โครงสร้างมาตรฐานของโรงเรียน: ผู้อำนวยการ → 4 ฝ่าย → กลุ่มสาระ (อยู่ใต้ฝ่ายวิชาการ) */
    public const PRESET = [
        'ฝ่ายบริหารวิชาการ' => ['ACA', ['ภาษาไทย', 'คณิตศาสตร์', 'วิทยาศาสตร์และเทคโนโลยี', 'สังคมศึกษา ศาสนา และวัฒนธรรม',
            'สุขศึกษาและพลศึกษา', 'ศิลปะ', 'การงานอาชีพ', 'ภาษาต่างประเทศ', 'กิจกรรมพัฒนาผู้เรียน']],
        'ฝ่ายบริหารงบประมาณ' => ['BUD', []],
        'ฝ่ายบริหารงานบุคคล' => ['HR', []],
        'ฝ่ายบริหารทั่วไป' => ['GEN', []],
    ];

    protected $fillable = ['name', 'code', 'kind', 'parent_id', 'head_id', 'sort'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort')->orderBy('id');
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('is_primary')->withTimestamps()->orderBy('name');
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }

    /** สร้างโครงสร้างมาตรฐาน (ใช้เมื่อยังไม่มีหน่วยงานเลย) */
    public static function createPreset(): void
    {
        $root = self::create(['name' => 'ผู้อำนวยการโรงเรียน', 'code' => 'DIR', 'kind' => 'executive']);
        $i = 0;
        foreach (self::PRESET as $name => [$code, $groups]) {
            $division = self::create(['name' => $name, 'code' => $code, 'kind' => 'division', 'parent_id' => $root->id, 'sort' => ++$i]);
            foreach ($groups as $j => $group) {
                self::create(['name' => 'กลุ่มสาระ'.$group, 'kind' => 'group', 'parent_id' => $division->id, 'sort' => $j + 1]);
            }
        }
    }

    /**
     * id ของหน่วยงานนี้และหน่วยย่อยทุกชั้น
     *
     * @param  Collection<int, self>  $all  หน่วยงานทั้งหมด
     * @return array<int>
     */
    public static function descendantIds(int $id, Collection $all): array
    {
        $ids = [$id];
        foreach ($all->where('parent_id', $id) as $child) {
            array_push($ids, ...self::descendantIds($child->id, $all));
        }

        return $ids;
    }
}
