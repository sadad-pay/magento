<?php
namespace Mcpayment\EcomRedirect\Model\Source;
class PaymentMode implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'sale', 
                'label' => 'Sale',
            ),
			array(
                'value' => 'preauth', 
				'label' => 'Pre Auth'
            ),
			
        );
    }
}