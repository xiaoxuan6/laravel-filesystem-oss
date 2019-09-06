<?php
/**
 * Created by PhpStorm.
 * User: james.xue
 * Date: 2019/9/5
 * Time: 17:25
 */

namespace James\AliOss\Plugins;

use League\Flysystem\Plugin\AbstractPlugin;

class FileUrl extends AbstractPlugin
{
    public function getMethod()
    {
        return 'getUrl';
    }

    public function handle($path = '')
    {
        return $this->filesystem->getAdapter()->getUrl($path);
    }
}