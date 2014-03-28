<?php
if (!defined ("TYPO3_MODE")) 	die ("Access denied.");

if (TYPO3_MODE=="BE")	{
		
	t3lib_extMgm::addModule("web","txt3locmanagerM1","before:info",t3lib_extMgm::extPath($_EXTKEY)."mod1/");
}


if (TYPO3_MODE=="BE")	{
	t3lib_extMgm::insertModuleFunction(
		"web_info",		
		"tx_t3locmanager_modfunc1",
		t3lib_extMgm::extPath($_EXTKEY)."modfunc1/class.tx_t3locmanager_modfunc1.php",
		"LLL:EXT:t3_locmanager/locallang_db.php:moduleFunction.tx_t3locmanager_modfunc1"
	);
}


if (TYPO3_MODE=="BE")	{
	$GLOBALS["TBE_MODULES_EXT"]["xMOD_alt_clickmenu"]["extendCMclasses"][]=array(
		"name" => "tx_t3locmanager_cm1",
		"path" => t3lib_extMgm::extPath($_EXTKEY)."class.tx_t3locmanager_cm1.php"
	);
}
?>