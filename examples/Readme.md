WebIM部署方法
----
客户端：webim/ ，将此目录加入nginx的静态文件请求中

服务器端：
```shell
php webim_server.php
php webim/flash_policy.php #这里是flash-websocket的xml-socket授权
```
swoole websocket是支持IE浏览器的，在不支持HTML5标准的浏览器上，如IE6/7/8/9，swoole框架会自动启用flash-websocket。

客户端在webim_client目录下，可以将此目录放到Apache/Nginx下，将config.js中的服务器ip和端口修改为对应的。在浏览器中访问index.html。

WebSocket服务器
----
```shell
php websocket_server.php
```
客户端websocket_client.html，需要修改js代码中的ip和端口，可以直接用浏览器打开此页面。然后打开chrome的调试工具，或火狐的firebug，
然后终端执行websocket.send("hello"),向服务器发送信息。

HttpServer的使用方法
----
http服务器跟fpm和apache很像，只是去包含documentRoot中的php文件，没有带有任何额外功能。
与app_server.php不同，http_server.php是没有携带任何Swoole Web框架功能的。
```shell
php http_server.php
```

AppServer的使用方法
----
AppServer就是Swoole的内置应用服务器，你需要安装Swoole Web框架的规范来写代码，所以应用程序的代码都在apps/目录中。
URL会路由到Controller的方法中，数据库的处理使用Swoole框架提供的Model或者SelectDB，模板使用smarty引擎或者直接使用php作为模板。
```shell
php app_server.php
```

