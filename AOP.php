<?php

class AOP {
	//应用ID
	public $appId;
    //私钥文件路径
	public $rsaPrivateKeyFilePath;
    //网关
	public $gatewayUrl = "https://openapi.alipay.com/gateway.do";
    //返回数据格式
	public $format = "json";
    //api版本
	public $apiVersion = "1.0";
    //签名类型
	protected $signType = "RSA";
	protected $alipaySdkVersion = "alipay-sdk-php-20130320";
	//授权地址
	public $auth_url = 'https://openauth.alipay.com/oauth2/authorize.htm';
	
	private $error_tips_lang = 'zh';//错误提示语言默认中文 如果需要E文 则修改为 en
	public  $respone = array('is_error'=>false,'result'=>'');//返回结果的结构
	
	public $data_dir = './';//记录日志的根目录
	public $config_file = './config.php';//配置文件地址
	
	private $apiMethod;
	private $apiParas = array();
	private $terminalType;	
	private $terminalInfo;
	private $rsaPublicKey;
	private $rsaPublicKeyPem;
	public $access_token;
	public $refreshToken;
	private $client_id;
	
	function __construct(){
		//载入配置
		require_once($config_file);
		$this->appId = $config['appId'];
		$this->client_id = $config['client_id'];
		$this->rsaPrivateKeyFilePath = $config['rsaPrivateKeyFilePath'];
		$this->rsaPublicKeyFilePath = $config['rsaPublicKeyFilePath'];
		$this->rsaPublicKey = $config['rsaPublicKey'];
		$this->rsaPublicKeyPem = $config['rsaPublicKeyPem'];
	}
	
	public function generateSign($params) {
		return $this->sign($this->getSignContent($params));
	}

	public function rsaSign($params) {
		return $this->sign($this->getSignContent($params));
	}
	
	protected function getSignContent($params){
		ksort($params);

		$stringToBeSigned = "";
		$i = 0;
		foreach ($params as $k => $v) {
			if (false === $this->checkEmpty($v) && "@" != substr($v, 0, 1)) {
				if ($i == 0) {
					$stringToBeSigned .= "$k" . "=" . "$v";
				} else {
					$stringToBeSigned .= "&" . "$k" . "=" . "$v";
				}
				$i++;
			}
		}
		unset ($k, $v);
		return $stringToBeSigned;
	}

	public function sign($data) {
		$priKey = file_get_contents($this->rsaPrivateKeyFilePath);
		$res = openssl_get_privatekey($priKey);
		openssl_sign($data, $sign, $res);
		openssl_free_key($res);
		$sign = base64_encode($sign);
		return $sign;
	}

	protected function curl($url, $postFields = null) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_FAILONERROR, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, false);

		$postBodyString = "";
		if (is_array($postFields) && 0 < count($postFields)) {
			
			$postMultipart = false;
			foreach ($postFields as $k => $v) {
				if ("@" != substr($v, 0, 1)) //判断是不是文件上传
					{
					$postBodyString .= "$k=" . urlencode($v) . "&";
				} else //文件上传用multipart/form-data，否则用www-form-urlencoded
					{
					$postMultipart = true;
				}
			}
			unset ($k, $v);
			curl_setopt($ch, CURLOPT_POST, true);
			if ($postMultipart) {
				curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
			} else {
				curl_setopt($ch, CURLOPT_POSTFIELDS, substr($postBodyString, 0, -1));
			}
		}
		$headers = array('content-type: application/x-www-form-urlencoded;charset=UTF-8');	
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		$reponse = curl_exec($ch);

		if (curl_errno($ch)) {
			throw new Exception(curl_error($ch), 0);
		} else {
			$httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			if (200 !== $httpStatusCode) {
				throw new Exception($reponse, $httpStatusCode);
			}
		}
		curl_close($ch);
		return $reponse;
	}

	protected function logCommunicationError($apiName, $requestUrl, $errorCode, $responseTxt) {
		$localIp = isset ($_SERVER["SERVER_ADDR"]) ? $_SERVER["SERVER_ADDR"] : "CLI";
		$log_file = rtrim($this->data_dir, '\\/') . '/' . "logs/aop_comm_err_" . date("Y-m-d") . ".log";
		$logData = array (
			date("Y-m-d H:i:s"),
			$apiName,
			$this->appId,
			$localIp,
			PHP_OS,
			$this->alipaySdkVersion,
			$requestUrl,
			$errorCode,
			str_replace("\n", "", $responseTxt)
		);
		error_log(var_export($logData,true).'\r\n\r\n',3,$log_file);
	}

	public function execute() {
		//组装系统参数
		$sysParams["app_id"] = $this->appId;
		$sysParams["version"] = $this->apiVersion;
		$sysParams["format"] = $this->format;
		$sysParams["sign_type"] = $this->signType;
		$sysParams["method"] = $this->apiMethod;
		$sysParams["timestamp"] = date("Y-m-d H:i:s");
		$sysParams["auth_token"] = $this->access_token;
		$sysParams["alipay_sdk"] = $this->alipaySdkVersion;
		$sysParams["terminal_type"] = $this->terminalType;
		$sysParams["terminal_info"] = $this->terminalInfo;
		
		//签名
		$sysParams["sign"] = $this->generateSign(array_merge($this->apiParas, $sysParams));

		//系统参数放入GET请求串
		$requestUrl = $this->gatewayUrl . "?";
		foreach ($sysParams as $sysParamKey => $sysParamValue) {
			$requestUrl .= "$sysParamKey=" . urlencode($sysParamValue) . "&";
		}
		$requestUrl = substr($requestUrl, 0, -1);
		//发起HTTP请求
		try {
			$resp = $this->curl($requestUrl, $this->apiParas);
		} catch (Exception $e) {
			$this->logCommunicationError($sysParams["method"], $requestUrl, "HTTP_ERROR_" . $e->getCode(), $e->getMessage());
			return false;
		}

		//解析AOP返回结果
		$respWellFormed = false;

		if ("json" == $this->format) {
			$respObject = json_decode($resp);
			if (null !== $respObject) {
				$respWellFormed = true;								
			}
		} else
			if ("xml" == $this->format) {
				$respObject = @ simplexml_load_string($resp);
				if (false !== $respObject) {
					$respWellFormed = true;
				}
			}

		//返回的HTTP文本不是标准JSON或者XML，记下错误日志
		if (false === $respWellFormed) {
			$this->logCommunicationError($sysParams["method"], $requestUrl, "HTTP_RESPONSE_NOT_WELL_FORMED", $resp);
			return false;
		}

		//如果AOP返回了错误码，记录到业务错误日志中
		if (isset ($respObject->code)) {
			$logger = new LtLogger;
			$logger->conf["log_file"] = rtrim($this->data_dir, '\\/') . '/' . "logs/aop_biz_err_" . $this->appId . "_" . date("Y-m-d") . ".log";
			$logger->log(array (
				date("Y-m-d H:i:s"),
				$resp
			));
		}
		return $respObject;
	}
	
	/**
	 * 校验$value是否非空
	 *  if not set ,return true;
	 *	if is null , return true;
	 **/
	protected function checkEmpty($value) {
		if(!isset($value))
			return true ;
		if($value === null )
			return true;
		if(trim($value) === "")
			return true;
		
		return false;
	}

	public function rsaCheckV1($params,$rsaPublicKeyFilePath){
		$sign = $params['sign'];
		$params['sign_type'] = null;
		$params['sign'] = null;
		
		return $this->verify($this->getSignContent($params),$sign,$rsaPublicKeyFilePath);
	}
	
	public function rsaCheckV2($params,$rsaPublicKeyFilePath){
		$sign = $params['sign'];
		$params['sign'] = null;
		
		return $this->verify($this->getSignContent($params),$sign,$rsaPublicKeyFilePath);
	}
	
	function verify($data, $sign, $rsaPublicKeyFilePath) {
		//读取公钥文件
		$pubKey = file_get_contents($rsaPublicKeyFilePath);

		//转换为openssl格式密钥
		$res = openssl_get_publickey($pubKey);

		//调用openssl内置方法验签，返回bool值
		$result = (bool) openssl_verify($data, base64_decode($sign), $res);

		//释放资源  
		openssl_free_key($res);

		return $result;
	}

	public function checkSignAndDecrypt($params,$rsaPublicKeyPem,$rsaPrivateKeyPem,$isCheckSign,$isDecrypt){
		$charset=$params['charset'];
		$bizContent=$params['biz_content'];
		if($isCheckSign){
			if(!$this->rsaCheckV2($params,$rsaPublicKeyPem)){
				echo "<br/>checkSign failure<br/>";
				exit;
			}
		}
		if($isDecrypt){
			return $this->rsaDecrypt($bizContent,$rsaPrivateKeyPem,$charset);
		}

		return $bizContent;
	}

	public function encryptAndSign($bizContent,$rsaPublicKeyPem,$rsaPrivateKeyPem,$charset,$isEncrypt,$isSign){
		// 加密，并签名
		if($isEncrypt&&$isSign){
			$encrypted=$this->rsaEncrypt($bizContent,$rsaPublicKeyPem,$charset);
			$sign=$this->sign($bizContent);
			$response = "<?xml version=\"1.0\" encoding=\"$charset\"?><alipay><response>$encrypted</response><encryption_type>RSA</encryption_type><sign>$sign</sign><sign_type>RSA</sign_type></alipay>";
			return $response;
		}
		// 加密，不签名
		if($isEncrypt&&(!$isSign)){
			$encrypted=$this->rsaEncrypt($bizContent,$rsaPublicKeyPem,$charset);
			$response = "<?xml version=\"1.0\" encoding=\"$charset\"?><alipay><response>$encrypted</response><encryption_type>RSA</encryption_type></alipay>";
			return $response;
		}
		// 不加密，但签名
		if((!$isEncrypt)&&$isSign){
			$sign=$this->sign($bizContent);
			$response = "<?xml version=\"1.0\" encoding=\"$charset\"?><alipay><response>$bizContent</response><sign>$sign</sign><sign_type>RSA</sign_type></alipay>";
			return $response;
		}
		// 不加密，不签名
		$response = "<?xml version=\"1.0\" encoding=\"$charset\"?>$bizContent";
		return $response;
	}

	public function rsaEncrypt($data, $rsaPublicKeyPem, $charset) {
		//读取公钥文件
		$pubKey = file_get_contents($rsaPublicKeyPem);
		//转换为openssl格式密钥
		$res = openssl_get_publickey($pubKey);
		$blocks = $this->splitCN($data, 0, 30, $charset);
		$chrtext  = null;
		$encodes  = array ();
		foreach ($blocks as $n => $block) {
			if (!openssl_public_encrypt($block, $chrtext , $res)) {
				echo "<br/>" . openssl_error_string() . "<br/>";
			}
			$encodes[] = $chrtext ;
		}
		$chrtext = implode(",", $encodes);
		
		return $chrtext;
	}

	public function rsaDecrypt($data, $rsaPrivateKeyPem, $charset) {
		//读取私钥文件
		$priKey = file_get_contents($rsaPrivateKeyPem);
		//转换为openssl格式密钥
		$res = openssl_get_privatekey($priKey);
		$decodes = explode(',', $data);
		$strnull = "";
		$dcyCont = "";
		foreach ($decodes as $n => $decode) {
			if (!openssl_private_decrypt($decode, $dcyCont, $res)) {
				echo "<br/>" . openssl_error_string() . "<br/>";
			}
			$strnull.=$dcyCont;
		}
		return $strnull;
	}

	function splitCN($cont, $n = 0, $subnum, $charset) {
		//$len = strlen($cont) / 3;
		$arrr = array ();
		for ($i = $n; $i < strlen($cont); $i += $subnum) {
			$res = $this->subCNchar($cont, $i, $subnum, $charset);
			if (!empty ($res)) {
				$arrr[] = $res;
			}
		}

		return $arrr;
	}

	function subCNchar($str, $start = 0, $length, $charset = "gbk") {
		if (strlen($str) <= $length) {
			return $str;
		}
		$re['utf-8']="/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
		$re['gb2312']="/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
		$re['gbk']="/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
		$re['big5']="/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
		preg_match_all($re[$charset], $str, $match);
		$slice = join("", array_slice($match[0], $start, $length));
		return $slice;
	}
	
	function getApiParas(){
		return $this->apiParas;
	}
	
	function getTerminalType(){
		return $this->terminalType;
	}
	
	function setTerminalType($terminalType){
		$this->terminalType = $terminalType;
	}	
	
	function getTerminalInfo(){
		return $this->terminalInfo;
	}
	
	function setTerminalInfo($terminalInfo){
		$this->terminalInfo = $terminalInfo;
	}
	
	function set_rsaPublicKey($rsaPublicKey){
		$this->rsaPublicKey = $rsaPublicKey;
	}
	
	function get_rsaPublicKey(){
		return $this->rsaPublicKey;
	}
	
	function set_access_token($access_token){
		$this->access_token = $access_token;
	}
	
	/**
	 * 重定向到授权地址
	 *
	 * @param string $redirect_uri
	 * @param boolean $is_pay 默认登录授权 如果为true 则支付授权
	 */
	function redirect_authorization_url($redirect_uri,$is_pay=false){
		$auth_url = $this->auth_url.'?client_id='.$this->client_id.'&redirect_uri='.$redirect_uri;
		$is_pay && $auth_url.='&scope=p';
		header('location:'.$auth_url);
	}
	
	/**
	 * 获取access_token
	 *
	 * @param unknown_type $code
	 * @return unknown
	 */
	function get_access_token($code=''){
		if(empty($this->access_token)){
			$this->apiMethod = 'alipay.system.oauth.token';
			$this->apiParas["code"] = $code;
			$this->apiParas["grant_type"] = 'authorization_code';
			$result = $this->execute();
			//错误处理
			if(isset($result->error_response)){ 
				$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; $this->respone['is_error']=true; return $this->respone;
			}
			$this->access_token = $result->alipay_system_oauth_token_response->access_token;
			$this->refreshToken = $result->alipay_system_oauth_token_response->refresh_token;
		}
		$this->respone = array('is_error'=>false,'result'=>$result->alipay_system_oauth_token_response);
		return $this->respone;
	}
	
	/**
	 * 刷新获取access_token
	 *
	 */
	function refresh_token($refreshToken){
		$this->apiMethod = 'alipay.system.oauth.token';
		$this->apiParas["grant_type"] = 'refresh_token';
		$this->apiParas["refresh_token"] = $refreshToken;
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		$this->access_token = $result->alipay_system_oauth_token_response->access_token;
		$this->refreshToken = $result->alipay_system_oauth_token_response->refresh_token;
		
		$this->respone = array('is_error'=>false,'result'=>$result->alipay_system_oauth_token_response);
		return $this->respone;
	}
	
	/**
	 * 校验开通时 返回的串
	 *
	 * @param string $rsaPublicKey 公钥串 不包含 空格,-----BEGIN PUBLIC KEY-----,-----END PUBLIC KEY-----
	 * @return string xml串
	 */
	function verify_open(){
		$sign = $this->sign('<success>true</success><biz_content>'.$this->rsaPublicKey.'</biz_content>');
	    $res = "<?xml version=\"1.0\" encoding=\"GBK\"?><alipay><response><success>true</success><biz_content>".$this->rsaPublicKey."</biz_content></response><sign>".$sign."</sign><sign_type>RSA</sign_type></alipay>";
	    return $res;
	}
	
	/**
	 * 给用户发消息 支持图文信息 纯文本信息
	 *
	 * @param string $to_user_id 用户id 如果为空 则发给所有用户
	 * @param array $msg_items 消息内容 array(
	 * 										//第一篇文章
	 * 										array(
	 * 											'title'=>'标题',
	 * 										 	'desc'=>'内容',
	 * 										 	'image_url'=>'图片url',
	 * 										 	'url'=>'跳转url',
	 * 										 	'action_name'=>'立即前往',//公众账号消息页面展现消息按钮文案，建议跳转就用“立即前往”，查看就用“立即查看”，不超过10个汉字
	 * 										 	'auth_type'=>'loginAuth'//免登类型
	 * 										),
	 * 										//第二篇文章
	 * 										array(
	 * 											'title'=>'标题',
	 * 										 	'desc'=>'内容',
	 * 										 	'image_url'=>'图片url',
	 * 										 	'url'=>'跳转url',
	 * 										 	'action_name'=>'立即前往',//公众账号消息页面展现消息按钮文案，建议跳转就用“立即前往”，查看就用“立即查看”，不超过10个汉字
	 * 										 	'auth_type'=>'loginAuth'//免登类型
	 * 										),
	 * 								)
	 * @param string $has_img 消息中是否是图片 
	 */
	function message_push($to_uid='',$msg_items,$has_img=true,$agreement_id=''){
		$this->apiMethod = 'alipay.mobile.public.message.push';
		$ArticleCount = count($msg_items);
		$articles = ''; 
		foreach ($msg_items as $item){
			$articles .= "<Item>
							<Title><![CDATA[".$item['title']."]]></Title>
							<Desc><![CDATA[".$item['desc']."]]></Desc>
							<ImageUrl><![CDATA[".$item['image_url']."]]></ImageUrl>
							<Url><![CDATA[".$item['url']."]]></Url>
							<ActionName><![CDATA[".$item['action_name']."]]></ActionName>
							<AuthType><![CDATA[".$item['auth_type']."]]></AuthType>
						 </Item>";
		}
		
		$biz_content ='<XML>
							<ToUserId><![CDATA[208856789999]]></ToUserId>
							<AppId><![CDATA['.$this->appId.']]></AppId>
							<AgreementId><![CDATA['.$agreement_id.']]></AgreementId>
							<CreateTime>'.time().'</CreateTime>
							<MsgType><![CDATA['.$has_img ? 'image-text]': ']'.']></MsgType>
							<ArticleCount>'.$ArticleCount.'</ArticleCount>
							<Articles>
								'.$articles.'
							</Articles>
							<Push><![CDATA[false]]></Push>
						</XML>';
		$this->apiParas['biz_content'] = $biz_content;
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		if($result->alipay_mobile_public_message_push_response->code == 200){
			$this->respone['is_error'] = false;
			$this->respone['result'] = $result->alipay_mobile_public_message_push_response->msg;
		}else{
			$this->respone['is_error'] = true;
			$this->respone['result'] = $result->alipay_mobile_public_message_push_response->msg;
		}
		return $this->respone;
	}
	
	function account_bind(){
		$this->apiMethod = 'alipay.asset.account.bind';
		$this->apiParas["provider_id"] = $providerId;
		$this->apiParas["provider_user_id"] = $providerUserId;
		$this->apiParas["provider_user_name"] = $providerUserName;
		$this->apiParas["bind_scene"] = $bindScene;
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		$this->respone['is_error'] = false;$this->respone['result'] = $result;
		return $this->respone;
	}
	
	function account_get(){
		$this->apiMethod = 'alipay.asset.account.get';
		$this->apiParas["provider_id"] = $providerId;
		$this->apiParas["provider_user_id"] = $providerUserId;
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		$this->respone['is_error'] = false;$this->respone['result'] = $result;
		return $this->respone;
	}
	
	function account_unbind(){
		$this->apiMethod = 'alipay.asset.account.unbind';
		$this->apiParas["provider_id"] = $providerId;
		$this->apiParas["provider_user_id"] = $providerUserId;
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		$this->respone['is_error'] = false;$this->respone['result'] = $result;
		return $this->respone;
	}
	
	function bill_downloadurl_get(){
		$this->apiMethod = 'alipay.data.bill.downloadurl.get';
		$this->apiParas["bill_date"] = $billDate;
		$this->apiParas["bill_type"] = $billType;
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		$this->respone['is_error'] = false;$this->respone['result'] = $result;
		return $this->respone;
	}
	
	/**
	 * 创建账单
	 *
	 * @param String $bankBillNo 外部订单号
	 * @param String $billDate 账单的账期，例如201203表示2012年3月的账单。
	 * @param String $billKey 账单单据号，例如水费单号，手机号，电费号，信用卡卡号。没有唯一性要求
	 * @param String $chargeInst 支付宝给每个出账机构指定了一个对应的英文短名称来唯一表示该收费单位。
	 * @param String $extendField 扩展属性
	 * @param String $merchantOrderNo 输出机构的业务流水号，需要保证唯一性
	 * @param String $mobile 用户的手机号
	 * @param String $orderType 支付宝订单类型。公共事业缴纳JF,信用卡还款HK
	 * @param String $ownerName 拥有该账单的用户姓名
	 * @param String $payAmount 缴费金额。用户支付的总金额。单位为：RMB Yuan。取值范围为[0.01，100000000.00]，精确到小数点后两位。
	 * @param String $serviceAmount 账单的服务费。
	 * @param String $subOrderType 子业务类型是业务类型的下一级概念，例如：WATER表示JF下面的水费，ELECTRIC表示JF下面的电费，GAS表示JF下面的燃气费。
	 * @param String $trafficLocation 交通违章地点，sub_order_type=TRAFFIC时填写。
	 * @param String $trafficRegulations 违章行为，sub_order_type=TRAFFIC时填写。
	 * @return $this->respone
	 */
	function ebpp_bill_add($bankBillNo,$billDate,$billKey,$chargeInst,$extendField,$merchantOrderNo,$mobile,$orderType,$ownerName,$payAmount,$serviceAmount,$subOrderType,$trafficLocation,$trafficRegulations){
		$this->apiMethod = 'alipay.ebpp.bill.add';
		$this->apiParas["bank_bill_no"] = $bankBillNo;
		$this->apiParas["bill_date"] = $billDate;
		$this->apiParas["bill_key"] = $billKey;
		$this->apiParas["charge_inst"] = $chargeInst;
		$this->apiParas["extend_field"] = $extendField;
		$this->apiParas["merchant_order_no"] = $merchantOrderNo;
		$this->apiParas["mobile"] = $mobile;
		$this->apiParas["order_type"] = $orderType;
		$this->apiParas["owner_name"] = $ownerName;
		$this->apiParas["pay_amount"] = $payAmount;
		$this->apiParas["service_amount"] = $serviceAmount;
		$this->apiParas["sub_order_type"] = $subOrderType;
		$this->apiParas["traffic_location"] = $trafficLocation;
		$this->apiParas["traffic_regulations"] = $trafficRegulations;
		
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		if(isset($result->alipay_ebpp_bill_add_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_ebpp_bill_add_response;
			return $this->respone;
		}
	}
	
	/**
	 * 查询批量账单付款链接
	 *
	 * @param String $callbackUrl 回调系统url
	 * @param String $orderType 订单类型
	 * @param String $payBillList alipayOrderNo-merchantOrderNo即业务流水号和业务订单号
	 * @return $this->respone
	 */
	function ebpp_bill_batch_payurl_get($callbackUrl,$orderType,$payBillList){
		$this->apiMethod = 'alipay.ebpp.bill.batch.payurl.get';
		$this->apiParas["callback_url"] = $callbackUrl;
		$this->apiParas["order_type"] = $orderType;
		$this->apiParas["pay_bill_list"] = $payBillList;
		
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		if(isset($result->alipay_ebpp_bill_batch_payurl_get_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_ebpp_bill_batch_payurl_get_response->pay_url;
			return $this->respone;
		}
		
	}
	
	
	/**
	 * 查询账单
	 *
	 * @param String $merchantOrderNo 支付宝订单类型。公共事业缴纳JF,信用卡还款HK
	 * @param String $orderType 输出机构的业务流水号，需要保证唯一性。
	 * @return $this->respone
	 */
	function ebpp_bill_get($merchantOrderNo,$orderType){
		$this->apiMethod = 'alipay.ebpp.bill.get';
		$this->apiParas["merchant_order_no"] = $merchantOrderNo;
		$this->apiParas["order_type"] = $orderType;
		
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		if(isset($result->alipay_ebpp_bill_get_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_ebpp_bill_get_response;
			return $this->respone;
		}
	}
	/**
	 * 商家对账单代付款
	 *
	 * @param string $orderType 支付宝订单类型。公共事业缴纳JF,信用卡还款HK
	 * @param string $merchantOrderNo 输出机构的业务流水号，需要保证唯一性。
	 * @param string $alipayOrderNo 支付宝的业务订单号，具有唯一性。
	 * @param string $extend 扩展字段
	 * @param string $dispatchClusterTarget openapi的spanner上增加规则转发到pcimapi集群上
	 * @return unknown
	 */
	function ebpp_bill_pay($orderType,$merchantOrderNo,$alipayOrderNo,$extend,$dispatchClusterTarget){
		$this->apiMethod = 'alipay.ebpp.bill.pay';
		$this->apiParas["alipay_order_no"] = $alipayOrderNo;
		$this->apiParas["dispatch_cluster_target"] = $dispatchClusterTarget;
		$this->apiParas["extend"] = $extend;
		$this->apiParas["merchant_order_no"] = $merchantOrderNo;
		$this->apiParas["order_type"] = $orderType;
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		if(isset($result->alipay_ebpp_bill_pay_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_ebpp_bill_pay_response;
			return $this->respone;
		}
	}
	
	/**
	 * 查询账单付款链接
	 *
	 * @param string $alipayOrderNo 支付宝的业务订单号，具有唯一性。
	 * @param string $callbackUrl 回调系统url
	 * @param string $merchantOrderNo 输出机构的业务流水号，需要保证唯一性。
	 * @param string $orderType 支付宝订单类型。公共事业缴纳JF,信用卡还款HK。
	 * @return $this->respone
	 */
	function ebpp_bill_payurl_get($alipayOrderNo,$callbackUrl,$merchantOrderNo,$orderType){
		$this->apiMethod = 'alipay.ebpp.bill.payurl.get';
		$this->apiParas["alipay_order_no"] = $alipayOrderNo;
		$this->apiParas["callback_url"] = $callbackUrl;
		$this->apiParas["merchant_order_no"] = $merchantOrderNo;
		$this->apiParas["order_type"] = $orderType;
		
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_ebpp_bill_payurl_get_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_ebpp_bill_payurl_get_response->pay_url;
			return $this->respone;
		}
	}
	
	
	
	function ebpp_bill_search(){
		$this->apiMethod = 'alipay.ebpp.bill.search';
		$this->apiParas["bill_key"] = $billKey;
		$this->apiParas["charge_inst"] = $chargeInst;
		$this->apiParas["chargeoff_inst"] = $chargeoffInst;
		$this->apiParas["company_id"] = $companyId;
		$this->apiParas["extend"] = $extend;
		$this->apiParas["order_type"] = $orderType;
		$this->apiParas["sub_order_type"] = $subOrderType;
		
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		$this->respone['is_error'] = false;$this->respone['result'] = $result;
		return $this->respone;
	}
	function ebpp_merchant_configGet(){
		$this->apiMethod = 'alipay.ebpp.merchant.config.get';
		
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		$this->respone['is_error'] = false;$this->respone['result'] = $result;
		return $this->respone;
	}
	function ecard_edu_public_bind($agentCode,$agreementId,$alipayUserId,$cardName,$cardNo,$publicId){
		$this->apiMethod = 'alipay.ecard.edu.public.bind';
		$this->apiParas["public_id"] = $publicId;
		$this->apiParas["card_no"] = $cardNo;
		$this->apiParas["card_name"] = $cardName;
		$this->apiParas["alipay_user_id"] = $alipayUserId;
		$this->apiParas["agreement_id"] = $agreementId;
		$this->apiParas["agent_code"] = $agentCode;
		
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
	}
	/**
	 * 支付宝会员卡开卡接口
	 *
	 * @param string $bizSerialNo 商户端开卡业务流水号
	 * @param string $cardMerchantInfo 商户会员卡号。 比如淘宝会员卡号、商户实体会员卡号、商户自有CRM虚拟卡号等
	 * @param string $cardMerchantInfo 请求来源。 PLATFORM：发卡平台商 PARTNER：直联商户
	 * @param string $extInfo 持卡用户信息，json格式。  目前仅支持如下key： userUniId：用户唯一标识 userUniIdType：支持以下3种取值。 LOGON_ID：用户登录ID，邮箱或者手机号格式； UID：用户支付宝用户号，以2088开头的16位纯数字组成；BINDING_MOBILE：用户支付宝账号绑定的手机号。
	 * @param string $externalCardNo 发卡商户信息，json格式。 目前仅支持如下key： merchantUniId：商户唯一标识 merchantUniIdType：支持以下3种取值。 LOGON_ID：商户登录ID，邮箱或者手机号格式； UID：商户的支付宝用户号，以2088开头的16位纯数字组成； BINDING_MOBILE：商户支付宝账号绑定的手机号。 注意： 本参数主要用于发卡平台接入场景，request_from为PLATFORM时，不能为空。
	 * @param string $requestFrom 开卡扩展参数，json格式。 用于商户的特定业务信息的传递，只有商户与支付宝约定了传递此参数且约定了参数含义，此参数才有效。
	 * @param string $return_url API执行完，页面跳转到的地址
	 * @return $this->respone
	 */
	function member_card_open($bizSerialNo,$cardMerchantInfo,$cardMerchantInfo,$extInfo,$externalCardNo,$requestFrom,$return_url=''){
		$this->apiMethod = 'alipay.member.card.open';
		!empty($return_url) && $this->apiParas['return_url'] = $return_url;
		$this->apiParas["biz_serial_no"] = $bizSerialNo;
		$this->apiParas["card_merchant_info"] = $cardMerchantInfo;
		$this->apiParas["card_user_info"] = $cardUserInfo;
		$this->apiParas["ext_info"] = $extInfo;
		$this->apiParas["external_card_no"] = $externalCardNo;
		$this->apiParas["request_from"] = $requestFrom;
		
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_member_card_open_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_member_card_open_response;
			return $this->respone;
		}
	}
	/**
	 * 支付宝会员卡查询接口
	 *
	 * @param unknown_type $bizCardNo 支付宝会员卡卡号。注意： biz_card_no和card_user_info不能同时为空。
	 * @param unknown_type $cardMerchantInfo 发卡商户信息，json格式。 目前仅支持如下key： merchantUniId：商户唯一标识 merchantUniIdType：支持以下3种取值。 LOGON_ID：商户登录ID，邮箱或者手机号格式； UID：商户的支付宝用户号，以2088开头的16位纯数字组成； BINDING_MOBILE：商户支付宝账号绑定的手机号。 注意： 本参数主要用于发卡平台接入场景，request_from为PLATFORM时，不能为空。
	 * @param unknown_type $cardUserInfo 持卡用户信息，json格式。 目前仅支持如下key： userUniId：用户唯一标识 userUniIdType：支持以下3种取值。 LOGON_ID：用户登录ID，邮箱或者手机号格式； UID：用户支付宝用户号，以2088开头的16位纯数字组成；BINDING_MOBILE：用户支付宝账号绑定的手机号。 注意： biz_card_no和card_user_info不能同时为空。
	 * @param unknown_type $extInfo  扩展参数，json格式。 用于商户的特定业务信息的传递，只有商户与支付宝约定了传递此参数且约定了参数含义，此参数才有效。
	 * @param unknown_type $requestFrom 请求来源。 PLATFORM：发卡平台商 PARTNER：直联商户
	 * @param string $return_url API执行完，页面跳转到的地址
	 * @return $this->respone
	 */
	function member_card_query($bizCardNo,$cardMerchantInfo,$cardUserInfo,$extInfo,$requestFrom,$return_url=''){
		$this->apiMethod = 'alipay.member.card.query';
		!empty($return_url) && $this->apiParas['return_url'] = $return_url;
		
		$this->apiParas["biz_card_no"] = $bizCardNo;
		$this->apiParas["card_merchant_info"] = $cardMerchantInfo;
		$this->apiParas["card_user_info"] = $cardUserInfo;
		$this->apiParas["ext_info"] = $extInfo;
		$this->apiParas["request_from"] = $requestFrom;
		
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_member_card_query_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_member_card_query_response;
			return $this->respone;
		}
	}
	/**
	 * 查询单笔有密支付地址
	 *
	 * @param string $alipayOrderNo 支付宝订单号，冻结流水号.这个是创建冻结订单支付宝返回的
	 * @param string $amount 本次转账的外部单据号（只能由字母和数字组成,maxlength=32）
	 * @param string $memo 收款方的支付宝ID
	 * @param string $receiveUserId 支付金额,区间必须在[0.01,30]，只能保留小数点后两位
	 * @param string $transferOutOrderNo 支付备注
	 * @param string $return_url API执行完，页面跳转到的地址
	 * @return $this->repspone
	 */
	function micropay_order_confirmpayurl_get($alipayOrderNo,$amount,$memo,$receiveUserId,$transferOutOrderNo,$return_url=''){
		$this->apiMethod = 'alipay.micropay.order.confirmpayurl.get';
		!empty($return_url) && $this->apiParas['return_url'] = $return_url;
		$this->apiParas["alipay_order_no"] = $alipayOrderNo;
		$this->apiParas["amount"] = $amount;
		$this->apiParas["memo"] = $memo;
		$this->apiParas["receive_user_id"] = $receiveUserId;
		$this->apiParas["transfer_out_order_no"] = $transferOutOrderNo;
		
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		if(isset($result->alipay_micropay_order_confirmpayurl_get_response->single_pay_detail)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_micropay_order_confirmpayurl_get_response->single_pay_detail;
			return $this->respone;
		}
	}
	
	/** 
	 * 单笔直接支付
	 * @param string $alipayOrderNo 支付宝订单号，冻结流水号.这个是创建冻结订单支付宝返回的
	 * @param string $amount 支付金额,区间必须在[0.01,30]，只能保留小数点后两位
	 * @param string $memo 支付备注
	 * @param string $receiveUserId 收款方的支付宝ID
	 * @param string $transferOutOrderNo 本次转账的外部单据号（只能由字母和数字组成,maxlength=32
	 * @param string $return_url API执行完，页面跳转到的地址
	 **/
	function micropay_order_direct_pay($alipayOrderNo,$amount,$memo,$receiveUserId,$transferOutOrderNo,$return_url=''){
		$this->apiMethod = 'alipay.micropay.order.direct.pay';
		!empty($return_url) && $this->apiParas['return_url'] = $return_url;
		$this->apiParas["alipay_order_no"] = $alipayOrderNo;
		$this->apiParas["amount"] = $amount;
		$this->apiParas["memo"] = $memo;
		$this->apiParas["receive_user_id"] = $receiveUserId;
		$this->apiParas["transfer_out_order_no"] = $transferOutOrderNo;
		
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		if(isset($result->alipay_micropay_order_direct_pay_response->single_pay_detail)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_micropay_order_direct_pay_response->single_pay_detail;
			return $this->respone;
		}
	}
	/**
	 * 查询冻结订单详情
	 *
	 * @param string $alipay_order_no 支付宝订单号，冻结流水号(创建冻结订单返回)
	 * @param string $return_url API执行完，页面跳转到的地址
	 */
	function micropay_order_get($alipay_order_no,$return_url=''){
		$this->apiMethod = 'alipay.micropay.order.get';	
		!empty($return_url) && $this->apiParas['return_url'] = $return_url;
		$this->apiParas["alipay_order_no"] = $alipay_order_no;
		
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_micropay_order_get_response->micro_pay_order_detail)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_micropay_order_get_response->micro_pay_order_detail;
			return $this->respone;
		}
	}
	
	/** 
	 * 查询冻结金支付地址
	 * @param string $alipayOrderNo 冻结订单号,创建冻结订单时支付宝返回的
	 * @param string $return_url API执行完，页面跳转到的地址
	 **/
	function micropay_order_freezepayurl_get($alipayOrderNo,$return_url=''){
		$this->apiMethod = 'alipay.micropay.order.freezepayurl.get';
		$this->apiParas["alipay_order_no"] = $alipayOrderNo;
		!empty($return_url) && $this->apiParas['return_url'] = $return_url;
		
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		if(isset($result->alipay_micropay_order_freezepayurl_get_response->pay_freeze_url)){
			$this->respone['is_error'] = false;
			$this->respone['result'] = $result->alipay_micropay_order_freezepayurl_get_response->pay_freeze_url;
		}
	}
	/** 
	 * 创建冻结订单 
	 * @param string $amount 需要冻结金额，[0.01,2000]，必须是正数，最多只能保留小数点两位,单位是元
	 * @param string $expireTime 冻结资金的到期时间，超过此日期，冻结金会自动解冻,时间要求是:[当前时间+24h,订购时间-8h] .
	 * @param string $memo 冻结备注,maxLength=40
	 * @param string $merchantOrderNo 商户订单号,只能由字母和数字组成，最大长度32.此外部订单号与支付宝的冻结订单号对应,注意 应用id和订购者id和外部订单号必须保证唯一性。
	 * @param string $payConfirm 在解冻转账的时候的支付方式: NO_CONFIRM：不需要付款确认，调用接口直接扣款 PAY_PASSWORD: 在转账需要付款方用支付密码确认，才可以转账成功
	 * @param string $return_url API执行完，页面跳转到的地址
	 **/
	function micropay_order_freeze($amount,$expireTime,$memo,$merchantOrderNo,$payConfirm,$return_url=''){
		$this->apiMethod = 'alipay.micropay.order.freeze';
		$this->apiParas["amount"] = $amount;
		$this->apiParas["expire_time"] = $expireTime;
		$this->apiParas["memo"] = $memo;
		$this->apiParas["merchant_order_no"] = $merchantOrderNo;
		$this->apiParas["pay_confirm"] = $payConfirm;
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		if(isset($result->alipay_micropay_order_freeze_response->micro_pay_order_detail)){
			$this->respone['is_error'] = false;
			$this->respone['result'] = $result->alipay_micropay_order_freeze_response->micro_pay_order_detail;
		}
	}
	
	/**
	 * 解冻冻结订单
	 * @param  $alipayOrderNo 冻结资金流水号,在创建资金订单时支付宝返回的流水号
	 * @param  $memo 冻结备注
	 * @param string $return_url API执行完，页面跳转到的地址
	 */
	function micropay_order_unfreeze($alipayOrderNo,$memo,$return_url=''){
		$this->apiMethod = 'alipay.micropay.order.unfreeze';
		$this->apiParas["alipay_order_no"] = $alipayOrderNo;
		$this->apiParas["memo"] = $memo;
		!empty($return_url) && $this->apiParas['return_url'] = $return_url;
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		if(isset($result->alipay_micropay_order_unfreeze_response->unfreeze_order_detail)){
			$this->respone['is_error'] = false;
			$this->respone['result'] = $result->alipay_micropay_order_unfreeze_response->unfreeze_order_detail;
		}
	}
	
	/**
	 * 公众平台绑定账号
	 *
	 * @param string $displayName
	 * @param string $realName
	 * @param string $bindAccountNo
	 * @param string $fromUserId
	 * @param string $return_url API执行完，页面跳转到的地址
	 * @return $this->respone
	 */
	function account_add($displayName,$realName,$bindAccountNo,$fromUserId,$return_url=''){
		$this->apiMethod = 'alipay.mobile.public.account.add';
		!empty($return_url) && $this->apiParas['return_url'] = $return_url;
		$bizContent = '{"displayName":"'.$displayName.'","appId":"'.$this->appId.'","realName":"'.$realName.'", "bindAccountNo":"'.$bindAccountNo.'", "fromUserId":"'.$fromUserId.'"}';
		
		$this->apiParas["biz_content"] = $bizContent;
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_mobile_public_account_add_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_mobile_public_account_add_response;
			return $this->respone;
		}
	}
	/**
	 * 公众平台删除绑定的商户会员号
	 *
	 * @param string $agreementId
	 * @param string $displayName
	 * @param string $realName
	 * @param string $bindAccountNo
	 * @param string $fromUserId
	 * @param string $return_url API执行完，页面跳转到的地址
	 * @return $this->respone
	 */
	function account_delete($agreementId,$displayName,$realName,$bindAccountNo,$fromUserId,$return_url=''){
		$this->apiMethod = 'alipay.mobile.public.account.delete';
		!empty($return_url) && $this->apiParas['return_url'] = $return_url;
		$bizContent = '{"agreementId":"'.$agreementId.'","displayName":"'.$displayName.'","appId":"'.$this->appId.'","realName":"'.$realName.'", "bindAccountNo":"'.$bindAccountNo.'", "fromUserId":"'.$fromUserId.'"}';
		
		$this->apiParas["biz_content"] = $bizContent;
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_mobile_public_account_delete_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_mobile_public_account_delete_response->msg;
			return $this->respone;
		}
	}
	/**
	 * 公众平台获取绑定账户列表
	 *
	 * @param string $alipay_uid
	 * @param string $return_url API执行完，页面跳转到的地址
	 * @return $this->respone
	 */
	function account_query($alipay_uid,$return_url=''){
		$this->apiMethod = 'alipay.mobile.public.account.query';
		!empty($return_url) && $this->apiParas['return_url'] = $return_url;
		$bizContent = '{"userId":"'.$alipay_uid.'"}';
		
		$this->apiParas["biz_content"] = $bizContent;
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_mobile_public_account_query_response->public_bind_accounts)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_mobile_public_account_query_response->public_bind_accounts;
			return $this->respone;
		}
	}
	
	/**
	 * 公众平台重设绑定的商户会员号
	 *
	 * @param string $agreementId
	 * @param string $displayName
	 * @param string $realName
	 * @param string $bindAccountNo
	 * @param string $fromUserId
	 * @param string $return_url API执行完，页面跳转到的地址
	 * @return $this->respone
	 */
	function account_reset($agreementId,$displayName,$realName,$bindAccountNo,$fromUserId,$return_url=''){
		$this->apiMethod = 'alipay.mobile.public.account.reset';
		!empty($return_url) && $this->apiParas['return_url'] = $return_url;
		$bizContent = '{"agreementId":"'.$agreementId.'","displayName":"'.$displayName.'","appId":"'.$this->appId.'","realName":"'.$realName.'", "bindAccountNo":"'.$bindAccountNo.'", "fromUserId":"'.$fromUserId.'"}';
		
		$this->apiParas["biz_content"] = $bizContent;
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_mobile_public_account_reset_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_mobile_public_account_reset_response->msg;
			return $this->respone;
		}
	}
	
	
	/**
	 * 获取关注者列表
	 *
	 * @param string $nextUserId 当nextUserId为空时,代表查询第一组,如果有值时以当前值为准查询下一组
	 * @param string $return_url API执行完，页面跳转到的地址
	 */
	function follow_list($nextUserId,$return_url=''){
		$this->apiMethod = 'alipay.mobile.public.follow.list';
		!empty($return_url) && $this->apiParas['return_url'] = $return_url;
		$bizContent = '{"nextUserId":"'.$nextUserId.'"}';
		
		$this->apiParas["biz_content"] = $bizContent;
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_mobile_public_follow_list_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_mobile_public_follow_list_response;
			return $this->respone;
		}
	}
	
	
	/**
	 * 公众号用户GIS查询接口
	 *
	 * @param string $alipay_uid 业务信息：usrid
	 * @param string $return_url API执行完，页面跳转到的地址
	 * @return unknown
	 */
	function gis_get($alipay_uid,$return_url=''){
		$this->apiMethod = 'alipay.mobile.public.gis.get';
		!empty($return_url) && $this->apiParas['return_url'] = $return_url;
		$this->apiParas["biz_content"] = '{"userId":"'.$alipay_uid.'"}';
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_mobile_public_gis_get_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_mobile_public_gis_get_response;
			return $this->respone;
		}
	}
	
	/**
	 * 获取公共号菜单
	 *
	 * @return array $this->respone
	 */
	function menu_get(){
		$this->apiMethod = 'alipay.mobile.public.menu.get';
		
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		$this->respone = array('is_error'=>false,'result'=>json_decode($result->alipay_mobile_public_menu_get_response->menu_content));
		return $this->respone;
	}
	/**
	 * 更新菜单
	 *
	 * @param array $button 按钮数组 例子 如下 最多支持4个一级按钮 每个一级按钮最多支持4个二级按钮 一级菜单最多4个汉字； 二级菜单最多12个汉字
					 //第一个一级按钮名称
					'专享特惠'=>array(
									//第一个二级按钮名称
									array(
											'name'=>'1MX2下单立减100元1',
											'msgShowType'=>'',
											'actionParam'=>'MENU_2014030400003720_1',//当菜单actionType=out时，actionParam是标识按钮作用的键值，用于“开发者接收消息事件”接口。当菜单actionType=link时，actionParam的值为直接跳转web/wap的链接地址。actionParam用于超链接时不能超过255个字符，不能使用特殊符号，如冒号。
											'actionType'=>'in',//in,link,alipay,tel 菜单动作类型。out：点击钱包公众账号首页中的菜单请求支付宝公众平台，支付宝公众平台会从公众账号的网关中获取该菜单对应的响应； link：点击菜单直接跳转web/wap页面，不需要请求支付宝公众平台
											'authType'=>''//免登标识当需要免登时，该参数必须取值为loginAuth。 只有actionType为link时才能配置该参数。 如果actionType为out，需在开发者调用“开发者响应回复消息”接口时，设置参数authType来标识是否免登。
										),
									//第二个二级按钮名称
									array(
											'name'=>'1MX2下单立减100元2',
											'msgShowType'=>'',
											'actionParam'=>'MENU_2014030400003720_1',
											'actionType'=>'in',//in,link,alipay,tel
											'authType'=>''
										),
									//第三个二级按钮名称
									array(
											'name'=>'1MX2下单立减100元3',
											'msgShowType'=>'',
											'actionParam'=>'MENU_2014030400003720_1',
											'actionType'=>'in',//in,link,alipay,tel
											'authType'=>''
										),
									//第四个二级按钮名称
									array(
											'name'=>'1MX2下单立减100元4',
											'msgShowType'=>'',
											'actionParam'=>'MENU_2014030400003720_1',
											'actionType'=>'in',//in,link,alipay,tel
											'authType'=>''
										),
								),
					//第二个一级按钮名称
					'专享特惠2'=>array(
									//第一个二级按钮名称
									array(
											'name'=>'2MX2下单立减100元1',
											'msgShowType'=>'',
											'actionParam'=>'MENU_2014030400003720_1',
											'actionType'=>'in',//in,link,alipay,tel
											'authType'=>''
										),
									//第二个二级按钮名称
										array(
											'name'=>'2MX2下单立减100元2',
											'msgShowType'=>'',
											'actionParam'=>'MENU_2014030400003720_1',
											'actionType'=>'in',//in,link,alipay,tel
											'authType'=>''
										),
									//第三个二级按钮名称
										array(
											'name'=>'2MX2下单立减100元3',
											'msgShowType'=>'',
											'actionParam'=>'MENU_2014030400003720_1',
											'actionType'=>'in',//in,link,alipay,tel
											'authType'=>''
										),
									//第四个二级按钮名称
									array(
											'name'=>'2MX2下单立减100元4',
											'msgShowType'=>'',
											'actionParam'=>'MENU_2014030400003720_1',
											'actionType'=>'in',//in,link,alipay,tel
											'authType'=>''
										),
								)
					);
	 * @return array $this->respone
	 */
	function menu_update ($buttons){
		$this->apiMethod = 'alipay.mobile.public.menu.update';
		//参数过滤
		if(!is_array($buttons) || empty($buttons) || count($buttons) >4 ){
			$this->respone['is_error']=true; $this->respone['result']= $this->error_tips_lang == 'zh' ? '参数错误' : 'Parameter error';
			return $this->respone;
		}
		
		$bizContent = new stdClass();
		foreach ($buttons as $name=>$sub_buttons){
			foreach ($sub_buttons as $k=>$sub_button){
				$button->name = $name;
				$button->subButton[$k]->msgShowType = $sub_button['msgShowType'];
				$button->subButton[$k]->name = $sub_button['name'];
				$button->subButton[$k]->actionParam = $sub_button['actionParam'];
				$button->subButton[$k]->actionType = $sub_button['actionType'];
				$bizContent->button[] = $button;
			}
		}
		
		$this->apiParas["biz_content"] = json_encode($bizContent);
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		$this->respone['is_error'] = false;$this->respone['result']=$result->alipay_mobile_public_menu_add_response->msg;
		return $this->respone;
	}
	
	/**
	 * 添加菜单 
	 * 参数跟上面一样
	 *
	 * @param array $buttons
	 * @return array $this->respone
	 */
	function menu_add($buttons){
		$this->apiMethod = 'alipay.mobile.public.menu.add';
		//参数过滤
		if(!is_array($buttons) || empty($buttons) || count($buttons) >4 ){
			$this->respone['is_error']=true; $this->respone['result']= $this->error_tips_lang == 'zh' ? '参数错误' : 'Parameter error';
			return $this->respone;
		}
		
		$bizContent = new stdClass();
		foreach ($buttons as $name=>$sub_buttons){
			foreach ($sub_buttons as $k=>$sub_button){
				$button->name = $name;
				$button->subButton[$k]->msgShowType = $sub_button['msgShowType'];
				$button->subButton[$k]->name = $sub_button['name'];
				$button->subButton[$k]->actionParam = $sub_button['actionParam'];
				$button->subButton[$k]->actionType = $sub_button['actionType'];
				$bizContent->button[] = $button;
			}
		}
		
		$this->apiParas["biz_content"] = json_encode($bizContent);
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
	}
	/**
	 * 	 生成二维码
	 *
	 * @param int $expireSecond 临时二维码失效时间，以秒为单位， 最大不超过1800（默认）；永久二维码传该值不生效。
	 * @param unknown_type $is_temp 是否是临时二维码
	 * @param String $sceneId 场景值ID。二维码详细信息codeInfo，即业务参数放在这里，目前只支持这一种场景scene，scene中可以传sceneId
	 * @param string $showLogo 二维码中间是否显示开发者logo Y：显示    N：不显示（默认）
	 * @return unknown
	 */
	function qrcode_create($expireSecond,$is_temp=true,$sceneId='',$showLogo='Y'){
		$this->apiMethod = 'alipay.mobile.public.qrcode.create';
		$bizContent = $is_temp ? '{"codeType":"TEMP","expireSecond":'.$expireSecond.',"codeInfo":{"scene":{"sceneId":""}'.$sceneId.'},"showLogo":"'.$showLogo.'"}' : '{"codeType":"PERM","codeInfo":{"scene":{"sceneId":""}'.$sceneId.'},"showLogo":"'.$showLogo.'"}';
		
		$this->apiParas["biz_content"] = $bizContent;
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		if(isset($result->alipay_mobile_public_qrcode_create_response->code_img)){
			$this->respone['result'] = $result->alipay_mobile_public_qrcode_create_response->code_img;
		}else{
			$this->respone['is_error'] = true;
			$this->respone['result'] = $this->alipay_mobile_public_qrcode_create_response->msg;
		}
		
		return $this->respone;
	}
	
	/** 
	 * 查询握手用户信息接口
	 * $dynamicId 动态ID
	 * $dynamicIdType 动态ID类型： wave_code：声波 qr_code：二维码 bar_code：条码
	 **/
	function mobile_shake_user_query($dynamicId,$dynamicIdType){
		$this->apiMethod = 'alipay.mobile.shake.user.query';
		$this->apiParas["dynamic_id"] = $dynamicId;
		$this->apiParas["dynamic_id_type"] = $dynamicIdType;
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		if(isset($result->alipay_mobile_shake_user_query_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_mobile_shake_user_query_response;
			return $this->respone;
		}
	}
	
	/** 
	 * $fileContent alipass文件Base64编码后的内容。
	 * $recognitionInfo 识别信息 当 recognition_type=1时， recognition_info={“partner_id”:”2088102114633762”,“out_trade_no”:”1234567”} 当recognition_type=2时， recognition_info={“user_id”:”2088102114633761“ }
	 * $recognitionType 发放对象识别类型 1-	订单信息 2-	支付宝userId
	 * $verifyType 该pass的核销方式,如果为空，则默认为["wave","qrcode"]
	 **/
	function pass_code_add($fileContent,$recognitionInfo,$recognitionType,$verifyType){
		$this->apiMethod = 'alipay.pass.code.add';
		$this->apiParas["file_content"] = $fileContent;
		$this->apiParas["recognition_info"] = $recognitionInfo;
		$this->apiParas["recognition_type"] = $recognitionType;
		$this->apiParas["verify_type"] = $verifyType;
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
	}
	
	/** 
	 * $extInfo 商户核销操作扩展信息
	 * $operatorId 操作员id 如果operator_type为1，则此id代表核销人员id 如果operator_type为2，则此id代表核销机具id
	 * $operatorType 操作员类型 1 核销人员 2 核销机具
	 * $verifyCode Alipass对应的核销码串
	 **/
	function pass_code_verify($extInfo,$operatorId,$operatorType,$verifyCode){
		$this->apiMethod = 'alipay.pass.code.verify';
		$this->apiParas["ext_info"] = $extInfo;
		$this->apiParas["operator_id"] = $operatorId;
		$this->apiParas["operator_type"] = $operatorType;
		$this->apiParas["verify_code"] = $verifyCode;
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
	}
	
	
	/**
	 * 模版方式添加卡券
	 * @param $tpl_id 支付宝pass唯一标识 支付宝pass模版ID
	 * @param $title 会员卡名字 模版动态参数信息【支付宝pass模版参数键值对JSON字符串】
	 * @param $startDate 会员卡开始时间 Alipass添加对象识别类型【1--订单信息;3--支付宝用户绑定手机号；4--支付宝OpenId;】
	 * @param string $return_url API执行完，页面跳转到的地址 支付宝用户识别信息： 当 recognition_type=1时， recognition_info={“partner_id”:”2088102114633762”,“out_trade_no”:”1234567”}； 当recognition_type=3时，recognition_info={“mobile”:”136XXXXXXXX“} 当recognition_type=4时， recognition_info={“open_id”:”afbd8d9bb12fc02c5094d8ea89d1fae8“}
	 * @return $this->respone
	 */
	function pass_tpl_content_add($tpl_id,$tpl_params,$recognition_type,$recognition_info,$return_url=''){
		$this->apiMethod = 'alipay.pass.tpl.content.add';
		!empty($return_url) && $this->apiParas['return_url'] = $return_url;
		$this->apiParas['tpl_id'] = $tpl_id;
		$this->apiParas['tpl_params'] = $tpl_params;
		$this->apiParas['recognition_type'] = $recognition_type;
		$this->apiParas['recognition_info'] = $recognition_info;
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_pass_tpl_add_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_pass_tpl_add_response->result;
			return $this->respone;
		}
	}
	
	/**
	 * 模版方式更新支付宝卡券
	 * @param $serial_number 支付宝pass唯一标识
	 * @param $title 会员卡名字
	 * @param $startDate 会员卡开始时间
	 * @param string $return_url API执行完，页面跳转到的地址
	 * @return $this->respone
	 */
	function pass_tpl_content_update($serial_number,$title,$startDate,$return_url=''){
		$this->apiMethod = 'alipay.pass.tpl.content.update';
		!empty($return_url) && $this->apiParas['return_url'] = $return_url;
		$this->apiParas['serial_number'] = $serial_number;
		$this->apiParas['tpl_params']='{"title":"'.$title.'","startDate":"'.date('Y-m-d H:i:s',$startDate).'"}';
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_pass_tpl_content_update_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_pass_tpl_content_update_response->result;
			return $this->respone;
		}
	}
	/**
	 * 支付宝pass模版更新
	 *
	 * @param string $tpl_content 支付宝pass模版内容【JSON格式】 
	 * @param string $return_url 
	 * @return unknown
	 */
	function pass_tpl_add($tpl_content,$return_url=''){
		$this->apiMethod = 'alipay.pass.tpl.add';
		!empty($return_url) && $this->apiParas['return_url'] = $return_url;
		$this->apiParas["tpl_id"] = $tpl_id;
		$this->apiParas["tpl_content"] = $tpl_content;
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_pass_template_sync_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_pass_template_sync_response->result;
			return $this->respone;
		}
	}
	
	/**
	 * 支付宝pass模版更新
	 *
	 * @param string $tpl_id
	 * @param string $tpl_content
	 * @param string $return_url
	 * @return unknown
	 */
	function pass_tpl_update($tpl_id,$tpl_content,$return_url=''){
		$this->apiMethod = 'alipay.pass.tpl.update';
		!empty($return_url) && $this->apiParas['return_url'] = $return_url;
		$this->apiParas["tpl_id"] = $tpl_id;
		$this->apiParas["tpl_content"] = $tpl_content;
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_pass_tpl_update_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_pass_tpl_update_response->result;
			return $this->respone;
		}
	}
	
	/** 
	 * $fileContent 支付宝pass文件二进制Base64加密字符串
	 * $recognitionInfo 支付宝用户识别信息：
			当 recognition_type=1时， recognition_info={“partner_id”:”2088102114633762”,“out_trade_no”:”1234567”}；
			当recognition_type=2时， recognition_info={“user_id”:”2088102114633761“}
			当recognition_type=3时，recognition_info={“mobile”:”136XXXXXXXX“}
	 *  $recognitionType Alipass添加对象识别类型【1--订单信息；2--支付宝userId;3--支付宝绑定手机号】
	 **/
	function pass_file_add($fileContent,$recognitionInfo,$recognitionType,$return_url=''){
		$this->apiMethod = 'alipay.pass.file.add';
		!empty($return_url) && $this->apiParas['return_url'] = $return_url;
		$this->apiParas["file_content"] = $fileContent;
		$this->apiParas["recognition_info"] = $recognitionInfo;
		$this->apiParas["recognition_type"] = $recognitionType;
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_pass_file_add_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_pass_file_add_response->biz_result;
			return $this->respone;
		}
	}
	
	/** 
	 * Alipass更新接
	 * @param string $extInfo 用来传递外部交易号等扩展参数信息，格式为json
	 * @param string $pass 需要修改的pass信息，可以更新全部pass信息，也可以斤更新某一节点。pass信息中的pass.json中的数据格式，如果不需要更新该属性值，设置为null即可。
	 * @param string $serialNumber Alipass唯一标识
	 * @param string $status Alipass状态，目前仅支持CLOSED及USED两种数据。status为USED时，verify_type即为核销时的核销方式。
	 * @param string $verifyCode 核销码串值
	 * @param string $verifyType 核销方式，目前支持：wave（声波方式）、qrcode（二维码方式）、barcode（条码方式）、input（文本方式，即手工输入方式）。pass和verify_type不能同时为空
	 * @param string $return_url API执行完，页面跳转到的地址
	 **/
	function pass_sync_update($extInfo,$pass,$serialNumber,$status,$verifyCode,$verifyType,$return_url=''){
		$this->apiMethod = 'alipay.pass.sync.update';
		$this->apiParas["ext_info"] = $extInfo;
		$this->apiParas["pass"] = $pass;
		$this->apiParas["serial_number"] = $serialNumber;
		$this->apiParas["status"] = $status;
		$this->apiParas["verify_code"] = $verifyCode;
		$this->apiParas["verify_type"] = $verifyType;
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_pass_sync_update_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_pass_sync_update_response->biz_result;
			return $this->respone;
		}
	}
	
	/**
	 * 将生成的Alipass数据上送到支付宝钱包
	 *
	 * @param string $user_id 支付宝用户ID，即买家用户ID
	 * @param string $out_trade_no 商户外部交易号，由商户生成并确保其唯一性
	 * @param string $file_content alipass文件Base64编码后的内容。
	 * @param string $partner_id 商户与支付宝签约时，分配的唯一ID。
	 * @param string $return_url API执行完，页面跳转到的地址
	 */
	function pass_sync_add($user_id,$out_trade_no,$file_content,$partner_id,$return_url=''){
		$this->apiMethod = 'alipay.pass.sync.add';
		!empty($return_url) && $this->apiParas['return_url'] = $return_url;
		$this->apiParas["user_id"] = $user_id;
		$this->apiParas["out_trade_no"] = $out_trade_no;
		$this->apiParas["file_content"] = $file_content;
		$this->apiParas["partner_id"] = $partner_id;
		
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_pass_sync_add_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_pass_sync_add_response->biz_result;
			return $this->respone;
		}
	}
	
	/**
	 * @param  $verifyCode Alipass对应的核销码串
	 */
	function pass_verify_query($verifyCode){
		$this->apiMethod = 'alipay.pass.verify.query';
		$this->apiParas["verify_code"] = $verifyCode;
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		$this->respone['is_error'] = false;$this->respone['result'] = $result;
		return $this->respone;
	}
	
	
	/**
	 * 集分宝余额查询
	 *
	 * @param string $return_url API执行完，页面跳转到的地址
	 * @return $this->respone
	 */
	function point_balance_get($return_url=''){
		$this->apiMethod = 'alipay.point.balance.get';
		!empty($return_url) && $this->apiParas['return_url'] = $return_url;
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_point_balance_get_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_point_balance_get_response->point_amount;
			return $this->respone;
		}
	}
	
	/**
	 * 查询已采购的集分宝余额
	 * @param string $return_url API执行完，页面跳转到的地址
	 * @return $this->respone
	 */
	function point_budget_get($return_url=''){
		$this->apiMethod = 'alipay.point.budget.get';
		!empty($return_url) && $this->apiParas['return_url'] = $return_url;
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_point_budget_get_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_point_budget_get_response;
			return $this->respone;
		}
	}
	
	/** 
	 * 发放集分宝
	 * @param string $memo 向用户展示集分宝发放备注
	 * @param string $merchantOrderNo isv提供的发放订单号，由数字和字母组成，最大长度为32位，需要保证每笔订单发放的唯一性，支付宝对该参数做唯一性校验。如果订单号已存在，支付宝将返回订单号已经存在的错误
	 * @param string $orderTime 发放集分宝时间
	 * @param string $pointCount 发放集分宝的数量
	 * @param string $userSymbol 用户标识符，用于指定集分宝发放的用户，和user_symbol_type一起使用，确定一个唯一的支付宝用户
	 * @param string $userSymbolType 用户标识符类型，现在支持ALIPAY_USER_ID:表示支付宝用户ID,ALIPAY_LOGON_ID:表示支付宝登陆号
	 * @param string $return_url API执行完，页面跳转到的地址
	 **/
	
	function point_order_add($memo,$merchantOrderNo,$orderTime,$pointCount,$userSymbol,$userSymbolType,$return_url=''){
		$this->apiMethod = 'alipay.point.order.add';
		!empty($return_url) && $this->apiParas['return_url'] = $return_url;
		
		$this->apiParas["memo"] = $memo;
		$this->apiParas["merchant_order_no"] = $merchantOrderNo;
		$this->apiParas["order_time"] = $orderTime;
		$this->apiParas["point_count"] = $pointCount;
		$this->apiParas["user_symbol"] = $userSymbol;
		$this->apiParas["user_symbol_type"] = $userSymbolType;
		$result = $this->execute();
		
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_point_order_add_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_point_order_add_response->alipay_order_no;
			return $this->respone;
		}
	}
	
	/** 
	 * 查询集分宝发放详情
	 * @param string $merchantOrderNo isv提供的发放号订单号，由数字和组成，最大长度为32为，需要保证每笔发放的唯一性，支付宝会对该参数做唯一性控制。如果使用同样的订单号，支付宝将返回订单号已经存在的错误
	 * @param string $userSymbol 用户标识符，用于指定集分宝发放的用户，和user_symbol_type一起使用，确定一个唯一的支付宝用户
	 * @param string $userSymbolType 用户标识符类型，现在支持ALIPAY_USER_ID:表示支付宝用户ID,ALIPAY_LOGON_ID:表示支付宝登陆号
	 * @param string $return_url API执行完，页面跳转到的地址
	 **/
	function point_order_get($merchantOrderNo,$userSymbol,$userSymbolType,$return_url=''){
		$this->apiMethod = 'alipay.point.order.get';
		!empty($return_url) && $this->apiParas['return_url'] = $return_url;
		
		$this->apiParas["merchant_order_no"] = $merchantOrderNo;
		$this->apiParas["user_symbol"] = $userSymbol;
		$this->apiParas["user_symbol_type"] = $userSymbolType;
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_point_order_get_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_point_order_get_response;
			return $this->respone;
		}
	}
	/** 
	 * 风险账户分析接口
	 * @param string $envClientBaseBand 客户端的基带版本
	 * $envClientBaseStation 客户端连接的基站信息
	 * @param string $envClientCoordinates 客户端的经纬度坐标
	 * @param string $envClientImei 操作的客户端的imei
	 * @param string $envClientImsi 操作的客户端的imsi
	 * @param string $envClientIosUdid IOS设备的UDID
	 * @param string $envClientIp 操作的客户端ip
	 * @param string $envClientMac 操作的客户端mac
	 * @param string $envClientScreen 操作的客户端分辨率，格式为：水平像素^垂直像素；如：800^600
	 * @param string $envClientUuid 客户端设备的统一识别码UUID
	 * @param string $jsTokenId JS SDK生成的 tokenID
	 * @param string $partnerId 签约的支付宝账号对应的支付宝唯一用户号
	 * @param string $sceneCode 场景编码
	 * @param string $userAccountNo 卖家账户ID
	 * @param string $userBindBankcard 用户绑定银行卡号
	 * @param string $userBindBankcardType 用户绑定银行卡的卡类型
	 * @param string $userBindMobile 用户绑定手机号
	 * @param string $userIdentityType 用户证件类型
	 * @param string $userRealName 用户真实姓名
	 * @param string $userRegDate 用户注册时间
	 * @param string $userRegEmail 用户注册Email
	 * @param string $userRegMobile 用户注册手机号
	 * @param string $userrIdentityNo 用户证件号码
	 **/
	function security_info_analysis(){
		$this->apiMethod = 'alipay.security.info.analysis';
		$this->apiParas["env_client_base_band"] = $envClientBaseBand;
		$this->apiParas["env_client_base_station"] = $envClientBaseStation;
		$this->apiParas["env_client_coordinates"] = $envClientCoordinates;
		$this->apiParas["env_client_imei"] = $envClientImei;
		$this->apiParas["env_client_imsi"] = $envClientImsi;
		$this->apiParas["env_client_ios_udid"] = $envClientIosUdid;
		$this->apiParas["env_client_ip"] = $envClientIp;
		$this->apiParas["env_client_mac"] = $envClientMac;
		$this->apiParas["env_client_screen"] = $envClientScreen;
		$this->apiParas["env_client_uuid"] = $envClientUuid;
		$this->apiParas["js_token_id"] = $jsTokenId;
		$this->apiParas["partner_id"] = $partnerId;
		$this->apiParas["scene_code"] = $sceneCode;
		$this->apiParas["user_account_no"] = $userAccountNo;
		$this->apiParas["user_bind_bankcard"] = $userBindBankcard;
		$this->apiParas["user_bind_bankcard_type"] = $userBindBankcardType;
		$this->apiParas["user_bind_mobile"] = $userBindMobile;
		$this->apiParas["user_identity_type"] = $userIdentityType;
		$this->apiParas["user_real_name"] = $userRealName;
		$this->apiParas["user_reg_date"] = $userRegDate;
		$this->apiParas["user_reg_email"] = $userRegEmail;
		$this->apiParas["user_reg_mobile"] = $userRegMobile;
		$this->apiParas["userr_identity_no"] = $userrIdentityNo;
		
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_security_info_analysis_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_security_info_analysis_response;
			return $this->respone;
		}
	}
	
	/** 
	 * 风险检测服务接口
	 * @param string $buyerAccountNo 买家账户编号
	 * @param string $buyerBindBankcard 买家绑定银行卡号
	 * @param string $buyerBindBankcardType 买家绑定银行卡的卡类型
	 * @param string $buyerBindMobile 买家绑定手机号
	 * @param string $buyerGrade 买家账户在商家的等级，范围：VIP（高级买家）, NORMAL(普通买家）。为空默认NORMAL
	 * @param string $buyerIdentityNo 买家证件号码
	 * @param string $buyerIdentityType 买家证件类型
	 * @param string $buyerRealName 买家真实姓名
	 * @param string $buyerRegDate 买家注册时间
	 * @param string $buyerRegEmail 买家注册时留的Email
	 * @param string $buyerRegMobile 买家注册手机号
	 * @param string $buyerSceneBankcard 买家业务处理时使用的银行卡号
	 * @param string $buyerSceneBankcardType 买家业务处理时使用的银行卡类型
	 * @param string $buyerSceneEmail 买家业务处理时使用的邮箱
	 * @param string $buyerSceneMobile 买家业务处理时使用的手机号
	 * @param string $currency 币种
	 * @param string $envClientBaseBand 客户端的基带版本
	 * @param string $envClientBaseStation 客户端连接的基站信息,格式为：CELLID^LAC
	 * @param string $envClientCoordinates 客户端的经纬度坐标,格式为：精度^维度
	 * @param string $envClientImei 操作的客户端的imei
	 * @param string $envClientImsi 操作的客户端IMSI识别码
	 * @param string $envClientIosUdid IOS设备的UDID
	 * @param string $envClientIp 操作的客户端ip
	 * @param string $envClientMac 操作的客户端mac
	 * @param string $envClientScreen 操作的客户端分辨率，格式为：水平像素^垂直像素；如：800^600
	 * @param string $envClientUuid 客户端设备的统一识别码UUID
	 * @param string $itemQuantity 订单产品数量，购买产品的数量（不可为小数）
	 * @param string $itemUnitPrice 订单产品单价，取值范围为[0.01,100000000.00]，精确到小数点后两位。 curren...
	 * @param string $jsTokenId  JS SDK生成的 tokenID
	 * @param string $orderAmount 订单总金额，取值范围为[0.01,100000000.00]，精确到小数点后两位。
	 * @param string $orderCategory 订单商品所在类目
	 * @param string $orderCredateTime 订单下单时间
	 * @param string $orderItemCity 订单商品所在城市
	 * @param string $orderItemName 订单产品名称
	 * @param string $orderNo 商户订单唯一标识号
	 * @param string $partnerId 签约的支付宝账号对应的支付宝唯一用户号
	 * @param string $receiverAddress 订单收货人地址
	 * @param string $receiverCity 订单收货人地址城市
	 * @param string $receiverDistrict 订单收货人地址所在区
	 * @param string $receiverEmail 订单收货人邮箱
	 * @param string $receiverMobile 订单收货人手机
	 * @param string $receiverName 订单收货人姓名
	 * @param string $receiverState 订单收货人地址省份
	 * @param string $receiverZip 订单收货人地址邮编
	 * @param string $sceneCode 场景编码
	 * @param string $sellerAccountNo 卖家账户编号
	 * @param string $sellerBindBankcard 卖家绑定银行卡号
	 * @param string $sellerBindBankcardType 卖家绑定的银行卡的卡类型
	 * @param string $sellerBindMobile 卖家绑定手机号
	 * @param string $sellerIdentityNo 卖家证件号码
	 * @param string $sellerIdentityType 卖家证件类型
	 * @param string $sellerRealName 卖家真实姓名
	 * @param string $sellerRegDate 卖家注册时间,格式为：yyyy-MM-dd。
	 * @param string $sellerRegEmail 卖家注册Email
	 * @param string $sellerRegMoile 卖家注册手机号
	 * @param string $transportType订单物流方式
	 **/
	function security_risk_detect(){
		$this->apiMethod = 'alipay.security.risk.detect';
		$this->apiParas["buyer_bind_bankcard"] = $buyerBindBankcard;
		$this->apiParas["buyer_bind_bankcard_type"] = $buyerBindBankcardType;
		$this->apiParas["buyer_bind_mobile"] = $buyerBindMobile;
		$this->apiParas["buyer_grade"] = $buyerGrade;
		$this->apiParas["buyer_identity_no"] = $buyerIdentityNo;
		$this->apiParas["buyer_identity_type"] = $buyerIdentityType;
		$this->apiParas["buyer_real_name"] = $buyerRealName;
		$this->apiParas["buyer_reg_date"] = $buyerRegDate;
		$this->apiParas["buyer_reg_email"] = $buyerRegEmail;
		$this->apiParas["buyer_reg_mobile"] = $buyerRegMobile;
		$this->apiParas["buyer_scene_bankcard"] = $buyerSceneBankcard;
		$this->apiParas["buyer_scene_bankcard_type"] = $buyerSceneBankcardType;
		$this->apiParas["buyer_scene_email"] = $buyerSceneEmail;
		$this->apiParas["buyer_scene_mobile"] = $buyerSceneMobile;
		$this->apiParas["currency"] = $currency;
		$this->apiParas["env_client_base_band"] = $envClientBaseBand;
		$this->apiParas["env_client_base_station"] = $envClientBaseStation;
		$this->apiParas["env_client_coordinates"] = $envClientCoordinates;
		$this->apiParas["env_client_imei"] = $envClientImei;
		$this->apiParas["env_client_imsi"] = $envClientImsi;
		$this->apiParas["env_client_ios_udid"] = $envClientIosUdid;
		$this->apiParas["env_client_ip"] = $envClientIp;
		$this->apiParas["env_client_mac"] = $envClientMac;
		$this->apiParas["env_client_screen"] = $envClientScreen;
		$this->apiParas["env_client_uuid"] = $envClientUuid;
		$this->apiParas["item_quantity"] = $itemQuantity;
		$this->apiParas["item_unit_price"] = $itemUnitPrice;
		$this->apiParas["js_token_id"] = $jsTokenId;
		$this->apiParas["order_amount"] = $orderAmount;
		$this->apiParas["order_category"] = $orderCategory;
		$this->apiParas["order_credate_time"] = $orderCredateTime;
		$this->apiParas["order_item_city"] = $orderItemCity;
		$this->apiParas["order_item_name"] = $orderItemName;
		$this->apiParas["order_no"] = $orderNo;
		$this->apiParas["partner_id"] = $partnerId;
		$this->apiParas["receiver_address"] = $receiverAddress;
		$this->apiParas["receiver_city"] = $receiverCity;
		$this->apiParas["receiver_district"] = $receiverDistrict;
		$this->apiParas["receiver_email"] = $receiverEmail;
		$this->apiParas["receiver_mobile"] = $receiverMobile;
		$this->apiParas["receiver_name"] = $receiverName;
		$this->apiParas["receiver_state"] = $receiverState;
		$this->apiParas["receiver_zip"] = $receiverZip;
		$this->apiParas["scene_code"] = $sceneCode;
		$this->apiParas["seller_account_no"] = $sellerAccountNo;
		$this->apiParas["seller_bind_bankcard"] = $sellerBindBankcard;
		$this->apiParas["seller_bind_bankcard_type"] = $sellerBindBankcardType;
		$this->apiParas["seller_bind_mobile"] = $sellerBindMobile;
		$this->apiParas["seller_identity_no"] = $sellerIdentityNo;
		$this->apiParas["seller_identity_type"] = $sellerIdentityType;
		$this->apiParas["seller_real_name"] = $sellerRealName;
		$this->apiParas["seller_reg_date"] = $sellerRegDate;
		$this->apiParas["seller_reg_email"] = $sellerRegEmail;
		$this->apiParas["seller_reg_moile"] = $sellerRegMoile;
		$this->apiParas["transport_type"] = $transportType;
		$this->apiParas["buyer_account_no"] = $buyerAccountNo;
		
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_security_risk_detect_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_security_risk_detect_response;
			return $this->respone;
		}
	}
	
	/** 
	 * 到店用户查询接口
	 * @param string  $merchantId 合作商户的分店ID
	 * @param string $needBirthday 是否查询当天生日
	 * @param string $publicId 分配给公众号的ID
	 * @param string $userId 支付宝用户的uesrid
	 * @param string $return_url API执行完，页面跳转到的地址
	 **/
	function siteprobe_instore_user($merchantId,$needBirthday,$publicId,$userId,$return_url=''){
		$this->apiMethod = 'alipay.siteprobe.instore.user';
		!empty($return_url) && $this->apiParas['return_url'] = $return_url;
		
		$this->apiParas["merchant_id"] = $merchantId;
		$this->apiParas["need_birthday"] = $needBirthday;
		$this->apiParas["public_id"] = $publicId;
		$this->apiParas["user_id"] = $userId;
		
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_siteprobe_instore_user_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_siteprobe_instore_user_response->users;
			return $this->respone;
		}
	}
	
	/** 
	 * $auth 是否已认证
	 * $deviceId wifi对应设备的编号
	 * $deviceMac wifi设备的mac地址
	 * $merchantId 合作商户的分店ID
	 * $partnerId 分配和合作方的id
	 * $token 上网的令牌，和用户设备mac有一对一的关系
	 * $userMac 连接wifi的设备的mac地址
	 **/
	function wifi_connect(){
		$this->apiMethod = 'alipay.siteprobe.wifi.connect';
		$this->apiParas["auth"] = $auth;
		$this->apiParas["device_id"] = $deviceId;
		$this->apiParas["device_mac"] = $deviceMac;
		$this->apiParas["merchant_id"] = $merchantId;
		$this->apiParas["partner_id"] = $partnerId;
		$this->apiParas["token"] = $token;
		$this->apiParas["user_mac"] = $userMac;
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		$this->respone['is_error'] = false;$this->respone['result'] = $result;
		return $this->respone;
	}
	
	/** 
	 * $deviceId wifi对应设备的编号
	 * $deviceMac wifi设备的mac地址
	 * $merchantId 合作商户的分店ID
	 * $partnerId 分配和合作方的id
	 * $userMac 连接wifi的设备的mac地址
	 **/
	function wifi_unconnect(){
		$this->apiMethod = 'alipay.siteprobe.wifi.unconnect';
		$this->apiParas["device_id"] = $deviceId;
		$this->apiParas["device_mac"] = $deviceMac;
		$this->apiParas["merchant_id"] = $merchantId;
		$this->apiParas["partner_id"] = $partnerId;
		$this->apiParas["user_mac"] = $userMac;
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		$this->respone['is_error'] = false;$this->respone['result'] = $result;
		return $this->respone;
	}
	
	/**
	 * 支付宝实名认证查询接口
	 *
	 * @return $this->respone
	 */
	function trust_user_alipaycert_get(){
		$this->apiMethod = 'alipay.trust.user.alipaycert.get';
		
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_trust_user_alipaycert_get_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_trust_user_alipaycert_get_response->ali_trust_alipay_cert;
			return $this->respone;
		}
	}
	
	/**
	 * 芝麻信用认证用户基本信息查询接口
	 *
	 * @return $this->respone
	 */
	function trust_user_basicinfo_get(){
		$this->apiMethod = 'alipay.trust.user.basicinfo.get';
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_trust_user_basicinfo_get_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_trust_user_basicinfo_get_response->ali_trust_user_basic_info;
			return $this->respone;
		}
	}
	/**
	 * 芝麻信用认证查询接口
	 *
	 * @return $this->respone
	 */
	function trust_user_cert_get(){
		$this->apiMethod = 'alipay.trust.user.cert.get';
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_trust_user_cert_get_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_trust_user_cert_get_response->ali_trust_cert;
			return $this->respone;
		}
	}
	
	/** 
	 * 接收商户回流的用户数据
	 * $data Json格式，具体内容根据不同的type_id而不同。详见芝麻信用的数据类型文档（线下提供）。
	 * $identity 用户身份标识，JSON格式，JSON中包括5个属性，除name外，必有一个字段的值不为空。尽可能填写完整。
	 * $typeId 数据类型ID，有芝麻信用针对不同商户而分配
	 **/
	function trust_user_datareceived_send($identity,$data,$typeId){
		$this->apiMethod = 'alipay.trust.user.datareceived.send';
		$this->apiParas["data"] = $data;
		$this->apiParas["identity"] = $identity;
		$this->apiParas["type_id"] = $typeId;
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_trust_user_datareceived_send_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_trust_user_datareceived_send_response;
			return $this->respone;
		}
	}
	
	/**
	 * 用户实名注册信息查询接口
	 *
	 * @return $this->respone
	 */
	function trust_user_realnameregistered_get(){
		$this->apiMethod = 'alipay.trust.user.realnameregistered.get';
		$result = $this->execute();
		
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_trust_user_realnameregister_get_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_trust_user_realnameregister_get_response->real_name_registered;
			return $this->respone;
		}
	}
	
	/**
	 * 芝麻信用风险识别接口
	 *
	 * @return $this->respone
	 */
	function trust_user_riskidentify_get(){
		$this->apiMethod = 'alipay.trust.user.riskidentify.get';
		
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_trust_user_riskidentify_search_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_trust_user_riskidentify_search_response->ali_trust_risk_identify;
			return $this->respone;
		}
	}
	
	/**
	 * 芝麻信用分查询
	 *
	 * @return $this->respone
	 */
	function trust_user_score_get(){
		$this->apiMethod = 'alipay.trust.user.score.get';
		
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_trust_user_score_get_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_trust_user_score_get_response->score;
			return $this->respone;
		}
	}
	/**
	 * 芝麻信用基本信息校验
	 *
	 * @param string $ali_trust_user_info 入参json串, 其中*号为encryp_code。 确保每个字段的值的总长度必须与没加密之前的字段长度要一致
	 * @param string $encryp_code 只能为单个字符，不传默认为*
	 * @return $this->respone
	 */
	function trust_user_basicinfo_verify_get($ali_trust_user_info,$encryp_code){
		$this->apiMethod = 'alipay.trust.user.basicinfo.verify.get';
		$this->apiParas['ali_trust_user_info'] = $ali_trust_user_info;
		$this->apiParas['encryp_code'] = $encryp_code;
		
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_trust_user_basicinfo_verify_get_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_trust_user_basicinfo_verify_get_response->verify_info;
			return $this->respone;
		}
	}
	
	
	/** 
	 * 查询支付宝账户冻结金额
	 * $freezeType 冻结类型，多个用,分隔。不传返回所有类型的冻结金额。 DEPOSIT_FREEZE,充值冻结 WITHDRAW_FREEZE,提现冻结 PAYMENT_FREEZE,支付冻结 BAIL_FREEZE,保证金冻结 CHARGE_FREEZE,收费冻结 PRE_DEPOSIT_FREEZE,预存款冻结 LOAN_FREEZE,贷款冻结 OTHER_FREEZE,其它
	 **/
	function user_account_freeze_get($freezeType){
		$this->apiMethod = 'alipay.user.account.freeze.get';
		$this->apiParas["freeze_type"] = $freezeType;
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_user_account_freeze_get_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_user_account_freeze_get_response;
			return $this->respone;
		}
	}
	/**
	 * 查询支付宝账户余额
	 *
	 * @return $this->respone
	 */
	function user_account_get(){
		$this->apiMethod = 'alipay.user.account.get';
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_user_account_get_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_user_account_get_response->alipay_account;
			return $this->respone;
		}
	}
	
	/** 
	 * 查询用户支付宝账务明细
	 * @param  string $fields 需要过滤的字符
	 * @param  string $pageNo 查询的页数
	 * @param  string $pageSize 每页的条数
	 * @param  string $startTime 查询的开始时间
	 * @param  string $endTime 查询的结束时间
	 * @param  string $type 查询账务的类型
	 * @return $this->respone
	 **/
	function user_account_search($fields,$pageNo,$pageSize,$startTime,$endTime,$type){
		$this->apiMethod = 'alipay.user.account.search';
		$this->apiParas["end_time"] = $endTime;
		$this->apiParas["fields"] = $fields;
		$this->apiParas["page_no"] = $pageNo;
		$this->apiParas["page_size"] = $pageSize;
		$this->apiParas["start_time"] = $startTime;
		$this->apiParas["type"] = $type;
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_user_account_search_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_user_account_search_response;
			return $this->respone;
		}
	}
	
	/** 
	 * 查询支付宝用户订购信息
	 * @param  string $subscriberUserId 订购者支付宝ID。session与subscriber_user_id二选一即可。
	 **/
	function user_contract_get($subscriberUserId){
		$this->apiMethod = 'alipay.user.contract.get';
		$this->apiParas["subscriber_user_id"] = $subscriberUserId;
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_user_contract_get_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_user_contract_get_response->alipay_contract;
			return $this->respone;
		}
	}
	
	/** 
	 * 查询支付宝用户信息
	 * 需要返回的字段列表。alipay_user_id：支付宝用户userId,user_status：支付宝用户状态,user_type：支付宝用户类型,certified：是否通过实名认证,real_name：真实姓名,logon_id：支付宝登录号,sex：用户性别
	 **/
	function user_get(){
		$this->apiMethod = 'alipay.user.get';
		$this->apiParas["fields"] = 'alipay_user_id,user_status,user_type,certified,real_name,logon_id,sex';
		$result = $this->execute();
		
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_user_get_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_user_get_response->alipay_user_detail;
			return $this->respone;
		}
	}
	
	/** 
	 * 会员卡信息更新接口
	 * @param string $balance 商户会员卡余额
	 * @param string $bizCardNo 会员卡卡号
	 * @param string $externalCardNo 商户会员卡号。 比如淘宝会员卡号、商户实体会员卡号、商户自有CRM虚拟卡号等
	 * @param string $level 商户会员卡会员等级
	 * @param string $point 商户会员卡积分
	 * @param string $return_url API执行完，页面跳转到的地址
	 * @return $this->respone
	 **/
	function user_member_card_update($balance,$bizCardNo,$externalCardNo,$level,$point,$return_url=''){
		$this->apiMethod = 'alipay.user.member.card.update';
		!empty($return_url) && $this->apiParas['return_url'] = $return_url;
		$this->apiParas["balance"] = $balance;
		$this->apiParas["biz_card_no"] = $bizCardNo;
		$this->apiParas["external_card_no"] = $externalCardNo;
		$this->apiParas["level"] = $level;
		$this->apiParas["point"] = $point;
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_user_card_update_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_user_card_update_response;
			return $this->respone;
		}
	}
	
	
	/** 
	 * 查询支付宝账户交易记录
	 * @param string $alipayOrderNo 支付宝订单号，为空查询所有记录
	 * @param string $endTime 结束时间。与开始时间间隔在七天之内
	 * @param string $merchantOrderNo 商户订单号，为空查询所有记录
	 * @param string $orderFrom 订单来源，为空查询所有来源。淘宝(TAOBAO)，支付宝(ALIPAY)，其它(OTHER)
	 * @param string $orderStatus 订单状态，为空查询所有状态订单
	 * @param string $orderType 订单类型，为空查询所有类型订单。
	 * @param string $pageNo 页码。取值范围:大于零的整数; 默认值1
	 * @param string $pageSize 每页获取条数。最大值500。
	 * @param string $startTime 开始时间，时间必须是今天范围之内。格式为yyyy-MM-dd HH:mm:ss，精确到秒(升级后的api 1.1版本)
	 * @return $this->respone
	 **/
	function user_trade_search(){
		$this->apiMethod = 'alipay.user.trade.search';
		$this->apiParas["alipay_order_no"] = $alipayOrderNo;
		$this->apiParas["end_time"] = $endTime;
		$this->apiParas["merchant_order_no"] = $merchantOrderNo;
		$this->apiParas["order_from"] = $orderFrom;
		$this->apiParas["order_status"] = $orderStatus;
		$this->apiParas["order_type"] = $orderType;
		$this->apiParas["page_no"] = $pageNo;
		$this->apiParas["page_size"] = $pageSize;
		$this->apiParas["start_time"] = $startTime;
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_user_trade_search_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_user_trade_search_response;
			return $this->respone;
		}
	}
	
	/**
	 * 支付宝钱包用户信息共享
	 *
	 * @return $this->respone
	 */
	function user_userinfo_share(){
		$this->apiMethod = 'alipay.user.userinfo.share';
		
		$result = $this->execute();
		//错误处理
		if(isset($result->error_response)){ 
			$this->respone['result'] = $this->error_tips_lang == 'zh' ? $result->error_response->sub_msg : $result->error_response->msg; $this->respone['is_error']=true; return $this->respone;
		}
		
		if(isset($result->alipay_user_userinfo_share_response)){
			$this->respone['is_error'] = false;$this->respone['result'] = $result->alipay_user_userinfo_share_response;
			return $this->respone;
		}
	}
}