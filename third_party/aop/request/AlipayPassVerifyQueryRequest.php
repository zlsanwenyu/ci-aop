<?php
/**
 * ALIPAY API: alipay.pass.verify.query request
 * 
 * @author auto create
 * @since 1.0, 2013-10-30 17:11:48
 */
class AlipayPassVerifyQueryRequest
{
	/** 
	 * Alipass对应的核销码串
	 **/
	private $verifyCode;
	
	private $apiParas = array();
	private $terminalType;	
	private $terminalInfo;
	
	public function setVerifyCode($verifyCode)
	{
		$this->verifyCode = $verifyCode;
		$this->apiParas["verify_code"] = $verifyCode;
	}

	public function getVerifyCode()
	{
		return $this->verifyCode;
	}

	public function getApiMethodName()
	{
		return "alipay.pass.verify.query";
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
