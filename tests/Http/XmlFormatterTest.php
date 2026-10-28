<?php

declare(strict_types=1);

namespace Fxri\Tests\Http;

use Fxri\Http\Envelope;
use Fxri\Http\XmlFormatter;
use PHPUnit\Framework\TestCase;

/**
 * XML 响应格式化测试。
 */
final class XmlFormatterTest extends TestCase
{
    /**
     * 信封四字段与嵌套数组都转成节点，列表项使用 item。
     */
    public function testFormat(): void
    {
        $formatter = XmlFormatter::make();
        $xml = $formatter->format(Envelope::success(['name' => '风音', 'tags' => ['a', 'b']]));

        self::assertSame('application/xml; charset=utf-8', $formatter->contentType());
        self::assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        self::assertStringContainsString('<ok>true</ok>', $xml);
        self::assertStringContainsString('<code>ok</code>', $xml);
        self::assertStringContainsString('<name>风音</name>', $xml);
        self::assertStringContainsString('<item>a</item>', $xml);
        self::assertStringContainsString('<item>b</item>', $xml);
        self::assertStringContainsString('<message></message>', $xml);
    }

    /**
     * 非法节点名被替换，数字开头补下划线。
     */
    public function testNodeName(): void
    {
        $xml = XmlFormatter::make()->format(Envelope::success(['1bad name' => 'x']));

        self::assertStringContainsString('<_1bad_name>x</_1bad_name>', $xml);
    }
}
