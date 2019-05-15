<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name', 'is_directory', 'level', 'is_path'];

    protected $casts = [
        'is_directory' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        // 监听创建事件，初始化 path 和 level 字段
        static::saving(function (Category $category) {
            // 如果是根目录
            if (is_null($category->parent_id)) {
                $category->level = 0;
                $category->path = '-';
            } else {
                $category->level = $category->parent->level + 1;
                // path 值为父类目的 path 值追加父类目 ID 以及一个 - 分隔符
                $category->path = $category->parent->path . $category->parent->id . '-';
            }
        });
    }

    public function parent()
    {
        return $this->belongsTo(Category::class);
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * 获取祖先类目的 id
     * @return array
     */
    public function getPathIdsAttribute()
    {
        // 去掉字符串两端的'-'
        // 以'-'分割字符串
        // 移除数组中的空值
        return array_filter(explode('-', trim($this->path, '-')));
    }

    /**
     * 获取所有祖先类目并按层级排序
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getAncestorsAttribute()
    {
        return Category::query()
            ->whereIn('id', $this->path_ids)
            ->orderBy('level')
            ->get();
    }

    /**
     * 获取以 - 分隔的所有祖先类目名称及当前类目名称
     * @return mixed
     */
    public function getFullNameAttribute()
    {
        return $this->ancestors
                    ->pluck('name')  // 获取祖先类目的 name 字段
                    ->push($this->name)  // 将当前类目的 name 加入数组末尾
                    ->implode(' - ');  // 将数组组合成字符串
    }
}
