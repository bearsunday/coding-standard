<?php

declare(strict_types=1);

namespace MyVendor\MyProject\Module;

use Ray\Di\AbstractModule;

class ProdModule extends AbstractModule
{
    protected function configure(): void
    {
        $this->install(new QiqProdModule($this->appMeta->appDir . '/var/tmp/cache/qiq'));
        $this->install(new CacheDirModule($this->appMeta->appDir . '/var/tmp/cache'));
        $this->bind()->annotatedWith('logPath')->toInstance($this->appMeta->appDir . '/var/log/app.log');
        $this->bind()->annotatedWith('assetsPath')->toInstance($this->appMeta->appDir . '/var/assets');

        $this->install(new QiqProdModule($this->appMeta->tmpDir . '/cache/qiq'));
        $this->bind()->annotatedWith('buildPath')->toInstance($this->appMeta->appDir . '/var/build');
        $this->bind()->annotatedWith('legacyPath')->toInstance($this->appMeta->appDir . '/var/tmp-old');
        $this->bind()->annotatedWith('bareTmpPath')->toInstance($this->appMeta->appDir . '/var/tmp');
        $this->bind()->annotatedWith('unrelatedPath')->toInstance($this->cache->appDir . '/var/tmp/cache');
    }
}
