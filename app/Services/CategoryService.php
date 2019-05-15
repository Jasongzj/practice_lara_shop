<?php

namespace App\Services;

use App\Models\Category;

class CategoryService
{
    /**
     * @param null $parentId 代表要获取子类目的父类目ID，null表示获取所有根类目
     * @param null $allCategories 表示数据库中所有的类目，null表示从数据库中查询
     * @return Category[]|\Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection
     */
    public function getCategoryTree($parentId = null, $allCategories = null)
    {
        if (is_null($allCategories)) {
            $allCategories = Category::all();
        }

        return $allCategories
            // 查询父类id为 $parentId 的类目
            ->where('parent_id', $parentId)
            // 遍历类目并返回一个新的集合
            ->map(function (Category $category) use ($allCategories) {
                $data = ['id' => $category->id, 'name' => $category->name];
                // 如果不是父类目，则直接返回
                if (!$category->is_directory) {
                    return $data;
                }
                // 递归获取父类目的子类
                $data['children'] = $this->getCategoryTree($category->id, $allCategories);
                return $data;
            });
    }
}
