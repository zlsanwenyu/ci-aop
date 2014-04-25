<?php
/**
 * ALIPAY API: alipay.trust.user.datareceived.send request
 * 
 * @author auto create
 * @since 1.0, 2014-03-05 15:41:50
 */
class AlipayTrustUserDatareceivedSendRequest
{
	/** 
	 * Json格式，具体内容根据不同的type_id而不同。详见芝麻信用的数据类型文档（线下提供）。
	 **/
	private $data;
	
	/** 
	 * 用户身份标识，JSON格式，JSON中包括5个属性，除name外，必有一个字段的值不为空。尽可能填写完整。
	 **/
	private $identity;
	
	/** 
	 * 数据类型ID，有芝麻信用针对不同商户而分配
	 **/
	private $typeId;
	
	private $apiParas = array();
	private $terminalType;	
	private $terminalInfo;
	
	public function setData($data)
	{
		$this->data = $data;
		$this->apiParas["data"] = $data;
	}

	public function getData()
	{
		return $this->data;
	}

	public function setIdentity($identity)
	{
		$this->identity = $identity;
		$this->apiParas["identity"] = $identity;
	}

	public function getIdentity()
	{
		return $this->identity;
	}

	public function setTypeId($typeId)
	{
		$this->typeId = $typeId;
		$this->apiParas["type_id"] = $typeId;
	}

	public function getTypeId()
	{
		return $this->typeId;
	}

	public function getApiMethodName()
	{
		return "alipay.trust.user.datareceived.send";
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
