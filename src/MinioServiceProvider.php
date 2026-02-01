<?php

namespace DagaSmart\Minio;

use DagaSmart\BizAdmin\Extend\ServiceProvider;
use DagaSmart\Minio\Services\MinioService;

/**
 * OwlMinio 扩展服务提供者
 *
 * 职责：
 * - 注册 MinIO 扩展菜单
 * - 合并并发布配置文件
 * - 向容器注册 MinioService
 * - 加载扩展路由
 */
class MinioServiceProvider extends ServiceProvider
{
    /**
     * 扩展后台菜单
     */
    protected $menu = [
        [
            'title' => 'MinIO 管理',
            'url'   => '/minio',
            'icon'  => 'tabler:bucket',
        ],
    ];

    /**
     * 注册服务（配置、容器绑定）
     */
    public function register()
    {
        parent::register();

        // 先合并扩展内部默认配置到应用 config 中
        // 注意路径必须正确
        $this->mergeConfigFrom(__DIR__ . '/Config/minio.php', 'minio');

        // 从合并后的 minio 配置中构建 MinioService
        $this->app->singleton(MinioService::class, function ($app) {
            $config = config('minio.disks.default', []);
            return new MinioService($config);
        });

        /**
         * 注册容器别名，便于通过 app('admin.minio') 调用
         */
        $this->app->alias(MinioService::class, 'admin.minio');
    }

    /**
     * 启动阶段：发布资源 & 加载路由
     */
    public function boot()
    {
        parent::boot();

        /**
         * 发布配置文件
         *
         * php artisan vendor:publish --tag=minio-config
         */
        $this->publishes([
            __DIR__ . '/Config/minio.php' => config_path('minio.php'),
        ], 'minio-config');

        // 自动加载扩展路由
        $this->loadRoutesFrom(__DIR__ . '/Http/routes.php');
    }
}
