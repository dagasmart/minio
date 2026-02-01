<?php

return [

    // 默认使用的 MinIO 磁盘
    'default' => 'default',

    // MinIO 磁盘配置
    'disks' => [

        'default' => [

            // MinIO 服务地址
            'endpoint' => env('MINIO_ENDPOINT', 'http://127.0.0.1:9000'),

            // Access Key
            'access_key' => env('MINIO_ACCESS_KEY', 'minioadmin'),

            // Secret Key
            'secret_key' => env('MINIO_SECRET_KEY', 'minioadmin'),

            // 默认 Bucket（可选）
            'bucket' => env('MINIO_BUCKET', 'default'),

            // 是否使用 path-style（MinIO 必须为 true）
            'use_path_style_endpoint' => true,
        ],
    ],
];
