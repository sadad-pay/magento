<?php
namespace Sadad\Gateway\Controller\Payment;
use Sadad\Library\SadadLibrary;

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
        	$orderID = $this->checkoutSession->getLastOrderId();
        	$orderData = $this->_objectManager->create('Magento\Sales\Model\Order')->load($orderID);

		//------- 
		// change order state & status to pending payment.
        	$orderData->setIsNotified(false);
        	$orderData->setState($orderData::STATE_PENDING_PAYMENT);
        	$orderData->setStatus($orderData::STATE_PENDING_PAYMENT);
        	$orderData->save();
		//------- 

        	$amount = number_format($orderData->getgrandTotal(), 2, '.', '');
        	$billingAddress = $orderData->getBillingAddress();

        	$referenceNo = $orderData->getIncrementId();
        	$error = '';
        	$currency   = $orderData->getOrderCurrencyCode();
        	$currency = $orderData->getBaseCurrencyCode();
        	$email = $orderData->getCustomerEmail();
        	$phoneNumber = $billingAddress->getTelephone();
        	$billFirstName = $billingAddress->getFirstname();
        	$billLastName = $billingAddress->getLastname();

        	$paymentMethod = $this->_objectManager->create('Sadad\Gateway\Model\SadadPayment');
        	$environment = $paymentMethod->getConfigData('environment');
        	$env = ($paymentMethod->getConfigData('environment') == 'test') ? true : false;
        	$sadadConfig = array(
        		'clientId'     => $paymentMethod->getConfigData('client_id'),
        		'clientSecret' => $paymentMethod->getConfigData('client_secret'),
        		'isTest'       => $env
        	);

        	if ( 'KWD' !== $currency ) {
        		$amount = SadadLibrary::getKWDAmount( $currency, $amount , $env);
        	}
        	$sadadObj = new SadadLibrary( $sadadConfig );

        	$invoice = array(
        		'ref_Number'=> "$referenceNo",
        		'amount'=> $amount,
        		'customer_Name'=> $billFirstName . ' ' . $billLastName,
        		'customer_Mobile'=> $phoneNumber,
        		'customer_Email'=> $email,
        		'lang'=> 'en',
        		'currency_Code'=> $currency
        	);					
        	$request = array('Invoices'=> array($invoice));
        	try{ 
        		$sadadInvoice = $sadadObj->createInvoice( $request, $paymentMethod->getConfigData('refresh_token') );
        		header("location:" . $sadadInvoice['InvoiceURL']);
        		exit;
        	} catch (Exception $ex) {
        		$this->messageManager->addError($error);
        		$_quoteFactory = $this->_objectManager->create('\Magento\Quote\Model\QuoteFactory');
        		$quote = $_quoteFactory->create()->loadByIdWithoutStore($orderData->getQuoteId());
        		if ($quote->getId()) {
        			$quote->setIsActive(1)->setReservedOrderId(null)->save();
				    $this->checkoutSession->replaceQuote($quote);
        			$resultRedirect = $this->resultRedirectFactory->create();
        			$resultRedirect->setPath('checkout/cart');
        			return $resultRedirect;
        		} else {
        			die('Ops, There is something went wong with magento qoute!');
        		}

        	}
        }
    }
