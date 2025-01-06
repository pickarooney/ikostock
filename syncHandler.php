<?php

require_once( __DIR__ .'/ikostock.php');

class SyncHandler
{
    private $module;
    private $context;
	private $ikostock;
	public $logger;

    public function __construct($module, $context)
    {
        $this->module = $module;
        $this->context = $context;
		$this->ikostock = new Ikostock();
		$this->logger = new FileLogger(0); 
		$this->logger->setFilename(_PS_ROOT_DIR_.'/var/logs/Ikostock_debug.log');
    }


	private function performPrestaShopSync()
	{
		$this->ikostock->synchroniseSuppliersToPrestaShop();
		$this->ikostock->synchroniseCategoriesToPrestaShop();
		$this->ikostock->synchroniseProductsToPrestaShop();
		//$this->ikostock->synchroniseProductQuantitiesFromIkosoft();
		
		$this->logger->logDebug('[*** FINISHED ***]');
		
		return true;
	}

	private function performIkosoftSync()
	{
		$this->ikostock->synchroniseSuppliersToIkosoft();
		$this->ikostock->synchroniseCategoriesToIkosoft();
		$this->ikostock->synchroniseProductsToIkosoft();
		//$this->ikostock->synchroniseProductQuantitiesFromPrestaShop(true);
		
		$this->logger->logDebug('[*** FINISHED ***]');
		
		return true; 
	}
	
	private function syncQuantitiesToIkosoft()
	{
		$this->logger->logDebug('[*** Synching quantities from Prestashop to Ikosoft ***]');
		$this->ikostock->synchroniseProductQuantitiesFromPrestaShop(true);
		$this->logger->logDebug('[*** FINISHED ***]');
		return true;
	}
	
	private function syncQuantitiesToPrestaShop()
	{
		$this->logger->logDebug('[*** Synching quantities from Prestashop to Ikosoft ***]');
		$this->ikostock->synchroniseProductQuantitiesFromIkosoft();
		$this->logger->logDebug('[*** FINISHED ***]');
		return true;
	}
	
    public function syncToPrestaShop()
    {
        if ($this->performPrestaShopSync()) {
            $this->context->controller->confirmations[] = $this->module->l('Synchronisation to Prestashop completed successfully.');
        } else {
            $this->context->controller->errors[] = $this->module->l('An error occurred during synchronization to Prestashop.');
        }
    }

    public function syncToIkosoft()
    {
        if ($this->performIkosoftSync()) {
            $this->context->controller->confirmations[] = $this->module->l('Synchronisation to Ikosoft completed successfully.');
        } else {
            $this->context->controller->errors[] = $this->module->l('An error occurred during synchronization to Ikosoft.');
        }
    }

	public function syncQToPrestaShop()
    {
        if ($this->syncQuantitiesToPrestaShop()) {
            $this->context->controller->confirmations[] = $this->module->l('Synching quantities to Prestashop completed successfully.');
        } else {
            $this->context->controller->errors[] = $this->module->l('An error occurred during synchronization to Prestashop.');
        }
    }

    public function syncQToIkosoft()
    {
        if ($this->syncQuantitiesToIkosoft()) {
            $this->context->controller->confirmations[] = $this->module->l('Synching quantities to Ikosoft completed successfully.');
        } else {
            $this->context->controller->errors[] = $this->module->l('An error occurred during synchronization to Ikosoft.');
        }
    }
	
	
}