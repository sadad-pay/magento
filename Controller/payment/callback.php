<?php
namespace Sadad\Gateway\Controller\Payment;
use Sadad\Library\SadadLibrary;
use Magento\Framework\Controller\ResultFactory; 
use Exception;

class Callback extends \Magento\Framework\App\Action\Action
{
		/**
		 * @var \Magento\Checkout\Model\Session
		 */
		protected $checkoutSession;
	    /**
	     * @var \Sadad\Gateway\Model\ResourceModel\CollectionFactory
	     */
	    public $sadadOrderCollection;
        /**
         * @param \Magento\Framework\App\Action\Context $context
         * @param \Magento\Checkout\Model\Session $checkoutSession
         * @param \Sadad\Gateway\Model\ResourceModel\CollectionFactory $sadadOrderCollection
         */
        public function __construct(
        	\Magento\Framework\App\Action\Context $context,
        	\Magento\Checkout\Model\Session $checkoutSession,
        	\Sadad\Gateway\Model\ResourceModel\CollectionFactory $sadadOrderCollection
        )
        {
        	$this->checkoutSession = $checkoutSession;
        	$this->sadadOrderCollection = $sadadOrderCollection;
        	parent::__construct($context);
        }

        public function execute()
        {
        	$error = '';
        	$paymentMethod = $this->_objectManager->create('Sadad\Gateway\Model\SadadPayment');
        	$invoiceId = SadadLibrary::filterInput('invoice_id');
        	if (empty($invoiceId)) {
        		$this->redirectWithError('Ops, you are accessing wrong data');
        	}
        	$collection = $this->sadadOrderCollection->create()->addFieldToFilter('sadad_invoice_id', $invoiceId);
        	$item       = $collection->getFirstItem();
        	$itemData   = $item->getData();
        	if (empty($itemData['sadad_invoice_id'])) {
        		$this->redirectWithError('Ops, you are accessing wrong order');
        	}

        	if (empty($itemData['magento_order_id'])) {
        		$this->redirectWithError('Ops, you are accessing wrong order');
        	}

        	if( $invoiceId !== $itemData['sadad_invoice_id']){
        		$this->redirectWithError('Ops, you are accessing wrong data');
        	}
        	$referenceNo = $itemData['magento_order_id'];
        	$orderData = $this->_objectManager->create('Magento\Sales\Model\Order')->load($referenceNo);

        	if(empty($orderData)){
        		$this->redirectWithError('Ops, Oder is not found!');
        	}

        	$status = $orderData->getState();

        	if ($status !== $orderData::STATE_PENDING_PAYMENT && $status !== $orderData::STATE_CANCELED) {
        		$this->messageManager->addSuccessMessage('Payment completed - Order ID: '.$referenceNo);
        		return $this->_redirect('checkout/onepage/success', ['_secure' => false]);
        	}
        	$env = ($paymentMethod->getConfigData('environment') == 'test') ? true : false;
        	$sadadConfig = array(
        		'clientId'     => $paymentMethod->getConfigData('client_id'),
        		'clientSecret' => $paymentMethod->getConfigData('client_secret'),
        		'isTest'       => $env
        	);
        	$sadadObj = new SadadLibrary( $sadadConfig );
        	$response = $sadadObj->getInvoiceInfo($invoiceId, $paymentMethod->getConfigData('refresh_token'));

        	if ( isset($response['isValid']) && $response['isValid'] == 'true' ) {
        		$ref_Number = $response['response']['ref_Number'];
        		if ($response['response']['status'] == 'Paid' && $referenceNo == $ref_Number) {
        			$transactionID = $response['response']['transactionID'];
        			$return_msg = 'Payment completed, Transaction ID: ' . $transactionID .' - Invoice ID: '.$invoiceId;
        			$item->setData('sadad_reference_id', $transactionID);
        			$item->save();

        			$payment = $orderData->getPayment();
        			$payment->setTransactionId($transactionID)
        			->setCurrencyCode($orderData->getBaseCurrencyCode())
        			->setPreparedMessage($return_msg)
        			->setParentTransactionId($transactionID)
        			->registerCaptureNotification($orderData->getgrandTotal());

        			$orderData->setState($orderData::STATE_PROCESSING)
        			->setStatus($orderData::STATE_PROCESSING)
        			->addStatusHistoryComment($return_msg);
        			$orderData->save();

        			$redirect_url = $this->_url->getUrl("checkout/onepage/success");
		        	$resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        			$resultRedirect->setUrl($redirect_url);
        			return $resultRedirect;
        		} else {
        			$error = "Order #$ref_Number: " . 'Payment return: Invoice is not Paid';
        			$this->restoreQuoteWithCancel($error, $orderData);
        		}
        	} else {
        		$error = 'Payment return: Invoice not found';
        		$this->restoreQuoteWithCancel($error, $orderData);
        	}        	
        }

        public function restoreQuoteWithCancel($error, $orderData){
        	$_quoteFactory = $this->_objectManager->create('\Magento\Quote\Model\QuoteFactory');
        	$quote = $_quoteFactory->create()->loadByIdWithoutStore($orderData->getQuoteId());
        	if ($quote->getId()) {
        		$quote->setIsActive(1)->setReservedOrderId(null)->save();
        		$this->checkoutSession->replaceQuote($quote);
        		$this->checkoutSession->restoreQuote();
        		if ($orderData->getState() === $orderData::STATE_PENDING_PAYMENT) {
        			$orderData->registerCancellation($error);
        		} else {
        			$orderData->addStatusHistoryComment($error);
        		}
        		$orderData->save();
        		$this->redirectWithError($error);
        	} else {
        		$this->redirectWithError('Ops, There is something went wong with magento qoute!');
        	}
        }
        public function redirectWithError($error){
        	$this->messageManager->addErrorMessage($error);
    		$this->_redirect('checkout/cart', ['_secure' => false]);
        }
    }