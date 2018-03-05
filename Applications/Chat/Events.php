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

/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */
//declare(ticks=1);

/**
 * 聊天主逻辑
 * 主要是处理 onMessage onClose
 */
use \GatewayWorker\Lib\Gateway;

class Events
{

    /**
     * 有消息时
     * @param int $client_id
     * @param mixed $message
     */
    public static function onMessage($client_id,$message)
    {

        // debug
//        echo "client:{$_SERVER['REMOTE_ADDR']}:{$_SERVER['REMOTE_PORT']} gateway:{$_SERVER['GATEWAY_ADDR']}:{$_SERVER['GATEWAY_PORT']}  client_id:$client_id session:".json_encode($_SESSION)." onMessage:".$message."\n";

        // 客户端传递的是加密json数据
        $message_data = self::crypto($message,2);

        if(!$message_data)
        {
            return ;
        }

        // 根据类型执行不同的业务
        switch($message_data['type'])
        {
            // 客户端回应服务端的心跳
            case 'pong':
                return ;
            // 客户端登录 message格式: {type:login, name:xx, room_id:1} ，添加到客户端，广播给所有客户端xx进入聊天室
            case 'login':
                // 判断是否有房间号
                if(!isset($message_data['room_id']))
                {
                    throw new \Exception("\$message_data['room_id'] not set. client_ip:{$_SERVER['REMOTE_ADDR']} \$message:$message");
                }
                if (!self::checkData($message_data)) {
                    return Gateway::closeCurrentClient();
                }
//                echo "是否在线";var_dump(Gateway::isOnline($client_id));
                if (isset($_SESSION['room_id'])) {
                    return Gateway::closeCurrentClient();
                }

                // 把房间号昵称放到session中
                $room_id = $message_data['room_id'];
                $client_name = htmlspecialchars($message_data['client_name']);
                $client_photo = $message_data['to_client_photo'];
                $_SESSION['room_id'] = $room_id;
                $_SESSION['client_name'] = $client_name;
                $_SESSION['client_photo'] = $client_photo;


                // 获取房间内所有用户列表
                $clients_list = Gateway::getClientCountByGroup($room_id);


                // 转播给当前房间的所有客户端，xx进入聊天室 message {type:login, client_id:xx, name:xx}
                $new_message = array('type'=>$message_data['type'], 'client_id'=>$client_id, 'client_name'=>htmlspecialchars($client_name), 'time'=>date('Y-m-d H:i:s'));
                Gateway::sendToGroup($room_id, self::crypto(json_encode($new_message),1));
                Gateway::joinGroup($client_id, $room_id);

                // 给当前用户发送用户列表
                $new_message['client_count'] = $clients_list;

                Gateway::sendToCurrentClient(self::crypto(json_encode($new_message),1));
                return;

            // 客户端发言 message: {type:say, to_client_id:xx, content:xx}
            case 'say':
                // 非法请求
                if(!isset($_SESSION['room_id']))
                {
                    throw new \Exception("\$_SESSION['room_id'] not set. client_ip:{$_SERVER['REMOTE_ADDR']}");
                }

                if (self::checkData($message_data)) {

                    $room_id = $_SESSION['room_id'];
                    $client_name = $_SESSION['client_name'];
                    $client_photo = $_SESSION['client_photo'];

                    // 私聊
//                if($message_data['to_client_id'] != 'all')
//                {
//                    $new_message = array(
//                        'type'=>'say',
//                        'from_client_id'=>$client_id,
//                        'from_client_name' =>$client_name,
//                        'to_client_id'=>$message_data['to_client_id'],
//                        'content'=>"<b>对你说: </b>".nl2br(htmlspecialchars($message_data['content'])),
//                        'time'=>date('Y-m-d H:i:s'),
//                    );
//                    Gateway::sendToClient($message_data['to_client_id'], json_encode($new_message));
//                    $new_message['content'] = "<b>你对".htmlspecialchars($message_data['to_client_name'])."说: </b>".nl2br(htmlspecialchars($message_data['content']));
//                    return Gateway::sendToCurrentClient(json_encode($new_message));
//                }
//                    $message = openssl_decrypt($message_data['content'], 'aes-128-cbc', $key, OPENSSL_ZERO_PADDING , $iv);
                    $new_message = array(
                        'type' => 'say',
                        'from_client_id' => $client_id,
                        'from_client_name' => $client_name,
                        'from_client_photo' => $client_photo,
                        'to_client_id' => 'all',
                        'content' => nl2br(htmlspecialchars($message_data['content'])),
                        'time' => date('Y-m-d H:i:s'),
                    );
                    return Gateway::sendToGroup($room_id, self::crypto(json_encode($new_message),1));
                } else {

                    return Gateway::closeCurrentClient();
                }
        }
    }

    /**
     * 当客户端断开连接时
     * @param integer $client_id 客户端id
     */
    public static function onClose($client_id)
    {
        // debug
        echo "client:{$_SERVER['REMOTE_ADDR']}:{$_SERVER['REMOTE_PORT']} gateway:{$_SERVER['GATEWAY_ADDR']}:{$_SERVER['GATEWAY_PORT']}  client_id:$client_id onClose:''\n";

        // 从房间的客户端列表中删除
        if(isset($_SESSION['room_id']))
        {
            $room_id = $_SESSION['room_id'];
            $new_message = array('type'=>'logout', 'from_client_id'=>$client_id, 'from_client_name'=>$_SESSION['client_name'], 'time'=>date('Y-m-d H:i:s'));
            Gateway::sendToGroup($room_id, self::crypto(json_encode($new_message),1));
        }
    }

    /*
     *  数据验证
     */
    private static function checkData($data)
    {
        $token = 'abcdefghijklmnopqrstuvwxyz*.123456';

        // 验证发送时间 Socket连接到进入房间不能超过五秒
        if (!$data['_timestamp'] || (abs($_SESSION['start_time'] - $data['_timestamp']) >5 && $data['type'] !== 'say')) {
            echo '时间错了';

            return false;
        } else if (!$data['_token'] || $data['_token'] !== $token) {
            echo 'token错了';

            return false;
        } else if($data['type'] !== 'say' && !self::checkMd5($data) ){
            echo '加密错了';

            return false;
        } else {

            return true;
        }
    }

    /**
     *  数据md5加密
     * @param $data
     * @return bool
     */
    private static function checkMd5($data)
    {
        $md5 = $data['_checksum'];
        unset($data['_checksum']);
        $data['_secret'] = $data['_token']."zt".$data['_timestamp'];
        ksort($data);
        $stringfy = '';
        foreach ($data as $k=>$v)
        {
            if($stringfy)
            {
                $stringfy .= '&';
            }
            $stringfy .= "{$k}={$v}";
        }
        $server_md5 = md5($stringfy);
        if ($server_md5 !== $md5) {

            return false;
        } else {

            return true;
        }
    }

    /**
     * openssl 加密解密
     */
    private static function crypto($data,$type)
    {
        $key = '*zt$zhengwu%win.';
        $iv = "hahahaha!@#$%^&*";
        if ($type == 1) { // 加密
            $data = openssl_encrypt($data, 'aes-128-cbc', $key, false , $iv);
//            echo '加密输出'.$data;

        } else { // 解密
            $data = openssl_decrypt($data, 'aes-128-cbc', $key, OPENSSL_ZERO_PADDING , $iv);
            $data = trim($data);
            $data = json_decode($data, true);
        }

        return $data;
    }

    private static function crypto1($data,$type)
    {
        $privateKeyFilePath = 'rsa_private_key.pem';
        $privateKey = openssl_pkey_get_private(file_get_contents($privateKeyFilePath));
        $res = '';
        if ($type == 1) { // 加密
            $data = openssl_private_encrypt($data, $res, $privateKey);
            $data = base64_encode($data);
        } else { // 解密
            $data = base64_decode($data);
            $data = openssl_private_decrypt($data, $res, $privateKey);
            $data = trim($data);
            $data = json_decode($data, true);
        }

        return $data;
    }

    /**
     * Socket服务连接
     * @param $connect
     */
    public static function onConnect($connect)
    {
        $_SESSION['start_time'] = time();
    }
}
