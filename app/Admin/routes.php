<?php

use Illuminate\Routing\Router;

Admin::registerAuthRoutes();

Route::group([
    'prefix'        => config('admin.route.prefix'),
    'namespace'     => config('admin.route.namespace'),
    'middleware'    => config('admin.route.middleware'),
], function (Router $router) {

    $router->get('/', 'HomeController@index');
    $router->get('users', 'UsersController@index');

    $router->get('products/create', 'ProductsController@create');
    $router->post('products', 'ProductsController@store');
    $router->get('products', 'ProductsController@index');
    $router->get('products/{id}/edit', 'ProductsController@edit');
    $router->put('products/{id}', 'ProductsController@update');

    $router->get('orders', 'OrdersController@index')->name('admin.orders.index');
    $router->get('orders/{order}', 'OrdersController@show')->name('admin.orders.show');
    $router->post('orders/{order}/ship', 'OrdersController@ship')->name('admin.orders.ship');
    $router->post('orders/{order}/refund', 'OrdersController@handleRefund')->name('admin.orders.handle_refund');

    $router->get('coupon_codes', 'CouponCodesController@index')->name('admin.coupon_codes.index');
    $router->post('coupon_codes', 'CouponCodesController@store')->name('admin.coupon_codes.store');
    $router->get('coupon_codes/create', 'CouponCodesController@create')->name('admin.coupon_codes.create');
    $router->get('coupon_codes/{id}/edit', 'CouponCodesController@edit')->name('admin.coupon_codes.edit');
    $router->put('coupon_codes/{id}', 'CouponCodesController@update')->name('admin.coupon_codes.update');
    $router->delete('coupon_codes/{id}', 'CouponCodesController@destroy')->name('admin.coupon_codes.destroy');

    $router->get('categories', 'CategoriesController@index')->name('admin.categories.index');
    $router->get('categories/create', 'CategoriesController@create')->name('admin.categories.create');
    $router->post('categories', 'CategoriesController@store')->name('admin.categories.store');
    $router->get('categories/{id}/edit', 'CategoriesController@edit')->name('admin.categories.edit');
    $router->put('categories/{id}', 'CategoriesController@update')->name('admin.categories.update');
    $router->delete('categories/{id}', 'CategoriesController@destroy')->name('admin.categories.destroy');
    $router->get('api/categories', 'CategoriesController@apiIndex')->name('admin.api.categories');

    $router->get('crowdfunding_products', 'CrowdfundingProductsController@index');
    $router->get('crowdfunding_products/create', 'CrowdfundingProductsController@create');
    $router->post('crowdfunding_products','CrowdfundingProductsController@store');
    $router->get('crowdfunding_products/{id}/edit', 'CrowdfundingProductsController@edit');
    $router->put('crowdfunding_products/{id}', 'CrowdfundingProductsController@update');
});
