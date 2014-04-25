<?php
/**
 * ALIPAY API: alipay.trust.user.cert.get request
 * 
 * @author auto create
 * @since 1.0, 2014-04-11 13:45:13
 */
class AlipayTrustUserCertGetRequest
{
	
	private $apiParas = array();
	private $terminalType;	
	private $terminalInfo;
	
	public function getApiMethodName()
	{
		return "alipay.trust.user.cert.get";
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
