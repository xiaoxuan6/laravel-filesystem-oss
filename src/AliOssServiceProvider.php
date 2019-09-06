<?php

namespace James\AliOss;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use James\AliOss\Plugins\FileUrl;
use James\AliOss\Plugins\PutFile;
use James\AliOss\Plugins\PutRemoteFile;
use League\Flysystem\Filesystem;
use OSS\OssClient;

class AliOssServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        Storage::extend('oss', function($app, $config){
            $ssl = Arr::get($config, 'ssl', '');
            $isCname = Arr::get($config, 'isCName', '');
            $cdnDomain = Arr::get($config, 'cdnDomain', '');
            $endPoint = Arr::get($config, 'endPoint', '');
            $endpoint_internal = Arr::get($config, 'endpoint_internal', '');

            $epInternal= $isCname ? $cdnDomain: ($endpoint_internal ? $endpoint_internal : $config['endpoint']); // 内部节点

            $client = new OssClient($config['access_id'], $config['access_key'], $epInternal, $isCname);
            $adapter = new AliOssAdapter($client, $config['bucket'], $config['prefix'], $endPoint, $ssl, $isCname, $cdnDomain);

            $flysystem = new Filesystem($adapter, new Config(['disable_asserts' => true]));
            $flysystem->addPlugin(new PutFile());
            $flysystem->addPlugin(new PutRemoteFile());
            $flysystem->addPlugin(new FileUrl());
//            $flysystem->addPlugin(new PrivateDownloadUrl());
//            $flysystem->addPlugin(new RefreshFile());

            return $flysystem;
        });
    }
}
