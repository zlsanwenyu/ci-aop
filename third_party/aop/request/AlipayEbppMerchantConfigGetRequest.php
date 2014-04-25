<?php
/**
 * ALIPAY API: alipay.ebpp.merchant.config.get request
 * 
 * @author auto create
 * @since 1.0, 2013-04-28 22:41:02
 */
class AlipayEbppMerchantConfigGetRequest
{
	
	private $apiParas = array();
	private $terminalType;	
	private $terminalInfo;
	
	public function getApiMethodName()
	{
		return "alipay.ebpp.merchant.config.get";
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
