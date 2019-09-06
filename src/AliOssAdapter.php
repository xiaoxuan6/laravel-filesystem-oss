<?php
/**
 * Created by PhpStorm.
 * User: james.xue
 * Date: 2019/9/5
 * Time: 13:27
 */

namespace James\AliOss;

use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Adapter\Polyfill\NotSupportingVisibilityTrait;
use League\Flysystem\Adapter\Polyfill\StreamedTrait;
use League\Flysystem\Config;
use League\Flysystem\Util;
use OSS\OssClient;
use OSS\Core\OssException;

class AliOssAdapter extends AbstractAdapter
{
    use NotSupportingVisibilityTrait, StreamedTrait;

    /**
     * Aliyun Oss Client.
     *
     * @var \OSS\OssClient
     */
    protected $client;

    /**
     * @var string
     */
    protected $bucket;

    /**
     * @var array
     */
    protected $options = [];

    protected $ssl;
    protected $isCname;
    protected $cdnComain;
    protected $endPoint;

    /**
     * @var array
     */
    protected static $mappingOptions = [
        'mimetype' => OssClient::OSS_CONTENT_TYPE,
        'size'     => OssClient::OSS_LENGTH,
        'filename' => OssClient::OSS_CONTENT_DISPOSTION,
    ];

    /**
     * AliOssAdapter constructor.
     * @param $accessKey
     * @param $secretKey
     * @param $bucket
     * @param $domain
     */
    public function __construct(OssClient $client, $bucket, $prefix = null, $endPoint, $ssl, $isCname, $cdnDomain, array $options = [])
    {
        $this->client = $client;
        $this->bucket = $bucket;
        $this->setPathPrefix($prefix);
        $this->ssl = $ssl;
        $this->endPoint = $endPoint;
        $this->isCname = $isCname;
        $this->cdnDomain = $cdnDomain;
        $this->options = array_merge($this->options, $options);
    }

    /**
     * Notes: Get the Aliyun Oss Client bucket.
     * Date: 2019/9/6 11:09
     * @return string
     */
    public function getBucket()
    {
        return $this->bucket;
    }

    /**
     * Notes: Get the Aliyun Oss Client instance.
     * Date: 2019/9/6 11:10
     * @return OssClient|\OSS\OssClient
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Notes: 上传内存中的内容
     * Date: 2019/9/6 11:30
     * @param string $path
     * @param string $contents
     * @param Config $config
     * @return array|bool|false
     */
    public function write($path, $contents, Config $config)
    {
        $object = $this->applyPathPrefix($path);
        $options = $this->getOptions($this->options, $config);

        if (! isset($options[OssClient::OSS_LENGTH])) {
            $options[OssClient::OSS_LENGTH] = Util::contentSize($contents);
        }
        if (! isset($options[OssClient::OSS_CONTENT_TYPE])) {
            $options[OssClient::OSS_CONTENT_TYPE] = Util::guessMimeType($path, $contents);
        }
        try {
            $this->client->putObject($this->bucket, $object, $contents, $options);
        } catch (OssException $e) {
            return false;
        }
        return $this->normalizeResponse($options, $path);
    }

    /**
     * Write a new file using a stream.
     *
     * @param string $path
     * @param resource $resource
     * @param Config $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function writeStream($path, $resource, Config $config)
    {
        $contents = stream_get_contents($resource);

        return $this->write($path, $contents, $config);
    }

    /**
     * Notes: 上传本地文件
     * Date: 2019/9/6 11:27
     * @param $path
     * @param $filePath
     * @param Config $config
     * @return bool
     */
    public function writeFile($path, $filePath, Config $config)
    {
        $object = $this->applyPathPrefix($path);
        $options = $this->getOptions($this->options, $config);

        $options[OssClient::OSS_CHECK_MD5] = true;

        if (! isset($options[OssClient::OSS_CONTENT_TYPE])) {
            $options[OssClient::OSS_CONTENT_TYPE] = Util::guessMimeType($path, '');
        }
        try {
            $this->client->uploadFile($this->bucket, $object, $filePath, $options);
        } catch (OssException $e) {
            return false;
        }
        return $this->normalizeResponse($options, $path);
    }

    /**
     * Update a file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function update($path, $contents, Config $config)
    {
        if (! $config->has('visibility') && ! $config->has('ACL')) {
            $config->set(static::$metaMap['ACL'], $this->getObjectACL($path));
        }
        return $this->write($path, $contents, $config);
    }

    /**
     * Update a file using a stream.
     *
     * @param string $path
     * @param resource $resource
     * @param Config $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function updateStream($path, $resource, Config $config)
    {
        $contents = stream_get_contents($resource);
        return $this->update($path, $contents, $config);
    }

    /**
     * @inheritdoc
     */
    public function rename($path, $newpath)
    {
        if (! $this->copy($path, $newpath)){
            return false;
        }

        return $this->delete($path);
    }

    /**
     * @inheritdoc
     */
    public function copy($path, $newpath)
    {
        $object = $this->applyPathPrefix($path);
        $newObject = $this->applyPathPrefix($newpath);
        try{
            $this->client->copyObject($this->bucket, $object, $this->bucket, $newObject);
        } catch (OssException $e) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($path)
    {
        $bucket = $this->bucket;
        $object = $this->applyPathPrefix($path);

        try{
            $this->client->deleteObject($bucket, $object);
        }catch (OssException $e) {
            return false;
        }

        return ! $this->has($path);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteDir($dirname)
    {
        $dirname = rtrim($this->applyPathPrefix($dirname), '/').'/';
        $dirObjects = $this->listDirObjects($dirname, true);

        if(count($dirObjects['objects']) > 0 ){
            $dirname = collect($dirObjects)->pluck('Key');
        }

        try {
            $this->client->deleteObject($this->bucket, $dirname);
        } catch (OssException $e) {
            return false;
        }

        return true;
    }

    public function listDirObjects($dirname = '', $recursive =  false)
    {
        $delimiter = '/';
        $nextMarker = '';
        $maxkeys = 1000;

        //存储结果
        $result = [];

        while(true){
            $options = [
                'delimiter' => $delimiter,
                'prefix'    => $dirname,
                'max-keys'  => $maxkeys,
                'marker'    => $nextMarker,
            ];

            try {
                $listObjectInfo = $this->client->listObjects($this->bucket, $options);
            } catch (OssException $e) {
                throw $e;
            }

            $nextMarker = $listObjectInfo->getNextMarker(); // 得到nextMarker，从上一次listObjects读到的最后一个文件的下一个文件开始继续获取文件列表
            $objectList = $listObjectInfo->getObjectList(); // 文件列表
            $prefixList = $listObjectInfo->getPrefixList(); // 目录列表

            if (!empty($objectList)) {
                foreach ($objectList as $objectInfo) {

                    $object['Prefix']       = $dirname;
                    $object['Key']          = $objectInfo->getKey();
                    $object['LastModified'] = $objectInfo->getLastModified();
                    $object['eTag']         = $objectInfo->getETag();
                    $object['Type']         = $objectInfo->getType();
                    $object['Size']         = $objectInfo->getSize();
                    $object['StorageClass'] = $objectInfo->getStorageClass();

                    $result['objects'][] = $object;
                }
            }else{
                $result["objects"] = [];
            }

            if (!empty($prefixList)) {
                foreach ($prefixList as $prefixInfo) {
                    $result['prefix'][] = $prefixInfo->getPrefix();
                }
            }else{
                $result['prefix'] = [];
            }

            if($recursive){
                foreach( $result['prefix'] as $pfix){
                    $next  =  $this->listDirObjects($pfix , $recursive);
                    $result["objects"] = array_merge($result['objects'], $next["objects"]);
                }
            }

            if ($nextMarker === '') {
                break;
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function createDir($dirname, Config $config)
    {
        $object = $this->applyPathPrefix($dirname);
        $options = $this->getOptionsFromConfig($config);

        try {
            $this->client->createObjectDir($this->bucket, $object, $options);
        } catch (OssException $e) {
            return false;
        }

        return ['path' => $dirname, 'type' => 'dir'];
    }

    /**
     * {@inheritdoc}
     */
    public function has($path)
    {
        $object = $this->applyPathPrefix($path);

        return $this->client->doesObjectExist($this->bucket, $object);
    }

    /**
     * {@inheritdoc}
     */
    public function read($path)
    {
        $result = $this->readObject($path);
        $result['contents'] = (string) $result['raw_contents'];
        unset($result['raw_contents']);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function readStream($path)
    {
        $result = $this->readObject($path);
        $result['stream'] = $result['raw_contents'];
        rewind($result['stream']);
        $result['raw_contents']->detachStream();
        unset($result['raw_contents']);

        return $result;
    }

    /**
     * Read an object from the OssClient.
     *
     * @param string $path
     *
     * @return array
     */
    protected function readObject($path)
    {
        $object = $this->applyPathPrefix($path);

        $result['Body'] = $this->client->getObject($this->bucket, $object);
        $result = array_merge($result, ['type' => 'file']);
        return $this->normalizeResponse($result, $path);
    }

    /**
     * {@inheritdoc}
     */
    public function listContents($directory = '', $recursive = false)
    {
        $dirObjects = $this->listDirObjects($directory, true);
        $contents = $dirObjects["objects"];

        $result = array_map([$this, 'normalizeResponse'], $contents);
        $result = array_filter($result, function ($value) {
            return $value['path'] !== false;
        });

        return Util::emulateDirectories($result);
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata($path)
    {
        $object = $this->applyPathPrefix($path);

        try {
            $objectMeta = $this->client->getObjectMeta($this->bucket, $object);
        } catch (OssException $e) {
            return false;
        }

        return $objectMeta;
    }

    /**
     * {@inheritdoc}
     */
    public function getSize($path)
    {
        $object = $this->getMetadata($path);
        $object['size'] = $object['content-length'] ?? '';

        return $object;
    }

    /**
     * {@inheritdoc}
     */
    public function getMimetype($path)
    {
        $object = $this->getMetadata($path);
        $object['mimetype'] = $object['content-type'] ?? '';

        return $object;
    }

    /**
     * {@inheritdoc}
     */
    public function getTimestamp($path)
    {
        $object = $this->getMetadata($path);
        $object['timestamp'] = strtotime($object['last-modified'] ?? date('Y-m-d') );

        return $object;
    }


    /**
     * @param $path
     *
     * @return string
     */
    public function getUrl($path)
    {
        if (!$this->has($path))
            throw new FileNotFoundException($path.' not found');

        $ssl = $this->ssl ? 'https://' : 'http://';
        $domain = $this->cdnDomain == '' ? $this->endPoint : $this->cdnDomain;
        $url = $this->bucket . '.' . $this->endPoint;
        $cname = $this->isCname ? str_replace(['https://', 'https//'], ['', ''], $domain) : $url;

        return $ssl . $cname . '/' . ltrim($path, '/');
    }

    /**
     * Get options from the config.
     *
     * @param Config $config
     * @return array
     */
    protected function getOptionsFromConfig(Config $config)
    {
        $options = $this->options;
        foreach (static::$mappingOptions as $option => $ossOption) {
            if (! $config->has($option)) {
                continue;
            }
            $options[$ossOption] = $config->get($option);
        }
        return $options;
    }

    /**
     * Get options for a OSS call. done
     *
     * @param array  $options
     *
     * @return array OSS options
     */
    protected function getOptions(array $options = [], Config $config = null)
    {
        $options = array_merge($this->options, $options);

        if ($config) {
            $options = array_merge($options, $this->getOptionsFromConfig($config));
        }

        return array(OssClient::OSS_HEADERS => $options);
    }

    /**
     * Normalize a result from OSS.
     *
     * @param array  $object
     * @param string $path
     *
     * @return array file metadata
     */
    protected function normalizeResponse(array $object, $path = null)
    {
        $result = ['path' => $path ?: $this->removePathPrefix(isset($object['Key']) ? $object['Key'] : $object['Prefix'])];
        $result['dirname'] = Util::dirname($result['path']);

        if (isset($object['LastModified'])) {
            $result['timestamp'] = strtotime($object['LastModified']);
        }

        if (substr($result['path'], -1) === '/') {
            $result['type'] = 'dir';
            $result['path'] = rtrim($result['path'], '/');

            return $result;
        }

        $result = array_merge($result, Util::map($object, static::$resultMap), ['type' => 'file']);

        return $result;
    }
}