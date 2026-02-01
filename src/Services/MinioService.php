<?php
namespace DagaSmart\Minio\Services;


/**
 * MinioService
 *
 * 职责说明：
 * - 对 MinIO（S3 协议）进行统一封装
 * - 屏蔽 Aws\S3\S3Client 细节
 * - 统一返回结构，统一错误信息
 *
 * 注意：
 * - 该类不处理任何 HTTP / Controller 逻辑
 * - 所有方法仅返回结构化数组
 */
class MinioService
{
    /**
     * Client 实例（兼容 MinIO）
     */
    protected $client;

    /**
     * 构造函数
     *
     * @param array $config 配置项：
     *  - region: 区域，默认为 us-east-1
     *  - endpoint: MinIO 服务器地址
     *  - use_path_style_endpoint: 是否使用路径风格访问，默认 true
     *  - access_key: 访问密钥
     *  - secret_key: 密钥
     */
    public function __construct(array $config = [])
    {
        $this->client = new S3Client([
            'version'                 => 'latest',
            'region'                  => $config['region']                 ?? 'us-east-1',
            'endpoint'                => $config['endpoint']               ?? '',
            'use_path_style_endpoint' => $config['use_path_style_endpoint'] ?? true,
            'credentials'             => [
                'key'    => $config['access_key']  ?? '',
                'secret' => $config['secret_key']  ?? '',
            ],
        ]);
    }

    /**
     * 统一返回结构
     *
     * @param bool        $success 是否成功
     * @param mixed|null  $data    返回数据
     * @param string|null $error   错误信息（仅失败时）
     */
    private function result(bool $success, $data = null, ?string $error = null): array
    {
        return [
            'success' => $success,
            'data'    => $data,
            'error'   => $error,
        ];
    }

    /**
     * 将 AwsException 转换为可读错误信息
     *
     * 仅返回「人类可读」的错误，不暴露 SDK 细节
     */
    private function parseAwsException(AwsException $e): string
    {
        $code = $e->getAwsErrorCode();
        return match ($code) {
            'BucketNotEmpty'         => '桶不是空的，无法删除',
            'NoSuchBucket'           => '桶不存在',
            'BucketAlreadyExists'    => '桶已存在',
            'BucketAlreadyOwnedByYou'=> '桶已经属于您',
            'InvalidBucketName'      => '桶名称不合法',
            'AccessDenied'           => '没有权限操作该桶',
            default                  => $e->getAwsErrorMessage() ?? $e->getMessage(),
        };
    }

    /* ============================
    | Bucket（桶）相关
    |============================ */

    /**
     * 获取桶列表（不支持分页，MinIO 原生限制）
     */
    public function listBuckets(): array
    {
        try {
            $result = $this->client->listBuckets();

            $buckets = collect($result['Buckets'] ?? [])->map(function ($bucket) {
                return [
                    'Name'         => $bucket['Name'],
                    'Access'       => $this->getBucketAccess($bucket['Name']),
                    'CreationDate' => $bucket['CreationDate'] ?? null,
                ];
            })->values();

            return $this->result(true, $buckets ?? []);
        } catch (AwsException $e) {
            return $this->result(false, null, $this->parseAwsException($e));
        }
    }

    /**
     * 创建桶
     */
    public function createBucket(string $bucketName): array
    {
        try {
            $result = $this->client->createBucket([
                'Bucket' => $bucketName,
            ]);
            return $this->result(true, [
                'bucket'   => $bucketName,
                'location' => $result['Location'] ?? null,
            ]);
        } catch (AwsException $e) {
            return $this->result(false, null, $this->parseAwsException($e));
        }
    }

    /**
     * 删除桶（注意：桶必须为空）
     */
    public function deleteBucket(string $bucketName): array
    {
        try {
            $result = $this->client->deleteBucket([
                'Bucket' => $bucketName,
            ]);
            return $this->result(true, [
                'bucket'   => $bucketName,
                'location' => $result['Location'] ?? null,
            ]);
        } catch (AwsException $e) {
            return $this->result(false, null, $this->parseAwsException($e));
        }
    }

    /**
     * 获取桶的访问状态
     *
     * 规则说明：
     * - 能成功获取 Bucket Policy：
     *     - 若 policy 中包含公开读（Principal = * 且 Action 包含 s3:GetObject）→ PUBLIC
     *     - 否则 → CUSTOM（存在策略，但不是公开读）
     * - 获取 policy 时报错（NoSuchBucketPolicy）→ PRIVATE
     *
     * @return string PUBLIC | PRIVATE | CUSTOM
     */
    public function getBucketAccess(string $bucket): string
    {
        try {
            $result = $this->client->getBucketPolicy([
                'Bucket' => $bucket,
            ]);

            $policyText = $result['Policy'] ?? '';

            // 公开读判断
            if (
                str_contains($policyText, '"Principal":"*"')
                && str_contains($policyText, 's3:GetObject')
            ) {
                return 'PUBLIC';
            }

            // 有策略，但不是公开读
            return 'CUSTOM';
        } catch (AwsException $e) {
            // 没有策略，视为 PRIVATE
            if ($e->getAwsErrorCode() === 'NoSuchBucketPolicy') {
                return 'PRIVATE';
            }

            // 其他异常默认 PRIVATE
            return 'PRIVATE';
        }
    }

    /**
     * 设置桶访问权限（CUSTOM / PRIVATE）
     */
    public function setBucketAccess(string $bucket, string $access): array
    {
        try {
            if ($access === 'CUSTOM') {
                $this->client->putBucketPolicy([
                    'Bucket' => $bucket,
                    'Policy' => $this->publicPolicy($bucket),
                ]);
            } else {
                // PRIVATE：删除策略即可
                $this->client->deleteBucketPolicy([
                    'Bucket' => $bucket,
                ]);
            }

            return $this->result(true, null);
        } catch (AwsException $e) {
            return $this->result(false, null, $this->parseAwsException($e));
        }
    }

    /**
     * 生成公开读（Public Read）桶策略
     *
     * Version 使用 AWS S3 官方策略版本号（固定值）
     */
    private function publicPolicy(string $bucket): string
    {
        return json_encode([
            'Version'   => '2012-10-17',
            'Statement' => [[
                'Effect'    => 'Allow',
                'Principal' => '*',
                'Action'    => [
                    's3:GetObject',
                ],
                'Resource'  => [
                    "arn:aws:s3:::{$bucket}/*",
                ],
            ]],
        ], JSON_UNESCAPED_SLASHES);
    }

    /* ============================
    | Object（文件）相关
    |============================ */

    /**
     * 获取桶内文件列表
     */
    public function listObjects(string $bucket): array
    {
        try {
            $result = $this->client->listObjectsV2([
                'Bucket' => $bucket,
            ]);
            return $this->result(true, $result['Contents'] ?? []);
        } catch (AwsException $e) {
            return $this->result(false, null, $this->parseAwsException($e));
        }
    }

    /**
     * 获取对象访问地址
     *
     * - 若桶为 CUSTOM，返回可直接访问的 URL
     * - 若桶为 PRIVATE，返回临时访问 URL（默认 1 小时）
     */
    public function getObjectUrl(string $bucket, string $key, int $expires = 3600): string
    {
        try {
            // 判断桶访问权限
            $access = $this->getBucketAccess($bucket);

            if ($access === 'CUSTOM') {
                $endpoint = rtrim((string) $this->client->getEndpoint(), '/');
                return "{$endpoint}/{$bucket}/{$key}";
            }

            // PRIVATE：生成临时访问地址
            $cmd = $this->client->getCommand('GetObject', [
                'Bucket' => $bucket,
                'Key'    => $key,
            ]);

            $request = $this->client->createPresignedRequest($cmd, "+{$expires} seconds");

            return (string) $request->getUri();
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * putObject（支持流上传，推荐）
     */
    public function putObject(string $bucket, string $key, $body, ?string $contentType = null): array
    {
        try {
            $params = [
                'Bucket' => $bucket,
                'Key'    => $key,
                'Body'   => $body,
            ];

            if ($contentType) {
                $params['ContentType'] = $contentType;
            }

            $this->client->putObject($params);

            return $this->result(true, [
                'bucket' => $bucket,
                'key'    => $key,
            ]);
        } catch (AwsException $e) {
            return $this->result(false, null, $this->parseAwsException($e));
        }
    }

    /**
     * 删除文件
     */
    public function deleteObject(string $bucket, string $key): array
    {
        try {
            $this->client->deleteObject([
                'Bucket' => $bucket,
                'Key'    => $key,
            ]);
            return $this->result(true, true);
        } catch (AwsException $e) {
            return $this->result(false, null, $this->parseAwsException($e));
        }
    }
}
