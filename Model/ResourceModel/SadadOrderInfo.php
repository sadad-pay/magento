<?php 
namespace Sadad\Gateway\Model\ResourceModel;

class SadadOrderInfo extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb 
{
	public function _construct(){
		$this->_init("sadad_order_info","id");
	}
}
?>