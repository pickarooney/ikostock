<?php

class PrestaShopInterface  
{
	public $logger;
	public $lang;
	public $country;
	
	public function __construct()
    {
		$this->lang = (int)Context::getContext()->language->id;
		$this->country = (int)Context::getContext()->country->id;
		$this->logger = new FileLogger(0); 
		$this->logger->setFilename(_PS_ROOT_DIR_.'/var/logs/Ikostock_debug.log');
    }
	
	
	
	public static function getBarcodeFromPrestaShopProduct($prod) 
	{
		$id_product = $prod['id_product'];
		$name = $prod['name'];	
		$quantity = $prod['cart_quantity'];		
		$product = new Product($id_product, false, $lang_id);
		if (Validate::isLoadedObject($product) && strlen($product->ean13) > 0) 
			return $product->ean13;
		else 
		{	
			$this->logger->logDebug("Barcode not found for ".$name.". ".$quantity." product(s) not added."); //move this second part of the message out to make it more generic
			return null;
		}
	}
	
	public function getPrestaShopCategoryIdFromName($name)
	{
		$command = "select `id_category` as id from `". _DB_PREFIX_ ."category_lang` where `name` = '".$name."' and id_lang = ".$this->lang." limit 1";
		
		$data = Db::getInstance()->executeS($command);
		if (count($data) == 1)
			return $data[0]['id'];
		else return null;
	}
		
	public function getPrestaShopCategoryNameFromId($id)
	{
		$command = "select `name` as name from `". _DB_PREFIX_ ."category_lang` where `id_category` = ".$id." and id_lang = ".$this->lang." limit 1";

		$data = Db::getInstance()->executeS($command);
		if (count($data) == 1)
			return $data[0]['name'];
		else return null;
	}
	
	public function getPrestaShopSupplierNameFromId($id)
	{
		$command = "select `name` as name from `". _DB_PREFIX_ ."manufacturer` where `id_manufacturer` = ".$id;

		$data = Db::getInstance()->executeS($command);
		if (count($data) == 1)
			return $data[0]['name'];
		else return null;
	}
	
	public function getPrestaShopTaxRate($id)
	{
		$command = "select rate from `". _DB_PREFIX_ ."tax` where `id_tax` in (select `id_tax` from `". _DB_PREFIX_."tax_rule` where `id_country` = ".$this->country." and `id_tax_rules_group` = ".$id.")";

		$data = Db::getInstance()->executeS($command);
		if (count($data) == 1)
			return $data[0]['rate'];
		else return null;
	}

	public function getPrestaShopTaxId($rate)
	{
			
		$command = "select trg.`id_tax_rules_group` as id  from `". _DB_PREFIX_ ."tax_rules_group` trg inner join `". _DB_PREFIX_ ."tax_rule` tr on tr.`id_tax_rules_group` = trg.`id_tax_rules_group`
					inner join `". _DB_PREFIX_ ."tax` t on t.`id_tax` = tr.`id_tax` where tr.`id_country` = ".$this->country." and t.`rate` = ".$rate."  limit 1";

		$data = Db::getInstance()->executeS($command);

		if (count($data) == 1)
			return $data[0]['id'];
		else return null;
	}

	public function  getAllPrestaShopProductBarcodes()
	{
		$command = "select distinct `ean13` from `". _DB_PREFIX_ ."product`";
		$data = Db::getInstance()->executeS($command);
		
		$barcodes = array();
		
		foreach ($data as $item)
		{
			$barcodes[]=$item['ean13']; 
		}

		if (count($barcodes) == 0) return null;
		else return $barcodes;
		
	}
		
	public function getUsedPrestaShopProductCategories() 
	{	
		$command = "select `name` from `". _DB_PREFIX_ ."category_lang` where `id_lang` = ".$this->lang." and `id_category` in (SELECT distinct `id_category` FROM `ps_category_product`)";
		$data = Db::getInstance()->executeS($command);
		
		$categories = array();
		
		foreach ($data as $item)
		{
			$categories[]=$item['name']; 
		}

		if (count($categories) == 0) return null;
		else return $categories;
	}
	
	public function getAllPrestaShopProductCategories() 
	{
		$command = "select `name` from `". _DB_PREFIX_ ."category_lang` where `id_lang` = ".$this->lang;
		$data = Db::getInstance()->executeS($command);
		
		$categories = array();
		
		foreach ($data as $item)
		{
			$categories[]=$item['name']; 
		}

		if (count($categories) == 0) return null;
		else return $categories;
	}

	public function getUsedPrestaShopSuppliers() 
	{	
		$command = "select `name` from `". _DB_PREFIX_ ."manufacturer` where `id_manufacturer` in (SELECT distinct `id_manufacturer` FROM `". _DB_PREFIX_ ."product`)";
		$data = Db::getInstance()->executeS($command);
		
		$suppliers = array();
		
		foreach ($data as $item)
		{
			$suppliers[]=$item['name']; 
		}

		if (count($suppliers) == 0) return null;
		else return $suppliers;
	}
	
	public function getAllPrestaShopSuppliers() 
	{
		$command = "select `name` from `". _DB_PREFIX_ ."manufacturer`";
		$data = Db::getInstance()->executeS($command);
		
		$suppliers = array();
		
		foreach ($data as $item)
		{
			$suppliers[]=$item['name']; 
		}

		if (count($suppliers) == 0) return null;
		else return $suppliers;
	}


	public function getPrestaShopProducts() 
	{
		$all_products=Product::getProducts($this->lang, 0, 0, 'id_product', 'ASC', false,true,null);
		
		$prestaShop_products = array(); 
		
		if (count($all_products) > 0)
		{
			foreach ($all_products as $prod)
			{
				$product = new Product();
				$product->ean13 = $prod['ean13'];
				$product->quantity = StockAvailable::getQuantityAvailableByProduct($prod['id_product'],null,null);
				$product->price = $prod['price'];
				$product->wholesale_price = $prod['wholesale_price'];
				$product->date_upd = $prod['date_upd'];
				$product->name = $prod['name'];
				$product->manufacturer_name = $prod['manufacturer_name'];
				$product->id_category_default = $prod['id_category_default'];
				$product->id_tax_rules_group = $prod['id_tax_rules_group'];
				
				$prestaShop_products[]=$product;
			}
			return $prestaShop_products;
		}
		else return null;
		
	}
	
	public function getPrestaShopQuantities()
	{
		$command = "select p.`ean13`, ifnull(s.`quantity`, p.`quantity`) as quantity from `". _DB_PREFIX_ ."product` p  left outer join  `". _DB_PREFIX_ ."stock_available` s on p.`id_product` = s.`id_product` where p.`ean13` not like '';";
		
		$data = Db::getInstance()->executeS($command);
		
		$prestaShop_products = array();
		
		foreach ($data as $item)
		{
			$product = new IkosoftProduct();
			$product->barcode=$item['ean13']; 
			$product->quantity=$item['quantity']; 
			$prestaShop_products[]=$product; 
		}

		if (count($prestaShop_products) == 0) return null;
		else return $prestaShop_products;
	}
	
	
	

    //UPDATE++
	public function addProductCategoriesToPrestaShop($categories = [])  
	{
		if (empty($categories)) {
            return false;
        }

        if (!is_array($categories)) {
            $categories = [$categories];
        }

		$toAdd = count($categories);
		$added = 0;
		$existing_categories = $this->getAllPrestaShopProductCategories();
		
		$this->logger->logDebug('[PRESTASHOP] There are '.$toAdd.' categories to add.');
		
		
		foreach ($categories as $cat) 
		{
			
		  if (in_array($cat, $existing_categories))
			  continue;
			
		  $category = new Category();          
		  
		  $category->name[$this->lang] = $cat;
		  $category->active = true;
		  $category->id_parent = 2;
		  $category->level_depth = 2;
		  $category->link_rewrite[$this->lang] = strtolower(str_replace(" ","-",$cat));
	  
		  $category->add();
		  $added++;
		  $this->logger->logDebug('[PRESTASHOP] Category added: '.$cat);;

		}

		$this->logger->logDebug('[PRESTASHOP] Added '.$added.' of '.$toAdd.' categories.');
			
		if ($toAdd == $added) return true;
		else return false;
		
	}
	
	
	public function addSuppliersToPrestaShop($suppliers)  
	{
		$toAdd = count($suppliers);
		$added = 0;
		
		$this->logger->logDebug('[PRESTASHOP] There are '.$toAdd.' suppliers to add.');
		
		
		foreach ($suppliers as $sup) 
		{
		   
		    $manufacturer = new Manufacturer();
		    $manufacturer->name = $sup;
		    $manufacturer->active = 1; 
		   
		    if ($manufacturer->add()) {
				$this->logger->logDebug('[PRESTASHOP] Supplier '.$sup.' added successfully.');
				$added++;
			} 
			else {
				$this->logger->logDebug('[PRESTASHOP] Supplier '.$sup.' not added.');
			}
		}

		$this->logger->logDebug('[PRESTASHOP] Added '.$added.' of '.$toAdd.' suppliers.');
		
		if ($toAdd == $added) return true;
		else return false;
		
	}


	public function addProductsToPrestashop($products = [])
    {
        if (empty($products)) {
            return false;
        }

        if (!is_array($products)) {
            $products = [$products];
        }
		
		$existing_barcodes = $this->getAllPrestaShopProductBarcodes();
				
		$this->logger->logDebug('[PRESTASHOP] Adding '.count($products).' product(s).');	
				
		foreach ($products as $prod)
		{
			
			if (in_array($prod->ean13, $existing_barcodes))
			  continue;
			
			$prod->add();
			$this->logger->logDebug('[PRESTASHOP] Added '.$prod->name);
		}
		
	}
	
	public function setPrestaShopQuantities($products)
	{
		foreach ($products as $prod)
		{
			$id_product = Product::getIdByEan13($prod->barcode);
			StockAvailable::setQuantity($id_product,0, $prod->quantity, $id_shop = null, $add_movement = true);
		}
	
	}

	
	//UPDATE--
	
	
}