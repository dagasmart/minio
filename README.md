# Owl Admin Extension（MinIO）

基于 BizAdmin 的 MinIO 管理扩展，
用于补充新版 MinIO Console 在后台管理场景下的不足。

📦 安装方式：

```
 composer require fanxd/owl-minio:^1.0
```

欢迎反馈建议，觉得有用的话点个 ⭐ 支持一下。


---


`.env` 示例：

```env
MINIO_ENDPOINT=http://127.0.0.1:9000
MINIO_ACCESS_KEY=minioadmin
MINIO_SECRET_KEY=minioadmin
```

---


# 🧪 BizAdmin 使用示例（ImageControl + MinIO 上传）

以下示例展示了如何在 **BizAdmin 表单中使用 AMIS ImageControl**，
将图片直接上传至 MinIO，并返回可访问的图片地址。

### 表单示例代码

```php
amis()->ImageControl('image', '图片')
        ->required(1)
        ->receiver([
            'url'    => admin_url('buckets/logo/objects'),
            'method' => 'post',
        ])
        ->accept('.jpg,.png,.jpeg')
        ->maxSize(2 * 1024 * 1024)
        ->remark('支持 jpg / png / jpeg，大小不超过 2MB'),
```

### 上传流程说明

1. 用户在 BizAdmin 表单中选择图片
2. AMIS 自动将文件 POST 到：
   ```
   /admin-api/buckets/logo/objects
   ```
3. 后端通过 MinIO `putObject` 上传文件
4. 接口返回图片访问 URL
5. ImageControl 自动回填该 URL 到表单字段

### 返回数据示例

```json
{
  "status": 0,
  "msg": "success",
  "data": {
    "value": "http://minio.example.com/logo/2024/01/xxx.jpg"
  }
}
```
<img width="2878" height="752" alt="1" src="https://github.com/user-attachments/assets/b58a2e30-cc73-467c-b513-18a3e193a62c" />

<img width="2878" height="752" alt="2" src="https://github.com/user-attachments/assets/aaf7b619-c290-4f65-bfdc-9e885e92681f" />

<img width="2878" height="760" alt="3" src="https://github.com/user-attachments/assets/b9612a65-417d-4da7-b808-501b15817c98" />

---

## 📄 License

MIT
