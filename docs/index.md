---
layout: home
# 首页无正文 h1，显式给 title 避免浏览器标签出现「风音框架 - 风音框架」重复
title: 可接入任意 PHP 项目的辅助框架

hero:
  name: 风音框架
  text: 可接入任意 PHP 项目的辅助框架
  tagline: 不引入容器、不接管路由，装配结果是一组不可变对象；参数、数据、SQL、文本、文件、HTTP 与安全等高频能力以纯静态类提供，随取随用。
  actions:
    - theme: brand
      text: 快速开始
      link: ./getting-started
    - theme: alt
      text: 1.x 升级 2.0
      link: ./upgrade-2.0
    - theme: alt
      text: GitHub
      link: https://github.com/fxri-net/fxyin

features:
  - icon: 🧩
    title: 最小装配
    details: KernelBuilder 链式装配，配置目录、日志、翻译器、HTTP 客户端与适配器全部可选；内核不可变，只暴露读取入口。
  - icon: ✅
    title: 参数定义与校验
    details: 定义、校验、合并、挑选与 URL 拼装齐备，模式统一以具名枚举表达，不再依赖数字码与点分模式号。
  - icon: 🧮
    title: 数据字段表达式
    details: 字段表达式、空值策略、数据比对与树形结构转换，覆盖列表拼装与层级数据的常见处理。
  - icon: 🔗
    title: 链式 SQL 条件
    details: 条件构造与 MySQL 方言，SQL 片段与绑定参数一并产出，避免手工拼串带来的注入与转义问题。
  - icon: 🔐
    title: 安全能力
    details: 摘要、对称与非对称加解密、令牌一应俱全；1.x 密文可经 DesCipher 平滑迁移，不必一次性重刷数据。
  - icon: ✍️
    title: 文本与时间
    details: 字符串切割与拼装、编码转换、IP 换算、时间戳与粒度、UUID 生成，日常处理不必再各写一份。
  - icon: 📁
    title: 文件与路径
    details: 目录读写、路径归一与拼接、MIME 识别，上传与静态资源场景无需另找实现。
  - icon: 🌐
    title: HTTP 与视图
    details: 统一响应信封、请求与服务器上下文、Cookie、视图渲染，并可按需接入 PSR-18 客户端发起外部请求。
---

## 30 秒上手

```bash
composer require fxri/fxyin
```

装配一个内核，随后按需取用配置与翻译：

```php
<?php

declare(strict_types=1);

use Fxri\KernelBuilder;

$kernel = KernelBuilder::make()
    ->withConfigDir(__DIR__ . '/config')
    ->build();

// 取配置，config/app.php 的内容位于 app 键下
$name = $kernel->config()->get('app.name', '未命名');

// 取翻译，未注入翻译器时由内置词条兜底
echo $kernel->trans('欢迎');
```

详细步骤见[快速开始](./getting-started)。

## 解决什么痛点

| 痛点 | 本框架的做法 |
| --- | --- |
| 参数校验、文件操作、加解密这些碎活，换个项目就得重写一遍 | 八类高频能力内置为纯静态类，`use` 进来即可调用，不绑定任何框架生命周期 |
| 为了一个工具函数引入重量级框架，容器、路由、中间件一并背上 | 不引入依赖注入容器、不提供路由与 MVC 基类；装配项全部可选，缺省即有可用实现 |
| 老代码里的数字码与点分模式号，过半年没人看得懂 | 全部改为具名枚举与链式方法，调用处自带语义 |
| 1.x 升级到 2.0 改动面大，不知从哪下手 | [1.x 升级 2.0 指南](./upgrade-2.0) 给出体检、路线、阶段与回滚，[API 对照](./migration-2.0) 逐项列出旧写法与新写法 |
| 文档散落在 README，翻起来靠关键词硬找 | 独立文档站，带全站中文搜索、按能力层划分的侧栏与逐页目录 |

## 文档索引

| 文档 | 适合谁 |
| --- | --- |
| [快速开始](./getting-started) | 第一次接触，想先把框架跑起来 |
| [1.x 升级 2.0](./upgrade-2.0) | 从 1.x 升级，需要路线与步骤 |
| [2.0 API 对照](./migration-2.0) | 查旧 API 对应的新写法 |
| [更新日志](./changelog) | 查各版本变更内容 |
| [IDE 元数据](./ide) | 想让工厂方法在编辑器里补全出具体类 |

## 环境要求

- PHP >= 8.2
- 扩展 `mbstring`、`openssl`（均为必需）
- 可选依赖：`psr/http-client`、`psr/http-message`、`psr/http-factory` 及其实现（如 `symfony/http-client`、`nyholm/psr7`）
