<?php
/**
*  @author    Redontex SL 
*  @copyright 2026
*  @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*/

class Rdtx_PayULatamValidationModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $cart = $this->context->cart;

        if (!$cart->id || $cart->nbProducts() == 0) {
            Tools::redirect('index.php?controller=order');
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $address = new Address($cart->id_address_delivery);
        if (!Validate::isLoadedObject($address)) {
            Tools::redirect('index.php?controller=order&step=1');
        }
        $identification = $address->vat_number;
        if ($identification) {
            $idType = 'CC';
            $idNumber = $identification;
        } else {
            $idType = 'NIT';
            $idNumber = '222222222222';            
        }
        unset($identification);
        $phoneNumber = trim($address->phone_mobile ?: $address->phone);
        if (!$phoneNumber) {
            $phoneNumber = '3330000000';
        }

        $currency = $this->context->currency;
        $amountTotal = (float)$cart->getOrderTotal(true, Cart::BOTH) + $cart->getTotalShippingCost();
        $amountTotal = round($amountTotal, 2);
		$amountOrder = $cart->getOrderTotal(true, Cart::BOTH);
		
		// Debug data:
		/*	$data = [
				'datetime' => date('Y-m-d H:i:s'),
				'amountPayU' => $amountTotal,
				'amountOrder' => $amountOrder,
				'datosOrderValidate'  => $cart->id . ', ' . Configuration::get('REDONTEX_PAYU_OS_PENDING') . ', ' .
										$amountOrder . ', ' . $this->module->displayName . ', null, [], ' . $currency->id . ', false, ' .
										$customer->secure_key
			];

			file_put_contents(
                dirname(__FILE__) . '/form_data.log',
                date('Y-m-d H:i:s') . "\n" . json_encode($data, JSON_PRETTY_PRINT) . "\n----------------------\n",
                FILE_APPEND
            );
		*/
		

        $this->module->validateOrder(
            (int) $cart->id,
            Configuration::get('REDONTEX_PAYU_OS_PENDING'),
            $amountOrder,
            $this->module->displayName,
            null,
            [],
            (int) $currency->id,
            false,
            $customer->secure_key
        );	
		
        $order = new Order($this->module->currentOrder);	

        $amountTotal = (float)$order->getOrdersTotalPaid();
        $amountTotal = round($amountTotal, 2);
		$amountOrder = $cart->getOrderTotal(true, Cart::BOTH);

		$currencyCode = Tools::strtoupper($currency->iso_code);
        $tax = $cart->getOrderTotal() - $cart->getOrderTotal(false);
        $tax = round($tax, 2);
        $taxReturnBase = $tax > 0 ? $amountTotal - $tax : 0;
        $taxReturnBase = round($taxReturnBase, 2);
        $signatureString = Configuration::get('REDONTEX_PAYU_LATAM_API_KEY').'~'.
                            Configuration::get('REDONTEX_PAYU_LATAM_MERCHANT_ID').'~'.
                            $order->id.'~'.
                            $amountTotal.'~'.
                            $currencyCode;
        $signature = md5($signatureString);
        $test = (int)Configuration::get('REDONTEX_PAYU_LATAM_TEST');
		
        $formFields = [
            ['name' => 'merchantId', 'value' => (int)Configuration::get('REDONTEX_PAYU_LATAM_MERCHANT_ID')],
		    ['name' => 'accountId', 'value' => (int)Configuration::get('REDONTEX_PAYU_LATAM_ACCOUNT_ID')],
            ['name' => 'referenceCode', 'value' => (string)$order->id],
            ['name' => 'description', 'value' => 'Order #' . (string)$order->id . ". Ref: " . (string)$order->reference],
            ['name' => 'amount', 'value' => (float)$amountTotal],
            ['name' => 'tax', 'value' => (float)$tax],
            ['name' => 'taxReturnBase', 'value' => (float)$taxReturnBase],
            ['name' => 'signature', 'value' => $signature],
            ['name' => 'currency', 'value' => $currencyCode],
            ['name' => 'buyerEmail', 'value' => $this->context->customer->email],
            ['name' => 'payerFullName', 'value' => $this->context->customer->firstname.' '.$this->context->customer->lastname],
            ['name' => 'payerEmail', 'value' => $this->context->customer->email],
            ['name' => 'payerPhone', 'value' => $phoneNumber],
            ['name' => 'payerDocumentType', 'value' => $idType], /*(NIT si no trae nada, CC si sí trae algo)*/
            ['name' => 'payerDocument', 'value' => $idNumber], /*(222222222222 si no trae nada, lo que pongan si sí trae)*/
            ['name' => 'buyerFullName', 'value' => $this->context->customer->firstname.' '.$this->context->customer->lastname],
            ['name' => 'buyerDocumentType', 'value' => $idType], /*(NIT si no trae nada, CC si sí trae algo)*/
            ['name' => 'buyerDocument', 'value' => $idNumber], /*(222222222222 si no trae nada, lo que pongan si sí trae)*/
            ['name' => 'telephone', 'value' => $phoneNumber],
            ['name' => 'algorithmSignature', 'value' => 'MD5'], /*(MD5)*/
            ['name' => 'responseUrl', 'value' => $this->context->link->getModuleLink($this->module->name, 'response', [], true)],
            ['name' => 'confirmationUrl', 'value' => $this->context->link->getModuleLink($this->module->name, 'confirmation', [], true)],
            ['name' => 'test', 'value' => $test],
        ];

        // Debug data:
		/*	$data = [
				'datetime' => date('Y-m-d H:i:s'),
				'formdata'  => $formFields,
			];

			file_put_contents(
                dirname(__FILE__) . '/form_data.log',
                date('Y-m-d H:i:s') . "\n" . json_encode($data, JSON_PRETTY_PRINT) . "\n----------------------\n",
                FILE_APPEND
            );
		*/
		
        $gateway = $test 
            ? 'https://sandbox.checkout.payulatam.com/ppp-web-gateway-payu/'
            : 'https://checkout.payulatam.com/ppp-web-gateway-payu/';
        $this->context->smarty->assign([
            'gateway_url' => $gateway,
            'form_fields' => $formFields,
        ]);

        $this->setTemplate('module:rdtx_payulatam/views/templates/front/redirect.tpl');
    }
}
