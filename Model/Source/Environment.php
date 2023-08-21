<?php
namespace Sadad\Gateway\Model\Source;
class Environment implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'test', 
                'label' => 'Test',
            ),
			array(
                'value' => 'live', 
				'label' => 'Live'
            ),
			
        );
    }
}