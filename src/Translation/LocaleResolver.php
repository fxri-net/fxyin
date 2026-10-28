<?php

declare(strict_types=1);

namespace Fxri\Translation;

use Fxri\Http\Cookie\CookieJar;
use Fxri\Http\Cookie\CookieOptions;
use Fxri\Http\ServerContext;
use Fxri\Support\Type;

/**
 * 语言检测器。
 *
 * 依次从查询参数、Cookie、`Accept-Language` 推断语言，按允许列表校验；配置项均可替换。
 */
final class LocaleResolver
{
    /** 默认语言 */
    public const DEFAULT_LOCALE = 'zh-cn';

    /** 浏览器语言提取规则 */
    private const ACCEPT_PATTERN = '/^([a-z\d\-]+)/i';

    /**
     * 初始化。
     *
     * @param string $detectVar 查询参数名
     * @param string $cookieName Cookie 名
     * @param int $cookieExpire Cookie 有效期，单位秒
     * @param list<string> $allowList 允许的语言列表，空数组表示不限制
     * @param string $locale 兜底语言
     */
    private function __construct(
        private readonly string $detectVar = 'lang',
        private readonly string $cookieName = 'lang',
        private readonly int $cookieExpire = 3600,
        private readonly array $allowList = [],
        private readonly string $locale = self::DEFAULT_LOCALE,
    ) {
    }

    /**
     * 返回允许语言列表。
     *
     * @return list<string> 语言列表
     */
    public function allowList(): array
    {
        return $this->allowList;
    }

    /**
     * 返回 Cookie 有效期。
     *
     * @return int 秒数
     */
    public function cookieExpire(): int
    {
        return $this->cookieExpire;
    }

    /**
     * 返回 Cookie 名。
     *
     * @return string Cookie 名
     */
    public function cookieName(): string
    {
        return $this->cookieName;
    }

    /**
     * 返回查询参数名。
     *
     * @return string 参数名
     */
    public function detectVar(): string
    {
        return $this->detectVar;
    }

    /**
     * 返回兜底语言。
     *
     * @return string 语言标识
     */
    public function locale(): string
    {
        return $this->locale;
    }

    /**
     * 创建实例。
     *
     * @param string $detectVar 查询参数名
     * @param string $cookieName Cookie 名
     * @param int $cookieExpire Cookie 有效期，单位秒
     * @param list<string> $allowList 允许的语言列表，空数组表示不限制
     * @param string $locale 兜底语言
     * @return self 检测器
     */
    public static function make(
        string $detectVar = 'lang',
        string $cookieName = 'lang',
        int $cookieExpire = 3600,
        array $allowList = [],
        string $locale = self::DEFAULT_LOCALE,
    ): self {
        return new self($detectVar, $cookieName, $cookieExpire, $allowList, $locale);
    }

    /**
     * 推断当前语言。
     *
     * 查询参数优先并回写 Cookie；Cookie 次之；最后读 `Accept-Language` 并回写 Cookie。
     * 检测结果不在允许列表内时保持兜底语言，`zh-hans-cn` 归一为 `zh-cn`。
     *
     * @param array<array-key, mixed> $query 查询参数
     * @param CookieJar|null $cookies Cookie 容器，缺省读全局
     * @param ServerContext|null $server 服务端上下文，缺省读全局
     * @param bool $persist 是否回写 Cookie
     * @return string 语言标识
     */
    public function resolve(array $query = [], ?CookieJar $cookies = null, ?ServerContext $server = null, bool $persist = true): string
    {
        $cookies ??= CookieJar::fromGlobals();
        $server ??= ServerContext::fromGlobals();
        $locale = '';

        if (isset($query[$this->detectVar])) {
            $locale = self::normalize(Type::toString($query[$this->detectVar]));
            $this->remember($cookies, $locale, $persist);
        } elseif ('' !== ($cookie = $cookies->get($this->cookieName))) {
            $locale = self::normalize($cookie);
        } elseif ('' !== ($header = $server->get('HTTP_ACCEPT_LANGUAGE')) && 1 === preg_match(self::ACCEPT_PATTERN, $header, $matches)) {
            $locale = self::normalize($matches[1]);
            $this->remember($cookies, $locale, $persist);
        }

        $resolved = $this->locale;
        if ([] === $this->allowList || in_array($locale, $this->allowList, true)) {
            $resolved = '' === $locale ? $this->locale : $locale;
        }

        return 'zh-hans-cn' === $resolved ? 'zh-cn' : $resolved;
    }

    /**
     * 指定允许语言列表。
     *
     * @param list<string> $allowList 语言列表
     * @return self 新实例
     */
    public function withAllowList(array $allowList): self
    {
        return new self($this->detectVar, $this->cookieName, $this->cookieExpire, $allowList, $this->locale);
    }

    /**
     * 指定 Cookie 有效期。
     *
     * @param int $expire 秒数
     * @return self 新实例
     */
    public function withCookieExpire(int $expire): self
    {
        return new self($this->detectVar, $this->cookieName, $expire, $this->allowList, $this->locale);
    }

    /**
     * 指定 Cookie 名。
     *
     * @param string $name Cookie 名
     * @return self 新实例
     */
    public function withCookieName(string $name): self
    {
        return new self($this->detectVar, $name, $this->cookieExpire, $this->allowList, $this->locale);
    }

    /**
     * 指定查询参数名。
     *
     * @param string $var 参数名
     * @return self 新实例
     */
    public function withDetectVar(string $var): self
    {
        return new self($var, $this->cookieName, $this->cookieExpire, $this->allowList, $this->locale);
    }

    /**
     * 指定兜底语言。
     *
     * @param string $locale 语言标识
     * @return self 新实例
     */
    public function withLocale(string $locale): self
    {
        return new self($this->detectVar, $this->cookieName, $this->cookieExpire, $this->allowList, $locale);
    }

    /**
     * 归一语言标识。
     *
     * @param string $locale 原始标识
     * @return string 小写标识
     */
    private static function normalize(string $locale): string
    {
        return strtolower(trim($locale));
    }

    /**
     * 回写语言 Cookie。
     *
     * 命令行环境与显式关闭持久化时不写，避免污染脚本输出与响应头。
     *
     * @param CookieJar $cookies Cookie 容器
     * @param string $locale 语言标识
     * @param bool $persist 是否回写
     * @return void
     */
    private function remember(CookieJar $cookies, string $locale, bool $persist): void
    {
        if (!$persist || 'cli' === PHP_SAPI || '' === $locale) {
            return;
        }

        $cookies->set(
            $this->cookieName,
            $locale,
            CookieOptions::make($this->cookieExpire > 0 ? time() + $this->cookieExpire : 0),
        );
    }
}
