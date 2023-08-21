<?php
namespace Sadad\Gateway\Model;

use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\View\LayoutFactory;
use Magento\Payment\Helper\Data as HelperData;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\Method\Logger;
//use Magento\Sales\Model\Invoice\Payment as InvoicePayment;
//use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Framework\Event\Observer;
class SadadPayment extends \Magento\Payment\Model\Method\AbstractMethod
{
    const CODE = 'sadad_gateway';
    protected $_code = self::CODE;
	
	protected $_modelOrder;
    protected $_viewLayoutFactory;
    protected $_logger;
    protected $_eventObserver;    
    protected $_storeManager;
    protected $_urlInterface;

	
	public function __construct(Context $context, 
        Registry $registry, 
        ExtensionAttributesFactory $extensionFactory, 
        AttributeValueFactory $customAttributeFactory, 
        Observer $eventObserver,
        HelperData $paymentData, 
        ScopeConfigInterface $scopeConfig, 
        Logger $logger, 
        Order $modelOrder, 
        LayoutFactory $viewLayoutFactory, 
        AbstractResource $resource = null, 
        AbstractDb $resourceCollection = null, 
        //StoreManagerInterface $storeManager,
        array $data = [])
    {
        $this->_modelOrder = $modelOrder;
        $this->_viewLayoutFactory = $viewLayoutFactory;
		$this->_logger = $logger;
		$this->_eventObserver = $eventObserver;
		//$this->_storeManager=$storeManager;
		$this->_urlInterface = \Magento\Framework\App\ObjectManager::getInstance()->get('Magento\Framework\UrlInterface');

        parent::__construct($context, $registry, $extensionFactory, $customAttributeFactory, $paymentData, $scopeConfig, $logger, $resource, $resourceCollection, $data);
    }
}