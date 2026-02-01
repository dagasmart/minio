<?php

/**
 * MinIO 模块路由定义
 */

use Illuminate\Support\Facades\Route;
use DagaSmart\Minio\Http\Controllers\MinioController;
use DagaSmart\Minio\Http\Controllers\BucketController;
use DagaSmart\Minio\Http\Controllers\ObjectController;

/*
|--------------------------------------------------------------------------
| MinIO 页面入口
|--------------------------------------------------------------------------
*/
Route::resource('minio', MinioController::class);

/*
|--------------------------------------------------------------------------
| MinIO API（供 AMIS 使用）
|--------------------------------------------------------------------------
*/
Route::prefix('admin-api')->group(function () {

    /*
    |------------------------------------------------------------------
    | Bucket（桶）管理
    |------------------------------------------------------------------
    */
    Route::resource('buckets', BucketController::class)
        ->except(['create', 'edit', 'show']);

    // 设置桶访问权限（PUBLIC / PRIVATE）
    Route::put('buckets/{bucket}/access', [BucketController::class, 'access']);

    /*
    |------------------------------------------------------------------
    | Object（文件）管理
    |------------------------------------------------------------------
    */
    // 获取桶内文件列表
    Route::get('buckets/{bucket}/objects', [ObjectController::class, 'index']);

    // 上传文件到指定桶
    Route::post('buckets/{bucket}/objects', [ObjectController::class, 'upload']);

    // 删除桶内指定文件
    Route::delete('buckets/{bucket}/objects/{key}', [ObjectController::class, 'delete']);
});
