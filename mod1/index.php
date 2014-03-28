<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2008 Daniel Zielinski (d.zielinski@l10ntech.de)
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * Module 'LocManager' for the 't3_locmanager' extension.
 *
 * @author	Daniel Zielinski <d.zielinski@l10ntech.de>
 */



	// DEFAULT initialization of a module [BEGIN]
unset($MCONF);
require ("conf.php");
require ($BACK_PATH."init.php");
require ($BACK_PATH."template.php");
$LANG->includeLLFile("EXT:t3_locmanager/cm1/locallang.php");
require_once (PATH_t3lib."class.t3lib_scbase.php");
require_once (t3lib_extMgm::extPath("cms").'web_info/class.tx_cms_webinfo_lang.php'); 
require_once (t3lib_extMgm::extPath('t3_locmanager').'class.tx_t3locmanager_main.php');
$BE_USER->modAccess($MCONF,1);	// This checks permissions and exits if the users has no permission for entry.
	// DEFAULT initialization of a module [END]

class tx_t3locmanager_module1 extends t3lib_SCbase {
	var $pageinfo;

	/**
	 *
	 */
	function init()	{
		global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;

		parent::init();

		/*
		if (t3lib_div::_GP("clear_all_cache"))	{
			$this->include_once[]=PATH_t3lib."class.t3lib_tcemain.php";
		}
		*/
	}

	/**
	 * Adds items to the ->MOD_MENU array. Used for the function menu selector.
	 */
	function menuConfig()	{
		global $LANG;
		$this->MOD_MENU = Array (
			"function" => Array (
				'1' => $LANG->getLL('function1'),
				'2' => $LANG->getLL('function2'),
				'3' => $LANG->getLL('function3'),
				'4' => $LANG->getLL('function4'),
				'5' => $LANG->getLL('function5'),
			)
		);
		parent::menuConfig();
	}

		// If you chose "web" as main module, you will need to consider the $this->id parameter which will contain the uid-number of the page clicked in the page tree
	/**
	 * Main function of the module. Write the content to $this->content
	 */
	function main()	{
		global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;
		$GLOBALS['TYPO3_DB']->debugOutput = true;

		// Access check!
		// The page will show only if there is a valid page and if this page may be viewed by the user
		$this->pageinfo = t3lib_BEfunc::readPageAccess($this->id,$this->perms_clause);
		$access = is_array($this->pageinfo) ? 1 : 0;

		if (($this->id && $access) || ($BE_USER->user["admin"] && !$this->id))	{

			$jScript=t3lib_div::getURL('../jscript.inc');
				// Draw the header.
			$this->doc = t3lib_div::makeInstance("mediumDoc");
			$this->doc->backPath = $BACK_PATH;
			$this->doc->form='<form action="" method="POST" enctype="multipart/form-data">';

				// JavaScript
			$this->doc->JScode = '
				<script language="javascript" type="text/javascript">
					script_ended = 0;
					function jumpToUrl(URL)	{
						document.location = URL;
					}

					'.$jScript.'

				</script>
			';
			$this->doc->postCode='
				<script language="javascript" type="text/javascript">
					script_ended = 1;
					if (top.fsMod) top.fsMod.recentIds["web"] = '.intval($this->id).';
				</script>
			';

			$headerSection = $this->doc->getHeader("pages",$this->pageinfo,$this->pageinfo["_thePath"])."<br>".$LANG->sL("LLL:EXT:lang/locallang_core.php:labels.path").": ".\TYPO3\CMS\Core\Utility\GeneralUtility::fixed_lgd_cs($this->pageinfo["_thePath"],50);

			$this->content.=$this->doc->startPage($LANG->getLL("title"));
			$this->content.=$this->doc->header($LANG->getLL("title"));
			$this->content.=$this->doc->spacer(5);
			$this->content.=$this->doc->section("",$this->doc->funcMenu($headerSection,t3lib_BEfunc::getFuncMenu($this->id,"SET[function]",$this->MOD_SETTINGS["function"],$this->MOD_MENU["function"])));
			$this->content.=$this->doc->divider(5);
				$css_content = t3lib_div::getURL('../css.inc');
				$marker = '/*###POSTCSSMARKER###*/';
				$this->content = str_replace($marker, $css_content.chr(10).$marker, $this->content);

			// Render content:
			$this->moduleContent();


			// ShortCut
			if ($BE_USER->mayMakeShortcut())	{
				$this->content.=$this->doc->spacer(20).$this->doc->section("",$this->doc->makeShortcutIcon("id",implode(",",array_keys($this->MOD_MENU)),$this->MCONF["name"]));
			}

			$this->content.=$this->doc->spacer(10);
		} else {
				// If no access or if ID == zero

			$this->doc = t3lib_div::makeInstance("mediumDoc");
			$this->doc->backPath = $BACK_PATH;

			$this->content.=$this->doc->startPage($LANG->getLL("title"));
			$this->content.=$this->doc->header($LANG->getLL("title"));
			$this->content.=$this->doc->spacer(5);
			$this->content.=$this->doc->spacer(10);
		}
	}

	/**
	 * Prints out the module HTML
	 */
	function printContent()	{

		$this->content.=$this->doc->endPage();
		echo $this->content;
	}

	/**
	 * Generates the module content
	 */
	function moduleContent()	{
		global $LANG;
		global $BE_USER;
			
			$main = new tx_t3locmanager_main();

			switch((string)$this->MOD_SETTINGS['function']) {
				case 1:
				$mtime = microtime(); 
				$mtime = explode(" ",$mtime); 
				$mtime = $mtime[1] + $mtime[0]; 
				$starttime = $mtime;
				//$content.="The 'Kickstarter' has made this module automatically, it contains a default framework for a backend module but apart from it does nothing useful until you open the script '".substr(t3lib_extMgm::extPath('t3_locmanager'),strlen(PATH_site))."cm1/index.php' and edit it!";
				if ($_POST['submit']==1) {
					$userPrefs['slang'] = t3lib_div::_POST('slang');
					$userPrefs['tlang'] = t3lib_div::_POST('tlang');
					$userPrefs['hidden'] = t3lib_div::_POST('hidden');
					$userPrefs['zipOwrite'] = t3lib_div::_POST('zipOwrite');
					$userPrefs['paOwrite'] = t3lib_div::_POST('paOwrite');
					$userPrefs['ceOwrite'] = t3lib_div::_POST('ceOwrite');
					$userPrefs['recursion'] = t3lib_div::_POST('recursion');
					//$userPrefs['recursion'] = 1;
					$userPrefs['paExport'] = t3lib_div::_POST('paExport');
					$userPrefs['ceExport'] = t3lib_div::_POST('ceExport');
					$userPrefs['ceHidden'] = t3lib_div::_POST('ceHidden');
					$userPrefs['locPages'] = t3lib_div::_POST('locPages');
					$userPrefs['ignLocCe'] = t3lib_div::_POST('ignLocCe');
					$userPrefs['id'] = t3lib_div::_POST('id');
					$userPrefs['doktypes'] = t3lib_div::_POST('doktypes');
					$userPrefs['locPaFields'] = t3lib_div::_POST('locPaFields');
					$userPrefs['locCeTypes'] = t3lib_div::_POST('locCeTypes');
					$userPrefs['locCeFields'] = t3lib_div::_POST('locCeFields');
					$userPrefs['sdl'] = t3lib_div::_POST('sdl');
					$userPrefs['passolo'] = t3lib_div::_POST('passolo');
					$userPrefs['tidy'] = t3lib_div::_POST('tidy');

					// store userPrefs in backend session so that user does not have to specify params again
					$BE_USER->pushModuleData('t3_locmanager/cm1/userPrefs', $userPrefs);
				}

				$params = $BE_USER->getModuleData('t3_locmanager/cm1/userPrefs', 'ses');
				// System defaults
				$params['tempDir'] = PATH_site.'typo3temp/t3LocData/';
				$params['downloads'] = PATH_site.'uploads/tx_t3locmanager/download/';
				$params['sysDoktypes'] = array(1 => $LANG->getLL('pType1'), 2 => $LANG->getLL('pType2'), 3 => $LANG->getLL('pType3'), 4 => $LANG->getLL('pType4'), 5 => $LANG->getLL('pType5'));
				$params['sysLocPaFields'] = array(0=>'title', 1=>'subtitle', 2=>'keywords', 3=>'description', 4=>'abstract', 5=>'nav_title', 6=>'url');
				$params['sysLocCeTypes'] = array('text', 'list', 'textpic', 'html', 'bullets', 'login', 'header', 'table', 'mailform', 'image', 'multimedia','uploads');
				$params['sysLocCeFields'] = array(0=>'header', 1=>'bodytext', 2=>'imagecaption', 3=>'subheader', 4=>'header_link', 5=>'image_link', 6=>'tx_dmcimagealttext', 7=>'tx_dmcimagetitletext');

				$params['submit'] = t3lib_div::_POST('submit');
				$params['id'] = $this->id;
				if (!isset($params['hidden'])) {
					$params['hidden'] = 0;
				}
				if (empty($params['doktypes'])) {
					$errorMsg.='PAGES::'.$LANG->getLL('w_noDoktypes');
					$params['doktypes']=array('1','2','3','4','5'); //default: all pages
				}
				preg_match_all('/([w]+\.)?([^.])+/', t3lib_div::getIndpEnv(TYPO3_HOST_ONLY), $matches, PREG_PATTERN_ORDER);
				$srv = $matches[0][0];
				$archivname = 't3_'.$srv.'_'.$params['id'].'_'.$params['slang'].'_'.$params['tlang'].'.zip';

				// Print localized module description
				$content .= $main->modDesc('1');
				// Print options form
				$content .= $main->printOpts($params);
				if ($params['submit']==1) {
					// Check necessary parameters
					$checkRes = $main->checkParams($params); 
					$errorMsg .= $checkRes['0'];
					$paramError = $checkRes['1'];

					// Get affected pages
					if ($params['recursion'] == '1') {
						$depth = '100'; // assuming that nested pages < 100
					} else {
						$depth = '0';
					}
					$affectedPagesArr = $main->getListOfSubpages($params['doktypes'], $params['hidden'], $params['id'], $depth);
					// Subtract localized pages if set to
					if (($params['locPages'] == 1) && (!empty($affectedPagesArr))) {
						// check for already localised pages in target lang
						$subLocRes = $main->subtractLocPages($affectedPagesArr, $params);
						$affectedPagesArr = $subLocRes['affectedPagesArr'];
						if ($subLocRes['loc'] == '1') {
							$errorMsg .= $LANG->getLL('w_pagLoc').'<br/>';
						}
					}
					// Export pages
					if (($paramError != '1') && ($params['paExport'] == 1)) {
						// if no parameter is missing export pages data
						$report .= '<strong>'.$LANG->getLL('pagesLocData').'</strong>';
						$exportPagesData = $main->exportPagesData($params, $affectedPagesArr);

						$report .= '<br/>'.$exportPagesData['report']; // Result summary
						$filename[] .= $exportPagesData['filename'];
						$errorMsg .= $exportPagesData['errorMsg'];
					}
					// Export content elements
					if (($paramError != '1') && ($params['ceExport'] == 1)) {
						// if no parameter missing export content elements data
						$report .= '<strong>'.$LANG->getLL('ceLocData').'</strong>';
						$exportCeData = $main->exportCeData($params, $affectedPagesArr);
						$report .= '<br/>'.$exportCeData['expCeRes']; // Result summary
						$filename[] .= $exportCeData['filename'];
						$errorMsg2 .= $exportCeData['errorMsg'];
					}
					// Write report to download dir

					$errorMsg.= $main->htmlReport($params,$report,$archivname);

					// Zip files
					$zipRes = $main->zipLocPackage($params,$errorMsg,$filename,$archivname);
					$errorMsg.= $zipRes['errorMsg'];
					$report.= $zipRes['report'];

					// Print error messages
					if (!empty($errorMsg) || (!empty($errorMsg2))) {
						$content .= '<h3 class="uppercase">Warnings:</h3>';
						$content .= '<div class="warning">';
						$content .= $errorMsg.$errorMsg2;
						$content .= '</div>';
					}
					// Print report
					if (!empty($report)) {
						$content .= '<h3 class="uppercase">Report:</h3>';
						$content .= '<div class="report">';
						$content .= $report;
						$content .= '</div>';
					}
				} else {
					$content .= '<div class="green"><img '.t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'],'gfx/helpbubble.gif','').' alt="Help"/> '.$LANG->getLL('w_setOpts').'</div>';
				}
				// Runtime info
				$mtime = microtime(); 
				$mtime = explode(" ",$mtime); 
				$mtime = $mtime[1] + $mtime[0]; 
				$endtime = $mtime; 
				$totaltime = ($endtime - $starttime); 
				echo $LANG->getLL('pagGen').$totaltime.$LANG->getLL('secs'); 
				$this->content .= $this->doc->section($LANG->getLL('export').':', $content, 0, 1);

				break;
			case 2:
				$mtime = microtime(); 
				$mtime = explode(" ",$mtime); 
				$mtime = $mtime[1] + $mtime[0]; 
				$starttime = $mtime;

				// userPrefs: source lang, target lang, ignore hidden pages, ...
				$userPrefs['upload'] = PATH_site.'uploads/tx_t3locmanager/upload/';
				if ($_POST['submit']==1) {
					$userPrefs['hideImpPages'] = t3lib_div::_POST('hideImpPages');
					$userPrefs['hideImpCes'] = t3lib_div::_POST('hideImpCes');
					$userPrefs['overwritePagesInfo'] = t3lib_div::_POST('overwritePagesInfo');
					$userPrefs['overwriteCeInfo'] = t3lib_div::_POST('overwriteCeInfo');
					$userPrefs['remExCes'] = t3lib_div::_POST('remExCes');

					// store userPrefs in backend session so that user does not have to specify params again
					$BE_USER->pushModuleData('t3_locmanager/cm1/userPrefs', $userPrefs);
				}

				$params = $BE_USER->getModuleData('t3_locmanager/cm1/userPrefs', 'ses');
				if (empty($params)) {
					$params=$userPrefs;
					//print 'Here for the first time';
				}
				$params['submit'] = t3lib_div::_POST('submit');
				$params['id'] = $this->id;

				$content = $main->modDesc('2');
				//$content .= $main->debug();
				$content .= $main->printImportOpts($params);

				if ($params['submit']==1) {
					//Proceed uploaded file
					if (is_uploaded_file($_FILES['loc']['tmp_name'])) {
						$uploadedTempFile= t3lib_div::upload_to_tempfile($_FILES['loc']['tmp_name']); 
						// check type
						//if ($_FILES['loc']['type'] != 'application/zip') { //TODO: Add XML //Breaks with IE 6!!!
						if (!preg_match('/.zip$/',$_FILES['loc']['name'])) {
							$errorMsg.='UPLOAD::'.$LANG->getLL('unsupportedFType').'<br/>';
						}
						// Unzip files
							$unzip = new tx_t3locmanager_zip();
							$unzipRes=$unzip->extractFile($uploadedTempFile);
						// unlink $uploadedTempFile
							t3lib_div::unlink_tempfile($uploadedTempFile);
					// Process extracted files if ftype = xml => IMPORT
						$xmlFilesArr = $main->checkFType($unzipRes['fileArr'],'xml');
						if (!empty($xmlFilesArr)) {
							if (PHP_VERSION < 5) {
								$errorMsg.=$main->importLocFile($params,$xmlFilesArr);	
							} else {
								$errorMsg.=$main->importLocFile2($params,$xmlFilesArr);	
							}
						} else {
							$errorMsg.='IMPORT::'.$LANG->getLL('noXMLFiles').'<br/>';	
						}
						// check if already exists page/CE
						// delete tempFiles
							$unzip->removeDir($unzipRes['tempDir']);
						// TODO: delete files from downloads if import success
						// TODO: print report
					} else {
						$errorMsg.= "UPLOAD:: Error uploading file!!!";
					}
				} else {
					$content .= '<br/><div class="green"><img '.t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'],'gfx/helpbubble.gif','').' alt="Help"/> '.$LANG->getLL('w_startImport').'</div>';
				}
				// Print error messages
				if (!empty($errorMsg)) {
					$content .= '<h3 class="uppercase">Warnings and Messages:</h3>';
					$content .= '<div class="warning">';
					$content .= $errorMsg;
					$content .= '</div>';
				}	

				// Runtime info
				$mtime = microtime(); 
				$mtime = explode(" ",$mtime); 
				$mtime = $mtime[1] + $mtime[0]; 
				$endtime = $mtime; 
				$totaltime = ($endtime - $starttime); 
				echo $LANG->getLL('pagGen').$totaltime.$LANG->getLL('secs'); 
				$this->content .= $this->doc->section($LANG->getLL('import').':', $content, 0, 1);
			break;
			case 3:
				$content = $main->modDesc('3');
				$content .= $main->showExportedLocData($this->id);
				$this->content .= $this->doc->section($LANG->getLL('function3').':', $content, 0, 1);
			break;
			case 4:
				$content = $main->modDesc('4');
				// Showing the tree:
				// Initialize starting point of page tree:
				$treeStartingPoint = intval($this->id);
				$treeStartingRecord = t3lib_BEfunc::getRecord('pages', $treeStartingPoint);
				$depth = '3';
				//$depth = $this->pObj->MOD_SETTINGS['depth']; //TODO: Implement depth

				// Initialize tree object:
				$tree = t3lib_div::makeInstance('t3lib_pageTree');
				$tree->init('AND '.$GLOBALS['BE_USER']->getPagePermsClause(1));
				$tree->addField('l18n_cfg');

				// Creating top icon; the current page
				$HTML = t3lib_iconWorks::getIconImage('pages', $treeStartingRecord, $GLOBALS['BACK_PATH'], 'align="top"');
				$tree->tree[] = array(
				'row' => $treeStartingRecord,
					'HTML' => $HTML );

				// Create the tree from starting point:
				$tree->getTree($treeStartingPoint, $depth, '');
				//debug($tree->tree);

				// Render information table:
				$locOverview = new tx_cms_webinfo_lang;
				$content .= $locOverview->renderL10nTable($tree);

				//$content .= $main->debug();
				$this->content .= $this->doc->section($LANG->getLL('function4').':', $content, 0, 1);
			break;
			case 5:
				$content = $main->modDesc('5');
				$content .= '<strong>'.$LANG->getLL('t3ver').': </strong>'.t3lib_div::int_from_ver(TYPO3_version).'<br/>';
				$content .= '<br/><strong>'.$LANG->getLL('cLangSysLang').': </strong> ('.$LANG->getLL('ignHidLangs').')<br/>'.$main->displaySysLanguageInfo ();
				$content .= '<br/><strong>$TYPO3_CONF_VARS[\'SYS\'][\'compat_version\']</strong>:'.$GLOBALS['TYPO3_CONF_VARS']['SYS']['compat_version'].'<br/>';
				$content .= '<strong>$TYPO3_CONF_VARS[\'SYS\'][\'setDBinit\']</strong>:'.$GLOBALS['TYPO3_CONF_VARS']['SYS']['setDBinit'].'<br/>';
				$content .= '<strong>$TYPO3_CONF_VARS[\'SYS\'][\'t3lib_cs_convMethod\']</strong>:'.$GLOBALS['TYPO3_CONF_VARS']['SYS']['t3lib_cs_convMethod'].'<br/>';
				$content .= '<strong>$TYPO3_CONF_VARS[\'SYS\'][\'t3lib_cs_utils\']</strong>:'.$GLOBALS['TYPO3_CONF_VARS']['SYS']['t3lib_cs_utils'].'<br/>';
				$content .= '<strong>$TYPO3_CONF_VARS[\'SYS\'][\'multiplyDBfieldSize\']</strong>:'.$GLOBALS['TYPO3_CONF_VARS']['SYS']['multiplyDBfieldSize'].'<br/>';
				$content .= '<strong>$TYPO3_CONF_VARS[\'BE\'][\'forceCharset\']</strong>:'.$GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'].'<br/>';
				$content .= '<br/><strong>PHPInfo: </strong><br/>';
				ob_start();                                                                                                       
				phpinfo();                                                                                                        
				$info = ob_get_contents();                                                                                        
				ob_end_clean();
				$content.= preg_replace('%^.*<body>(.*)</body>.*$%ms', '$1', $info);
				$this->content .= $this->doc->section($LANG->getLL('function5').':', $content, 0, 1);
			break;
		}
	}
}



if (defined("TYPO3_MODE") && $TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/t3_locmanager/mod1/index.php"])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/t3_locmanager/mod1/index.php"]);
}

// Make instance:
$SOBE = t3lib_div::makeInstance("tx_t3locmanager_module1");
$SOBE->init();

// Include files?
foreach($SOBE->include_once as $INC_FILE)	include_once($INC_FILE);

$SOBE->main();
$SOBE->printContent();

?>
