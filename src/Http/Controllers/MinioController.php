<?php

namespace DagaSmart\Minio\Http\Controllers;

class MinioController extends AdminController
{
    /**
     * MinIO 管理入口页面控制器
     */

    /**
     * MinIO 管理首页
     */
    public function list()
    {
        return $this->bucket();
    }

    /**
     * Bucket 管理页面
     */
    public function bucket()
    {
        return amis()->Page()->body([
            $this->bucketTable(),
        ]);
    }

    /**
     * Bucket 列表表格
     */
    protected function bucketTable()
    {
        return amis()->CRUDTable()
            ->syncLocation('')
            ->api('get:/buckets')
            ->headerToolbar([
                $this->createBucketButton(),
            ])
            ->columns([
                amis()->Plain()->label('桶')->name('Name'),
                amis()->Plain()->label('访问')->name('Access'),
                amis()->Date()->label('创建时间')->name('CreationDate'),
                $this->bucketActions(),
            ]);
    }

    /**
     * 新建 Bucket 按钮
     */
    protected function createBucketButton()
    {
        return amis()->VanillaAction()
            ->label('新增')
            ->level('primary')
            ->actionType('dialog')
            ->dialog(
                amis()->Dialog()->title('新增 Bucket')->body(
                    amis()->Form()->api('post:/buckets')->body([
                        amis()->TextControl()
                            ->name('bucket')
                            ->label('桶名称')
                            ->required()
                            ->description('仅支持小写字母、数字和 -，长度 3–60'),
                    ])
                )
            );
    }

    /**
     * Bucket 操作列
     */
    protected function bucketActions()
    {
        return amis()->Operation()->label('操作')->buttons([
            $this->bucketAccessAction(),
            $this->bucketObjectsAction(),
            $this->bucketDeleteAction(),
        ]);
    }

    /**
     * Bucket 访问权限编辑
     */
    protected function bucketAccessAction()
    {
        return amis()->VanillaAction()
            ->label('访问权限')
            ->level('link')
            ->actionType('dialog')
            ->dialog(
                amis()->Dialog()->title('访问权限')->body(
                    amis()->Form()->api('put:/buckets/${Name}/access')->body([
                        amis()->TextControl()->label('桶')->name('Name')->disabled(),
                        amis()->SwitchControl()
                            ->name('access')
                            ->label('访问权限')
                            ->trueValue('CUSTOM')
                            ->falseValue('PRIVATE')
                            ->onText('公开')
                            ->offText('私有')
                            ->value('PRIVATE'),
                    ])
                )
            );
    }

    /**
     * 查看桶内对象
     */
    protected function bucketObjectsAction()
    {
        return amis()->VanillaAction()
            ->label('查看文件')
            ->level('link')
            ->actionType('drawer')
            ->drawer(
                amis()->Drawer()->title('对象列表')->size('lg')->body([
                    amis()->CRUDTable()
                        ->api('get:buckets/${Name}/objects')
                        ->columns([
                            amis()->Image()
                                ->enlargeAble('1')
                                ->label('预览')
                                ->name('url')
                                ->width(24)
                                ->height(24),
                            amis()->Plain()->label('Key')->name('key'),
                            amis()->Plain()->label('大小')->name('size'),
                            amis()->Operation()->buttons([
                                amis()->VanillaAction()
                                    ->label('删除')
                                    ->level('link')
                                    ->className('text-danger')
                                    ->confirmText('确定删除该文件？')
                                    ->actionType('ajax')
                                    ->api('delete:buckets/${Name}/objects/${key}'),
                            ]),
                        ]),
                ])
            );
    }

    /**
     * 删除 Bucket
     */
    protected function bucketDeleteAction()
    {
        return amis()->VanillaAction()
            ->label('删除')
            ->level('link')
            ->className('text-danger')
            ->confirmText('确定删除该桶？')
            ->actionType('ajax')
            ->api('delete:/buckets/${Name}');
    }
}
