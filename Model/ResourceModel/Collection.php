<?php 
namespace Sadad\Gateway\Model\ResourceModel;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
	public function _construct(){
		$this->_init("Sadad\Gateway\Model\SadadOrderInfo","Sadad\Gateway\Model\ResourceModel\SadadOrderInfo");
	}
}