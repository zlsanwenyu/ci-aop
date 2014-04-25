<?php

class Index extends CI_Controller {
	function __construct(){
		parent::__construct();
	}
	
	function index(){
		//todo 校验sign
		$this->load->library('aop');
		switch ($this->input->post('service')){
			case 'alipay.service.check'://验证网关url、和开发者公钥的有效性
				echo $this->aop->check_string();
				break;
			case 'alipay.mobile.public.message.notify'://开发者接收消息 支持 follow：关注消息； unfollow：取消关注； click：自定义菜单点击事件(authentication:公众账户进行鉴权 添加商户账号绑定事件 delete:解除绑定商户会员号)
				$biz_content = $this->input->post('biz_content');
				$biz_content = $this->aop->xml2array($biz_content);
				//todo 根据不同的事件 做相应的业务处理
				break;
				
			default:
				break;
		}
	}
}