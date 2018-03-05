<?php 
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */
use \Workerman\Worker;
use \GatewayWorker\Gateway;
use \Workerman\Autoloader;

// gateway 进程
$gateway = new Gateway("Websocket://0.0.0.0:7272");
// 设置名称，方便status时查看
$gateway->name = 'ChatGateway';
// 设置进程数，gateway进程数建议与cpu核数相同
$gateway->count = 4;
// 分布式部署时请设置成内网ip（非127.0.0.1）
$gateway->lanIp = '127.0.0.1';
// 内部通讯起始端口。假如$gateway->count=4，起始端口为2300
// 则一般会使用2300 2301 2302 2303 4个端口作为内部通讯端口 
$gateway->startPort = 2300;
// 心跳间隔
$gateway->pingInterval = 10;
// 心跳数据
//$gateway->pingData = '{"type":"ping"}';
//$gateway->pingData = '9QaOwoCdwLGbIAnsmiFkvQ=='; //加密后

// 服务注册地址
$gateway->registerAddress = '127.0.0.1:1236';

// 改写心跳事件 手动去覆盖
//$gateway->ping = function ()
//{
//    $t = rand(1,20);
//    $b = rand(1,20);
//    $ping_data = '{"t":'.$b.',"type":"ping","b":'.$t."}";
//    $key = '*zt$zhengwu%win.';
//    $iv = "hahahaha!@#$%^&*";
//    $ping_data = openssl_encrypt($ping_data, 'aes-128-cbc', $key, false , $iv);
//
//    $raw = false;
//    if ($this->protocolAccelerate && $ping_data && $this->protocol) {
//        $ping_data = $this->preEncodeForClient($ping_data);
//        $raw = true;
//    }
//    // 遍历所有客户端连接
//    foreach ($this->_clientConnections as $connection) {
//        // 上次发送的心跳还没有回复次数大于限定值就断开
//        if ($this->pingNotResponseLimit > 0 &&
//            $connection->pingNotResponseCount >= $this->pingNotResponseLimit * 2
//        ) {
//            $connection->destroy();
//            continue;
//        }
//
//        // 连接后到第一次心跳没有session认为非法连接
//        if (!$connection->session) {
//            $connection->destroy();
//            continue;
//        }
//        // $connection->pingNotResponseCount 为 -1 说明最近客户端有发来消息，则不给客户端发送心跳
//        $connection->pingNotResponseCount++;
//        if ($ping_data) {
//            if ($connection->pingNotResponseCount === 0 ||
//                ($this->pingNotResponseLimit > 0 && $connection->pingNotResponseCount % 2 === 1)
//            ) {
//                continue;
//            }
//            $connection->send($ping_data, $raw);
//        }
//    }
//};



// // 当客户端连接上来时，设置连接的onWebSocketConnect，即在websocket握手时的回调
//$gateway->onConnect = function($connection)
//{
//    $connection->onWebSocketConnect = function($connection , $http_header)
//    {
//        // 可以在这里判断连接来源是否合法，不合法就关掉连接
//        // $_SERVER['HTTP_ORIGIN']标识来自哪个站点的页面发起的websocket链接
//        if($_SERVER['HTTP_ORIGIN'] != 'http://chat.workerman.net')
//        {
//            $connection->close();
//        }
//        // onWebSocketConnect 里面$_GET $_SERVER是可用的
//        // var_dump($_GET, $_SERVER);
//    };
//};


// 如果不是在根目录启动，则运行runAll方法
if(!defined('GLOBAL_START'))
{
    Worker::runAll();
}

