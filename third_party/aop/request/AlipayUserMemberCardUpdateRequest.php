<?php
/**
 * ALIPAY API: alipay.user.member.card.update request
 * 
 * @author auto create
 * @since 1.0, 2014-04-08 20:04:54
 */
class AlipayUserMemberCardUpdateRequest
{
	/** 
	 * 商户会员卡余额
	 **/
	private $balance;
	
	/** 
	 * 会员卡卡号
	 **/
	private $bizCardNo;
	
	/** 
	 * 商户会员卡号。 
比如淘宝会员卡号、商户实体会员卡号、商户自有CRM虚拟卡号等
	 **/
	private $externalCardNo;
	
	/** 
	 * 商户会员卡会员等级
	 **/
	private $level;
	
	/** 
	 * 商户会员卡积分
	 **/
	private $point;
	
	private $apiParas = array();
	private $terminalType;	
	private $terminalInfo;
	
	public function setBalance($balance)
	{
		$this->balance = $balance;
		$this->apiParas["balance"] = $balance;
	}

	public function getBalance()
	{
		return $this->balance;
	}

	public function setBizCardNo($bizCardNo)
	{
		$this->bizCardNo = $bizCardNo;
		$this->apiParas["biz_card_no"] = $bizCardNo;
	}

	public function getBizCardNo()
	{
		return $this->bizCardNo;
	}

	public function setExternalCardNo($externalCardNo)
	{
		$this->externalCardNo = $externalCardNo;
		$this->apiParas["external_card_no"] = $externalCardNo;
	}

	public function getExternalCardNo()
	{
		return $this->externalCardNo;
	}

	public function setLevel($level)
	{
		$this->level = $level;
		$this->apiParas["level"] = $level;
	}

	public function getLevel()
	{
		return $this->level;
	}

	public function setPoint($point)
	{
		$this->point = $point;
		$this->apiParas["point"] = $point;
	}

	public function getPoint()
	{
		return $this->point;
	}

	public function getApiMethodName()
	{
		return "alipay.user.member.card.update";
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
