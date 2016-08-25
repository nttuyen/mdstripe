<?php
require_once dirname(__FILE__).'/../../config/config.inc.php';
header('Content-Type: text/plain');

// Remove untrusted
$untrustedXml = simplexml_load_file(_PS_ROOT_DIR_.Module::CACHE_FILE_UNTRUSTED_MODULES_LIST);
$module = $untrustedXml->xpath('//module[@name="mdstripe"]');
if (empty($module)) {
    // Module list has not been refreshed, return
    die('ok');
}
unset($module[0][0]);
$untrustedXml->saveXML(_PS_ROOT_DIR_.Module::CACHE_FILE_UNTRUSTED_MODULES_LIST);

// Add untrusted
$trustedXml = simplexml_load_file(_PS_ROOT_DIR_.Module::CACHE_FILE_TRUSTED_MODULES_LIST);
/** @var SimpleXMLElement $modules */
@$modules = $trustedXml->xpath('//modules')[0];
if (empty($modules)) {
    die('ok');
}
/** @var SimpleXMLElement $module */
$module = $modules->addChild('module');
$module->addAttribute('name', 'mdstripe');
$trustedXml->saveXML(_PS_ROOT_DIR_.Module::CACHE_FILE_TRUSTED_MODULES_LIST);

// Add to active payments list
$modulesTabXml = simplexml_load_file(_PS_ROOT_DIR_.Module::CACHE_FILE_TAB_MODULES_LIST);

$moduleFound = $modulesTabXml->xpath('//tab[@class_name="AdminPayment"]/module[@name="mdstripe"]');
if (!empty($moduleFound)) {
    die('ok');
}

// Find highest position
/** @var array $modules */
$modules = $modulesTabXml->xpath('//tab[@class_name="AdminPayment"]/module');
$highestPosition = 0;
foreach ($modules as $module) {
    /** @var SimpleXMLElement $module */
    foreach ($module->attributes() as $name => $attribute) {
        if ($name == 'position' && $attribute[0] > $highestPosition) {
            $highestPosition = (int) $attribute[0];
        }
    }
}
$highestPosition++;
/** @var SimpleXMLElement $modules */
@$modules = $modulesTabXml->xpath('//tab[@class_name="AdminPayment"]')[0];
if (empty($modules)) {
    die('ok');
}
$module = $modules->addChild('module');
$module->addAttribute('name', 'mdstripe');
$module->addAttribute('position', $highestPosition);
$modulesTabXml->saveXML(_PS_ROOT_DIR_.Module::CACHE_FILE_TAB_MODULES_LIST);
die('ok');