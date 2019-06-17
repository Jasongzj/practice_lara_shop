<?php

namespace App\Providers;

use App\Http\ViewComposers\CategoryTreeComposer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Monolog\Logger;
use Yansongda\Pay\Pay;
use Elasticsearch\ClientBuilder as ESClientBuilder;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // 注册一个名为 alipay 的单例对象
        $this->app->singleton('alipay', function () {
            $config = config('pay.alipay');
            $config['return_url'] = route('payment.alipay.return');
            $config['notify_url'] = ngrok_url('payment.alipay.notify');

            // 判断当前环境是否生产环境
            if (app()->environment() !== 'production') {
                $config['mode'] = 'dev';
                $config['log']['level'] = Logger::DEBUG;
            } else {
                $config['log']['level'] = Logger::WARNING;
            }
            return Pay::alipay($config);
        });

        // 注册一个 wechat 支付单例对象
        $this->app->singleton('wechat_pay', function () {
            $config = config('pay.wechat');
            $config['notify_url'] = route('payment.wechat.notify');

            if (app()->environment() !== 'production') {
                $config['log']['level'] = Logger::DEBUG;
            } else {
                $config['log']['level'] = Logger::WARNING;
            }
            return Pay::wechat($config);
        });

        // 注册一个 es 的单例对象
        $this->app->singleton('es', function () {
             // 从配置文件读取 Elasticsearch 服务器列表
            $builder = ESClientBuilder::create()->setHosts(config('database.elasticsearch.hosts'));

            if (app()->environment() === 'local') {
                // 配置日志，Elasticsearch 的请求和返回数据打印到日志中，方便调试
                $builder->setLogger(app('log')->driver());
            }

            return $builder->build();
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        View::composer(['products.index', 'products.show'], CategoryTreeComposer::class);
        Carbon::setLocale('zh');

        if (app()->environment('local')) {
            DB::listen(function ($query) {
                Log::info(Str::replaceArray('?', $query->bindings, $query->sql));
            });
        }
    }
}
