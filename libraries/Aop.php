<?php
class Aop{
	public $ci = '';
	public $client = '';
	function __construct(){
		$this->ci = &get_instance();
		require_once(APPPATH.'/third_party/aop/AopClient.php');
		$this->client = new AopClient;
		$this->ci->config->load('alipay');
		$this->client->appId = $this->ci->config->item('appId');
		$this->client->rsaPrivateKeyFilePath = $this->ci->config->item('rsaPrivateKeyFilePath');
	}
	
	/**
	 * xml转数组
	 *
	 * @param string $xml
	 * @return unknown
	 */
	function xml2array(&$xml) {
	  	$parser = xml_parser_create();
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parse_into_struct($parser, $xml, $values, $index);

        xml_parser_free($parser);
        $count = count($values);
        $result = array();
        for ($i=1;$i<$count-1;$i++){
        	if($values[$i]['tag'] == 'UserInfo'){//userinfo返回的是json所以转下
        		$result[$values[$i]['tag']] = isset($values[$i]['value']) ? json_decode($values[$i]['value'],true) : '';
        	}else{
        		$result[$values[$i]['tag']] = isset($values[$i]['value']) ? $values[$i]['value'] : '';
        	}
        }
        return $result;
	}
	
	//支付宝公共帐号开通校验函数
	function check_string(){
		$rsaPublicKey =$this->ci->config->item('rsaPublicKey');
		$sign = $this->client->sign('<success>true</success><biz_content>'.$rsaPublicKey.'</biz_content>');
	    $res = "<?xml version=\"1.0\" encoding=\"GBK\"?><alipay><response><success>true</success><biz_content>".$rsaPublicKey."</biz_content></response><sign>".$sign."</sign><sign_type>RSA</sign_type></alipay>";
	    return $res;
	}
	
	/**
	 * 给用户发消息 支持图文信息 纯文本信息
	 *
	 * @param string $to_user_id 用户id 如果为空 则发给所有用户
	 * @param string $msg_type 消息类型 
	 * @param array $msg_items 消息内容 array('Title'=>,
	 * 										 'Desc'=>'',
	 * 										 'ImageUrl'=>'',
	 * 										 'Url'=>'',
	 * 										 'ActionName'=>'',
	 * 										 'AuthType'=>''
	 * 									)
	 */
	function send_msg($to_user_id='',$msg_type,$msg_items,$agreement_id=''){
		//todo 参数过滤
		require_once('/request/AlipayMobilePublicMessagePushRequest.php');
		$req = new AlipayMobilePublicMessagePushRequest();
		$bizContent = '<XML>
						    <ToUserId><![CDATA[2088102582608698]]></ToUserId>
						    <AgreementId><![CDATA[20130925002108505032]]></AgreementId>
						    <AppId><![CDATA[2013091300001603]]></AppId>
						    <CreateTime>1380160451638</CreateTime>
						    <MsgType><![CDATA[image-text]]></MsgType>
						    <ArticleCount>1</ArticleCount>
						    <Articles>
						        <Item>
						            <Title><![CDATA[优惠信息]]></Title>
						            <Desc><![CDATA[老用户全场优惠，免运费。]]></Desc>
						            <ImageUrl><![CDATA[http://alipay.com/ima/2013.jpg]]></ImageUrl>
						            <Url><![CDATA[http://alipay.com/7602.html]]></Url>
						        </Item>
						    </Articles>
						    <Push><![CDATA[false]]></Push>
						</XML>';
					/*
						<XML>
							<ToUserId><![CDATA[208856789999]]></ToUserId>
							<AppId><![CDATA[20885678888]]></AppId>
							<AgreementId><![CDATA[2013080800008888]]></AgreementId>
							<CreateTime>12334349884</CreateTime>
							<MsgType><![CDATA[image-text]]></MsgType>
							<ArticleCount>1</ArticleCount>
							<Articles>
							<Item>
							<Title><![CDATA[这是标题]]></Title>
							<Desc><![CDATA[这是纯文本内容]]></Desc>
							<ImageUrl><![CDATA[]]></ImageUrl>
							<Url><![CDATA[]]></Url>
							</Item>
							</Articles>
							<Push><![CDATA[false]]></Push>
						</XML>
					*/
						
		$req->setBizContent($bizContent);
		$resp = $this->client->execute($req);
		//todo 结果处理
	}
	
	/**
	 * 创建带参数的二维码 目前有2种类型的二维码，分别是临时二维码、和永久二维码，前者有过期时间，最大为1800秒
	 *
	 */
	function create_qrcode($codeType,$expireSecond='',$sceneId='',$showLogo=''){
		//todo 参数过滤
		require_once('/request/AlipayMobilePublicQrcodeCreateRequest.php');
		$req = new AlipayMobilePublicQrcodeCreateRequest();
		
		/*
		biz_content格式：json
			临时二维码biz_content例子：
			 
			{
			"codeType":"TEMP",
			"expireSecond":1800,
			"codeInfo":{
			"scene":{
			"sceneId":"1234"
			}
			},
			"showLogo":"N"
			}
			 
			永久二维码biz_content例子：
			{
			"codeType":"PERM",
			"codeInfo":{
			"scene":{
			"sceneId":"1234"
			}
			},
			"showLogo":"N"
			}
		*/
		$bizContent = '';
		$req->setBizContent($bizContent);
		$resp = $this->client->execute($req);
		//todo 结果处理
	}
	
	/**
	 * 创建菜单
	 */
	function add_menu(){
		//todo 参数过滤
		require_once('/request/AlipayMobilePublicMenuAddRequest.php');
		$req = new AlipayMobilePublicMenuAddRequest();
		
		/**
		 * { "button": [ { "actionParam": "ZFB_HFCZ", "actionType": "out", "name": "话费充值" }, { "name": "查询", "subButton": [ { "actionParam": "ZFB_YECX", "actionType": "out", "name": "余额查询" }, { "actionParam": "ZFB_LLCX", "actionType": "out", "name": "流量查询" }, { "actionParam": "ZFB_HFCX", "actionType": "out", "name": "话费查询" } ] }, { "actionParam": "http://m.alipay.com", "actionType": "link", "name": "最新优惠" } ] }
		 */
		$bizContent = '';
		$req->setBizContent($bizContent);
		$resp = $this->client->execute($req);
		//todo 结果处理
	}
	
	function update_menu(){
		//todo 参数过滤
		require_once('/request/AlipayMobilePublicMenuUpdateRequest.php');
		$req = new AlipayMobilePublicMenuUpdateRequest();
		/**
		 * { "button": [ { "actionParam": "ZFB_HFCZ", "actionType": "out", "name": "话费充值" }, { "name": "查询", "subButton": [ { "actionParam": "ZFB_YECX", "actionType": "out", "name": "余额查询" }, { "actionParam": "ZFB_LLCX", "actionType": "out", "name": "流量查询" }, { "actionParam": "ZFB_HFCX", "actionType": "out", "name": "话费查询" } ] }, { "actionParam": "http://m.alipay.com", "actionType": "link", "name": "最新优惠" } ] }
		 */
		$bizContent = '';
		$req->setBizContent($bizContent);
		$resp = $this->client->execute($req);
		//todo 结果处理
	}
	
	function get_menu(){
		require_once('/request/AlipayMobilePublicMenuGetRequest.php');
		$req = new AlipayMobilePublicMenuGetRequest();
		/**
		 * { "button": [ 
					{ "actionParam": "ZFB_HFCZ", "actionType": "out", "name": "话费充值" }, 
					{ "name": "查询", "subButton": [ 
						{ "actionParam": "ZFB_YECX", "actionType": "out", "name": "余额查询" }, 
						{ "actionParam": "ZFB_LLCX", "actionType": "out", "name": "流量查询" }, 
						{ "actionParam": "ZFB_HFCX", "actionType": "out", "name": "话费查询" } 
						] 
					} 
				] 
			}
		 */
		$resp = $this->client->execute($req);
		//todo 结果处理
	}
	
	/**
	 * 获取免登录的access_token
	 *
	 */
	function get_access_token(){
		require_once('/request/AlipaySystemOauthTokenRequest.php');
		$req = new AlipaySystemOauthTokenRequest();
		$req->setGrantType($GrantType);
		$req->setCode($Code);
		$req->setRefreshToken($RefreshToken);
	}
	
	//获取用户信息
	function get_userinfo(){
		require_once('/request/AlipayUserUserinfoShareRequest.php');
		$req = new AlipayUserUserinfoShareRequest();
		$resp = $this->client->execute($req);
		//todo 结果处理
	}
}

