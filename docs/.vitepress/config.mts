import { defineConfig } from 'vitepress'

// 站点描述与包信息共用一份，避免文档与 composer.json 描述漂移
const DESCRIPTION = '风音框架：可被任意 PHP 框架无缝接入的辅助框架'

// 站点基础路径：本地与根域名部署默认 '/'，GitHub Pages 子路径经 VITEPRESS_BASE 覆盖
const base = process.env.VITEPRESS_BASE || '/'

// GitHub 主仓库：社交链接与默认回链共用（对外主品牌渠道，不随部署平台变）
const githubRepo = 'https://github.com/fxri-net/fxyin'

// 站点规范地址（sitemap hostname 与 og:image 用）与编辑回链仓库：默认 GitHub Pages / GitHub 主站，
// 其他部署场景经 SITE_URL / REPO_URL 环境变量注入（值由对应 CI/CD 变量提供，只出现在该站构建产物，
// 不进仓库源码）
const siteUrl = process.env.SITE_URL || 'https://fxri-net.github.io/fxyin/'
const repo = process.env.REPO_URL || githubRepo

export default defineConfig({
  lang: 'zh-CN',
  // title 同时控制浏览器标签页与导航栏品牌名（logo 旁显示）
  title: '风音框架',
  // 内页标签统一为「当前页 - 风音框架」
  titleTemplate: ':title - 风音框架',
  description: DESCRIPTION,
  base,
  // 顶级开关：按 git 提交时间生成「最后更新于」（themeConfig.lastUpdated 仅控制文案）
  lastUpdated: true,
  head: [
    ['link', { rel: 'icon', href: `${base}logo.png` }],
    ['meta', { property: 'og:type', content: 'website' }],
    ['meta', { property: 'og:site_name', content: '风音框架' }],
    ['meta', { property: 'og:title', content: '风音框架' }],
    ['meta', { property: 'og:description', content: DESCRIPTION }],
    // og:image 必须为绝对地址，社交平台据此抓取分享卡片
    ['meta', { property: 'og:image', content: `${siteUrl}og-image.png` }],
    ['meta', { property: 'og:url', content: siteUrl }],
    ['meta', { name: 'twitter:card', content: 'summary_large_image' }],
  ],
  // 生成 sitemap.xml；hostname 带 base 子路径，保证各页 URL 拼接正确
  sitemap: { hostname: siteUrl },
  themeConfig: {
    // 导航栏 logo（字符串路径由 VitePress 自动拼接 base 前缀）
    logo: '/logo.png',
    nav: [
      { text: '指南', link: '/getting-started' },
      {
        text: '升级',
        items: [
          { text: '1.x 升级 2.0', link: '/upgrade-2.0' },
          { text: '接入形态', link: '/upgrade-2.0-runtime' },
          { text: '扩展目录', link: '/upgrade-2.0-extend' },
          { text: 'API 对照', link: '/migration-2.0' },
          { text: '检查清单', link: '/upgrade-2.0-checklist' },
        ],
      },
      {
        text: '参考',
        items: [
          { text: '内核与装配', link: '/kernel' },
          { text: '参数', link: '/param' },
          { text: '数据', link: '/data' },
          { text: 'SQL', link: '/sql' },
          { text: '文本', link: '/text' },
          { text: '文件', link: '/file' },
          { text: '安全', link: '/security' },
          { text: 'HTTP', link: '/http' },
          { text: '配置', link: '/config' },
          { text: '支撑', link: '/support' },
          { text: '接入', link: '/integration' },
        ],
      },
      {
        text: '答疑',
        items: [
          { text: '常见问题', link: '/faq' },
          { text: '贡献指南', link: '/contributing' },
        ],
      },
      { text: '更新日志', link: '/changelog' },
    ],
    // 侧栏按能力层分组：入门、内核与配置、参数与数据、文本与文件、网络与安全、支撑与协作
    sidebar: [
      {
        text: '开始',
        items: [
          { text: '首页', link: '/' },
          { text: '快速开始', link: '/getting-started' },
        ],
      },
      {
        text: '升级 2.0',
        items: [
          { text: '升级总览', link: '/upgrade-2.0' },
          { text: '接入形态', link: '/upgrade-2.0-runtime' },
          { text: '扩展目录', link: '/upgrade-2.0-extend' },
          { text: 'API 对照', link: '/migration-2.0' },
          { text: '检查清单', link: '/upgrade-2.0-checklist' },
        ],
      },
      {
        text: '内核与配置',
        items: [
          { text: '内核与装配', link: '/kernel' },
          { text: '配置', link: '/config' },
          { text: '接入', link: '/integration' },
        ],
      },
      {
        text: '参数与数据',
        items: [
          { text: '参数', link: '/param' },
          { text: '数据', link: '/data' },
          { text: 'SQL', link: '/sql' },
        ],
      },
      {
        text: '文本与文件',
        items: [
          { text: '文本', link: '/text' },
          { text: '文件', link: '/file' },
        ],
      },
      {
        text: '网络与安全',
        items: [
          { text: '安全', link: '/security' },
          { text: 'HTTP', link: '/http' },
        ],
      },
      {
        text: '支撑与协作',
        items: [
          { text: '支撑', link: '/support' },
          { text: '贡献指南', link: '/contributing' },
          { text: '常见问题', link: '/faq' },
        ],
      },
      {
        text: '变更',
        items: [{ text: '更新日志', link: '/changelog' }],
      },
    ],
    // 本地搜索 + 中文界面文案
    search: {
      provider: 'local',
      options: {
        translations: {
          button: { buttonText: '搜索文档', buttonAriaLabel: '搜索文档' },
          modal: {
            noResultsText: '未找到相关结果',
            resetButtonTitle: '清除查询条件',
            footer: { selectText: '选择', navigateText: '切换', closeText: '关闭' },
          },
        },
      },
    },
    outline: { level: [2, 3], label: '本页目录' },
    docFooter: { prev: '上一篇', next: '下一篇' },
    lastUpdated: { text: '最后更新于' },
    // 页脚「在 GitHub 上编辑此页」跳转目标
    editLink: {
      pattern: `${repo}/edit/main/docs/:path`,
      text: '在 GitHub 上编辑此页',
    },
    // 全站页脚
    footer: {
      message: 'Apache License 2.0',
      copyright: 'Copyright © 2016-2026 唐启云',
    },
    returnToTopLabel: '回到顶部',
    sidebarMenuLabel: '菜单',
    socialLinks: [{ icon: 'github', link: githubRepo }],
  },
})
