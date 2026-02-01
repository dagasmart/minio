<?php

namespace DagaSmart\Minio\Http\Controllers;

use Illuminate\Http\Request;
use DagaSmart\Minio\Services\MinioService;

class ObjectController extends AdminController
{
    /**
     * MinIO Object（文件）管理控制器
     */

    /**
     * 获取 MinioService 实例
     */
    protected function minio(): MinioService
    {
        return app(MinioService::class);
    }

    /**
     * 校验并获取 bucket 参数
     */
    protected function getBucket(Request $request): string
    {
        $bucket = $request->route('bucket');

        if (!$bucket) {
            abort(400, 'Bucket name is required');
        }

        return $bucket;
    }

    /**
     * 统一异常返回（避免重复 try/catch）
     */
    protected function handleException(\Throwable $e)
    {
        return $this->response()->fail(
            method_exists($e, 'getAwsErrorMessage') && $e->getAwsErrorMessage()
                ? $e->getAwsErrorMessage()
                : $e->getMessage()
        );
    }

    /**
     * 获取指定桶下的对象列表（MinIO 不支持分页）
     */
    public function index()
    {
        try {
            $bucket  = $this->getBucket(request());
            $result  = $this->minio()->listObjects($bucket);
            $objects = $result['data'] ?? [];

            $data = array_map(function ($object) use ($bucket) {
                return [
                    'key'          => $object['Key'],
                    'url'          => $this->minio()->getObjectUrl($bucket, $object['Key']) ?? null,
                    'lastModified' => $object['LastModified'] ?? null,
                    'size'         => $this->formatSize((int) ($object['Size'] ?? 0)),
                ];
            }, $objects);

            return $this->response()->success($data);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * 上传文件到指定桶
     */
    public function upload($type = 'file')
    {
        try {
            $bucket = $this->getBucket(request());
            $file   = request()->file('file');

            if (!$file || !$file->isValid()) {
                abort(400, 'Invalid upload file');
            }

            $ext  = $file->getClientOriginalExtension();
            $key  = 'upload/' . date('Ymd') . '/' . uniqid('', true) . ($ext ? '.' . $ext : '');
            $size = $file->getSize();
            $mime = $file->getMimeType();

            $stream = fopen($file->getRealPath(), 'r');

            $this->minio()->putObject($bucket, $key, $stream, $mime);

            if (is_resource($stream)) {
                fclose($stream);
            }

            return $this->response()->success([
                'url'     => $this->minio()->getObjectUrl($bucket, $key) ?? null,
                'key'     => $key,
                'name'    => $file->getClientOriginalName(),
                'size'    => $this->formatSize($size),
                'rawSize' => $size,
            ], 'File uploaded successfully');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * 删除指定对象
     */
    public function delete(Request $request)
    {
        try {
            $bucket = $this->getBucket($request);
            $object = $request->route('key');

            if (!$object) {
                abort(400, 'Object key is required');
            }

            $this->minio()->deleteObject($bucket, $object);

            return $this->response()->success([], 'File deleted successfully');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * 文件大小格式化（B / KB / MB / GB）
     */
    private function formatSize(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' B';
        }
    }
}
