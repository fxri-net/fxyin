<?php

declare(strict_types=1);

namespace Fxri\Exception;

use RuntimeException;

/**
 * 路由未命中异常。
 *
 * 控制器或方法不存在时抛出，接入方可据此统一映射为 404 或业务错误码。
 */
final class RouteNotFoundException extends RuntimeException
{
    /**
     * 初始化。
     *
     * @param string $controller 控制器名
     * @param string $method 方法名
     * @param string $message 提示语
     */
    public function __construct(
        private readonly string $controller,
        private readonly string $method,
        string $message = '',
    ) {
        parent::__construct('' !== $message ? $message : '控制器或方法不存在：' . $controller . '::' . $method);
    }

    /**
     * 取控制器名。
     *
     * @return string 控制器名
     */
    public function controller(): string
    {
        return $this->controller;
    }

    /**
     * 取方法名。
     *
     * @return string 方法名
     */
    public function method(): string
    {
        return $this->method;
    }
}
