<?php

namespace DagaSmart\Minio\Http\Controllers;

use Illuminate\Http\Request;
use DagaSmart\Minio\Services\MinioService;

class BucketController extends AdminController
{
    /**
     * MinIO Bucket 管理控制器
     */

    /**
     * 获取 MinioService 实例
     */
    protected function minio(): MinioService
    {
        return app(MinioService::class);
    }

    /**
     * 统一处理 MinIO Service 返回结果
     *
     * @param array  $result      MinioService 返回结构
     * @param string $successMsg  成功提示文案
     */
    protected function handleResult(array $result, string $successMsg)
    {
        if ($result['success'] ?? false) {
            return $this->response()->success(null, $successMsg);
        }

        return $this->response()->fail(
            $result['error'] ?? '操作失败'
        );
    }

    /**
     * 获取桶列表
     */
    public function index()
    {
        $result = $this->minio()->listBuckets();

        return $this->response()->success([
            'items' => $result['data'] ?? [],
            'total' => count($result['data'] ?? []),
        ]);
    }

    /**
     * 创建桶
     */
    public function store(Request $request)
    {
        $request->validate([
            'bucket' => [
                'required',
                'string',
                'regex:/^[a-z0-9][a-z0-9\-]{1,61}[a-z0-9]$/',
            ],
        ]);

        $bucket = $request->string('bucket');

        return $this->handleResult(
            $this->minio()->createBucket($bucket),
            "桶 {$bucket} 创建成功"
        );
    }

    /**
     * 删除桶（仅当桶为空时允许）
     *
     * OwlAdmin 约定 destroy 方法参数为 $ids
     * 这里将其视为 bucket 名称
     */
    public function destroy($ids)
    {
        $bucket = is_array($ids) ? $ids[0] : $ids;

        return $this->handleResult(
            $this->minio()->deleteBucket($bucket),
            "桶 {$bucket} 删除成功"
        );
    }

    /**
     * 设置桶访问权限（CUSTOM / PRIVATE）
     */
    public function access(string $bucket, Request $request)
    {
        $request->validate([
            'access' => 'required|in:CUSTOM,PRIVATE',
        ]);

        return $this->handleResult(
            $this->minio()->setBucketAccess($bucket, $request->string('access')),
            '桶访问权限更新成功'
        );
    }
}
