<?php
/**
 * ALIPAY API: alipay.mobile.public.account.reset request
 * 
 * @author auto create
 * @since 1.0, 2014-01-14 16:10:57
 */
class AlipayMobilePublicAccountResetRequest
{
	/** 
	 * json
	 **/
	private $bizContent;
	
	private $apiParas = array();
	private $terminalType;	
	private $terminalInfo;
	
	public function setBizContent($bizContent)
	{
		$this->bizContent = $bizContent;
		$this->apiParas["biz_content"] = $bizContent;
	}

	public function getBizContent()
	{
		return $this->bizContent;
	}

	public function getApiMethodName()
	{
		return "alipay.mobile.public.account.reset";
	}
	
	public function getApiParas()
	{
		return $this->apiParas;
	}
	
	public function getTerminalType()
	{
		return $this->terminalType;
	}
	
	public function setTerminalType($terminalType)
	{
		$this->terminalType = $terminalType;
	}	
	
	public function getTerminalInfo()
	{
		return $this->terminalInfo;
	}
	
	public function setTerminalInfo($terminalInfo)
	{
		$this->terminalInfo = $terminalInfo;
	}		
}
