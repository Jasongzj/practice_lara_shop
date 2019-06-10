@extends('layouts.app')
@section('title', '商品列表')

@section('content')
    <div class="row">
        <div class="col-lg-10 offset-lg-1">
            <div class="card">
                <div class="card-body">
                    <!-- 筛选组件开始 -->
                    <form action="{{ route('products.index') }}" class="search-form">
                        <!-- 创建一个隐藏字段 -->
                        <input type="hidden" name="filters">
                        <div class="form-row">
                            <div class="col-md-9">
                                <div class="form-row">
                                    {{-- 面包屑开始 --}}
                                    <div class="col-auto category-breadcrumb">
                                        <a href="{{ route('products.index') }}" class="all-products">全部</a>
                                        @if($category)
                                        @foreach($category->ancestors as $ancestor)
                                            <span class="category">
                                                <a href="{{ route('products.index', ['category_id' => $ancestor->id]) }}">{{ $ancestor->name }}</a>
                                            </span>
                                            <span>&gt;</span>
                                        @endforeach
                                            {{-- 最后显示当前类目名称 --}}
                                            <span class="category">{{ $category->name }}</span><span> ></span>
                                            <input type="hidden" name="category_id" value="{{ $category->id }}">
                                        @endif
                                        <!-- 商品属性面包屑开始 -->
                                        @foreach($propertiesFilters as $name => $value)
                                            <span class="filter">{{ $name }}:
                                                <span class="filter-value">{{$value}}</span>
                                                <a href="javascript: removeFilterFromQuery('{{ $name }}')" class="remove-filter">x</a>
                                            </span>
                                        @endforeach
                                    </div>
                                    {{-- 面包屑结束 --}}
                                    <div class="col-auto"><input type="text" class="form-control form-control-sm" name="search" placeholder="搜索"></div>
                                    <div class="col-auto"><button class="btn btn-primary btn-sm">搜索</button></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select name="order" class="form-control form-control-sm float-right">
                                    <option value="">排序方式</option>
                                    <option value="price_asc">价格从低到高</option>
                                    <option value="price_desc">价格从高到低</option>
                                    <option value="sold_count_desc">销量从高到低</option>
                                    <option value="sold_count_asc">销量从低到高</option>
                                    <option value="rating_desc">评价从高到低</option>
                                    <option value="rating_asc">评价从低到高</option>
                                </select>
                            </div>
                        </div>
                    </form>
                    {{-- 展示子类目 --}}
                    <div class="filters">
                        @if($category && $category->is_directory)
                        <div class="row">
                            <div class="col-3 filter-key">子类目：</div>
                            <div class="col-9 filter-values">
                            @foreach($category->children as $child)
                                <a href="{{ route('products.index', ['category_id' => $child->id]) }}">
                                    {{ $child->name }}
                                </a>
                            @endforeach
                            </div>
                        </div>
                        @endif
                        <!-- 分面搜索结果开始 -->
                        <!-- 遍历聚合的商品属性 -->
                        @foreach($properties as $property)
                            <div class="row">
                                <div class="col-3 filter-key">{{ $property['key'] }}</div>
                                <div class="col-9 filter-values">
                                    @foreach($property['values'] as $value)
                                        <a href="javascript: appendFilterToQuery('{{ $property['key'] }}', '{{ $value }}')">{{ $value }}</a>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                    {{-- 展示子类目结束 --}}
                    <!-- 筛选组件结束 -->
                    <div class="row products-list">
                        @foreach($products as $product)
                            <div class="col-3 product-item">
                                <div class="product-content">
                                    <div class="top">
                                        <div class="img">
                                            <a href="{{ route('products.show', ['product' => $product->id]) }}">
                                                <img src="{{ $product->image_url }}" alt="">
                                            </a>
                                        </div>
                                        <div class="price"><b>￥</b>{{ $product->price }}</div>
                                        <div class="title">
                                            <a href="{{ route('products.show', ['product' => $product->id]) }}">
                                                {{ $product->title }}
                                            </a>
                                        </div>
                                    </div>
                                    <div class="bottom">
                                        <div class="sold_count">销量 <span>{{ $product->sold_count }}笔</span></div>
                                        <div class="review_count">评价 <span>{{ $product->review_count }}</span></div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="float-right">{{ $products->appends($filters)->render() }}</div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scriptsAfterJs')
    <script>
        var filters = {!! json_encode($filters) !!};
        $(document).ready(function () {
            $('.search-form input[name=search]').val(filters.search);
            $('.search-form select[name=order]').val(filters.order);
            $('.search-form select[name=order]').on('change', function () {
                // 解析当前查询参数
                var searches = parseSearch();
                // 如果有属性筛选
                if (searches['filters']) {
                    // 将属性值放入隐藏字段中
                    $('.search-form input[name=filters]').val(searches['filters']);
                }
                $('.search-form').submit();
            })
        })
        
        function parseSearch() {
            // 初始化一个空对象
            var searches = {};
            location.search.substr(1).split('&').forEach(function (str) {
                // 将字符串以 = 分割成数组
                var result = str.split('=');
                // 将数组第一个值解码后作为key，第二个值作为value放到初始化的对象中
                searches[decodeURIComponent(result[0])] = decodeURIComponent(result[1]);
            });

            return searches;
        }

        /**
         * 解析当前 url 的查询参数
         * @param searches
         * @returns {string}
         */
        function buildSearch(searches) {
            var query = '?';
            // 遍历searches对象
            _.forEach(searches, function (value, key) {
                query += encodeURIComponent(key) + '=' + encodeURIComponent(value) + '&';
            });
            // 去除末尾的 &
            return query.substr(0, query.length - 1);
        }

        /**
         * 添加分类查询
         * @param name
         * @param value
         */
        function appendFilterToQuery(name, value) {
            // 解析当前url 的查询参数
            var searches = parseSearch();
            
            if (searches['filters']) {
                // 在现有的filters 后面追加
                searches['filters'] += '|' + name + ':' + value;
            } else {
                searches['filters'] = name + ':' + value;
            }
            // 重新构建查询参数，并触发浏览器跳转
            location.search = buildSearch(searches);
        }

        function removeFilterFromQuery(name) {
            var searches = parseSearch();
            
            if (!searches['filters']) {
                return;
            }

            var filters = [];
            searches['filters'].split('|').forEach(function (filter) {
                // 解析出属性名和属性值
                var result = filter.split(':');
                // 如果属性名和要移除的一致，则不操作
                if (result[0] === name) {
                    return;
                }
                // 否则将该 filter 条件存入初始化的数组中
                filters.push(filter);
            });
            // 重建 searches 查询
            searches['filters'] = filters.join('|');
            // 重新构建查询参数，并触发浏览器跳转
            location.search = buildSearch(searches);
        }
    </script>
@endsection
