<?php

declare(strict_types=1);

namespace Fxri\Sql;

use InvalidArgumentException;

/**
 * 原生表达式。
 *
 * 构造即视为可信片段，不再转义，调用方无法把普通字符串误当表达式传入。
 */
final class Expression
{
    /**
     * 初始化。
     *
     * @param string $sql 原生片段
     */
    private function __construct(
        private readonly string $sql,
    ) {
        if ('' === trim($sql)) {
            throw new InvalidArgumentException('表达式不能为空');
        }
    }

    /**
     * 生成经纬度距离表达式。
     *
     * 返回球面距离，单位公里；参数应为已转义的字段片段。
     *
     * @param string $lngStart 起点经度
     * @param string $latStart 起点纬度
     * @param string $lngEnd 终点经度
     * @param string $latEnd 终点纬度
     * @return self 表达式
     */
    public static function distance(string $lngStart, string $latStart, string $lngEnd, string $latEnd): self
    {
        return self::make(
            'ACOS(SIN(' . $latStart . '*3.1415/180)*SIN(' . $latEnd . '*3.1415/180)+'
            . 'COS(' . $latStart . '*3.1415/180)*COS(' . $latEnd . '*3.1415/180)*'
            . 'COS(' . $lngStart . '*3.1415/180-' . $lngEnd . '*3.1415/180))*6378.137',
        );
    }

    /**
     * 生成安全除法表达式。
     *
     * 除数为 0 时返回 0，避免数据库报除零错误。
     *
     * @param string $dividend 被除数片段
     * @param string $divisor 除数片段
     * @return self 表达式
     */
    public static function division(string $dividend, string $divisor): self
    {
        return self::make('IF(' . $divisor . '=0,0,' . $dividend . '/' . $divisor . ')');
    }

    /**
     * 生成中文首字母表达式。
     *
     * 按 GBK 编码区间映射到 A-Z，非中文字符取原值首字母大写。
     *
     * @param string $field 字段片段
     * @return self 表达式
     */
    public static function initial(string $field): self
    {
        return self::make(
            'ELT(INTERVAL(CONV(HEX(LEFT(CONVERT(' . $field . ' USING gbk),1)),16,10),'
            . '0,0xB0A1,0xB0C5,0xB2C1,0xB4EE,0xB6EA,0xB7A2,0xB8C1,0xB9FE,0xBBF7,'
            . '0xBFA6,0xC0AC,0xC2E8,0xC4C3,0xC5B6,0xC5BE,0xC6DA,0xC8BB,0xC8F6,'
            . '0xCBFA,0xCDDA,0xCEF4,0xD1B9,0xD4D1),'
            . "UPPER(LEFT(" . $field . ",1)),"
            . "'A','B','C','D','E','F','G','H','J','K','L','M','N','O','P',"
            . "'Q','R','S','T','W','X','Y','Z')",
        );
    }

    /**
     * 创建实例。
     *
     * @param string $sql 原生片段
     * @return self 表达式
     */
    public static function make(string $sql): self
    {
        return new self($sql);
    }

    /**
     * 取原生片段。
     *
     * @return string 原生片段
     */
    public function sql(): string
    {
        return $this->sql;
    }
}
