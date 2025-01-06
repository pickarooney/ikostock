<?php

// Load PrestaShop environment
require_once dirname(__FILE__) . '/../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../init.php';
require_once dirname(__FILE__) . '/syncHandler.php';
require_once dirname(__FILE__) . '/ikostock.php';

// Security: Check for token or authentication

/*
if (!Context::getContext()->employee->isLoggedBack()) {
	PrestaShopLogger::addLog('Synchronization cancelled, no access rights.');
    die('Unauthorized access');
}
*/

session_start();

$context = Context::getContext();
$moduleName = 'ikostock'; 
$module = Module::getInstanceByName($moduleName);

if (!$module) {
    die('Module not found!');
}

$syncHandler = new SyncHandler($module,$context);
$action = isset($_GET['action']) ? $_GET['action'] : '';
$redirectUrl = isset($_GET['redirect_url']) ? $_GET['redirect_url'] : '';
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];  
$baseUrl = $protocol . $host . $redirectUrl;

if ($action === 'syncToPrestaShop') {
	$syncHandler->syncToPrestaShop();
	$message = 'Synchronised product details to Prestashop.';
	PrestaShopLogger::addLog($message);   
	if (!empty($baseUrl)){
		header('Location: ' . $baseUrl . '&sync_message=' . urlencode($message));
		exit;
	}
	else 
		die('no starting url');
} 
elseif ($action === 'syncToIkosoft') {
	$syncHandler->syncToIkosoft();
	$message = 'Synchronised product details to Ikosoft.';
	PrestaShopLogger::addLog($message);
	if (!empty($baseUrl)){
		header('Location: ' . $baseUrl . '&sync_message=' . urlencode($message));
		exit;
	}
	else 
		die('no starting url');
} 
elseif ($action === 'syncQToPrestaShop') {
	$syncHandler->syncQToPrestaShop();
	$message = 'Synchronised quantities to Prestashop.';
	PrestaShopLogger::addLog($message);
	if (!empty($baseUrl)){
		header('Location: ' . $baseUrl . '&sync_message=' . urlencode($message));
		exit;
	}
	else 
		die('no starting url');
} 
elseif ($action === 'syncQToIkosoft') {
	$syncHandler->syncQToIkosoft();
	$message = 'Synchronised quantities to Ikosoft.';
	PrestaShopLogger::addLog($message);
	if (!empty($baseUrl)){
		header('Location: ' . $baseUrl . '&sync_message=' . urlencode($message));
		exit;
	}
	else 
		die('no starting url');
} 
else {
	$message = 'Invalid action';
	if (!empty($baseUrl)){
		header('Location: ' . $baseUrl . '&sync_message=' . urlencode($message));
		exit;
	}
	else 
		die('no starting url');
}
