<?php
/**
*  @author    Redontex SL 
*  @copyright 2026
*  @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*/

class Rdtx_PayULatamResponseModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function initContent()
    {

        $this->setTemplate('module:rdtx_payulatam/views/templates/front/response.tpl');

        parent::initContent();

        $apiKey = Configuration::get('REDONTEX_PAYU_LATAM_API_KEY');
        $merchant_id = Tools::getValue('merchantId');
        $referenceCode = Tools::getValue('referenceCode');
        $TX_VALUE = (float) Tools::getValue('TX_VALUE');
        $currency = Tools::getValue('currency');
        $transactionState = Tools::getValue('transactionState');

        // Apply rounding for signature validation -- payU documentation:
            //$new_value = round($TX_VALUE, 1, PHP_ROUND_HALF_EVEN);
		$new_value = $this->payu_round_half_even($TX_VALUE);

        $signature_string = $apiKey.'~'.
                            $merchant_id.'~'.
                            $referenceCode.'~'.
                            $new_value.'~'.
                            $currency.'~'.
                            $transactionState;
        $calculated_signature = md5($signature_string);
        $received_signature = Tools::getValue('signature');

        if (hash_equals(strtolower($received_signature), strtolower($calculated_signature))) {
            $order = new Order((int)$referenceCode);

            $transactionId = pSQL(Tools::getValue('transactionId'));
			if (Tools::getValue('authorizationCode')) {
		        $authorizationCode = pSQL(Tools::getValue('authorizationCode'));
			} else {
				$authorizationCode = $this->l('NOT AUTHORIZED');
			}

            $this->module->processPayUNotification((int)$referenceCode, $transactionState, $TX_VALUE, $transactionId, $authorizationCode);

            $this->context->smarty->assign([
                'status' => true,
            ]);
            Tools::redirect(
                'index.php?controller=order-confirmation'
                .'&id_cart='.$order->id_cart
                .'&id_module='.$this->module->id
                .'&id_order='.$order->id
                .'&key='.$order->secure_key
            );
        } else {
            $this->context->smarty->assign([
                'status' => false,
            ]);
            $this->errors[] = $this->module->l('Data integrity broken.');
            return $this->setTemplate('securityerror.tpl');
        }
    }
	
	function payu_round_half_even($value) {
		// Convertimos a string para analizar decimales
		$value = (float)$value;
		$str = number_format($value, 3, '.', ''); // 3 decimales para analizar

		// Separar parte entera y decimal
		list($int, $dec) = explode('.', $str);

		// Primer decimal
		$d1 = (int)$dec[0];
		// Segundo decimal
		$d2 = (int)$dec[1];

		// Caso especial PayU: si el segundo decimal es 5
		if ($d2 === 5) {
			if ($d1 % 2 === 0) {
				// Primer decimal par → redondea hacia abajo
				return number_format($int . '.' . $d1, 1, '.', '');
			} else {
				// Primer decimal impar → redondea hacia arriba
				return number_format($int . '.' . ($d1 + 1), 1, '.', '');
			}
		}

		// En cualquier otro caso → redondeo normal
		return number_format(round($value, 1), 1, '.', '');
	}
	
}
?>
