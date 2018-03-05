 项目
======
致敬原作者 walkor https://github.com/walkor/workerman-chat

基于workerman的GatewayWorker框架开发的聊天室系统(修改加密版)。

GatewayWorker框架文档：http://www.workerman.net/gatewaydoc/

 特性
======
 * 使用websocket协议
 * 多浏览器支持（浏览器支持html5或者flash任意一种即可）
 * 支持多服务器部署
  
安装
=====
composer install

启动停止(Linux系统)
=====
以debug方式启动  
```php start.php start  ```

以daemon方式启动  
```php start.php start -d ```

测试
=======
浏览器访问 http://服务器ip或域:55152,例如http://127.0.0.1:55152

 [更多请访问www.workerman.net](http://www.workerman.net/workerman-chat)
