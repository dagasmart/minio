<?php
namespace DagaSmart\Minio\Facades;
use Illuminate\Support\Facades\Facade;

class Minio extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'minio';
    }
}
