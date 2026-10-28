<?php

declare(strict_types=1);

namespace Fxri\Application;

/**
 * 应用模块。
 *
 * 模块在 `Bootstrap::withModule()` 时获得启动器，自行追加配置、语言、函数、服务与钩子。
 */
interface Module
{
    /**
     * 注册模块。
     *
     * @param Bootstrap $bootstrap 应用启动器
     * @return Bootstrap 注册后的启动器
     */
    public function register(Bootstrap $bootstrap): Bootstrap;
}
