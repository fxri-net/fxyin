<?php

declare(strict_types=1);

namespace Fxri\Tests\Config;

use Fxri\Config\Config;
use Fxri\Config\Store;
use Fxri\File\Loader;
use PHPUnit\Framework\TestCase;

/**
 * 配置存储测试：基底读取、运行期覆盖与不可变基底。
 */
final class StoreTest extends TestCase
{
    /**
     * 未覆盖时读取基底。
     */
    public function testReadsBase(): void
    {
        $store = Store::make(Config::make(['app' => ['name' => '风音', 'debug' => true]]));

        self::assertSame('风音', $store->get('app.name'));
        self::assertTrue($store->has('app.debug'));
        self::assertFalse($store->has('app.missing'));
        self::assertSame('默认', $store->get('app.name2', '默认'));
        self::assertSame(['app' => ['name' => '风音', 'debug' => true]], $store->base()->all());
    }

    /**
     * 运行期写入优先于基底，并支持点号路径展开。
     */
    public function testSetOverrides(): void
    {
        $store = Store::make(Config::make(['app' => ['name' => '风音', 'debug' => true]]));

        $store->set('app.name', '覆盖');
        $store->set('app.extra.enabled', true);

        self::assertSame('覆盖', $store->get('app.name'));
        self::assertTrue($store->get('app.extra.enabled'));
        self::assertTrue($store->has('app.debug'));
        self::assertTrue($store->has('app.extra.enabled'));
    }

    /**
     * 批量写入递归合并，空值覆盖同样生效。
     */
    public function testSetArrayMerges(): void
    {
        $store = Store::make(Config::make(['app' => ['name' => '风音', 'a.b' => '含点']]));

        $store->set(['app' => ['debug' => false, 'name' => null]]);

        self::assertFalse($store->get('app.debug'));
        self::assertNull($store->get('app.name'));
        self::assertTrue($store->has('app.a/_b'));
    }

    /**
     * 环境叠加层由 `env` 命名空间承接，文件数据经 `File\Loader` 读入。
     */
    public function testEnvironmentOverlay(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'fxri-env-');
        self::assertIsString($file);
        file_put_contents($file, "<?php\nreturn ['lang' => 'en-us'];\n");

        $store = Store::make(Config::make(['env' => ['base' => ['host' => null]]]));

        try {
            $store->set('env.view', Loader::read($file));

            self::assertSame('en-us', $store->get('env.view.lang'));
            self::assertTrue($store->has('env.base.host'));
            self::assertSame('fallback', $store->get('env.missing', 'fallback'));
        } finally {
            @unlink($file);
        }
    }

    /**
     * 写入只进覆盖层，基底与合并结果互不影响。
     */
    public function testSetKeepsBase(): void
    {
        $base = Config::make(['app' => ['name' => '风音']]);
        $store = Store::make($base);

        $store->set('app.name', '覆盖');

        self::assertSame('风音', $base->get('app.name'));
        self::assertSame('风音', $store->base()->get('app.name'));
        self::assertSame(['app' => ['name' => '覆盖']], $store->all());
    }
}
