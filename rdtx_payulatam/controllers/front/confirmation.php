<?php
/**
*  @author    Redontex SL 
*  @copyright 2026
*  @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*/

class Rdtx_PayULatamConfirmationModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $display_header = false;
    public $display_footer = false;

    public function postProcess()
    {

		$bodyData = $_POST;		
		
		if (empty($bodyData)) {
			$rawBody = file_get_contents('php://input');
			$bodyData = [];
			parse_str($rawBody, $bodyData); // ✔ Esto sí funciona
		}

        // Debug data:
		/*	$data = [
				'datetime' => date('Y-m-d H:i:s'),
				'request'  => $_REQUEST,
				'post' => $_POST, 
				'raw' => $rawBody,
				'raw-json' => $bodyData,
			];

			file_put_contents(
                dirname(__FILE__) . '/raw_input.log',
                date('Y-m-d H:i:s') . "\n" . json_encode($data, JSON_PRETTY_PRINT) . "\n----------------------\n",
                FILE_APPEND
            );
			die();
		*/
		
        $id_order = $bodyData['reference_sale'];
        $merchantId = $bodyData['merchant_id'];
        $value = $bodyData['value'];
        $currency = $bodyData['currency'];
        $statePol = $bodyData['state_pol'];
        $receivedSign = $bodyData['sign'];

        $apiKey     = Configuration::get('REDONTEX_PAYU_LATAM_API_KEY');

        // Pay U Documentation: --------------------------------------------------------
        // 4. Format value for signature (NO floats)
        //    Rule:
        //    - If second decimal is 0 → 1 decimal (150.00 → 150.0)
        //    - Otherwise → 2 decimals (150.25 → 150.25)
        // -----------------------------------------------------------------------------
        $parts = explode('.', $value);
        if (!isset($parts[1])) {
            $formattedValue = $parts[0] . '.0';
        } elseif (strlen($parts[1]) > 1 && $parts[1][1] !== '0') {
            $formattedValue = $parts[0] . '.' . substr($parts[1], 0, 2);
        } else {
            $formattedValue = $parts[0] . '.' . $parts[1][0];
        }
        // Pay U Documentation End -----------------------------------------------------

        $signatureString = $apiKey.'~'.
                            $merchantId.'~'.
                            $id_order.'~'.
                            $formattedValue.'~'.
                            $currency.'~'.
                            $statePol;
        $calculatedSignature = md5($signatureString);

        if (!hash_equals(strtolower($receivedSign), strtolower($calculatedSignature))) {
            http_response_code(403);
            echo 'Invalid signature';
            exit;               
        }

        $transactionId = pSQL($bodyData['transaction_id']);
		if (isset($bodyData['authorization_code'])) {
	        $authorizationCode = pSQL($bodyData['authorization_code']);
		} else {
			$authorizationCode = $this->l('NOT AUTHORIZED');
		}

        $this->module->processPayUNotification($id_order, $statePol, $value, $transactionId, $authorizationCode);

        http_response_code(200);
        echo 'OK';
        exit;               
    }
}
