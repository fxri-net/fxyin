<?php

declare(strict_types=1);

namespace Fxri\Tests\Param;

use Fxri\Param\Json;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * JSON 编解码测试：数组/对象解码与非法输入。
 */
final class JsonTest extends TestCase
{
    /**
     * 默认解码为数组，关闭关联时保留对象。
     */
    public function testDecode(): void
    {
        self::assertSame(['a' => 1], Json::decode('{"a":1}'));
        self::assertSame(['a' => 1], Json::decode('{"a":1}', true));

        $object = Json::decode('{"a":1}', false);
        self::assertInstanceOf(stdClass::class, $object);
        self::assertSame(1, $object->a);
    }

    /**
     * 非法 JSON 抛异常。
     */
    public function testDecodeInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Json::decode('{');
    }

    /**
     * 编码与解码可往返。
     */
    public function testEncodeRoundTrip(): void
    {
        self::assertSame(['a' => '风音/文字'], Json::decode(Json::encode(['a' => '风音/文字'])));
    }
}
