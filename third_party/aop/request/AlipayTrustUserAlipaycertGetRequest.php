<?php
/**
 * ALIPAY API: alipay.trust.user.alipaycert.get request
 * 
 * @author auto create
 * @since 1.0, 2014-03-05 15:42:27
 */
class AlipayTrustUserAlipaycertGetRequest
{
	
	private $apiParas = array();
	private $terminalType;	
	private $terminalInfo;
	
	public function getApiMethodName()
	{
		return "alipay.trust.user.alipaycert.get";
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
