<?php
namespace Sadad\Gateway\Controller\PaymentReturn;
use Sadad\Library\SadadLibrary;
use Magento\Framework\Controller\ResultFactory; 

class Index extends \Magento\Framework\App\Action\Action
{
		/**
		 * @var \Magento\Checkout\Model\Session
		 */
		protected $checkoutSession;
		
        /**
         * @param \Magento\Framework\App\Action\Context $context
         * @param \Magento\Checkout\Model\Session $checkoutSession
         */
        public function __construct(
        	\Magento\Framework\App\Action\Context $context,
        	\Magento\Checkout\Model\Session $checkoutSession
        )
        {
        	$this->checkoutSession = $checkoutSession;
        	parent::__construct($context);
        }

        public function execute()
        {
        	$error = '';
        	$redirect_url = '';

        	$orderID = $this->checkoutSession->getLastOrderId();
        	$orderData = $this->_objectManager->create('Magento\Sales\Model\Order')->load($orderID);
        	$status = $orderData->getState();
        	$referenceNo = $orderData->getIncrementId();

        	if ($status !== $orderData::STATE_PENDING_PAYMENT && $status !== $orderData::STATE_CANCELED) {
				$this->messageManager->addSuccessMessage('Payment completed - Order ID: '.$referenceNo);
            	return $this->_redirect('checkout/onepage/success', ['_secure' => false]);
        	}

        	$paymentMethod = $this->_objectManager->create('Sadad\Gateway\Model\SadadPayment');

        	$resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        	$invoiceId = SadadLibrary::filterInput('invoice_id');
        	$payment = isset($_GET["payment"])?$_GET["payment"]:'';

        	if (empty($invoiceId)) {
        		$error = 'Ops, you are accessing wrong data';
        	}

    		// $log_invoiceId = $this->getCustomCookie("invoiceId");
		    // if( $invoiceId !== $log_invoiceId){
		    //     $error = 'Ops, you are accessing wrong data';
		    // }
		    
        	if (empty($error)) {
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
        				$resultRedirect->setUrl($redirect_url);
        				return $resultRedirect;
        			} else {
        				$error = "Order #$ref_Number: " . 'Payment return: Invoice is not Paid';
        			}
        		} else {
        			$error = 'Payment return: Invoice not found';
        		}
        	}
        	if (!empty($error)) {
        		$this->messageManager->addErrorMessage($error);
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
        			$resultRedirect = $this->resultRedirectFactory->create();
        			$resultRedirect->setPath('checkout/cart');
        			return $resultRedirect;
        		} else {
        			die('Ops, There is something went wong with magento qoute!');
        		}

        	}
        }
    }