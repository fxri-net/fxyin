<?php

declare(strict_types=1);

namespace Fxri\Http;

use Fxri\Support\Type;

/**
 * XML 响应格式化器。
 *
 * 把固定五字段信封转成 XML，数组按键名展开，列表项统一使用 `item`。
 */
final class XmlFormatter implements Formatter
{
    /**
     * 取响应内容类型。
     *
     * @return string 内容类型
     */
    public function contentType(): string
    {
        return 'application/xml; charset=utf-8';
    }

    /**
     * 格式化响应信封。
     *
     * @param Envelope $envelope 响应信封
     * @return string XML 串
     */
    public function format(Envelope $envelope): string
    {
        return '<?xml version="1' . '.0" encoding="UTF-8"?>' . "\n" . $this->node('response', $envelope->toArray());
    }

    /**
     * 创建实例。
     *
     * @return self 格式化器
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * 生成节点。
     *
     * @param string $name 节点名
     * @param mixed $value 节点值
     * @return string XML 片段
     */
    private function node(string $name, mixed $value): string
    {
        $name = $this->name($name);

        // 空调试信息为对象，统一按数组展开
        if (is_object($value)) {
            $value = (array) $value;
        }

        if (is_array($value)) {
            $children = '';
            foreach ($value as $key => $item) {
                $children .= $this->node(is_int($key) ? 'item' : (string) $key, $item);
            }

            return '<' . $name . '>' . $children . '</' . $name . '>';
        }

        if (null === $value) {
            return '<' . $name . '/>';
        }

        return '<' . $name . '>' . $this->text($value) . '</' . $name . '>';
    }

    /**
     * 归一节点名。
     *
     * XML 元素名不能以数字开头，也不允许空白与标点，非法字符统一替换为下划线。
     *
     * @param string $name 原始名称
     * @return string 合法名称
     */
    private function name(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9_.-]/', '_', $name) ?? '';
        if ('' === $name) {
            return 'item';
        }
        if (1 === preg_match('/^[0-9.-]/', $name)) {
            return '_' . $name;
        }

        return $name;
    }

    /**
     * 转义文本值。
     *
     * @param mixed $value 值
     * @return string 转义结果
     */
    private function text(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return htmlspecialchars(Type::toString($value), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
