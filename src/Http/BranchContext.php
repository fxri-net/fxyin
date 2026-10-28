<?php

declare(strict_types=1);

namespace Fxri\Http;

/**
 * 分支上下文。
 *
 * 按主机名从分支配置中匹配分支名并取出对应配置，替代 1.x 的 `Server::branch()`。
 */
final class BranchContext
{
    /**
     * 初始化。
     *
     * @param string $host 主机名
     * @param string $branch 命中的分支名
     * @param mixed $config 分支配置
     */
    private function __construct(
        private readonly string $host,
        private readonly string $branch,
        private readonly mixed $config,
    ) {
    }

    /**
     * 取命中的分支名。
     *
     * @return string 分支名，未命中时为默认分支
     */
    public function branch(): string
    {
        return $this->branch;
    }

    /**
     * 取分支配置。
     *
     * @return mixed 分支配置，未命中时为 null
     */
    public function config(): mixed
    {
        return $this->config;
    }

    /**
     * 取主机名。
     *
     * @return string 主机名
     */
    public function host(): string
    {
        return $this->host;
    }

    /**
     * 解析分支。
     *
     * 配置键按优先级从后往前检查：完整主机名优先，其次 `^` 前缀的部分匹配，
     * 最后 `$` 前缀的完整正则匹配；键中的多个主机名用逗号分隔。
     *
     * @param string $host 主机名
     * @param array<array-key, mixed> $store 分支配置
     * @param string $default 未命中时的默认分支
     * @return self 分支上下文
     */
    public static function resolve(string $host, array $store, string $default = ''): self
    {
        $full = [];
        $partial = [];
        $complete = [];

        foreach (array_reverse($store, true) as $key => $config) {
            foreach (explode(',', (string) $key) as $value) {
                $full[$value] = (string) $key;

                $initial = mb_substr($value, 0, 1, 'UTF-8');
                $after = mb_substr($value, 1, null, 'UTF-8');
                if ('^' === $initial) {
                    $partial[$after] = (string) $key;
                } elseif ('$' === $initial) {
                    // 完整正则沿用原始键，`$` 本身就是合法分隔符
                    $complete[$value] = (string) $key;
                }
            }
        }

        $branch = $full[$host] ?? '';
        if ('' === $branch) {
            foreach ($partial as $pattern => $value) {
                if (1 === preg_match('/' . $pattern . '/i', $host)) {
                    $branch = $value;
                    break;
                }
            }
        }
        if ('' === $branch) {
            foreach ($complete as $pattern => $value) {
                if (1 === preg_match($pattern, $host)) {
                    $branch = $value;
                    break;
                }
            }
        }

        $branch = '' === $branch ? $default : $branch;

        return new self($host, $branch, $store[$branch] ?? null);
    }
}
