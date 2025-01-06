<?php
/**
* 2007-2024 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2024 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

require_once( __DIR__ .'/ikosoftInterface.php');
require_once( __DIR__ .'/prestaShopInterface.php');
//require_once dirname(__FILE__) . '/syncHandler.php';

if (!defined('_PS_VERSION_')) {
    exit;
}


class Ikostock extends Module
{
    protected $config_form = false;

	public $logger;
	public $lang;
	private $prestaShopInterface;
	private $ikosoftInterface;
	private $masterStock;

    public function __construct()
    {
        $this->name = 'ikostock';
        $this->tab = 'quick_bulk_update';
        $this->version = '0.1.0';
        $this->author = 'Ikosoft';
        $this->need_instance = 0;
		
		$this->lang = (int)Context::getContext()->language->id;
		$this->logger = new FileLogger(0); 
		$this->logger->setFilename(_PS_ROOT_DIR_.'/var/logs/Ikostock_debug.log');
		$this->masterStock = Configuration::get('IKOSTOCK_MASTER_STOCK_SETTING');

		$this->prestaShopInterface = new PrestaShopInterface();
		$this->ikosoftInterface = new IkosoftInterface();
		
		$this->tab_class = 'AdminYourCustomController';

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Ikostock');
        $this->description = $this->l('Ikosoft stock module');

        $this->confirmUninstall = $this->l('Are you sure you want to remove Ikostock?');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => '9.0');
		
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
	 
    public function install()
    {
        Configuration::updateValue('IKOSTOCK_LIVE_MODE', false);

        include(dirname(__FILE__).'/sql/install.php');
		
        return parent::install() &&
			$this->installTab() &&  //experimental 1
            $this->registerHook('header') &&
            $this->registerHook('displayBackOfficeHeader') &&
            $this->registerHook('actionObjectCustomerUpdateAfter') &&
			$this->registerHook('actionValidateOrder') &&
			$this->registerHook('displayDashboardToolbarTopMenu');
    }

    public function uninstall()
    {
        Configuration::deleteByName('IKOSTOCK_LIVE_MODE');

        include(dirname(__FILE__).'/sql/uninstall.php');

        return parent::uninstall() && $this->uninstallTab();
    }

	//EXPERIMENTAL 1++
    
	private function installTab()
    {
        $tab = new Tab();
        $tab->class_name = 'AdminYourCustomController';
        $tab->module = $this->name;
        $tab->id_parent = (int) Tab::getIdFromClassName('AdminCatalog'); // Default tab, or use AdminCatalog
        $tab->active = 1;

        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'Custom Controller';
        }

        return $tab->add();
    }

    private function uninstallTab()
    {
        $id_tab = (int) Tab::getIdFromClassName('AdminYourCustomController');
        if ($id_tab) {
            $tab = new Tab($id_tab);
            return $tab->delete();
        }
        return true;
    }

	//EXPERIMENTAL 1--  
	

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitIkostockModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output.$this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitIkostockModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
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
                        'type' => 'switch',
                        'label' => $this->l('Live mode'),
                        'name' => 'IKOSTOCK_LIVE_MODE',
                        'is_bool' => true,
                        'desc' => $this->l('Use this module in live mode'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'desc' => $this->l('Database number from Ikosoft'),
                        'name' => 'IKOSTOCK_DATABASE_NUMBER',
                        'label' => $this->l('DB Number'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'desc' => $this->l('API key from Ikosoft'),
                        'name' => 'IKOSTOCK_API_KEY',
                        'label' => $this->l('API key'),
                    ),
					
					array(
                        'col' => 2,
                        'type' => 'text',
                        'desc' => $this->l('Server suffix \(empty by default\)'),
                        'name' => 'IKOSTOCK_SUFFIX',
                        'label' => $this->l('Server suffix'),
                    ),
					array(
                        'col' => 2,
                        'type' => 'text',
                        'desc' => $this->l('Stock movement prefix'),
                        'name' => 'IKOSTOCK_MOVEMENT_PREFIX',
                        'label' => $this->l('Prefix'),
                    ),

					/* Added configuration++ */
					array(
                        'type' => 'switch',
                        'label' => $this->l('Master stock'),
                        'name' => 'IKOSTOCK_MASTER_STOCK_SETTING',
                        'is_bool' => true,
                        'desc' => $this->l('Select the stock master DB'),
                        'values' => array(
                            array(
                                'id' => 'ikosoft_master',
                                'value' => 'Ikosoft',
                                'label' => $this->l('Ikosoft')
                            ),
                            array(
                                'id' => 'prestashop_master',
                                'value' => 'PrestaShop',
                                'label' => $this->l('Prestashop')
                            )
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Schedule sync'),
                        'name' => 'IKOSTOCK_SCHEDULE_SYNC_SETTING',
                        'is_bool' => true,
                        'desc' => $this->l('Enable synchronisation'),
                        'values' => array(
                            array(
                                'id' => 'Sync on',
                                'value' => true,
                                'label' => $this->l('On')
                            ),
                            array(
                                'id' => 'Sync off',
                                'value' => false,
                                'label' => $this->l('Off')
                            )
                        ),
                    ),
                    array(
                        'col' => 2,
                        'type' => 'text',
                        'desc' => $this->l('In minutes between 10 and 1440'),
                        'name' => 'IKOSTOCK_SYNC_FREQUENCY',
                        'label' => $this->l('Sync frequency'),
                    ),
					
					/* Added configuration-- */

					
                ),
				

                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'IKOSTOCK_LIVE_MODE' => Configuration::get('IKOSTOCK_LIVE_MODE', true),
            'IKOSTOCK_ACCOUNT_EMAIL' => Configuration::get('IKOSTOCK_ACCOUNT_EMAIL', 'contact@prestashop.com'),
            'IKOSTOCK_ACCOUNT_PASSWORD' => Configuration::get('IKOSTOCK_ACCOUNT_PASSWORD', null),
			'IKOSTOCK_MASTER_STOCK_SETTING' => Configuration::get('IKOSTOCK_MASTER_STOCK_SETTING', null),
			'IKOSTOCK_SCHEDULE_SYNC_SETTING' => Configuration::get('IKOSTOCK_SCHEDULE_SYNC_SETTING', true),
			'IKOSTOCK_DATABASE_NUMBER' => Configuration::get('IKOSTOCK_DATABASE_NUMBER', null),
			'IKOSTOCK_API_KEY' => Configuration::get('IKOSTOCK_API_KEY', null),
			'IKOSTOCK_SUFFIX' => Configuration::get('IKOSTOCK_SUFFIX', ''),
			'IKOSTOCK_MOVEMENT_PREFIX' => Configuration::get('IKOSTOCK_MOVEMENT_PREFIX', 'PRESTASHOP'),
			'IKOSTOCK_SYNC_FREQUENCY' => Configuration::get('IKOSTOCK_SYNC_FREQUENCY', 0),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }

    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('configure') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
		
		//$this->context->controller->addCSS('https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css');
		$this->context->controller->addJS('https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js');
		
    }


    /**
    * Processing functions
    */

    public static function jsonEncode($data, $options = 0, $depth = 512)
    {
        return json_encode($data, $options, $depth);
    }


	public function getIkosoftProducts()
	{
		$items = $this->ikosoftInterface->getProducts();
		
		if ($items == null) return null;
		
		$ikosoft_products = array();

		foreach ($items as $item)  
		{
			$product = new Product();
			$product->ean13 = $item[0];
			$product->quantity = $item[1];
			$product->price = $item[2];
			$product->wholesale_price = $item[3];
			$product->date_upd = $item[4];
			$product->name = $item[5];
			$product->id_manufacturer = Manufacturer::getIdByName($item[6]);
			
			$rate = $this->prestaShopInterface->getPrestaShopTaxId($item[7]);
			$product->id_tax_rules_group = ($rate != null) ? $rate : 1;
			
			$cat = $this->prestaShopInterface->getPrestaShopCategoryIdFromName($item[8]);
			$product->id_category_default = ($cat != null) ? $cat : 2;
			
			$ikosoft_products[]=$product;
		}

		if (count($ikosoft_products) == 0) return null;
		else return $ikosoft_products;
		
	}

	public function addIkosoftProducts($products)
	{
		$this->logger->logDebug('[IKOSOFT] Adding '.count($products).' product(s).');	
		
		$ikosoft_products = array();
		
		foreach ($products as $prod)
		{
			$ikosoftProduct = new IkosoftProduct();

			if(empty($prod->ean13))
			{
				$this->logger->logDebug('[IKOSOFT] No barcode for: '.$prod->name.'. Ignored.');
				continue;
			}
			if(empty($prod->id_category_default))
			{
				$this->logger->logDebug('[IKOSOFT] No category for: '.$prod->name.'. Ignored.');
				continue;
			}
			if(empty($prod->manufacturer_name))
			{
				$this->logger->logDebug('[IKOSOFT] No supplier for: '.$prod->name.'. Ignored.');
				continue;
			}
			if(empty($prod->id_tax_rules_group))
			{
				$this->logger->logDebug('[IKOSOFT] No tax rate for: '.$prod->name.'. Ignored.');
				continue;
			}
			
			$ikosoftProduct->name = $prod->name;
			$ikosoftProduct->barcode = $prod->ean13;
			$ikosoftProduct->quantity = $prod->quantity; //this will get root quantity not available quantity
			$ikosoftProduct->wholesale = (int) ($prod->wholesale_price*100);
			$ikosoftProduct->retail = (int) ($prod->price*100);
			
			$ikosoftProduct->category = 
			   str_replace("'","''",$this->prestaShopInterface->getPrestaShopCategoryNameFromId($prod->id_category_default));
			
			$ikosoftProduct->supplier = $prod->manufacturer_name;
			$ikosoftProduct->tax_rate = (int) ($this->prestaShopInterface->getPrestaShopTaxRate($prod->id_tax_rules_group) *100);
					
			$ikosoft_products[]=$ikosoftProduct;
		}
		
		return $this->ikosoftInterface->addProducts($ikosoft_products);
	}


	public function getIkosoftCategoriesNotInPrestaShop()
	{
		$ikosoftCats = $this->ikosoftInterface->getUsedIkosoftProductCategories();
		$prestaShopCats = $this->prestaShopInterface->getAllPrestaShopProductCategories();	
		
     	$delta = array_diff($ikosoftCats, $prestaShopCats);
		
		return $delta;
		
	}
	
	public function getPrestaShopCategoriesNotInIkosoft()
	{
		$ikosoftCats = $this->ikosoftInterface->getAllIkosoftProductCategories();
		$prestaShopCats = $this->prestaShopInterface->getAllPrestaShopProductCategories();	
		
		$delta = array_diff($prestaShopCats, $ikosoftCats);
		
		return $delta;
		
	}
	
	public function getIkosoftSuppliersNotInPrestaShop()
	{
		$ikosoftSuppliers = $this->ikosoftInterface->getUsedIkosoftSuppliers();
		$prestaShopSuppliers = $this->prestaShopInterface->getAllPrestaShopSuppliers();	
		
     	$delta = array_diff($ikosoftSuppliers, $prestaShopSuppliers);
		
		return $delta;
		
	}
	
	public function getPrestaShopSuppliersNotInIkosoft()
	{
		$ikosoftSuppliers = $this->ikosoftInterface->getAllIkosoftSuppliers();
		$prestaShopSuppliers = $this->prestaShopInterface->getUsedPrestaShopSuppliers();	
		
		$delta = array_diff($prestaShopSuppliers, $ikosoftSuppliers);
		
		return $delta;
		
	}
	
	
	public function getIkosoftProductsNotInPrestaShop()
	{
		$prestaShopProducts = $this->prestaShopInterface->getPrestaShopProducts();
		$ikosoftProducts = $this->getIkosoftProducts();	

		$delta = array_udiff($ikosoftProducts, $prestaShopProducts,
			  function ($obj_a, $obj_b) {
				return $obj_a->ean13 <=> $obj_b->ean13;
			  }
		);
		return $delta;
		
	}
	
	public function getPrestaShopProductsNotInIkosoft()
	{
		$prestaShopProducts = $this->prestaShopInterface->getPrestaShopProducts();
		$ikosoftProducts = $this->getIkosoftProducts();	

		$delta = array_udiff($prestaShopProducts, $ikosoftProducts,
			  function ($obj_a, $obj_b) {
				return $obj_a->ean13 <=> $obj_b->ean13;
			  }
		);
		
		return $delta;
		
	}
	

	 /**
    * Synchronising functions
    */

	public function synchroniseCategoriesBothWays()
	{
		$this->synchroniseCategoriesToIkosoft();
		$this->synchroniseCategoriesToPrestaShop();
	}

	public function synchroniseCategoriesToIkosoft()
	{
		$this->logger->logDebug('SYNCHRONISING CATEGORIES TO IKOSOFT');
		
		$ikosoftCatsToAdd = $this->getPrestaShopCategoriesNotInIkosoft();
		if (count($ikosoftCatsToAdd) > 0)
			$this->ikosoftInterface->addProductCategoriesToIkosoft($ikosoftCatsToAdd);
		else
			$this->logger->logDebug('[IKOSOFT] Categories are up to date.');

	}
	
	public function synchroniseCategoriesToPrestaShop()
	{
		$this->logger->logDebug('SYNCHRONISING CATEGORIES TO PRESTASHOP');

		$prestaShopCatsToAdd = $this->getIkosoftCategoriesNotInPrestaShop();
		if (count($prestaShopCatsToAdd) > 0)
			$this->prestaShopInterface->addProductCategoriesToPrestaShop($prestaShopCatsToAdd);
		else
			$this->logger->logDebug('[PRESTASHOP] Categories are up to date.');

	}
	
	
	public function synchroniseSuppliersBothWays()
	{		
		$this->synchroniseSuppliersToIkosoft();
		$this->synchroniseSuppliersToPrestaShop();
	}
	
	public function synchroniseSuppliersToIkosoft()
	{		
		$this->logger->logDebug('SYNCHRONISING SUPPLIERS TO IKOSOFT');
	
		$ikosoftSuppliersToAdd = $this->getPrestaShopSuppliersNotInIkosoft();
		if (count($ikosoftSuppliersToAdd) > 0)
			$this->ikosoftInterface->addSuppliersToIkosoft($ikosoftSuppliersToAdd); 
		else
			$this->logger->logDebug('[IKOSOFT] Suppliers are up to date.');

	}

	public function synchroniseSuppliersToPrestaShop()
	{		
		$this->logger->logDebug('SYNCHRONISING SUPPLIERS TO PRESTASHOP');

		$prestaShopSuppliersToAdd = $this->getIkosoftSuppliersNotInPrestaShop();
		if (count($prestaShopSuppliersToAdd) > 0)
			$this->prestaShopInterface->addSuppliersToPrestaShop($prestaShopSuppliersToAdd);
		else
			$this->logger->logDebug('[PRESTASHOP] Suppliers are up to date.');

	}	
	
	public function synchroniseProductsBothWays()
	{
		$this->synchroniseProductsToIkosoft();
		$this->synchroniseProductsToPrestaShop();
	}
	
	
	public function synchroniseProductsToIkosoft()
	{
		$this->logger->logDebug('SYNCHRONISING PRODUCTS TO IKOSOFT');

		$ikosoftProdsToAdd = $this->getPrestaShopProductsNotInIkosoft();
		$this->logger->logDebug('[IKOSOFT] There are '.count($ikosoftProdsToAdd).' products(s)to add.');
		if (count($ikosoftProdsToAdd) > 0)
			$this->addIkosoftProducts($ikosoftProdsToAdd);

	}
	
	public function synchroniseProductsToPrestaShop()
	{
		$this->logger->logDebug('SYNCHRONISING PRODUCTS TO PRESTASHOP');
	
		$prestaShopProdsToAdd = $this->getIkosoftProductsNotInPrestaShop();
		$this->logger->logDebug('[PRESTASHOP] There are '.count($prestaShopProdsToAdd).' products(s)to add.');
		if (count($prestaShopProdsToAdd) > 0)
			$this->prestaShopInterface->addProductsToPrestashop($prestaShopProdsToAdd);
		
	}
	
	public function synchroniseProductQuantitiesFromIkosoft()
	{
		$this->logger->logDebug('SYNCHRONISING QUANTITIES FROM IKOSOFT');
		$ikosoftQuantities = $this->ikosoftInterface->getIkosoftQuantities();
		$this->prestaShopInterface->setPrestaShopQuantities($ikosoftQuantities);
	}
		
	public function synchroniseProductQuantitiesFromPrestaShop($trace = false)
	{		
		$this->logger->logDebug('SYNCHRONISING QUANTITIES FROM PRESTASHOP');
		$prestaShopQuantities = $this->prestaShopInterface->getPrestaShopQuantities();
		$this->ikosoftInterface->setIkosoftQuantities($prestaShopQuantities, $trace);
	}
	
	
	public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    public function hookActionObjectCustomerUpdateAfter($params) //added to random hook to test and avoid recursion
    {
		
		if($this->masterStock == 'PrestaShop')
			$this->synchroniseProductQuantitiesFromPrestaShop(true);
		else
			$this->synchroniseProductQuantitiesFromIkosoft();

		//synchronisePrices to be decided (use flag to decide direction?)
		//cron job this ?

    }
	
	
	public function hookActionValidateOrder($params) 
	{

		$order = $params['order'];
		$product_list = $order->product_list;
		$total_ordered = 0;
		$total_removed = 0;

		$this->logger->logDebug("Order number: ".$order->reference);
		$this->ikosoftInterface->addStockMovementMenuItem();
		
	    $order_ok = $this->ikosoftInterface->createStockMovement($order->reference, 2);

				
		if ($order_ok){
			foreach ($product_list as $prod)
			{
				$quantity = $prod['cart_quantity'];
				$purchase_price = (int) ($prod['wholesale_price']*100);
				
				$total_ordered+=$quantity;
				$barcode = PrestaShopInterface::getBarcodeFromPrestaShopProduct($prod);
				
				if ($barcode != null){
					$id_product = $this->ikosoftInterface->getIkosoftIdFromBarcode($barcode);
			
					if ($id_product != null)
					{
						$added = $this->ikosoftInterface->createStockMovementDetail($id_product,$quantity,$purchase_price);
						if ($added)
							$this->logger->logDebug("Product ".$barcode." added to order ".$order->reference.".");
						$removed = $this->ikosoftInterface->reduceStockAmount($id_product,$quantity);
						if ($removed)
						{
							$this->logger->logDebug("Product ".$barcode.": removed ".$quantity." item(s) from stock.");
							$total_removed+=$quantity;
						}
						
					}
				}

			}
			$this->logger->logDebug("Order ".$order->reference." completed. ".$total_removed." of ".$total_ordered." item(s) stocked out.");
		}
		else
			$this->logger->logDebug("Order ".$order->reference." failed to complete");

		
	}


	//TEST HOOK area++
    
	public function hookDisplayDashboardToolbarTopMenu($params)
	{
		
		if ($this->context->controller->controller_name === 'AdminProducts') {

			$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
			$host = $_SERVER['HTTP_HOST'];  
			$requestUri = $_SERVER['REQUEST_URI'];  
			$baseUrl = $protocol . $host . $requestUri;
			
			$this->context->smarty->assign([
				'sync_url' => $this->context->link->getBaseLink() . 'modules/' . $this->name . '/sync_action.php',
				'sync_message' => Tools::getValue('sync_message'),
				'base_url' => $baseUrl,
			]);

			//return $this->display(__FILE__, 'views/templates/hook/dropdown.tpl');
			return $this->fetch('module:ikostock/views/templates/hook/dropdown.tpl');
			
		}
	}
	


	//TEST HOOK area--


	
}
