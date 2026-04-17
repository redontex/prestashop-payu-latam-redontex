<?php
/**
*  @author    Redontex SL 
*  @copyright 2026
*  @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*/

if (!defined('_PS_VERSION_')) {
    function exitPs()
    {
        exit;
    }
}

class Rdtx_PayULatam extends PaymentModule
{

    private $postErrors = array();

    public function __construct()
    {
        $this->name = 'rdtx_payulatam';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Redontex SL';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->currencies = true;
        $this->controllers = [
            'confirmation',
            'validation',
            'response',
        ];

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('PayU Latam by Redontex');
        $this->description = $this->l('Payment gateway for PayU Latam, webcheckout connection');

        $this->confirmUninstall = $this->l('Uninstall?');
    }
    
    public function install()
    {
        if (!parent::install()) {
            return false;            
        }

        if (!$this->registerHook('payment')) {
            return false;
        }
        if (!$this->registerHook('displayPaymentReturn')) {
            return false;            
        }
        if (!$this->registerHook('paymentOptions')) {
            return false;
        }
        if (!$this->registerHook('displayBackOfficeHeader')) {
            return false;
        }

        $this->createStates();

        return true;
    }  

    public function uninstall()
    {

        if(!parent::uninstall()) {
            return false;            
        }

        if(!Configuration::deleteByName('REDONTEX_PAYU_LATAM_MERCHANT_ID')) {
            return false;            
        }
        if(!Configuration::deleteByName('REDONTEX_PAYU_LATAM_ACCOUNT_ID')) {
            return false;            
        }
        if(!Configuration::deleteByName('REDONTEX_PAYU_LATAM_API_KEY')) {
            return false;            
        }
        if(!Configuration::deleteByName('REDONTEX_PAYU_LATAM_TEST')) {
            return false;            
        }
        if (!$this->unistallStates()) {
            return false;            
        }

        return true;
    }

    public function getContent()
    {
        $output = '';

        if (!empty(Tools::getValue('submitValues'))) {
            $this->postValidation();
            if ($this->errors) {
                foreach($this->errors as $error) {
                    $output .= $this->displayError($error);
                }
            } else {
                $this->saveConfiguration();
                if ($this->confirmations) {
                    foreach($this->confirmations as $message) {
                        $output .= $this->displayConfirmation($message);
                    }
                }
            }
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output .= $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');   

        return $output.$this->renderForm();

    }

    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        //$helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitValues';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), 
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    protected function getConfigForm()
    {
        
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'col' => 5,
                        'type' => 'text',
                        'name' => 'REDONTEX_PAYU_LATAM_MERCHANT_ID',
                        'label' => $this->l('Merchant ID:'),
                    ),
                    array(
                        'col' => 8,
                        'type' => 'text',
                        'name' => 'REDONTEX_PAYU_LATAM_ACCOUNT_ID',
                        'label' => $this->l('Account ID:'),
                    ),                    
                    array(
                        'col' => 5,
                        'type' => 'text',
                        'name' => 'REDONTEX_PAYU_LATAM_API_KEY',
                        'label' => $this->l('Api Key:'),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Test Mode'),
                        'name' => 'REDONTEX_PAYU_LATAM_TEST',
                        'is_bool' => true,
                        'desc' => $this->l('Turn on to operate in test mode.'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Test Mode')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Production Mode')
                            )
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    protected function getConfigFormValues()
    {
        return array(
            'REDONTEX_PAYU_LATAM_MERCHANT_ID' => Configuration::get('REDONTEX_PAYU_LATAM_MERCHANT_ID', ''),
            'REDONTEX_PAYU_LATAM_ACCOUNT_ID' => Configuration::get('REDONTEX_PAYU_LATAM_ACCOUNT_ID', ''),
            'REDONTEX_PAYU_LATAM_API_KEY' => Configuration::get('REDONTEX_PAYU_LATAM_API_KEY', ''),
            'REDONTEX_PAYU_LATAM_TEST' =>(bool)Configuration::get('REDONTEX_PAYU_LATAM_TEST', true),
        );
    }

    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('configure') === $this->name) {
            $this->context->controller->addCSS($this->_path.'views/css/admin.css');
        }
    }

    public function hookPayment($params)
    {
        if (!$this->active) {
            return;
        }
        return $this->display(__FILE__, 'views/templates/hook/payulatam_payment.tpl');
    }

    public function hookDisplayPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }
        $order = $params['order'];
        $this->context->smarty->assign([
            'order_reference' => $order->reference,
            'order_status'    => $order->getCurrentState(),
            'status_approved' => Configuration::get('REDONTEX_PAYU_OS_ACCEPTED'),
            'status_pending'  => Configuration::get('REDONTEX_PAYU_OS_PENDING'),
            'status_rejected'   => Configuration::get('REDONTEX_PAYU_OS_REJECTED'),
            'status_failed'   => Configuration::get('REDONTEX_PAYU_OS_FAILED'),
        ]);
        return $this->display(__FILE__, 'views/templates/hook/payment_return.tpl');
    }


	public function hookPaymentOptions($params)
	{
		if (!$this->active) {
			return;
		}
		$newOption = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
		$newOption->setCallToActionText($this->l('Pay with PayU Latam'))
				  ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/logo.png'))
				  ->setAction(
                        $this->context->link->getModuleLink(
                            $this->name, 
                            'validation', 
                            ['option' => 'external'], 
                            true
                        )
                    );

        $payment_options = [
            $newOption,
        ];
		return $payment_options;
	}	


    private function postValidation()
    {
        if (!Validate::isCleanHtml(Tools::getValue('REDONTEX_PAYU_LATAM_MERCHANT_ID')) ||
                !Validate::isGenericName(Tools::getValue('REDONTEX_PAYU_LATAM_MERCHANT_ID'))) {
            $this->errors[] = $this->l('You must indicate the merchant id');
        }

        if (!Validate::isCleanHtml(Tools::getValue('REDONTEX_PAYU_LATAM_ACCOUNT_ID')) ||
                !Validate::isGenericName(Tools::getValue('REDONTEX_PAYU_LATAM_ACCOUNT_ID'))) {
            $this->errors[] = $this->l('You must indicate the account id');
        }

        if (!Validate::isCleanHtml(Tools::getValue('REDONTEX_PAYU_LATAM_API_KEY')) ||
                !Validate::isGenericName(Tools::getValue('REDONTEX_PAYU_LATAM_API_KEY'))) {
            $this->errors[] = $this->l('You must indicate the API key');
        }

        if (!Validate::isCleanHtml(Tools::getValue('REDONTEX_PAYU_LATAM_TEST')) ||
                !Validate::isGenericName(Tools::getValue('REDONTEX_PAYU_LATAM_TEST'))) {
            $this->errors[] = $this->l('You must indicate if payments are in test mode or in real mode');
        }

    }

    private function saveConfiguration()
    {
        Configuration::updateValue('REDONTEX_PAYU_LATAM_MERCHANT_ID', (string)Tools::getValue('REDONTEX_PAYU_LATAM_MERCHANT_ID'));
        Configuration::updateValue('REDONTEX_PAYU_LATAM_ACCOUNT_ID', (string)Tools::getValue('REDONTEX_PAYU_LATAM_ACCOUNT_ID'));
        Configuration::updateValue('REDONTEX_PAYU_LATAM_API_KEY', (string)Tools::getValue('REDONTEX_PAYU_LATAM_API_KEY'));
		Configuration::updateValue('REDONTEX_PAYU_LATAM_TEST', (bool)Tools::getValue('REDONTEX_PAYU_LATAM_TEST'));
        $this->confirmations[] = $this->l('Settings saved');
    }
	
    public function processPayUNotification($id_order, $statePol, $value, $transactionId, $authorizationCode)
    {
        $order = new Order((int)$id_order);

        if (!Validate::isLoadedObject($order)) {
            return false;
        }

        if ($statePol == 4) {
            $newState = Configuration::get('REDONTEX_PAYU_OS_ACCEPTED');
        } elseif ($statePol == 6) {
            $newState = Configuration::get('REDONTEX_PAYU_OS_REJECTED');
        } elseif ($statePol == 7) {
            $newState = Configuration::get('REDONTEX_PAYU_OS_PENDING');
        } else {
            $newState = Configuration::get('REDONTEX_PAYU_OS_FAILED');
        }

        $acceptedState = Configuration::get('REDONTEX_PAYU_OS_ACCEPTED');

        if ($order->getCurrentState() == $acceptedState) {
            return true;
        }

        if ($order->getCurrentState() != $newState) {
            $order->setCurrentState($newState);
        }

        $payment = new OrderPayment();
        $payment->order_reference = $order->reference;
        $payment->id_currency = (int)$order->id_currency;
        $payment->amount = (float)$value;
        $payment->payment_method = $this->name; // nombre del módulo
        $payment->transaction_id = "ID de Transacción: " . pSQL($transactionId) .
                                    " . Código de Autorización: " . pSQL($authorizationCode);
        $payment->date_add = date('Y-m-d H:i:s');
        $payment->add();

        return true;
    }	
	
    private function createStates()
    {
        
        if (!Configuration::get('REDONTEX_PAYU_OS_ACCEPTED')) {
            $order_state = new OrderState();
            $order_state->name = array();
            $langData = [];
            foreach (Language::getLanguages() as $language) {
                $langData[$language['id_lang']] = 'PayU Latam - Accepted';
            }
            $order_state->name = $langData;           
            $order_state->send_email = false;
            $order_state->color = '#28A745';
            $order_state->hidden = false;
            $order_state->delivery = false;
            $order_state->logable = false;
            $order_state->invoice = false;
            $order_state->module_name = $this->name;
            $order_state->paid = true;
            $order_state->shipped = false;
            $order_state->pdf_delivery = false;
            $order_state->pdf_invoice = false;
            $order_state->unremovable = false;
            $order_state->template = '';
            $order_state->deleted = false;            
            
            if ($order_state->add()) {
                $source = dirname(__FILE__) . '/views/img/logoPayU.png';
                $destination = _PS_ROOT_DIR_ . '/img/os/' . (int)$order_state->id . '.gif';
                copy($source, $destination);
            } else {
                $this->_errors[] = sprintf(
                    'Failed to copy icon of OrderState %s',
                    'REDONTEX_PAYU_OS_ACCEPTED'
                );

                return false;
            }                
            Configuration::updateValue('REDONTEX_PAYU_OS_ACCEPTED', (int)$order_state->id);
        }


        if (!Configuration::get('REDONTEX_PAYU_OS_PENDING')) {
            $order_state = new OrderState();
            $order_state->name = array();
            $langData = [];
            foreach (Language::getLanguages() as $language) {
                $langData[$language['id_lang']] = 'PayU Latam - Pending';
            }
            $order_state->name = $langData;           
            $order_state->send_email = false;
            $order_state->color = '#FEFF64';
            $order_state->hidden = false;
            $order_state->delivery = false;
            $order_state->logable = false;
            $order_state->invoice = false;
            $order_state->module_name = $this->name;
            $order_state->paid = false;
            $order_state->shipped = false;
            $order_state->pdf_delivery = false;
            $order_state->pdf_invoice = false;
            $order_state->unremovable = false;
            $order_state->template = '';
            $order_state->deleted = false;            
            

            if ($order_state->add()) {
                $source = dirname(__FILE__) . '/views/img/logoPayU.png';
                $destination = _PS_ROOT_DIR_ . '/img/os/' . (int)$order_state->id . '.gif';
                copy($source, $destination);
            }  else {
                $this->_errors[] = sprintf(
                    'Failed to copy icon of OrderState %s',
                    'REDONTEX_PAYU_OS_PENDING'
                );

                return false;
            }
            Configuration::updateValue('REDONTEX_PAYU_OS_PENDING', (int)$order_state->id);
        }

        if (!Configuration::get('REDONTEX_PAYU_OS_FAILED')) {
            $order_state = new OrderState();
            $order_state->name = array();
            $langData = [];
            foreach (Language::getLanguages() as $language) {
                $langData[$language['id_lang']] = 'PayU Latam - Failed';
            }
            $order_state->name = $langData;           
            $order_state->send_email = false;
            $order_state->color = '#8F0621';
            $order_state->hidden = false;
            $order_state->delivery = false;
            $order_state->logable = false;
            $order_state->invoice = false;
            $order_state->module_name = $this->name;
            $order_state->paid = false;
            $order_state->shipped = false;
            $order_state->pdf_delivery = false;
            $order_state->pdf_invoice = false;
            $order_state->unremovable = false;
            $order_state->template = '';
            $order_state->deleted = false;            


            if ($order_state->add()) {
                $source = dirname(__FILE__) . '/views/img/logoPayU.png';
                $destination = _PS_ROOT_DIR_ . '/img/os/' . (int)$order_state->id . '.gif';
                copy($source, $destination);
            } else {
                $this->_errors[] = sprintf(
                    'Failed to copy icon of OrderState %s',
                    'REDONTEX_PAYU_OS_FAILED'
                );

                return false;
            }
            Configuration::updateValue('REDONTEX_PAYU_OS_FAILED', (int)$order_state->id);
        }

        if (!Configuration::get('REDONTEX_PAYU_OS_REJECTED')) {
            $order_state = new OrderState();
            $order_state->name = array();
            foreach (Language::getLanguages() as $language) {
                $order_state->name[$language['id_lang']] = 'PayU Latam - Rejected';
            }

            $order_state->send_email = false;
            $order_state->color = '#C23416';
            $order_state->hidden = false;
            $order_state->delivery = false;
            $order_state->logable = false;
            $order_state->invoice = false;

            if ($order_state->add()) {
                $source = dirname(__FILE__) . '/views/img/logoPayU.png';
                $destination = _PS_ROOT_DIR_ . '/img/os/' . (int)$order_state->id . '.gif';
                copy($source, $destination);
            } else {
                $this->_errors[] = sprintf(
                    'Failed to copy icon of OrderState %s',
                    'REDONTEX_PAYU_OS_REJECTED'
                );

                return false;
            }
            Configuration::updateValue('REDONTEX_PAYU_OS_REJECTED', (int)$order_state->id);
        }
        return true;
    }

    private function unistallStates() 
    {
        $states = [
            'REDONTEX_PAYU_OS_PENDING',
            'REDONTEX_PAYU_OS_ACCEPTED',
            'REDONTEX_PAYU_OS_REJECTED',
            'REDONTEX_PAYU_OS_FAILED',
        ];

        foreach ($states as $state_key) {
            $id_state = (int) Configuration::get($state_key);

            if ($id_state) {
                $order_state = new OrderState($id_state);

                if (Validate::isLoadedObject($order_state)) {
                    $order_state->delete();
                }

                Configuration::deleteByName($state_key);
            }
        }

        return true;
    }

}
