<?php

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;


class IkosoftProduct
{
	public $name;
	public $barcode;
	public $quantity;
	public $wholesale;
	public $retail;
	public $category;
	public $supplier;
	public $tax_rate;
	
}

class IkosoftInterface  
{
	public $db_number;
	public $suffix;
	public $APIkey;
	public $endpoint; 
	public $logger;
	public $movement_comment;
	
    public function __construct()
    {
		
		//SALON SPECIFIC++
		$this->db_number = 'DE844AE04B20C9819C010007F'; //can this be stored in the interface/databse?
		$this->suffix = '';
		$this->APIkey = '6923CD11-849A-42C6-86CE-DC7B1C2FE2F2';
		$this->endpoint = "https://salonapi".$this->suffix.".ikosoft.net/dev/api/salon/".$this->db_number."/query";
		$this->movement_comment = "PRESTASHOP";
		////SALON SPECIFIC--

		$this->logger = new FileLogger(0); 
		$this->logger->setFilename(_PS_ROOT_DIR_.'/var/logs/debug.log');
    }



	private function callExternalApi($command)
    {
		
		try {
            $client = new GuzzleHttp\Client();
 
			$response = $client->post($this->endpoint, [
				'headers' => [
					'Content-Type' => 'application/json',
					'API_KEY'      => $this->APIkey
				],
				'json' => [
					'Command' => $command
				]
			]);
					
            $responseBody = $response->getBody()->getContents();
            $decodedResponse = json_decode($responseBody, true);
 
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->logDebug('Error decoding JSON: ' . json_last_error_msg());
                return null;
            }
 
            return $decodedResponse;
			
        } 
		catch (Exception $e) {
            $this->logger->logDebug($e->getMessage());
			return null;
        }
    }

	
	public function getIkosoftIdFromBarcode($barcode)
	{
		$command="select Top 1 [Id] from [Produit] where [CodeABarre] like '".$barcode."'";
		$response = $this->callExternalApi($command);
		
		$count = $response['count'];
		
		if ($count == 1)
			return $response['data'][0][0];
		else {
			$this->logger->logDebug("No Ikosoft product found with barcode ".$barcode);
			return null;
		}
	}

    public function addStockMovementMenuItem() 
	{
		$command = "insert into sysmenu (flagarchive, groupe,libelle,guidcs,iguid) select 0,1010,'".$this->movement_comment."',newid(),newid() where not exists (select id from sysmenu where libelle = '".$this->movement_comment."' and groupe = 1010);";
		$response = $this->callExternalApi($command);
	
		if ($response == null) return false;
		else return true;
	}

    
	
	//STOCK MOVEMENTS++
   	public function createStockMovement($order_id, $direction) //Direction: 1 = in, 2 = out   
	{		
		$command = "insert into ProduitMouvement (commentmvt,id,idperso,motif,typemvt,guidcs,iguid) select '".$this->movement_comment." : ".$order_id."',(cast (DATEDIFF(day,'1990-01-01',getdate()) as int) * 100000) + datediff(second,convert(date,getdate()),getdate()), -1,max(s.id),".$direction.",newid(),newid() from sysmenu s where s.libelle = '".$this->movement_comment."' and s.groupe = 1010";
	
		$response = $this->callExternalApi($command);
	
		if ($response == null) return false;
		else return true;

	}

    public function createStockMovementDetail($id_product,$quantity,$purchase_price)  
	{
		$command = "insert into ProduitMvtDetail (idproduit,IdProduitMvt,quantite,GuidCS,iguid,PrixAchat) select ".$id_product.",max(pm.id),".$quantity.",newid(),newid(),".$purchase_price." from ProduitMouvement pm;";

		$response = $this->callExternalApi($command);
	
		if ($response == null) return false;
		else return true;
	}	


	public function reduceStockAmount($id_product,$quantity) 
	{
		$command="update produit set reel = reel - ".$quantity." where id = ".$id_product.";";
		$response = $this->callExternalApi($command);

		if ($response == null) return false;
		else return true;
	}	
	
	public function increaseStockAmount($id_product,$quantity) 
	{
		$command="update produit set reel = reel + ".$quantity." where id = ".$id_product.";";
		$response = $this->callExternalApi($command);

		if ($response == null) return false;
		else return true;
	}	
	//STOCK MOVEMENTS--
	
	
	//PRODUCT INFORMATION++
	public function getUsedIkosoftProductCategories() 
	{
		$command = "select NomFamille from ProduitFamille where id in (select idproduitfamille from produit where FlagArchive = 0 and IdProduitType in (1,3))";
		$response = $this->callExternalApi($command);
		
		$items = $response['data'];
		$categories = array();
		
		
		foreach ($items as $prod)
		{
			$categories[]=$prod[0];
		}

		if (count($categories) == 0) return null;
		else return $categories;
	}
	
	public function getAllIkosoftProductCategories() 
	{
		$command = "select [NomFamille] from [ProduitFamille]";
		$response = $this->callExternalApi($command);
		
		$items = $response['data'];
		$categories = array();
		
		
		foreach ($items as $prod)
		{
			$categories[]=$prod[0];
		}

		if (count($categories) == 0) return null;
		else return $categories;
	}
	
	public function getUsedIkosoftSuppliers() 
	{
		$command = "select [NomFournisseur] from [ProduitFournisseur] where [FlagArchive] = 0 and [Id] in (select [IdProduitFournisseur] from [Produit] where [FlagArchive] = 0 and [IdProduitType] in (1,3))";
		$response = $this->callExternalApi($command);
		
		$items = $response['data'];
		$suppliers = array();
				
		foreach ($items as $prod)
		{
			$suppliers[]=$prod[0];
		}

		if (count($suppliers) == 0) return null;
		else return $suppliers;
	}
	
	public function getAllIkosoftSuppliers() 
	{
		$command = "select [NomFournisseur] from [ProduitFournisseur]";
		$response = $this->callExternalApi($command);
		
		$items = $response['data'];
		$suppliers = array();
				
		foreach ($items as $prod)
		{
			$suppliers[]=$prod[0];
		}

		if (count($suppliers) == 0) return null;
		else return $suppliers;
	}
	
	
	public function getIkosoftQuantities() 
	{
		$command = "select [CodeABarre],[Reel],[Referen] from [Produit] where [FlagArchive] = 0 and [IdProduitType] in (1,3) and  DATALENGTH([CodeABarre]) > 0 ";
		$response = $this->callExternalApi($command);
		
		$items = $response['data'];
		$products = array();
				
		foreach ($items as $prod)
		{
			$ikosoft_product = New IkosoftProduct();
			$ikosoft_product->barcode = $prod[0];
			$ikosoft_product->quantity = $prod[1];
			$ikosoft_product->name = $prod[2];
			$products[] = $ikosoft_product;
		}

		if (count($products) == 0) return null;
		else return $products;
	}
	
	
	
	
	
	public function getProducts() 
	{
		
		$command = "select p.[CodeABArre] as ean13, p.[Reel] as quantity, p.[Vente]/100 as price, p.[PrixCatalogue]/100 as wholesale_price, p.[DateHeure] as date_upd,
					p.[Referen] as name, f.[NomFournisseur] as manufacturer_name, s.[Taux]/100 as rate, pf.[NomFamille] as category from [Produit] p inner join [SysTauxTva] s on s.[Id] = p.[IdTauxTVA] inner join [ProduitFournisseur] f on f.[id] = p.[IdProduitFournisseur] inner join [ProduitFamille] pf on pf.[Id] = p.[IdProduitfamille] where p.[IdProduitType] in (1,3) and p.[CodeABarre] not like ''";
		
		//$command = $command."and p.[CodeABarre] = '4025087079080';"; //test for one product

		$response = $this->callExternalApi($command);
	
		$ikosoft_products = $response['data'];
		
		if (count($ikosoft_products) == 0) return null;
			else return $ikosoft_products;


	}
	//PRODUCT INFORMATION--
	
	
	//PRODUCT CHANGES++
	public function addProductCategoriesToIkosoft($categories)  
	{
		foreach ($categories as $cat)
		{
			//use STYLING by default for now
		    $command=$command."insert into [ProduitFamille] ([NomFamille],[IdSysFamilleGroupe]) Select '".$cat."',3 where not exists (select 1 from [ProduitFamille] where [NomFamille] = '".$cat."');";
			$this->logger->logDebug('[IKOSOFT] Category added: '.$cat);;
		}
		
		$response = $this->callExternalApi($command);
	
		if ($response == null) return false;
		else 
		{
			$this->logger->logDebug('[IKOSOFT] Added '.count($categories).' categories.');
			return true;
		}
		
	}
	
	public function addSuppliersToIkosoft($suppliers)  
	{
		
		$this->logger->logDebug('[IKOSOFT] There are '.count($suppliers).' supplier(s)to add.');
		
		foreach ($suppliers as $sup)
		{
			$this->logger->logDebug('[IKOSOFT] Supplier added: '.$sup);
		    $command=$command."insert into [ProduitFournisseur] ([NomFournisseur]) Select '".$sup."' where not exists (select 1 from [ProduitFournisseur] where [NomFournisseur] = '".$sup."');";
		}
		
		$response = $this->callExternalApi($command);
	
		if ($response == null) return false;
		else 
		{
			$this->logger->logDebug('[IKOSOFT] Added '.count($suppliers).' suppliers.');
			return true;
		}
		
	}
	

	public function addProducts($products)  
	{
		
		foreach ($products as $prod)
		{
			$command=$command."insert into [Produit] ([CodeABarre],[DateCreation],[DateHeure],[IdProduitFamille],[IdProduitFournisseur],[IdProduitType],[IdTauxTVA],[PrixCatalogue],[Reel],[Referen],[Vente],[GuidCS],[IGuid])
					select '".$prod->barcode."',getdate(),getdate(),pf.[Id], f.[Id], 3, s.[Id], ".$prod->wholesale.",".$prod->quantity.",'".$prod->name."',".$prod->retail.",newid(),newid() from [ProduitFamille] pf, [ProduitFournisseur] f, [SysTauxTva] s
					where pf.[NomFamille] = '".$prod->category."' and f.[NomFournisseur] = '".$prod->supplier."' and s.[Taux] = ".$prod->tax_rate." and s.[FlagArchive] = 0
					and not exists (select 1 from [Produit] where [CodeABarre] = '".$prod->barcode."');";
			
			$this->logger->logDebug('[IKOSOFT] Added '.$prod->name);
			
		}

		if (!empty($command))
			$response = $this->callExternalApi($command);
		else $response = null;
		
		if ($response == null) return false;
		else return true;

	}
	
	public function setIkosoftQuantities($products)
	{
		foreach ($products as $prod)
		{
			$command=$command."Update [Produit] set [Reel] = ".$prod->quantity." where [CodeABarre] = '".$prod->barcode."';";
			$this->logger->logDebug('[IKOSOFT] Updating '.$prod->barcode.' with quantity '.$prod->quantity);
		}

		if (!empty($command))
			$response = $this->callExternalApi($command);
		else $response = null;
		
		if ($response == null) return false;
		else return true;
	}
	
	
	
	//PRODUCT CHANGES--
}