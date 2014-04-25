<?php
/**
 * ALIPAY API: alipay.point.budget.get request
 * 
 * @author auto create
 * @since 1.0, 2013-04-27 20:16:11
 */
class AlipayPointBudgetGetRequest
{
	
	private $apiParas = array();
	private $terminalType;	
	private $terminalInfo;
	
	public function getApiMethodName()
	{
		return "alipay.point.budget.get";
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
