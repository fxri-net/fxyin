<?php

declare(strict_types=1);

namespace Fxri\Application;

use Closure;
use Fxri\Exception\RouteNotFoundException;
use Fxri\Http\RequestContext;

/**
 * 可选应用路由。
 *
 * 从请求路径解析控制器与方法，默认直接实例化控制器；也可注入项目解析器接管执行。
 * 默认不装配，只有显式调用 `dispatch()` 时才生效。
 */
final class Router
{
    /** 默认控制器命名空间 */
    public const DEFAULT_NAMESPACE = 'App';

    /**
     * 初始化。
     *
     * @param string $namespace 控制器命名空间
     * @param string $suffix 控制器类名后缀
     * @param Closure|null $resolver 项目解析器
     */
    private function __construct(
        private readonly string $namespace,
        private readonly string $suffix,
        private readonly ?Closure $resolver,
    ) {
    }

    /**
     * 解析控制器类名。
     *
     * 路径最后一段为方法，其余段为控制器路径；路径段转小写，控制器末段首字母大写。
     *
     * @param RequestContext $request 请求上下文
     * @return string 完整类名
     */
    public function controller(RequestContext $request): string
    {
        $parts = $this->parts($request);
        array_pop($parts);

        $segments = array_map(strtolower(...), $parts);
        $last = array_pop($segments);
        if (null === $last) {
            throw new RouteNotFoundException($this->namespace, '');
        }
        $segments[] = ucfirst($last);

        return '\\' . $this->namespace . '\\' . implode('\\', $segments) . $this->suffix;
    }

    /**
     * 执行路由。
     *
     * @param RequestContext $request 请求上下文
     * @return mixed 控制器返回值
     */
    public function dispatch(RequestContext $request): mixed
    {
        $controller = $this->controller($request);
        $method = $this->method($request);

        if (null !== $this->resolver) {
            return ($this->resolver)($controller, $method, $request);
        }

        if (!class_exists($controller)) {
            throw new RouteNotFoundException($controller, $method, '控制器不存在：' . $controller);
        }

        $instance = new $controller();
        if (!is_callable([$instance, $method])) {
            throw new RouteNotFoundException($controller, $method, '方法不存在：' . $controller . '::' . $method);
        }

        return $instance->{$method}();
    }

    /**
     * 创建实例。
     *
     * @param string $namespace 控制器命名空间
     * @param string $suffix 控制器类名后缀
     * @param callable(string, string, RequestContext): mixed|null $resolver 项目解析器
     * @return self 路由器
     */
    public static function make(
        string $namespace = self::DEFAULT_NAMESPACE,
        string $suffix = '',
        ?callable $resolver = null,
    ): self {
        return new self(trim($namespace, '\\'), $suffix, null === $resolver ? null : $resolver(...));
    }

    /**
     * 解析方法名。
     *
     * @param RequestContext $request 请求上下文
     * @return string 方法名
     */
    public function method(RequestContext $request): string
    {
        $parts = $this->parts($request);

        return array_pop($parts) ?? '';
    }

    /**
     * 解析路由片段。
     *
     * @param RequestContext $request 请求上下文
     * @return array{0: string, 1: string} 控制器类名与方法名
     */
    public function parse(RequestContext $request): array
    {
        return [$this->controller($request), $this->method($request)];
    }

    /**
     * 指定控制器命名空间。
     *
     * @param string $namespace 命名空间
     * @return self 新实例
     */
    public function withNamespace(string $namespace): self
    {
        return new self(trim($namespace, '\\'), $this->suffix, $this->resolver);
    }

    /**
     * 指定项目解析器。
     *
     * @param callable(string, string, RequestContext): mixed $resolver 解析器
     * @return self 新实例
     */
    public function withResolver(callable $resolver): self
    {
        return new self($this->namespace, $this->suffix, $resolver(...));
    }

    /**
     * 指定控制器类名后缀。
     *
     * @param string $suffix 后缀
     * @return self 新实例
     */
    public function withSuffix(string $suffix): self
    {
        return new self($this->namespace, $suffix, $this->resolver);
    }

    /**
     * 拆分路径片段。
     *
     * 至少需要控制器与方法两段；空路径与单段路径都视为无法解析。
     *
     * @param RequestContext $request 请求上下文
     * @return list<string> 路径片段
     */
    private function parts(RequestContext $request): array
    {
        $path = trim($request->path(), '/');
        $parts = '' === $path ? [] : explode('/', $path);

        if (count($parts) < 2) {
            throw new RouteNotFoundException($this->namespace, '');
        }

        return $parts;
    }
}
