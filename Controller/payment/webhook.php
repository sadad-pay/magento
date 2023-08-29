<?php
namespace Sadad\Gateway\Controller\Payment;
use Sadad\Library\SadadLibrary;
use Magento\Framework\Controller\ResultFactory; 
use Exception;

class Webhook extends \Magento\Framework\App\Action\Action
{
        /**
         * @var \Sadad\Gateway\Model\ResourceModel\CollectionFactory
         */
        public $sadadOrderCollection;
        /**
         * @param \Magento\Framework\App\Action\Context $context
         * @param \Sadad\Gateway\Model\ResourceModel\CollectionFactory $sadadOrderCollection
         */
        public function __construct(
            \Magento\Framework\App\Action\Context $context,
            \Sadad\Gateway\Model\ResourceModel\CollectionFactory $sadadOrderCollection
        )
        {
            $this->sadadOrderCollection = $sadadOrderCollection;
            parent::__construct($context);
        }

        public function execute()
        {
            $body       = file_get_contents("php://input");
            $webhook    = json_decode($body);
            $invoiceId = $webhook->invoiceId;
            $collection = $this->sadadOrderCollection->create()->addFieldToFilter('sadad_invoice_id', $invoiceId);
            $item       = $collection->getFirstItem();
            $itemData   = $item->getData();
            if (!empty($itemData['sadad_invoice_id']) && !empty($itemData['magento_order_id'])) {
                $referenceNo = $itemData['magento_order_id'];
                $orderData = $this->_objectManager->create('Magento\Sales\Model\Order')->load($referenceNo);

                if(!empty($orderData)){
                    $status = $orderData->getState();
                    if ($status !== $orderData::STATE_PENDING_PAYMENT && $status !== $orderData::STATE_CANCELED) {
                            die('order '.$referenceNo.' is already processed');
                    }
                    $paymentMethod = $this->_objectManager->create('Sadad\Gateway\Model\SadadPayment');
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
                            $return_msg = 'SADAD Webhook - Payment completed, Transaction ID: ' . $transactionID .' - Invoice ID: '.$invoiceId;
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

                            die ('order '.$ref_Number.' is processed successfully');

                        } else {
                            $error = "SADAD Webhook - Order #$ref_Number: " . 'Payment return: Invoice is not Paid';
                            if ($orderData->getState() === $orderData::STATE_PENDING_PAYMENT) {
                                $orderData->registerCancellation($error);
                            } else {
                                $orderData->addStatusHistoryComment($error);
                            }
                            $orderData->save();
                            die ($error);

                        }
                    }
                }
            }
        }


    }
