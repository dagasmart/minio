<?php

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use DagaSmart\Minio\Http\Controllers\MinioController;
use DagaSmart\Minio\Http\Controllers\BucketController;
use DagaSmart\Minio\Http\Controllers\ObjectController;

Route::group([
    'prefix' => 'biz',
    'middleware' => [],
], function (Router $router) {
    $router->resource('minio', MinioController::class);

    /*
    |------------------------------------------------------------------
    | Bucket（桶）管理
    |------------------------------------------------------------------
    */
    $router->resource('buckets', BucketController::class)
        ->except(['create', 'edit', 'show']);

    // 设置桶访问权限（PUBLIC / PRIVATE）
    $router->put('buckets/{bucket}/access', [BucketController::class, 'access']);

    /*
    |------------------------------------------------------------------
    | Object（文件）管理
    |------------------------------------------------------------------
    */
    // 获取桶内文件列表
    $router->get('buckets/{bucket}/objects', [ObjectController::class, 'index']);

    // 上传文件到指定桶
    $router->post('buckets/{bucket}/objects', [ObjectController::class, 'upload']);

    // 删除桶内指定文件
    $router->delete('buckets/{bucket}/objects/{key}', [ObjectController::class, 'delete']);
});
