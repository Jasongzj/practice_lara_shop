<?php

namespace App\SearchBuilders;

use App\Models\Category;

class ProductSearchBuilder
{
    protected $params = [
        'index' => 'products',
        'type'  => '_doc',
        'body'  => [
            'query' => [
                'bool' => [
                    'filter' => [],
                    'must'   => [],
                ],
            ],
        ],
    ];

    /**
     * 分页器
     * @param $size
     * @param $page
     * @return $this
     */
    public function paginate($size, $page)
    {
        $this->params['body']['from'] = ($page - 1) * $size;
        $this->params['body']['size'] = $size;

        return $this;
    }

    /**
     * 筛选上架商品
     * @return $this
     */
    public function onSale()
    {
        $this->params['body']['query']['bool']['filter'][] = ['term' => ['on_sale' => true]];

        return $this;
    }

    /**
     * 按类目搜索
     * @param Category $category
     * @return $this
     */
    public function category(Category $category)
    {
        // 如果是父类目，则查询类目下所有子类目
        if ($category->is_directory) {
            $this->params['body']['query']['bool']['filter'][] = [
                'prefix' => ['category_path' => $category->path.$category->id.'-'],
            ];
        } else {
            $this->params['body']['query']['bool']['filter'][] = [
                'term' => ['category_id' => $category->id],
            ];
        }

        return $this;
    }

    /**
     * 关键词搜索
     * @param $keywords
     * @return $this
     */
    public function keywords($keywords)
    {
        $keywords = is_array($keywords) ? $keywords : [$keywords];
        foreach ($keywords as $keyword) {
            $this->params['body']['query']['bool']['must'][] = [
                'multi_match' => [
                    'query' => $keyword,
                    'fields' => [
                        'title^3',
                        'long_title^2',
                        'category^2',
                        'description',
                        'skus_title',
                        'skus_description',
                        'properties_value',
                    ],
                ],
            ];
        }

        return $this;
    }

    /**
     * 分面搜索的聚合
     * @return $this
     */
    public function aggregateProperties()
    {
        $this->params['body']['aggs'] = [
            'properties' => [
                'nested' => [
                    'path' => 'properties',
                ],
                'aggs'   => [
                    'properties' => [
                        'terms' => [
                            'field' => 'properties.name',
                        ],
                        'aggs'  => [
                            'value' => [
                                'terms' => [
                                    'field' => 'properties.value',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        return $this;
    }

    /**
     * 筛选商品属性
     * @param $name
     * @param $value
     * @return $this
     */
    public function propertyFilter($name, $value)
    {
        $this->params['body']['query']['bool']['filter'][] = [
            'nested' => [
                'path' => 'properties',
                'query' => [
                    ['term' => ['properties.search_value' => $name . ':' . $value]],
                ],
            ],
        ];
        return $this;
    }

    /**
     * 排序查询
     * @param $field
     * @param $direction
     * @return $this
     */
    public function orderBy($field, $direction)
    {
        if (!isset($this->params['body']['sort'])) {
            $this->params['body']['sort'] = [];
        }

        $this->params['body']['sort'][] = [$field => $direction];
        return $this;
    }

    public function getParams()
    {
        return $this->params;
    }
}
