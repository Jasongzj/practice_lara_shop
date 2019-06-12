<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidRequestException;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\SearchBuilders\ProductSearchBuilder;
use App\Services\ProductService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductsController extends Controller
{
    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = 16;
        // 新建查询构造器对象，设置只搜索上架商品，设置分页
        $builder =  (new ProductSearchBuilder())->onSale()->paginate($perPage, $page);

        // 如果传入 category_id，并存在对应类目
        if ($request->input('category_id') && $category = Category::query()->find($request->input('category_id'))) {
            $builder->category($category);
        }

        // 判断是否有提交search参数，如果有就赋值给search变量
        if ($search = $request->input('search', '')) {
            $keywords = array_filter(explode(' ', $search));
            $builder->keywords($keywords);
        }

        // 如果有排序要求，则赋值给 order变量
        if ($order = $request->input('order', '')) {
            // 是否以_asc或者_desc结尾
            if (preg_match('/^(.+)_(asc|desc)$/', $order, $m)) {
                // 如果开头满足以下数组的值，则是合法的排序值
                if (in_array($m[1], ['price', 'sold_count', 'rating'])) {
                    $builder->orderBy($m[1], $m[2]);
                }
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
                $builder->propertyFilter($name, $value);
            }
        }

        if ($search || isset($category)) {
            $builder->aggregateProperties();
        }

        $result = app('es')->search($builder->getParams());
        $productIds = collect($result['hits']['hits'])->pluck('_id')->all();
        $products = Product::query()
            ->byIds($productIds)
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

    public function show(Product $product, Request $request, ProductService $productService)
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

        $similarProductIds = $productService->getSimilarProductIds($product, 4);
        $similarProducts = Product::query()
            ->byIds($similarProductIds)
            ->get();

        return view('products.show', [
            'product' => $product,
            'favored' => $favored,
            'reviews' => $reviews,
            'similar' => $similarProducts,
        ]);
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
