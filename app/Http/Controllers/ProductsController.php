<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidRequestException;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductsController extends Controller
{
    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = 16;

        $params = [
            'index' => 'products',
            'type'  => '_doc',
            'body'  => [
                'from' => ($page - 1) * $perPage,
                'size' => $perPage,
                'query' => [
                    'bool' => [
                        'filter' => [
                            ['term' => ['on_sale' => true]],
                        ],
                    ],
                ],
            ],
        ];

        // 如果有排序要求，则赋值给 order变量
        if ($order = $request->input('order', '')) {
            // 是否以_asc或者_desc结尾
            if (preg_match('/^(.+)_(asc|desc)$/', $order, $m)) {
                // 如果开头满足以下数组的值，则是合法的排序值
                if (in_array($m[1], ['price', 'sold_count', 'rating'])) {
                    $params['body']['sort'] = [$m[1] => $m[2]];
                }
            }
        }

        // 如果传入 category_id，并存在对应类目
        if ($request->input('category_id') && $category = Category::query()->find($request->input('category_id'))) {
            // 如果是父类目，则查询类目下所有子类目
            if ($category->is_directory) {
                $params['body']['query']['bool']['filter'][] = [
                    'prefix' => ['category_path' => $category->path . $category->id . '-'],
                ];
            } else {
                $params['body']['query']['bool']['filter'][] = ['term' => ['category_id' => $category->id]];
            }
        }

        // 判断是否有提交search参数，如果有就赋值给search变量
        if ($search = $request->input('search', '')) {
            $keywords = array_filter(explode(' ', $search));
            foreach ($keywords as $keyword) {
                $params['body']['query']['bool']['must'][] = [
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
        }

        $propertyFilters = [];
        if ($filterString = $request->input('filters')) {
            $filterArray = explode('|', $filterString);
            foreach ($filterArray as $filter) {
                list($name, $value) = explode(':', $filter);

                // 将用户筛选的属性添加到数组中
                $propertyFilters[$name] = $value;

                // 添加到 filter 类型中
                $params['body']['query']['bool']['filter'][] = [
                    // 由于我们要筛选的是 nested 类型下的属性，因此需要用 nested 查询
                    'nested' => [
                        // 指明 nested 字段
                        'path'  => 'properties',
                        'query' => [
                            ['term' => ['properties.search_value' => $filter]],
                        ],
                    ],
                ];
            }
        }

        if ($search || isset($category)) {
            $params['body']['aggs'] = [
                'properties' => [
                    'nested' => [
                        'path' => 'properties',
                    ],
                    'aggs' => [
                        'properties' => [
                            'terms' => [
                                'field' => 'properties.name',
                            ],
                            'aggs' => [
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
        }

        $result = app('es')->search($params);
        $productIds = collect($result['hits']['hits'])->pluck('_id')->all();
        $products = Product::query()
            ->whereIn('id', $productIds)
            ->orderByRaw(sprintf("FIND_IN_SET(id, '%s')", join(',', $productIds)))
            ->get();

        $properties = [];
        // 如果返回结果有 aggregations 字段，说明做了分面搜索
        if (isset($result['aggregations'])) {
            $properties = collect($result['aggregations']['properties']['properties']['buckets'])
                ->map(function ($bucket) {
                    return [
                        'key' => $bucket['key'],
                        'values' => collect($bucket['value']['buckets'])->pluck('key')->all(),
                    ];
                })
                ->filter(function ($property) use ($propertyFilters) {
                    return count($property['values']) > 1 && !isset($propertyFilters[$property['key']]);
                });
        }

        $pageOption = [
            'path' => route('products.index', false), // 手动构建分页url
        ];
        $pager = new LengthAwarePaginator($products, $result['hits']['total'], $perPage, $page, $pageOption);

        return view('products.index', [
            'products' => $pager,
            'filters' => [
                'search' => $search,
                'order' => $order,
            ],
            'category' => $category ?? null,
            'properties' => $properties,
            'propertiesFilters' => $propertyFilters,
        ]);
    }

    public function show(Product $product, Request $request)
    {
        if (!$product->on_sale) {
            throw new InvalidRequestException('商品未上架');
        }

        $favored = false;
        if ($user = $request->user()) {
            $favored = boolval($user->favoriteProducts->find($product->id));
        }

        $reviews = OrderItem::query()->with(['order.user', 'productSku'])
            ->where('product_id', $product->id)
            ->whereNotNull('reviewed_at')
            ->orderBy('reviewed_at', 'desc')
            ->limit(10)
            ->get();

        return view('products.show', ['product' => $product, 'favored' => $favored, 'reviews' => $reviews]);
    }

    public function favor(Product $product, Request $request)
    {
        $user = $request->user();
        if ($user->favoriteProducts()->find($product->id)) {
            return [];
        }

        $user->favoriteProducts()->attach($product);

        return [];
    }

    public function disFavor(Product $product, Request $request)
    {
        $user = $request->user();
        $user->favoriteProducts()->detach($product);

        return [];
    }

    public function favorites(Request $request)
    {
        $products = $request->user()->favoriteProducts()->paginate(16);

        return view('products.favorites', ['products' => $products]);
    }
}
