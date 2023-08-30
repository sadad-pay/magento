<?php
namespace Sadad\Gateway\Controller\Payment;
use Sadad\Library\SadadLibrary;

class Process extends \Magento\Framework\App\Action\Action
{
		/**
		 * @var \Magento\Checkout\Model\Session
		 */
		protected $checkoutSession;

	    /**
        * @var \Sadad\Gateway\Model\SadadOrderInfo
        */
        private $sadadOrderInfo;
        /**
         * @var Magento\Framework\Locale\Resolver
         */
        private $localeResolver;

        /**
         * @param \Magento\Framework\App\Action\Context $context
         * @param \Magento\Checkout\Model\Session $checkoutSession
         * @param \Sadad\Gateway\Model\SadadOrderInfo $sadadOrderInfo
         */
        public function __construct(
        	\Magento\Framework\App\Action\Context $context,
        	\Magento\Checkout\Model\Session $checkoutSession,
            \Sadad\Gateway\Model\SadadOrderInfo $sadadOrderInfo,
            \Magento\Framework\Locale\Resolver $localeResolver
        )
        {
            $this->checkoutSession = $checkoutSession;
            $this->sadadOrderInfo = $sadadOrderInfo;
            $this->localeResolver = $localeResolver;

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
            $url      = $this->_url->getBaseUrl() . 'sadad_gateway/payment/callback/';
        	$invoice = array(
        		'ref_Number'=> "$referenceNo",
        		'amount'=> $amount,
        		'customer_Name'=> $billFirstName . ' ' . $billLastName,
        		'customer_Mobile'=> $phoneNumber,
        		'customer_Email'=> $email,
        		'lang'=> $this->getLocalLanguage(),
        		'currency_Code'=> $currency,
                'Success_ReturnURL'=>$url,
                'Fail_ReturnURL'=>$url
        	);					
        	$request = array('Invoices'=> array($invoice));
        	try{ 
        		$sadadInvoice = $sadadObj->createInvoice( $request, $paymentMethod->getConfigData('refresh_token') );

                //save the sadad invoice information
                $this->sadadOrderInfo->addData(
                    [
                            'magento_order_id'     => $referenceNo,
                            'sadad_invoice_id'   => $sadadInvoice['InvoiceId'],
                            'sadad_invoice_url'  => $sadadInvoice['InvoiceURL'],
                        ]
                );
                $this->sadadOrderInfo->save();

        		header("location:" . $sadadInvoice['InvoiceURL']);
        		exit;
        	} catch (\Exception $ex) {
        		$this->messageManager->addError($ex->getMessage());
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

        /**
         * Get get Local Language Code
         *
         * @return string
         */
        public function getLocalLanguage()
        {
            $localLangCode = $this->localeResolver->getLocale();
            return strstr($localLangCode, '_', true);
        }
    }
