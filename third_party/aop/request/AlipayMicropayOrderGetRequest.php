<?php
/**
 * ALIPAY API: alipay.micropay.order.get request
 * 
 * @author auto create
 * @since 1.0, 2013-04-28 22:39:25
 */
class AlipayMicropayOrderGetRequest
{
	/** 
	 * 支付宝订单号，冻结流水号(创建冻结订单返回)
	 **/
	private $alipayOrderNo;
	
	private $apiParas = array();
	private $terminalType;	
	private $terminalInfo;
	
	public function setAlipayOrderNo($alipayOrderNo)
	{
		$this->alipayOrderNo = $alipayOrderNo;
		$this->apiParas["alipay_order_no"] = $alipayOrderNo;
	}

	public function getAlipayOrderNo()
	{
		return $this->alipayOrderNo;
	}

	public function getApiMethodName()
	{
		return "alipay.micropay.order.get";
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
