<?php

declare(strict_types=1);

namespace Fxri\Http;

use Fxri\Support\Type;

/**
 * 访问者信息。
 *
 * 默认只信任连接对端地址；转发链地址必须显式启用并配置可信代理网段，
 * 避免请求头被随意伪造。
 */
final class ClientInfo
{
    /** 无法识别时的地址 */
    private const UNKNOWN_IP = '0.0.0.0';

    /** 无法识别时的名称 */
    private const UNKNOWN_NAME = 'Others';

    /** @var array<string, string> UA 片段 => 浏览器名，顺序即匹配优先级 */
    private const BROWSERS = [
        'micromessenger' => 'MicroMessenger',
        'alipayclient' => 'Alipay',
        'edg/' => 'Microsoft Edge',
        'opr/' => 'Opera',
        'firefox' => 'Firefox',
        'chrome' => 'Google Chrome',
        'safari' => 'Safari',
        'trident' => 'Internet Explorer',
    ];

    /** @var array<string, string> UA 片段 => 系统名，顺序即匹配优先级 */
    private const SYSTEMS = [
        'windows nt' => 'Windows',
        'android' => 'Android',
        'iphone' => 'iOS',
        'ipad' => 'iOS',
        'ipod' => 'iOS',
        'mac os x' => 'macOS',
        'linux' => 'Linux',
    ];

    /** UA 小写副本，用于片段匹配 */
    private readonly string $agent;

    /**
     * 初始化。
     *
     * @param ServerContext $server 服务端上下文
     * @param IpSource $ipSource IP 取信来源
     * @param list<string> $trustedProxies 可信代理网段
     */
    private function __construct(
        private readonly ServerContext $server,
        private readonly IpSource $ipSource,
        private readonly array $trustedProxies,
    ) {
        $this->agent = strtolower($server->get('HTTP_USER_AGENT'));
    }

    /**
     * 取浏览器名。
     *
     * @param CaseFormat $format 输出大小写形态
     * @return string 浏览器名
     */
    public function browser(CaseFormat $format = CaseFormat::Original): string
    {
        return $format->apply($this->match(self::BROWSERS));
    }

    /**
     * 取当前请求的访问者信息。
     *
     * @param IpSource $ipSource IP 取信来源
     * @param list<string> $trustedProxies 可信代理网段
     * @return self 访问者信息
     */
    public static function fromGlobals(IpSource $ipSource = IpSource::Remote, array $trustedProxies = []): self
    {
        return self::make(ServerContext::fromGlobals(), $ipSource, $trustedProxies);
    }

    /**
     * 取客户端地址。
     *
     * 仅当启用转发来源且对端命中可信代理时，才从右侧反向取第一个非代理地址；
     * 候选地址非法或全部为代理时回落到对端地址。
     *
     * @return string 客户端地址
     */
    public function ip(): string
    {
        $remote = $this->remote();
        if (IpSource::Remote === $this->ipSource || !$this->isTrusted($remote)) {
            return $remote;
        }

        foreach (array_reverse($this->forwarded()) as $candidate) {
            if ($this->isTrusted($candidate)) {
                continue;
            }

            return Type::isIp($candidate) ? $candidate : $remote;
        }

        return $remote;
    }

    /**
     * 判断对端是否为可信代理。
     *
     * @return bool 是否可信
     */
    public function isTrustedProxy(): bool
    {
        return $this->isTrusted($this->remote());
    }

    /**
     * 创建实例。
     *
     * @param ServerContext $server 服务端上下文
     * @param IpSource $ipSource IP 取信来源
     * @param list<string> $trustedProxies 可信代理网段
     * @return self 访问者信息
     */
    public static function make(ServerContext $server, IpSource $ipSource = IpSource::Remote, array $trustedProxies = []): self
    {
        return new self($server, $ipSource, $trustedProxies);
    }

    /**
     * 取操作系统名。
     *
     * @param CaseFormat $format 输出大小写形态
     * @return string 系统名
     */
    public function system(CaseFormat $format = CaseFormat::Original): string
    {
        return $format->apply($this->match(self::SYSTEMS));
    }

    /**
     * 取原始 UA 串。
     *
     * @return string UA 串
     */
    public function userAgent(): string
    {
        return $this->server->get('HTTP_USER_AGENT');
    }

    /**
     * 解析转发链地址。
     *
     * @return list<string> 从左到右的候选地址
     */
    private function forwarded(): array
    {
        $value = $this->server->get('HTTP_X_FORWARDED_FOR');
        if ('' === $value) {
            return [];
        }

        $addresses = [];

        foreach (explode(',', $value) as $item) {
            $candidate = trim($item);
            if ('' === $candidate || 'unknown' === strtolower($candidate)) {
                continue;
            }

            $addresses[] = $candidate;
        }

        return $addresses;
    }

    /**
     * 判断地址是否落在网段内。
     *
     * @param string $ip 待判定地址
     * @param string $range CIDR 网段，如 10.0.0.0/8
     * @return bool 是否命中
     */
    private function inRange(string $ip, string $range): bool
    {
        $slash = strrpos($range, '/');
        if (false === $slash) {
            return false;
        }

        $network = substr($range, 0, $slash);
        $bits = substr($range, $slash + 1);
        if (!Type::isIp($ip) || !Type::isIp($network) || !ctype_digit($bits)) {
            return false;
        }

        $ipBinary = inet_pton($ip);
        $networkBinary = inet_pton($network);
        if (false === $ipBinary || false === $networkBinary || strlen($ipBinary) !== strlen($networkBinary)) {
            return false;
        }

        $prefix = (int) $bits;
        $length = strlen($networkBinary) * 8;
        if ($prefix > $length) {
            return false;
        }

        $bytes = intdiv($prefix, 8);
        $remainder = $prefix % 8;

        if ($bytes > 0 && substr($ipBinary, 0, $bytes) !== substr($networkBinary, 0, $bytes)) {
            return false;
        }

        if (0 === $remainder) {
            return true;
        }

        $mask = (0xFF << (8 - $remainder)) & 0xFF;

        return (ord(substr($ipBinary, $bytes, 1)) & $mask) === (ord(substr($networkBinary, $bytes, 1)) & $mask);
    }

    /**
     * 判断地址是否可信。
     *
     * @param string $ip 待判定地址
     * @return bool 是否可信
     */
    private function isTrusted(string $ip): bool
    {
        foreach ($this->trustedProxies as $range) {
            if ($this->inRange($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 按片段表匹配名称。
     *
     * @param array<string, string> $map 片段 => 名称
     * @return string 匹配结果，未命中时为缺省名称
     */
    private function match(array $map): string
    {
        foreach ($map as $needle => $label) {
            if (str_contains($this->agent, $needle)) {
                return $label;
            }
        }

        return self::UNKNOWN_NAME;
    }

    /**
     * 取连接对端地址。
     *
     * @return string 对端地址
     */
    private function remote(): string
    {
        $value = $this->server->get('REMOTE_ADDR');

        return Type::isIp($value) ? $value : self::UNKNOWN_IP;
    }
}
