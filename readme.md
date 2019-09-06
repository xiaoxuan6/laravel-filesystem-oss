# aliyun oss filesystem storage for laravel
Aliyun oss filesystem storage adapter for laravel. You can use Aliyun OSS just like laravel Storage as usual.    

## Require
- Laravel 5.*

##Installation
you can simply run below command to install:

    "composer require james.xue/laravel-filesystem-oss"

## Configuration
Add the following in app/filesystems.php:
```php
'disks'=>[
    ...
    'oss' => [
            'driver'        => 'oss',
            'access_id'     => '<Your Aliyun OSS AccessKeyId>',
            'access_key'    => '<Your Aliyun OSS AccessKeySecret>',
            'bucket'        => '<OSS bucket name>',
            'endpoint'      => '<the endpoint of OSS, E.g: oss-cn-hangzhou.aliyuncs.com | custom domain, E.g:img.abc.com>', // OSS 外网节点或自定义外部域名
            //'endpoint_internal' => '<internal endpoint [OSS内网节点] 如：oss-cn-shenzhen-internal.aliyuncs.com>', // 如果为空，则默认使用 endpoint 配置
            'cdnDomain'     => '<CDN domain, cdn域名>', // 如果isCName为true, getUrl会判断cdnDomain是否设定来决定返回的url，如果cdnDomain未设置，则使用endpoint来生成url，否则使用cdn
            'ssl'           => <true|false> // true to use 'https://' and false to use 'http://'. default is false,
            'isCName'       => <true|false> // 是否使用自定义域名,true: 则Storage.url()会使用自定义的cdn或域名生成文件url， false: 则使用外部节点生成url
            'prefix'        => ''
    ],
    ...
]
```

Ok, well! You are finish to configure. Just feel free to use Aliyun OSS like Storage!

## Usage
```php
Storage::disk('oss'); // if default filesystems driver is oss, you can skip this step

//fetch all files of specified bucket(see upond configuration)

Storage::put('path/to/file/file.jpg', $contents); //first parameter is the target file path, second paramter is file content
Storage::putFile('path/to/file/file.jpg', 'local/path/to/local_file.jpg'); // upload file from local path

Storage::get('path/to/file/file.jpg'); // get the file object by path
Storage::size('path/to/file/file.jpg'); // get the file size (Byte)

Storage::copy('old/file1.jpg', 'new/file1.jpg');
Storage::rename('path/to/file1.jpg', 'path/to/file2.jpg');

Storage::delete('file.jpg');
Storage::delete(['file1.jpg', 'file2.jpg']);

Storage::putRemoteFile('target/path/to/file/jacob.jpg', 'http://example.com/jacob.jpg'); //upload remote file to storage by remote url
Storage::url('path/to/img.jpg') // get the file url
```

## Documentation
More development detail see [Aliyun OSS DOC](https://help.aliyun.com/document_detail/32099.html?spm=5176.doc31981.6.335.eqQ9dM)
## License
Source code is release under MIT license. Read LICENSE file for more information.
