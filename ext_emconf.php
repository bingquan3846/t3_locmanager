<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "t3_locmanager".
 *
 * Auto generated 28-03-2014 09:24
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = array (
	'title' => 'LocManager',
	'description' => 'Localization management extension supporting export of localizable content into a localization-friendly XML format that can be dealt with in professional CAT (Computer Aided Translation) and Software Localization tools. After translation the XML files can be imported and localized content is integrated into TYPO3. Offers various export/import options, checks, and automatically generated settings files for professional tools etc. For further information see http://l10ntech.de.',
	'category' => 'be',
	'shy' => 0,
	'version' => '0.3.1',
	'dependencies' => '',
	'conflicts' => '',
	'priority' => '',
	'loadOrder' => '',
	'module' => 'mod1,cm1',
	'state' => 'beta',
	'uploadfolder' => 1,
	'createDirs' => 'uploads/tx_t3locmanager/download,uploads/tx_t3locmanager/upload',
	'modify_tables' => '',
	'clearcacheonload' => 0,
	'lockType' => 'L',
	'author' => 'Daniel Zielinski',
	'author_email' => 'd.zielinski@l10ntech.de',
	'author_company' => 'L10Ntech.de, Germany',
	'CGLcompliance' => NULL,
	'CGLcompliance_note' => NULL,
	'constraints' => 
	array (
		'depends' => 
		array (
			'php' => '4.0-5.9.9',
			'typo3' => '3.7-0.0.0',
		),
		'conflicts' => 
		array (
		),
		'suggests' => 
		array (
		),
	),
);

?>