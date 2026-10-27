<p align="center">
  <img src="./docs/public/logo.png" width="128" alt="风音框架">
</p>

# 风音框架

[![CI](https://github.com/fxri-net/fxyin/actions/workflows/ci.yml/badge.svg)](https://github.com/fxri-net/fxyin/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/php-%E2%89%A58.2-777bb4)](./docs/getting-started.md)
[![License: Apache-2.0](https://img.shields.io/badge/license-Apache--2.0-blue)](./LICENSE)
[![文档站](https://img.shields.io/badge/docs-fxri--net.github.io%2Ffxyin-blueviolet)](https://fxri-net.github.io/fxyin/)

面向 PHP 8.2 的轻量开发辅助框架：可接入任意 PHP 项目，内置业务开发里高频出现的能力。

> 定位：不引入依赖注入容器，不接管路由、中间件与 MVC 基类。装配结果是一组不可变对象，业务代码按需取用；工具类均为纯静态，可直接调用。余下的判断留给宿主框架。

## 能力矩阵

| 能力 | 适用场景 | 入口 |
| --- | --- | --- |
| 参数定义与整形 | 入口参数补默认值、定形状、按需挑选 | `Fxri\Param\Definition` |
| 字段与数据整理 | 生成 SQL 字段表达式、列表转树、差异比对 | `Fxri\Data\Field` |
| 链式条件构造 | 拼 SQL 片段与绑定值，执行交给调用方 | `Fxri\Sql\WhereBuilder` |
| 文本与时间 | 字符串处理、编解码、IP 转换、UUID | `Fxri\Text\Str` |
| 文件与路径 | 目录读写、路径归一、MIME 映射 | `Fxri\File\Filesystem` |
| 安全 | 摘要、对称与非对称加解密、一次性令牌 | `Fxri\Security\Cipher` |
| HTTP | 统一响应信封、请求上下文、Cookie、视图 | `Fxri\Http\Envelope` |
| 装配与配置 | 配置目录加载、日志、翻译器、框架适配 | `Fxri\KernelBuilder` |

## 文档

| 文档 | 适合谁 |
| --- | --- |
| [快速开始](./docs/getting-started.md) | 第一次接触，想先跑起来 |
| [内核与装配](./docs/kernel.md) | 了解内核边界与装配方式 |
| [配置](./docs/config.md) | 配置目录、环境变量与翻译词条 |
| [参数](./docs/param.md) | 入参定义、合并与挑选 |
| [数据](./docs/data.md) | 字段表达式、树形结构与数据比对 |
| [SQL](./docs/sql.md) | 链式条件与语句片段构造 |
| [文本](./docs/text.md) | 字符串、编码、IP、时间、UUID |
| [文件](./docs/file.md) | 目录读写、路径与 MIME |
| [安全](./docs/security.md) | 摘要、加密、签名与令牌 |
| [HTTP](./docs/http.md) | 响应信封、请求上下文、Cookie、视图与客户端 |
| [支撑](./docs/support.md) | 类型判定、随机串、格式化、调试输出 |
| [接入已有框架](./docs/integration.md) | 把内核绑定进宿主容器 |
| [1.x 升级 2.0](./docs/upgrade-2.0.md) | 从 1.x 升级的路线、阶段与回滚 |
| [2.0 API 对照](./docs/migration-2.0.md) | 查旧 API 对应的新写法 |
| [常见问题](./docs/faq.md) | 遇到问题先来这里找 |
| [贡献指南](./docs/contributing.md) | 本地开发、验收命令与发版链路 |
| [更新日志](./docs/changelog.md) | 查各版本变更内容 |
| [完整文档站](https://fxri-net.github.io/fxyin/) | 在线阅读体验 |

## 安装

```bash
composer require fxri/fxyin
```

运行环境要求 PHP 8.2 起，需开启 `mbstring` 扩展，安全相关功能另需 `openssl`；其余能力依赖的 PSR 包按需安装。

## 快速上手

```php
<?php

declare(strict_types=1);

use Fxri\KernelBuilder;

$kernel = KernelBuilder::make()
    ->withConfigDir(__DIR__ . '/config')
    ->build();

// config/app.php 的内容位于 app 键下
echo $kernel->config()->get('app.name', '未命名');
```

装配项均可选，目录结构、注入方式与更多示例见[快速开始](./docs/getting-started.md)。

## 参与贡献

开发环境、验收命令（`composer check`）、提交规范与发版链路见[贡献指南](./docs/contributing.md)；问题反馈走 [GitHub Issues](https://github.com/fxri-net/fxyin/issues)。

## 版权信息

作者：唐启云 <tqy@fxri.net>

出品：方弦研究所

版权：Copyright © 2016-2026 唐启云. All rights reserved.

网站：[方弦研究信息网](https://fxri.net:444/)

协议：[Apache License 2.0](./LICENSE)

商标："方弦®"为第42类注册商标（注册号89648411），本开源许可不授予商标使用权，详见 [TRADEMARK.md](./TRADEMARK.md)

> 方弦研究所为唐启云个人项目品牌与出品方，非独立法人实体；本软件著作权归唐启云所有。
