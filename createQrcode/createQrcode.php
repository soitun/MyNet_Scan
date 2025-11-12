<?php
    header("Content-type:application/json");
    
    include '../Db.php';
    $db = new DB_API($config);
    
    $machine_code = $_GET['mcode'];
    $name = $_GET['name'];
    $os = $_GET['os'];
    $peer_id = $_GET['pid'];

    if((!$machine_code) || (!$name) || (!$os) || (!$peer_id)){
        $result = array(
            'code' => 400,
            'msg' => '请输入所有必要参数'
        );
        echo json_encode($result);exit;
    }
    
    // appid和appsecret
    $appid = $config['appid'];
    $appsecret = $config['appsecret'];
    
    // 本地缓存access_token的文件
    $cacheFile = 'access_token.php';
    $access_token = getAccessToken($cacheFile, $appid, $appsecret);
    
    // -----------------------------------------------------------------------------------------------------------------------------------------------------
    // 获取access_token
    function getAccessToken($cacheFile, $appid, $appsecret) {
        if (file_exists($cacheFile)) {
            $cacheFiledata = include($cacheFile);
            if ($cacheFiledata['expire_time'] > time()) {
                return $cacheFiledata['access_token'];
            }else {
                return createAccessToken($appid, $appsecret, $cacheFile);
            }
        } else {            
            return createAccessToken($appid, $appsecret, $cacheFile);
        }
    }
    
    // -----------------------------------------------------------------------------------------------------------------------------------------------------
    // 生成access_token
    function createAccessToken($appid, $appsecret, $cacheFile) {
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$appsecret}";
        $response = file_get_contents($url);
        $access_token_data = json_decode($response, true);
        
        // 获取获取到access_token
        if (isset($access_token_data['access_token'])) {
           $access_token_data['expire_time'] = time() + 7200;
           $cacheFiledata = [
              'access_token' => $access_token_data['access_token'],
              'expire_time' => $access_token_data['expire_time']
           ];
            
           file_put_contents($cacheFile, "<?php return " . var_export($cacheFiledata, true) . ";");
           return $access_token_data['access_token'];
        }
    }
    
    /**
     * -----------------------------------------------------------------------------------------------------------------------------------------------------
     * 获取客户端真实 IP（尽力而为）
     * @return string  返回 IPv4/IPv6 地址；如获取失败返回 '0.0.0.0'
     */
    function get_client_real_ip(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',   // Cloudflare
            'HTTP_X_FORWARDED_FOR',    // 通用代理
            'HTTP_CLIENT_IP',          // 旧版代理
            'HTTP_X_REAL_IP',          // Nginx 反向代理
            'HTTP_X_CLUSTER_CLIENT_IP',// 集群环境
        ];

        $ip = '';
        foreach ($headers as $h) {
            if (empty($_SERVER[$h])) {
                continue;
            }
            // 可能为 "1.1.1.1, 2.2.2.2, 3.3.3.3"
            $ips = array_map('trim', explode(',', $_SERVER[$h]));
            foreach ($ips as $segment) {
                // 过滤私有/保留地址
                if (filter_var($segment, FILTER_VALIDATE_IP,
                            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    $ip = $segment;
                    break 2;
                }
            }
        }

        if ($ip === '') {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }

        return $ip;
    }


    // -----------------------------------------------------------------------------------------------------------------------------------------------------
    // 创建小程序码
    function creatQrcode($db, $access_token, $machine_code, $name, $os, $peer_id){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=' . $access_token);
        curl_setopt($ch, CURLOPT_POST, true);
        
        // 生成scene（如果你要换算法，也只能生成纯数字的）
        $scene = rand(1000000,9999999);
        $data = array(
                "page"          => "pages/index/index", // 小程序扫码页面的路径
                "scene"         => $scene,
                "check_path"    => false, // 是否验证你的路径是否正确
                "env_version"   => "develop" // 开发的时候这个参数是develop，小程序审核通过发布上线之后改为release  
        );
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
    
        // 将数据写入本地文件并保存在qrcode目录
        file_put_contents('./qrcode/' . $scene . '.png', $result);
        curl_close($ch);
        
        $qrcode = $db->set_table('scanlogin_loginAuth')->find(['machine_code' => $machine_code]);
        if($qrcode){
            $creatQrcode = $db->set_table('scanlogin_loginAuth')->update(
                ['machine_code'=>$machine_code],
                ['scene'=>$scene,'wan_ip'=>get_client_real_ip()]
            );
        }else{
            // 向数据库插入一条生成小程序码的记录
            $creatQrcode = $db->set_table('scanlogin_loginAuth')->add(
                [
                    'createTime'    => time(),
                    'scene'         => $scene,
                    'machine_code'   => $machine_code,
                    'name'          => $name,
                    'peer_id'       => $peer_id,
                    'os'            => $os,
                    'wan_ip'        => get_client_real_ip(),
                ]
            );
        }
        
        // 创建成功
        if($creatQrcode){
            $result = array(
                'code'    => 200,
                'msg'     => 'success',
                'scene'   => $scene,
                'qrcode'  => $scene.'.png'
            );
        }else{
            $result = array(
                'code'   => 201,
                'msg'    => 'fail',
                'scene'  => '',
                'qrcode' => ''
            );
        }
        
        // 输出JSON数据
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }
    
    // 调用函数进行创建小程序码
    creatQrcode($db, $access_token, $machine_code, $name, $os, $peer_id);
?>