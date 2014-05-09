<?php

function index(){
	//todo 校验sign
	require_once('./AOP.php');
	$service = strtolower($_POST['service']);
	//验证网关url、和开发者公钥的有效性
	if($service == 'alipay.service.check'){
		$aop = new AOP;
		echo $aop->check_string();
	}elseif ($service == 'alipay.mobile.public.message.notify'){//开发者接收消息
		$biz_content = $_POST['biz_content'];
		$aop = new AOP;
		$biz_content_arr = $aop->xml2array($biz_content);
		switch ($biz_content_arr['EventType']){
			case 'follow': //follow：关注消息
				break;
			case 'unfollow'://unfollow：取消关注
				break;
			case 'click'://click：自定义菜单点击事件(authentication:公众账户进行鉴权 添加商户账号绑定事件 delete:解除绑定商户会员号)
				break;
			default:
				break;
		}
	}
}