<?php

declare(strict_types=1);

namespace Fxri\Tests\Http;

use Fxri\Http\Renderer;
use Fxri\Http\View;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * 视图测试：覆盖路径解析、越界拒绝与目录替换。
 */
final class ViewTest extends TestCase
{
    /** 临时模板目录 */
    private string $directory;

    /**
     * 准备模板文件。
     */
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/fxri-view-' . bin2hex(random_bytes(4));
        mkdir($this->directory);
        file_put_contents($this->directory . '/hello.php', 'hello');
    }

    /**
     * 清理模板文件。
     */
    protected function tearDown(): void
    {
        if (is_file($this->directory . '/hello.php')) {
            unlink($this->directory . '/hello.php');
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    /**
     * 渲染时把解析后的路径交给渲染器。
     */
    public function testRender(): void
    {
        $view = View::make($this->directory, self::renderer());

        self::assertSame($this->directory, $view->directory());
        self::assertSame('hello.php|1', $view->render('hello.php', ['name' => 'x']));
        self::assertSame('hello.php|0', $view->render('hello.php'));
    }

    /**
     * 更换目录返回新实例。
     */
    public function testWithDirectory(): void
    {
        $view = View::make($this->directory, self::renderer());
        $next = $view->withDirectory('/other');

        self::assertSame($this->directory, $view->directory());
        self::assertSame('/other', $next->directory());
    }

    /**
     * 空模板名被拒绝。
     */
    public function testEmptyTemplateRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        View::make($this->directory, self::renderer())->render('  ');
    }

    /**
     * 上级目录写法被拒绝。
     */
    public function testTraversalRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        View::make($this->directory, self::renderer())->render('../hello.php');
    }

    /**
     * 模板不存在时报错。
     */
    public function testMissingTemplateRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        View::make($this->directory, self::renderer())->render('missing.php');
    }

    /**
     * 构造透传渲染器。
     *
     * @return Renderer 渲染器
     */
    private static function renderer(): Renderer
    {
        return new class () implements Renderer {
            public function render(string $template, array $data): string
            {
                return basename($template) . '|' . count($data);
            }
        };
    }
}
