=== ImageX ===
Contributors: shenyanzhi
Donate link: https://qq52o.me/sponsor.html
Tags: imagex, byteoc, volcengine, 火山引擎, 字节跳动
Requires at least: 4.2
Tested up to: 6.3
Requires PHP: 7.0.0
Stable tag: 1.1.0
License: Apache 2.0
License URI: http://www.apache.org/licenses/LICENSE-2.0.html

使用火山引擎图片服务（ImageX）作为附件存储空间。（This is a plugin that uses VolcEngine ImageX for attachments remote saving.）

== Description ==

使用火山引擎图片服务（ImageX）作为附件存储空间。（This is a plugin that uses VolcEngine ImageX for attachments remote saving.）

* 依赖火山引擎图片服务：https://zjsms.com/RVvQxX8/

## 插件特点

1. 可配置是否上传缩略图和是否保留本地备份
2. 本地删除可同步删除火山引擎图片服务 ImageX 中的文件
3. 支持替换数据库中旧的资源链接地址
4. 支持完整地域使用
5. 支持同步历史附件到火山引擎图片服务 ImageX
6. 支持火山引擎图片服务 ImageX 图片处理
7. 支持自动重命名文件

插件更多详细介绍和安装：[https://github.com/sy-records/volcengine-imagex-wordpress](https://github.com/sy-records/volcengine-imagex-wordpress)

## 作者博客

[沈唁志](https://qq52o.me "沈唁志")

欢迎加入沈唁的WordPress云存储全家桶QQ交流群：887595381

== Installation ==

1. Upload the folder `volcengine-imagex-wordpress` or `imagex` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. That's all

== Screenshots ==

1. screenshot-1.png

== Frequently Asked Questions ==

= 开启插件并上传图片，在后台媒体库和文章中不能正常访问，但在前台显示正常 =

请前往火山引擎官网 [创建工单](https://console.volcengine.cn/ticket/createTicket/?step=3&ProviderName=%E5%9B%BE%E7%89%87%E8%A7%A3%E5%86%B3%E6%96%B9%E6%A1%88&TemplateName=%E5%8A%9F%E8%83%BD%E9%85%8D%E7%BD%AE) ，问题描述里填写：`WordPress插件开通源地址访问功能`

== Changelog ==

= 1.1.0 =
* 升级依赖
* 支持 WordPress 6.3 版本
* 支持自动重命名文件

= 1.0.4 =
* 修复在文章中从媒体库添加图片携带了图片处理模板

= 1.0.3 =
* 修复丢失火山引擎SDK

= 1.0.2 =
* 更新SDK
* 更新火山引擎链接

= 1.0.1 =
* 支持WordPress 5.6
* 优化缩略图删除逻辑

= 1.0.0 =
* First version
