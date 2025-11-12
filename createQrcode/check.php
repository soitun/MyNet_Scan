<?php
	// 编码
	header("Content-type:application/json");
	
    // 获取参数
    $mcode = trim($_GET['mcode']);
    
    if($mcode) {
       
        // 数据库配置
    	include '../Db.php';
        
        // 实例化类
    	$db = new DB_API($config);
    	
        // 查看Scene的状态
    	$checkScanStatus = $db->set_table('scanlogin_loginAuth')->find(['machine_code' => $mcode]);

    	if($checkScanStatus){
            // 扫码状态
    	    $status = $checkScanStatus['status'];
    	    $openid = $checkScanStatus['openid'];
    	    $expire = $checkScanStatus['expire'];
    	    $token  = $checkScanStatus['token'];
			$scene  = $checkScanStatus['scene'];
    	    
    	    if($status == 1) {
    	       // 未扫码
    	       $result = array(
    		        'code' => 202,
    		        'msg' => '请使用微信扫码'
    		   );
    	    }else if($status == 2) {
    	       // 已扫码
    	       $result = array(
    		        'code' => 203,
    		        'msg' => '已扫码，请点击授权登录'
    		   );
    		   
    	    }else if($status == 3 && $openid) {
    		   // 删除临时文件
               unlink('qrcode/' . $scene . '.png');
               
               // 已登录
    	       $result = array(
    		        'code' => 200,
    		        'msg' => '节点已绑定， 可以关闭了',
    		        'token' => $token
    		   );
    	    }else if($status == 4) {
    	       // 已取消授权
    	       $result = array(
    		        'code' => 204,
    		        'msg' => '已取消授权'
    		   );
    		   // 删除临时文件
               unlink('qrcode/' . $scene . '.png');   	       
    	    }
    	}else{
    	    // 获取失败
            $result = array(
                'code' => 204,
                'msg' => '该二维码无效'
            );
    	}
    }else {
        $result = array(
            'code' => 204,
            'msg' => '缺少参数'
        );
    }

	// 输出JSON
	echo json_encode($result, JSON_UNESCAPED_UNICODE);	
?>