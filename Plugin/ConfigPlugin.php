<?php
namespace Sadad\Gateway\Plugin;

class ConfigPlugin
{
	private $_configWriter;
	
	public function __construct(\Magento\Framework\App\Config\Storage\WriterInterface $configWriter) {
		$this->_configWriter = $configWriter;
	}
    public function aroundSave(
        \Magento\Config\Model\Config $subject,
        \Closure $proceed
    ) {
        // your custom logic
		$client_id 		= $subject->getConfigDataValue('payment/sadad_gateway/client_id');
		$client_secret  = $subject->getConfigDataValue('payment/sadad_gateway/client_secret');
		$environment 	= $subject->getConfigDataValue('payment/sadad_gateway/environment');
		$refreshToken = $this->generateFreshToken($client_id, $client_secret, $environment);
		if($refreshToken) {
			$this->_configWriter->save('payment/sadad_gateway/refresh_token', $refreshToken);
		}
        return $proceed();
    }
	
	public function generateFreshToken($client_id, $client_secret, $environment) {
		$refreshToken = '';
		if($environment == 'test')
			$gateway_url = 'https://apisandbox.sadadpay.net/api';
		else
			$gateway_url = 'https://api.sadadpay.net/api';
			
		$endpoint = '/User/GenerateRefreshToken';
		
		$headers = array(
				'Content-Type: application/json',
				'Authorization: Basic '. base64_encode($client_id . ":" . $client_secret)
			);
		$request = json_encode([]);	
			
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $gateway_url . $endpoint);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $request);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($curl);
		if(curl_exec($curl) !== false) {
			$response = json_decode( $response, true );
			if ( isset($response['response']['refreshToken']) ) {
				$refreshToken = $response['response']['refreshToken'];
			}
		}
		curl_close($curl);
		
		$this->write_log('refreshToken: ' . $refreshToken);
		return $refreshToken;
	}
	
	public function write_log($message){
		
		$message = PHP_EOL . date('d-m-Y H:i:s') . ': ' . $message;
		$fp = fopen(dirname(__FILE__) . '/debug.txt', 'a');
		fwrite($fp, $message);
		fclose($fp);
	}
}