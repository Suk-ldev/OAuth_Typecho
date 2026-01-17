# Typecho Casdoor登录插件

Typecho Casdoor OAuth2登录插件，支持Casdoor身份认证

# 功能特性

- 支持Casdoor OAuth2登录
- 支持账号绑定和解绑
- 新页面跳转授权
- 简洁的配置界面

# 使用方法

下载之后把插件丢到 `plugins` 目录，目录名改成 `OAuth`

## 配置

1. 在插件设置中配置Casdoor相关信息：
   - Casdoor服务器地址（例如：https://door.casdoor.com）
   - Casdoor客户端ID
   - Casdoor客户端密钥
   - Casdoor组织名称
   - Casdoor应用名称
2. 将插件设置中显示的回调地址添加到Casdoor应用的回调URL列表中

## 使用

在模板中添加Casdoor登录按钮：

```php
<?php OAuth_Plugin::oauth(); //输出Casdoor登录按钮 ?>
```

或者直接输出登录链接：

```php
<?php echo OAuth_Plugin::url('login'); //输出登录URL ?>
```

登录后会自动跳转到Casdoor授权页面，授权完成后回调到Typecho完成登录。

# 版本

v2.0
1. 插件重命名为OAuth
2. 数据库表名改为oauth
3. 所有类名和路由改为OAuth
4. 优化代码结构
5. 修复用户创建时用户名重复问题
6. 添加删除用户时自动清理oauth数据(暂未完成，目前typecho中删除账户后需要去插件控制台手动清理孤立数据)
7. 删除解绑功能，只允许绑定
