<?php
/**
 * Created by PhpStorm.
 * User: james.xue
 * Date: 2019/9/5
 * Time: 17:25
 */

namespace James\AliOss\Plugins;

use League\Flysystem\Config;
use League\Flysystem\Plugin\AbstractPlugin;

class PutRemoteFile extends AbstractPlugin
{
    /**
     * Get the method name.
     *
     * @return string
     */
    public function getMethod()
    {
        return 'putRemoteFile';
    }

    public function handle($path, $remoteUrl, array $options = [])
    {
        $config = new Config($options);
        if (method_exists($this->filesystem, 'getConfig')) {
            $config->setFallback($this->filesystem->getConfig());
        }

        //Get file stream from remote url
        $resource = fopen($remoteUrl, 'r');

        return (bool)$this->filesystem->getAdapter()->writeStream($path, $resource, $config);
    }
}
