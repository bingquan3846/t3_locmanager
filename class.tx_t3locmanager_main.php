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
 * Functions used in t3_locManager
 *
 * @author	Daniel Zielinski <d.zielinski@l10ntech.de>
 */
	require_once (PATH_t3lib.'class.t3lib_scbase.php');
	require_once (PATH_t3lib.'class.t3lib_page.php');
	require_once (t3lib_extMgm::extPath('t3_locmanager').'class.tx_t3locmanager_zip.php');
	require_once (PATH_t3lib.'class.t3lib_parsehtml_proc.php');

class tx_t3locmanager_main extends t3lib_SCbase {

		/**
		 * Generates a list of Page-uids from $id. List does include $id itself
		 * The only pages WHICH PREVENTS DECENDING in a branch are
		 *    - deleted pages,
		 *    - hidden pages if options set to Ignore hidden pages,
		 *    - pages of an unsupported page type
		 *
		 * @param	integer		$id: The id of the start page from which point in the page tree to decend.
		 * @param	integer		$depth: The number of levels to decend. If you want to decend infinitely, just set this to 100 or so, because 100 is almost infinity, eh?
		 * @param	array			Array of IDs from previous recursions. In order to prevent infinite loops with mount pages.
		 * @param	integer		Internal: Zero for the first recursion, incremented for each recursive call.
		 * @return	array			Returns an array of page uids
		 */
		function getListOfSubpages($doktypes, $hidden, $id, $depth, $recursionLevel = 0) {
			$this->pageSelectObj = t3lib_div::makeInstance('t3lib_pageSelect');
			global $TYPO3_DB;
			// user defined doktype settings
			$sqlDoktype = join(',', $doktypes);
			// if ignore hidden pages
			if ($hidden==1) {
				$sqlHidden = ' AND hidden=0';
			}

			$depth = intval($depth);
			$id = intval($id);
			$subPagesArr = array();

			if ($id) {
				if ($recursionLevel == 0) {
					// Check start page and return blank if the start page was NOT found at all:
					if (!$this->pageSelectObj->getRawRecord('pages', $id, 'uid')) {
						return '';
					} elseif (($hidden == 1) && ($this->pageIsHidden($id))) {
						return ''; // Check if start page is not hidden
					}
				}

				if ($versions = $this->getVersionsOfPage($id)) {
					$subPagesArr[$id] = $versions;
					$subPagesArr[$id][$id] = $id; // add current page, too
				} else {
					// Add the current ID to the array of IDs:
					$subPagesArr[$id] = $id;
				}

				// Find subpages:
				if ($depth >= $recursionLevel) {
					$res = $TYPO3_DB->exec_SELECTquery('uid,doktype,php_tree_stop', 'pages', 'pid='.$id.' AND doktype IN ('.$sqlDoktype.') AND deleted=0'.$sqlHidden, '' , 'sorting');

                    while ($row = $TYPO3_DB->sql_fetch_assoc($res)) {
						$next_id = $row['uid'];

						// Add ID to list:
						$subPagesArr[$next_id] = $next_id;

						// Next level:
						if ($depth > $recursionLevel+1 && !$row['php_tree_stop']) {
							// Call recursively
							$subPagesArr = t3lib_div::array_merge ($subPagesArr, $this->getListOfSubpages ($doktypes, $hidden, $next_id, $depth, $recursionLevel+1));
						}
					}
				}
			}
			// Return list of subpages:
			return $subPagesArr;
		}

		/**
		 * Check for supported pages types
		 *
		 * @param	integer		$id: The id of the page
		 * @param	array		Array of IDs that passed the test
		 * @return	array		Returns an array of page uids
		 */
		function getVersionsOfPage($id) {
			$id = intval($id);
			// $GLOBALS['TYPO3_DB']->debugOutput = TRUE;

			if ($id > 0) {
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid,doktype,php_tree_stop', 'pages', ' t3ver_oid = '.$id.' AND doktype IN (1,2,3,4,5) AND deleted=0');
				while ($rowVersion = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
					$subPagesArr[$rowVersion['uid']] = $rowVersion['uid'];
				}
				return $subPagesArr?$subPagesArr:
				array();
			} else {
				return array();
			}
		}

		/**
		 * Export localizable data from tt_content
		 *
		 * @param	array		$params: Array of parameters (id,hidden,recursion,locPages,...)
		 * @param	array		$affectedPagesArr: Array of affected page IDs
		 * @return	array		$out: Returns an array of two arrays: $out[0]=Messages to be printed out, $out[1]=affected content element IDs
		 */
		function exportCeData($params,$affectedPagesArr) {
			global $LANG;
			$table = 'tt_content';
			// TODO:Image => localizable fields


			if ((is_array($affectedPagesArr)) && (!empty($affectedPagesArr))) {
				sort($affectedPagesArr, SORT_NUMERIC);
				if ($params['ceHidden'] == '1') {
					$hidden = ' AND `hidden`=0';
					$unhidden = ' '.$LANG->getLL('unhid').' ';
				}
				// Build list of CTypes select statement 
				if (!empty($params['locCeTypes'])) {
					$sqlTemp=' AND (';
					foreach ($params['locCeTypes'] as $cType) {
						$sqlTemp .='`CType`=\''.$cType.'\' OR ';
					}
					$sqlCType=preg_replace('/( OR )$/','',$sqlTemp);
					$sqlCType.=')';
				}
				// get CE IDs for Page IDs
				foreach ($affectedPagesArr as $pids) {
					$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid', 'tt_content', '`pid`='.$pids.$hidden.' AND `deleted`=0 AND `sys_language_uid`='.$params['slang'].$sqlCType);
                    //print_r($GLOBALS['TYPO3_DB']->SELECTquery('uid', 'tt_content', '`pid`='.$pids.$hidden.' AND `deleted`=0 AND `sys_language_uid`='.$params['slang'].$sqlCType));
					//$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid', 'tt_content', '`pid`='.$pids.$hidden.' AND `deleted`=0 AND `sys_language_uid`='.$params['slang']);
					while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
						$affectedCeArr[] .= $row['uid'];
					}
				}
				//print_r($affectedCeArr); die;// Debug

				// subtract already localized content elements (checking if there is a CE with l18n_parent equal to source CE)
				//!!!!! check param locCe
				if (($params['ignLocCe'] == 1) && (!empty($affectedCeArr))) {
					foreach ($affectedCeArr as $ceids) {
						$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid', 'tt_content', '`l18n_parent`='.$ceids.' AND `sys_language_uid`='.$params['tlang']);
						while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
							$affectedLocCeArr[] .= $row['uid'];
						}
						//print_r($affectedLocCeArr); // Debug
					}
					if ((is_array($affectedLocCeArr)) && (!empty($affectedLocCeArr))) {
						$difference = array_diff($affectedCeArr, $affectedLocCeArr);
						$affectedCeArr = $difference;
					}
					//print_r($affectedCeArr); // Debug
				}
				elseif (empty($affectedCeArr)) {
					$errorMsg .= 'CONTENT::'.$LANG->getLL('w_noLocCes').'<br/>';
				} 
				if (!empty($affectedCeArr)) {
					$string = join(', ', $affectedCeArr);
					// getLocData2XML
					if (PHP_VERSION < 5) {
						$ceLocData = $this->getLocData($params, $affectedCeArr, $table);
					} else {
						$ceLocData = $this->getLocData2($params, $affectedCeArr, $table);
					}
					//print_r($affectedCeArr); // Debug

					if (!empty($ceLocData['errorMsg'])) {
						$errorMsg .= 'CONTENT::'.$ceLocData['errorMsg'];
					}
				}
				$num = count($affectedCeArr);
				$expCeRes .= "$num $unhidden ".$LANG->getLL('locCe').':<br/>'.$string.'<br/>'; // affected content elements

			} else {
				$errorMsg .= 'CONTENT::'.$LANG->getLL('w_noLocPages').'<br/>';
			}
			$out['expCeRes'] = $expCeRes;
			$out['errorMsg'] = $errorMsg;
			$out['filename'] = $ceLocData['filename'];
            //print_r($out);die;
			return $out;
		}

		/**
		 * Export localizable data from table pages to file
		 *
		 * @param	array		$params: Array of parameters (id,hidden,recursion,locPages,...)
		 * @param	array		$affectedPagesArr: Array of affected page IDs
		 * @return	array		$out: Returns an array of two arrays: $out[0]=Messages to be printed out, $out[1]=affected page IDs, $out['errorMsg']= error messages, $out['files']=filename for later zipping
		 */
		function exportPagesData($params,$affectedPagesArr) {
			global $LANG;

			if ($params['slang'] == '0') {
				$table = 'pages';
			} else {
				$table = 'pages_language_overlay';
			}

			if ((is_array($affectedPagesArr)) && (!empty($affectedPagesArr))) {
				sort($affectedPagesArr, SORT_NUMERIC);
				if ($params['hidden'] == 1) {
					// remove hidden pages from array ==> not needed anymore
					// $affectedPagesArr = $this->subtractHiddenPages($affectedPagesArr);
					$unhidden = ' '.$LANG->getLL('unhid').' ';
				}
				$num = count($affectedPagesArr);
				$string = join(', ', $affectedPagesArr);
				$report .= "$num $unhidden ".$LANG->getLL('locPages').':<br/> '.$string.'<br/>'; // affected pages
				// get localisable data from table pages
				if (PHP_VERSION < 5) {
					$pagesLocData = $this->getLocData($params, $affectedPagesArr, $table); 
				} else {
					$pagesLocData = $this->getLocData2($params, $affectedPagesArr, $table); 
				}
				if (!empty($pagesLocData['errorMsg'])) {
					$errorMsg .= 'PAGES::'.$pagesLocData['errorMsg'];
				}
			} else {
				$errorMsg .= 'PAGES::'.$LANG->getLL('w_noLocPages').'<br/>';
			}
			$out['report'] = $report;
			$out['errorMsg'] = $errorMsg;
			$out['filename'] = $pagesLocData['filename'];

			return $out;
		}

		/**
		 * Check whether a page is hidden
		 *
		 * @param	integer		$id: page ID
		 * @return	integer		$hidden: Returns 0 or 1
		 */
		function pageIsHidden($id) {
			if ($id > 0) {
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('hidden', 'pages', ' uid = '.$id.' AND deleted=0 ');
			}
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$hidden = $row['hidden'];
			}
			return $hidden;
		}

		/**
		 * Prints XHTML form with export options
		 *
		 * @param	array		$params: export parameters
		 * @return	string		$form: Returns the XHTML form code
		 */
		function printOpts($params) {
			global $LANG;
			global $TODO;

			if (isset($params['id'])) {
				$id = $params['id'];
			} elseif (isset($_GET['id'])) {
				$id = t3lib_div::_GET('id');
			}

			$form .= '<h3 class="uppercase">'.$LANG->getLL('opts').':</h3>';
			$form .= '<div id="ddtabs" class="basictab">
				<ul>
				<li><a onClick="expandcontent(\'sc1\', this)">'.$LANG->getLL('general').'</a></li>
				<li><a onClick="expandcontent(\'sc2\', this)">'.$LANG->getLL('pages').'</a></li>
				<li><a onClick="expandcontent(\'sc3\', this)">'.$LANG->getLL('ces').'</a></li>';
			if (t3lib_extMgm::isLoaded('tt_news')) {
				$form .= '<li><a onClick="expandcontent(\'sc4\', this)">News</a></li>';
			}
			$form .= '<li><a onClick="expandcontent(\'sc5\', this)">'.$LANG->getLL('help').'</a></li>
				</ul>
				</div>

				<div id="tabcontentcontainer">';
			$form .= '<form action="'.t3lib_div::getIndpEnv(TYPO3_REQUEST_HOST).t3lib_div::linkThisScript($getParams=array('id'=>'','SET'=>'')).'" name="" enctype="multipart/form-data" method="POST" accept-charset="utf-8">'; 

			$form .= '<div id="sc1" class="tabcontent">';
			$form .= '<table class="printOpts"><colgroup><col width="50%" /><col width="50%" /></colgroup>';
			$form .= '<tr><td colspan="2">';
			$form .= '<div><strong>'.$LANG->getLL('optGen').'</strong></div>';
			$form .= '<div>'.$LANG->getLL('seeOtherTabs').'<br/><br/></div>';
			$form .= '</td><td></td></tr>';
			$form .= '<tr><td class="printOpts">';
			$form .= $LANG->getLL('slang').': '.$this->displaySysLanguageSelection('slang', $params).'<br/>';
			$form .= $LANG->getLL('tlang').': '.$this->displaySysLanguageSelection('tlang', $params).'<br/>';
			$form .= $LANG->getLL('startPid').': <input type="text" name="id" value="'.$id.'" size="5" /><br/>';
			$form .= '<br/>';
			if ((empty($_POST)) || ($params['hidden'] == 1)) {
				$hidden = ' checked="checked"';
			}
			$form .= '<input type="checkbox" name="hidden" value="1" '.$hidden.' /> '.$LANG->getLL('ignHidPag').'<br/>';
			if ($params['recursion'] == 1) {
				$recursion = ' checked="checked"';
			}
			$form .= '<input type="checkbox" name="recursion" value="1" '.$recursion.' /> '.$LANG->getLL('expRec').'<br/>';
			if ($params['zipOwrite'] == 1) {
				$zipOwrite = ' checked="checked"';
			}
			$form .= '<input type="checkbox" name="zipOwrite" value="1" '.$zipOwrite.' /> '.$LANG->getLL('label_zipOwrite').'<br/>';
			if (extension_loaded('tidy')) {
				if (($params['tidy']==1) || (empty($params['submit']))) {
					$tidy = ' checked="checked"';
				}
				$form .= '<input type="checkbox" name="tidy" value="1" '.$tidy.' /> '.$LANG->getLL('tidySupport').' (default)<br/>';
			} else {
				$form .= '<span class="warning">'.$LANG->getLL('tidyUnavailable').'</span><br/>';
			}
			$form .= '</td>';
			$form .= '<td class="printOpts">';
			$form .= '<strong>'.$LANG->getLL('configFiles').'</strong><br />';
			if ($params['sdl'] == 1) {
				$sdl = ' checked="checked"';
			}
			$form .= '<input type="checkbox" name="sdl" value="1" '.$sdl.' /> '.$LANG->getLL('sdlIni').'<br/>';
			if ($params['passolo'] == 1) {
				$passolo = ' checked="checked"';
			}
			$form .= '<input type="checkbox" name="passolo" value="1" '.$passolo.' /> '.$LANG->getLL('passoloXFG').'<br/>';
			$form .= '</td>';
			$form .= '</tr></table>';
			$form .= '</div>';
			$form .= '<div id="sc2" class="tabcontent">';
			$form .= '<table class="printOpts"><colgroup><col width="35%" /><col width="30%" /><col width="35%" /></colgroup>';
			$form .= '<tr><td>';
			$form .= '<div><strong>'.$LANG->getLL('optPa').'</strong><br/><br/></div>';
			$form .= '</td><td></td><td></td></tr>';
			$form .= '<tr><td class="printOpts">';
			if ($params['paExport'] == 1) {
				$paExport = ' checked="checked"';
			}
			$form .= '<input type="checkbox" name="paExport" value="1" '.$paExport.' /> '. $LANG->getLL('expPages').'<br/>';
			$form .= '<input type="checkbox" name="deleted" value="1" checked="checked" /> '.$LANG->getLL('ignDelPag').' (default)<br/>';
			if ((empty($params['locPages']) && ($params['submit'] !=1)) || ($params['locPages'] == 1)) {
				$locPages = 'checked="checked"';
			}
			$form .= '<input type="checkbox" name="locPages" value="1" '.$locPages.' /> '.$LANG->getLL('ignLocPag').' (default)<br/>';
			if ($params['paOwrite'] == 1) {
				$paOwrite = ' checked="checked"';
			}
			$form .= '<input type="checkbox" name="paOwrite" value="1" '.$paOwrite.' /> '.$LANG->getLL('owPaFile').'<br/>';
			$form .= '</td>';
			$form .= '<td class="printOpts">';
			$form .= '<strong>'.$LANG->getLL('locPTypes').'</strong><br />';
			// available page types
			foreach ($params['sysDoktypes'] as $key => $value) {
				$checked = '';
				if (!empty($params['doktypes'])) {
					if (in_array($key, $params['doktypes'])) {
						$checked = ' checked="checked"';
					}
				} else {
					$checked = ' checked="checked"';
				}
				$form .= '<input type="checkbox" name="doktypes[]" value="'.$key.'" '.$checked.' /> '.$value.'<br/>';
			}
			//sysLocPaFields
			$form .= '</td>';
			$form .= '<td class="printOpts">';
			$form .= '<strong>'.$LANG->getLL('locPaFields').'</strong><br />';
			// available fields from table pages, pages_language_overlay
			foreach ($params['sysLocPaFields'] as $key => $value) {
				$checked = '';
				if (!empty($params['locPaFields'])) {
					if (in_array($value, $params['locPaFields'])) {
						$checked = ' checked="checked"';
					}
				} else {
					$checked = ' checked="checked"';
				}
				$form .= '<input type="checkbox" name="locPaFields[]" value="'.$value.'" '.$checked.' /> '.$value.'<br/>';
			}
			$form .= '</td>';
			$form .= '</tr>';
			$form .= '</table>';
			$form .= '</div>';

			$form .= '<div id="sc3" class="tabcontent">';
			$form .= '<table class="printOpts"><colgroup><col width="35%" /><col width="30%" /><col width="35%" /></colgroup>';
			$form .= '<tr><td colspan="3">';
			$form .= '<div><strong>'.$LANG->getLL('optCe').'</strong><br/><br/></div>';
			$form .= '</td><td></td><td></td></tr>';
			$form .= '<tr><td class="printOpts">';
			if ($params['ceExport'] == 1) {
				$ceExport = ' checked="checked"';
			}
			$form .= '<input type="checkbox" name="ceExport" value="1" '.$ceExport.' /> '.$LANG->getLL('expCe').'<br/>';
			if ($params['ceHidden'] == 1) {
				$ceHidden = ' checked="checked"';
			}
			$form .= '<input type="checkbox" name="ceHidden" value="1" '.$ceHidden.' /> '.$LANG->getLL('ignHidCe').'<br/>';
			$form .= '<input type="checkbox" name="ceDeleted" value="1" checked="checked" /> '.$LANG->getLL('ignDelCe').' (default)<br/>';
			if ((empty($_POST)) || ($params['ignLocCe'] == 1)) {
				$locCe = 'checked="checked"';
			}
			$form .= '<input type="checkbox" name="ignLocCe" value="1" '.$locCe.' /> '.$LANG->getLL('ignLocCe').' (default)<br/>';
			if ($params['ceOwrite'] == 1) {
				$ceOwrite = ' checked="checked"';
			}
			$form .= '<input type="checkbox" name="ceOwrite" value="1" '.$ceOwrite.' /> '.$LANG->getLL('owCeFile').'<br/>';
			$form .= '</td>';
			$form .= '<td class="printOpts">';
			$form .= '<strong>'.$LANG->getLL('locCeTypes').'</strong><br />';
			// localizable content element types
			foreach ($params['sysLocCeTypes'] as $key => $value) {
				$checked = '';
				if (!empty($params['locCeTypes'])) {
					if (in_array($value, $params['locCeTypes'])) {
						$checked = ' checked="checked"';
					}
				} else {
					$checked = ' ';
				}
				$form .= '<input type="checkbox" name="locCeTypes[]" value="'.$value.'" '.$checked.' /> '.$value.'<br/>';
			}
			$form .= '</td>';
			$form .= '<td class="printOpts">';
			$form .= '<strong>'.$LANG->getLL('locCeFields').'</strong><br />';
			// localizable fields in tt_conent // TODO: load dynamically from db table
			foreach ($params['sysLocCeFields'] as $key => $value) {
				$checked = '';
				if (!empty($params['locCeFields'])) {
					if (in_array($value, $params['locCeFields'])) {
						$checked = ' checked="checked"';
					}
				} else {
					$checked = '';
				}
				$form .= '<input type="checkbox" name="locCeFields[]" value="'.$value.'" '.$checked.' /> '.$value.'<br/>';
			}
			$form .= '</td>';
			$form .= '</tr>';
			$form .= '</table>';
			$form .= '</div>';
			if (t3lib_extMgm::isLoaded('tt_news')) {
				$form .= '<div id="sc4" class="tabcontent">';
				$form .= '<div>'.$LANG->getLL('newsInfo').'<br/><br/></div>';
				$form .= '</div>';
			}
			$form .= '<div id="sc5" class="tabcontent">';
			$helpIndex = t3lib_div::getURL(t3lib_extMgm::extPath('t3_locmanager').'helpIndex.htm');
			$helpIndex = preg_replace('/###HOST###/', t3lib_div::getIndpEnv(TYPO3_SITE_URL), $helpIndex);
			$form .= $helpIndex;
			$form .= '</div>';
			$form .= '<input type="hidden" name="submit" value="1" />';
			$form .= '<input type="hidden" name="SET[function]" value="1" />';
			$form .= '<div class="right"><input type="submit" value="'.$LANG->getLL('submit').'" /></div>'; 
			$form .= '</div>';

			$form .= '</form></div><hr />';

			return $form;
		}

		/**
		 * Prints XHTML form with import options
		 *
		 * @param	array		$params: export parameters
		 * @return	string	$form: Returns the XHTML form code
		 */
		function printImportOpts($params) {
			global $LANG;

			$form .= '<h3 class="uppercase">'.$LANG->getLL('opts').':</h3>';
			$form .= '<div id="ddtabs" class="basictab">
				<ul>
				<li><a onClick="expandcontent(\'sc1\', this)">'.$LANG->getLL('opts').'</a></li>
				<li><a onClick="expandcontent(\'sc5\', this)">'.$LANG->getLL('help').'</a></li>
				</ul>
				</div>

				<div id="tabcontentcontainer">';
			$form .= '<form action="'.t3lib_div::getIndpEnv(TYPO3_REQUEST_HOST).t3lib_div::linkThisScript($getParams=array('id'=>'','SET'=>'')).'" enctype="multipart/form-data" method="POST" accept-charset="utf-8">';  

			$form .= '<div id="sc1" class="tabcontent">';
			$form .= '<table class="printOpts"><colgroup><col width="50%" /><col width="50%" /></colgroup>';
			$form .= '<tr><td colspan="2">';
			$form .= '<div><strong>'.$LANG->getLL('optGenImport').'</strong></div>';
			$form .= $LANG->getLL('uploadFile').' <input name="loc" type="file" size="50" accept="application/zip" /><br/>';
			$form .= '</td><td></td></tr>';
			$form .= '<tr><td class="printOpts">';
			$form .= '<div><strong>'.$LANG->getLL('onImport').'</strong></div>';
			if ($params['hideImpPages'] == 1) {
				$hideImpPages = ' checked="checked"';
			}
			$form .= '<input type="checkbox" name="hideImpPages" value="1" '.$hideImpPages.' /> '.$LANG->getLL('hideImpPages').'<br/>';
			if ($params['hideImpCes'] == 1) {
				$hideImpCes = ' checked="checked"';
			}
			$form .= '<input type="checkbox" name="hideImpCes" value="1" '.$hideImpCes.' /> '.$LANG->getLL('hideImpCes').'<br/>';
			if ($params['overwritePagesInfo'] == 1) {
				$overwritePagesInfo = ' checked="checked"';
			}
			$form .= '<input type="checkbox" name="overwritePagesInfo" value="1" '.$overwritePagesInfo.' /> '.$LANG->getLL('overwritePagesInfo').'<br/>';
			if ($params['overwriteCeInfo'] == 1) {
				$overwriteCeInfo = ' checked="checked"';
			}
			$form .= '<input type="checkbox" name="overwriteCeInfo" value="1" '.$overwriteCeInfo.' /> '.$LANG->getLL('overwriteCeInfo').'<br/>';
			if ($params['remExCes'] == 1) {
				$remExCes = ' checked="checked"';
			}
			$form .= '<input type="checkbox" name="remExCes" value="1" '.$remExCes.' /> '.$LANG->getLL('remExCes').'<br/>';
			$form .= '</td></tr>';
			$form .= '</table>';
			$form .= '</div>';
			$form .= '<div id="sc5" class="tabcontent">';
			$helpIndex = t3lib_div::getURL(t3lib_extMgm::extPath('t3_locmanager').'helpIndex.htm');
			$helpIndex = preg_replace('/###HOST###/', t3lib_div::getIndpEnv(TYPO3_SITE_URL), $helpIndex);
			$form .= $helpIndex;
			$form .= '</div>';
			$form .= '<input type="hidden" name="submit" value="1" />';
			$form .= '<input type="hidden" name="MAX_FILE_SIZE" value="2000000" />';
			$form .= '<input type="hidden" name="SET[function]" value="2" />';
			$form .= '<div class="right"><input type="submit" value="'.$LANG->getLL('upload').'" /></div>'; 
			$form .= '</div>';

			$form .= '</form></div>';
			return $form;
		}

		/**
		 * Subtract already localized pages from affected pages
		 *
		 * @param	array		$affectedPagesArr: page IDs
		 * @param	array		$params: Export parameters
		 * @return	array		$out: IDs of still not localized pages
		 */
		function subtractLocPages($affectedPagesArr, $params) {
			// check in table pages_languages_overlay; no need for checking table pages
			// because overlay without default lang (entry in pages) not possible
			foreach ($affectedPagesArr as $pids) {
				//$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('pid','pages_language_overlay', '`pid`=721 AND `sys_language_uid`=1');
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('pid', 'pages_language_overlay', '`pid`='.$pids.' AND `sys_language_uid`='.$params['tlang']);
				$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				if (!empty($row)) {
					foreach ($row as $field) {
						$localised[] .= $field;
					}
				}
			}
			if (!empty($localised)) {
				$difference = array_diff($affectedPagesArr, $localised);
				$out['affectedPagesArr'] = $difference;
				$out['loc'] = '1';
			} else {
				$out['affectedPagesArr'] = $affectedPagesArr;
				$out['loc'] = '0';
			}
			return $out;
		}

		/**
		 * Get ISO-2-Letter code for sys_language
		 *
		 * @param	string		$sysLangUid as it says
		 * @return	string		$xmlLang: ISO-2-Letter code
		 */
		function sysLang2Iso2Letter ($sysLangUid) {
			$res3 = $GLOBALS['TYPO3_DB']->exec_SELECTquery('static_languages.lg_iso_2', '`static_languages`, `sys_language`', 'sys_language.static_lang_isocode=static_languages.uid AND sys_language.uid='.$sysLangUid, '', '', '1');
			while ($row3 = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res3)) {
				$xmlLang = $row3['lg_iso_2'];
			}
			// If default language has no code associated
			if (empty($xmlLang)){
				$xmlLang = 'default';
			}
			return $xmlLang;
		}

        /**
                 * Clean dirty X/HTML from database
                 * Requires tidy support for PHP!
                 *
                 * @param       string          $dirty: string to be cleaned
                 * @param       array                   $params: settings
                 * @return      string          $clean: Clean XHTML string
                 */
        function tidy ($dirty,$params) {
                        // check for tidy support first
                        if (extension_loaded('tidy') && ($params['tidy']==1)) {
                                $config = array(
                                        'doctype' => 'omit',
                                        'show-body-only' => 1,
                                        'output-xhtml' => 1,
                                        'indent' => 0,
                                        'drop-proprietary-attributes' => 0,
                                        'wrap-attributes' => 0,
                                        'wrap-sections' => 0,
                                        'markup' => 1,
                                        //'bare' => 1,
                                        'quote-marks' => 1,
                                        //'punctuation-wrap' => 0,
                                        'break-before-br' => 0,
                                        'break-after-br' => 0,
                                        //'char-encoding' => 'utf8',
                                        'preserve-entities' => 1,
                                        'wrap' => 0);

                                if (PHP_VERSION < 5) {
                                        tidy_set_encoding('UTF8');
                                        foreach ($config as $key => $value) {
                                                tidy_setopt($key, $value);
                                        }
                                        tidy_parse_string($dirty);
                                        tidy_clean_repair();
                                        $clean = tidy_get_output();
                                        //print tidy_get_error_buffer();
                                } else {
                                        $clean = tidy_repair_string($dirty,$config,"utf8");
                                }
                                return $clean;
                        } else {
                                return $dirty;
                        }
                }


		/**
		 * Get table info
		 *
		 * @param	string		$table: Name of table
		 * @return	string		$fieldInfoArr: Table information
		 */
		function getFieldInfo ($table) {
            $fieldInfoArr = array();
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $table, '', '', '', '1');
/*			for($i = 0; $i < mysql_num_fields($res); $i++) {
				$fieldInfoArr[$i]['name'] .= mysql_field_name($res, $i);
				$fieldInfoArr[$i]['length'] .= mysql_field_len($res, $i);
				$fieldInfoArr[$i]['type'] .= mysql_field_type($res, $i);
			}*/
            $i = 0;
            foreach ($GLOBALS['TCA'][$table]['columns'] as $fieldName => $fieldValue) {

                $fieldInfoArr[$i]['name'] = $fieldName;
                $fieldInfoArr[$i]['length'] = 255;
                $fieldInfoArr[$i]['type'] = $fieldValue['config']['type'];
                $i++;
            }
            $fieldInfoArr[$i]['name'] = 'uid';
            $fieldInfoArr[$i]['length'] = 255;
            $fieldInfoArr[$i]['type'] = 'integer';
            $fieldInfoArr[$i+1]['name'] = 'pid';
            $fieldInfoArr[$i+1]['length'] = 255;
            $fieldInfoArr[$i+1]['type'] = 'integer';
			return $fieldInfoArr;
		}


		/**
		 * Get localizable data from tables in XML
		 *
		 * @param	array		$affectedArr: page IDs or content element IDs
		 * @param	array		$params: export parameters
		 * @param	string		$table: table name
		 * @return	array		$out['filename']: filename of temp XML file, $out['errorMsg']: if temp file not written
		 */
		function getLocData ($params, $affectedArr, $table) {
			global $TYPO3_CONF_VARS;
			global $LANG;
			$parseHTML = t3lib_div::makeInstance("t3lib_parseHTML_proc");
			$filename = 't3LocData_'.$table.'_'.$params['id'].'_'.$params['slang'].'_'.$params['tlang'];
			$tempDir = $params['tempDir'];

			// TODO: read from file2array->flexforms?
			$cDataFields = array('text', 'varchar', 'string', 'blob');

			// start building XML file
			$dom = domxml_new_doc('1.0');
			$dom->append_child($dom->create_comment($LANG->getLL('locDataT3Inst').$TYPO3_CONF_VARS['SYS']['sitename'].$LANG->getLL('exported').date('d/m/Y').' (dd/mm/yyyy)'));
			$dom->append_child($dom->create_comment($LANG->getLL('l10nSource').$this->sysLang2Iso2Letter($params['slang']).$LANG->getLL('l10nTarget').$this->sysLang2Iso2Letter($params['tlang']).$LANG->getLL('l10nStartPid').$params['id']));
			$t3locData = $dom->append_child($dom->create_element('t3_'.$table));
			$t3locData->set_attribute('slang', $params['slang']); 
			$t3locData->set_attribute('tlang', $params['tlang']); 
			$region = $t3locData->append_child($dom->create_element('region'));
			$region->set_attribute('xml:lang', $this->sysLang2Iso2Letter($params['slang'])); 

			// Get field infos
			$fieldInfoArr = $this->getFieldInfo($table); //TODO: TYPO3 function???

			// process page  or content element IDs
			foreach ($affectedArr as $ids) {

				// Set opts
				switch ($table) {
					case 'pages':
						$sql = array('*', $table, '`uid`='.$ids, '', '', '');
						$locFields = $params['locPaFields'];
						$owrite = $params['paOwrite'];
						$errSrc = 'PAGES::';
					break;
					case 'pages_language_overlay':
						$sql = array('*', $table, '`pid`='.$ids.' AND `sys_language_uid`='.$params['slang'], '', '', '');
						$locFields = $params['locPaFields'];
						$owrite = $params['paOwrite'];
						$errSrc = 'PAGES::';
					break;
					case 'tt_content':
						$sql = array('*', $table, '`uid`='.$ids, '', '', '');
						$locFields = $params['locCeFields'];
						$owrite = $params['ceOwrite'];
						$errSrc = 'CONTENT::';
					break;
				}


				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($sql[0], $sql[1], $sql[2], $sql[3], $sql[4], $sql[5]);

				while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
					$dataSet = $region->append_child($dom->create_element('dataSet'));
					$dataSet->set_attribute('id', $row['uid']);
					for ($key = 0, $size = count($fieldInfoArr); $key < $size; $key++) {
						$data[$field] = $dataSet->append_child($dom->create_element($fieldInfoArr[$key]['name']));
						$data[$field]->set_attribute('type', $fieldInfoArr[$key]['type']);
						if (array_search($fieldInfoArr[$key]['name'], $locFields) !== FALSE) {
							$data[$field]->set_attribute('max-len', $fieldInfoArr[$key]['length']);
							$data[$field]->set_attribute('localizable', '1');
							$data[$field]->set_attribute('cdata', '1');
							$content = $row[$fieldInfoArr[$key]['name']];
							if ($fieldInfoArr[$key]['name'] == 'bodytext') {

								$cleanContent = $parseHTML->TS_images_rte($content);
								$cleanContent = $parseHTML->TS_links_rte($cleanContent);
								$cleanContent = $parseHTML->TS_transform_rte($cleanContent,$css=1); // which mode is best?


								// Tidy content if available 
								if (extension_loaded('tidy')) {
									$cleanContent = preg_replace('/\n/is', '<br/><br/>', $cleanContent);
									$cleanContent = $this->tidy($cleanContent,$params);
									$cleanContent = preg_replace('/\n/is', '', $cleanContent);
									$cleanContent = preg_replace('/<br \/><br \/>/is', "\n", $cleanContent); //Problem with newlines first removed by tidy then by TagEditor
								} 
							} else {
								// Substitute &
								$cleanContent = preg_replace('/&/s', '&amp;', $content);
							}
							$data[$field]->append_child($dom->create_text_node($cleanContent));
						} else {
							if (array_search($fieldInfoArr[$key]['type'], $cDataFields) != '') {
								$data[$field]->append_child($dom->create_cdata_section($row[$fieldInfoArr[$key]['name']]));
							} else {
								$data[$field]->append_child($dom->create_text_node($row[$fieldInfoArr[$key]['name']]));
							}

						}
					}
				}
			}
			if ($params['sdl']==1) {
				// Generate SDLTRADOS.ini
				$ext['.ini']=$this->mkSdlIni($fieldInfoArr,$locFields,$cDataFields,$table);
			}

			if ($params['passolo']==1) {
				// make PASSOLO rule
				if (PHP_VERSION < 5) {
					$ext['.xfg']=$this->mkP6xfg($fieldInfoArr,$locFields,$table);
				} else {
					$ext['.xfg']=$this->mkP6xfg2($fieldInfoArr,$locFields,$table);
				}
			}

			$xml = $dom->dump_mem(true, 'UTF-8');
			// Here you can add your own entity definitions
			$xml = preg_replace('/(<\?xml version="1.0" encoding="UTF-8"\?>)/', '${0}'."\n<!DOCTYPE t3_".$table.' [ <!ENTITY nbsp " "> ]>', $xml, '1');
			$ext['.xml'] = html_entity_decode($xml);
//print $ext['.xml'];
			// check if tempDir exists else create it
			if (!is_dir($tempDir)) {
				t3lib_div::mkdir($tempDir);
				if (!@is_dir($tempDir)) {
					$errorMsg .= $errorSrc.'WRITE: Could not create directory '.$tempDir;
				}
			}
			// write XML, INI and XFG file
			foreach ($ext as $key => $value) {
				if ((file_exists($tempDir.$filename.$key) && $owrite == '1') || (!file_exists($tempDir.$filename.$key))) {
					$errorMsg .= t3lib_div::writeFileToTypo3tempDir($tempDir.$filename.$key, $value);
				} else {
					$errorMsg .= $errorSrc.$LANG->getLL('w_fileExists').'<br/>';
				}
			}
			// Previous hack: Obsolete as everything should already be XML- conform

				/* $xml = preg_replace('/&amp;amp;/', '&amp;', $xml);
				$xml = preg_replace('/&amp;gt;/', '>', $xml);
				$xml = preg_replace('/&amp;lt;/', '<', $xml);
				$xml = preg_replace('/&lt;/', '<', $xml);
				$xml = preg_replace('/&gt;/', '>', $xml);*/
				//$xml = preg_replace('/cdata="1">\<!\[CDATA\[/', 'cdata="1">', $xml); //<![CDATA[
				//$xml = preg_replace('/\]\]>/', '', $xml);

			$out['filename'] = $filename;
			$out['errorMsg'] = $errorMsg;
			return $out;
		}

		/**
		 * Get localizable data from tables in XML (PHP5 version)
		 *
		 * @param	array		$affectedArr: page IDs or content element IDs
		 * @param	array		$params: export parameters
		 * @param	string		$table: table name
		 * @return	array		$out['filename']: filename of temp XML file, $out['errorMsg']: if temp file not written
		 */
		function getLocData2 ($params, $affectedArr, $table) {
			global $TYPO3_CONF_VARS;
			global $LANG;
			$parseHTML = t3lib_div::makeInstance("t3lib_parseHTML_proc");
			$filename = 't3LocData_'.$table.'_'.$params['id'].'_'.$params['slang'].'_'.$params['tlang'];
			$tempDir = $params['tempDir'];

			// TODO: read from file2array->flexforms?
			$cDataFields = array('text', 'varchar', 'string', 'blob');

			// start building XML file
			$dom = new DOMDocument('1.0', 'utf-8');
			$dom->appendChild($dom->createComment($LANG->getLL('locDataT3Inst').$TYPO3_CONF_VARS['SYS']['sitename'].$LANG->getLL('exported').date('d/m/Y').' (dd/mm/yyyy)'));
			$dom->appendChild($dom->createComment($LANG->getLL('l10nSource').$this->sysLang2Iso2Letter($params['slang']).$LANG->getLL('l10nTarget').$this->sysLang2Iso2Letter($params['tlang']).$LANG->getLL('l10nStartPid').$params['id']));
			$t3locData = $dom->appendChild($dom->createElement('t3_'.$table));
			$t3locData->setAttribute('slang', $params['slang']); 
			$t3locData->setAttribute('tlang', $params['tlang']); 
			$region = $t3locData->appendChild($dom->createElement('region'));
			$region->setAttribute('xml:lang', $this->sysLang2Iso2Letter($params['slang'])); 

			// Get field infos
			$fieldInfoArr = $this->getFieldInfo($table); //TODO: TYPO3 function???
			//$fieldInfoArr = $GLOBALS['TCA'][$table]['columns'];
            //print_r($fieldInfoArr);die;

			// process page  or content element IDs
			foreach ($affectedArr as $ids) {

				// Set opts
				switch ($table) {
					case 'pages':
						$sql = array('*', $table, '`uid`='.$ids, '', '', '');
						$locFields = $params['locPaFields'];
						$owrite = $params['paOwrite'];
						$errSrc = 'PAGES::';
					break;
					case 'pages_language_overlay':
						$sql = array('*', $table, '`pid`='.$ids.' AND `sys_language_uid`='.$params['slang'], '', '', '');
						$locFields = $params['locPaFields'];
						$owrite = $params['paOwrite'];
						$errSrc = 'PAGES::';
					break;
					case 'tt_content':
						$sql = array('*', $table, '`uid`='.$ids, '', '', '');
						$locFields = $params['locCeFields'];
						$owrite = $params['ceOwrite'];
						$errSrc = 'CONTENT::';
					break;
				}

				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($sql[0], $sql[1], $sql[2], $sql[3], $sql[4], $sql[5]);

				while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {

					$dataSet = $region->appendChild($dom->createElement('dataSet'));
					$dataSet->setAttribute('id', $row['uid']);
					for ($key = 0, $size = count($fieldInfoArr); $key < $size; $key++) {
						$data[$field] = $dataSet->appendChild($dom->createElement($fieldInfoArr[$key]['name']));
						$data[$field]->setAttribute('type', $fieldInfoArr[$key]['type']);
                        $row[$fieldInfoArr[$key]['name']] = htmlentities($row[$fieldInfoArr[$key]['name']]);

						if (array_search($fieldInfoArr[$key]['name'], $locFields) !== FALSE) {
							$data[$field]->setAttribute('max-len', $fieldInfoArr[$key]['length']);
							$data[$field]->setAttribute('localizable', '1');
							$data[$field]->setAttribute('cdata', '1');
							$content = $row[$fieldInfoArr[$key]['name']];
							// Substitute &
							$content = preg_replace('/&nbsp;/s', ' ', $content);
							$content = preg_replace('/&/s', '&amp;', $content);
                            $content = htmlentities($content);

							if ($fieldInfoArr[$key]['name'] == 'bodytext') {



								//Convert all entities to chars 
								// TODO: Use new funct to avoid UTF-8 de-/encoding
								//$content = utf8_decode($content);
								//$content = htmlentities($content);
								//$content = utf8_encode($content);
								// Mask page and file, external & mail links and eventual attributes to make everything XML conform  
								/* $cleanContent = preg_replace('/<(LINK) ([^ >]+)( (\d+x\d+|_top|-|_self|_new|_blank))?( ([^ _\d">]+))?( "([^>]+)")?>/is', '<a name="\2" target="\4" type="\6" title="\8">', $content);
								$cleanContent = preg_replace('/<(LINK) ([^ >]+)( (\d+x\d+|_top|-|_self|_new|_blank))?( ([^ _\d">]+))?( "([^>]+)")?>/is', '<\1 id="\2" target="\4" type="\6" title="\8">', $content); */
								//$cleanContent = preg_replace('/<\/link>/is', '</a>', $cleanContent);
								//$cleanContent = preg_replace('/&/s', '&amp;', $content);
								//$cleanContent = preg_replace('/<br>/is', '<br />', $cleanContent);
								//$cleanContent = t3lib_div::deHSCentities($cleanContent); 

								$cleanContent = $parseHTML->TS_images_rte($content);
								$cleanContent = $parseHTML->TS_links_rte($cleanContent);
								$cleanContent = $parseHTML->TS_transform_rte($cleanContent,$css=1); // which mode is best?



								// Tidy content
								$cleanContent = $this->tidy($cleanContent,$params);
							} else {
								$cleanContent = $content;
							}
							$data[$field]->appendChild($dom->createTextNode($cleanContent));
						} else {
							if (array_search($fieldInfoArr[$key]['type'], $cDataFields) != '') {
								$data[$field]->appendChild($dom->createCDATASection($row[$fieldInfoArr[$key]['name']]));
							} else {
								$data[$field]->appendChild($dom->createTextNode($row[$fieldInfoArr[$key]['name']]));
							}

						}
					}

				}
			}
			if ($params['sdl']==1) {
				// Generate SDLTRADOS.ini
				$ext['.ini']=$this->mkSdlIni($fieldInfoArr,$locFields,$cDataFields,$table);
			}

			if ($params['passolo']==1) {
				// make PASSOLO rule
				if (PHP_VERSION < 5) {
					$ext['.xfg']=$this->mkP6xfg($fieldInfoArr,$locFields,$table);
				} else {
					$ext['.xfg']=$this->mkP6xfg2($fieldInfoArr,$locFields,$table);
				}
			}

			$xml = $dom->saveXML();

			// Here you can add your own entity definitions
			$xml = preg_replace('/(<\?xml version="1.0" encoding="UTF-8"\?>)/', '${0}'."\n<!DOCTYPE t3_".$table.' [ <!ENTITY nbsp " "> ]>', $xml, '1');
			$ext['.xml'] = html_entity_decode($xml);
			// check if tempDir exists else create it
			if (!is_dir($tempDir)) {
				t3lib_div::mkdir($tempDir);
				if (!@is_dir($tempDir)) {
					$errorMsg .= $errorSrc.'WRITE: Could not create directory '.$tempDir;
				}
			}
			// write XML, INI and XFG file
			foreach ($ext as $key => $value) {
				if ((file_exists($tempDir.$filename.$key) && $owrite == '1') || (!file_exists($tempDir.$filename.$key))) {
					$errorMsg .= t3lib_div::writeFileToTypo3tempDir($tempDir.$filename.$key, $value);
				} else {
					$errorMsg .= $errorSrc.$LANG->getLL('w_fileExists').'<br/>';
				}
			}
			// Previous hack: Obsolete as everything should already be XML- conform

				/* $xml = preg_replace('/&amp;amp;/', '&amp;', $xml);
				$xml = preg_replace('/&amp;gt;/', '>', $xml);
				$xml = preg_replace('/&amp;lt;/', '<', $xml);
				$xml = preg_replace('/&lt;/', '<', $xml);
				$xml = preg_replace('/&gt;/', '>', $xml);*/
				//$xml = preg_replace('/cdata="1">\<!\[CDATA\[/', 'cdata="1">', $xml); //<![CDATA[
				//$xml = preg_replace('/\]\]>/', '', $xml);

			$out['filename'] = $filename;
			$out['errorMsg'] = $errorMsg;
			return $out;
		}

		/**
		 * Make SDL INI
		 *
		 * @param	array		$fieldInfoArr: Information about table fields
		 * @param	array		$locFields: Localisable table fields
		 * @param	array		$params: export parameters
		 * @return	string		$sdlIni: SDL ini
		 */
		function mkSdlIni($fieldInfoArr,$locFields,$cDataFields,$table) {
				$sdlIniSubstEnd='--end--';
				$sdlIniSubst='';
				$tagId=147;
				$sdlIniSubst='Tag146=t3_'.$table.":External\n";
				for ($key = 0, $size = count($fieldInfoArr); $key < $size; $key++) {
					if (array_search($fieldInfoArr[$key]['name'], $locFields) !== FALSE) {
						$sdlIniSubst.='Tag'.$tagId.'='.$fieldInfoArr[$key]['name'].':External,Other Attributes:localizable,max-len,type,cdata,TRADOS:AlwaysTranslate'."\n";
					} else {
						if (array_search($fieldInfoArr[$key]['type'], $cDataFields) != '') {
							$sdlIniSubst.='Tag'.$tagId.'='.$fieldInfoArr[$key]['name'].':External,Group,CDATA,Other Attributes:max-len,type'."\n";
						} else {
							$sdlIniSubst.='Tag'.$tagId.'='.$fieldInfoArr[$key]['name'].':External,Group,Other Attributes:max-len,type'."\n";
						}
					}
					$tagId++;
				}
				$tagId+1;
				$sdlIniSubst.='Tag'.$tagId.'='.$sdlIniSubstEnd."\n";
				if ($table == "tt_content") {
					$sdlIniTemplate=t3lib_div::getURL('../SDLTRADOS_template_content.ini');
				} elseif ($table == "pages_language_overlay") {
					$sdlIniTemplate=t3lib_div::getURL('../SDLTRADOS_template_pages.ini');
				} else {
					$sdlIniTemplate=t3lib_div::getURL('../SDLTRADOS_template.ini');
				}
				$sdlIni=preg_replace('/###T3TAGS###/', $sdlIniSubst, $sdlIniTemplate);
			return $sdlIni;
		}

		/**
		 * Make PASSOLO XFG rule
		 *
		 * @param	array		$fieldInfoArr: Information about table fields
		 * @param	array		$locFields: Localisable table fields
		 * @param	array		$params: export parameters
		 * @return	string		$p6xfg: XML configuration string
		 */
		function mkP6xfg($fieldInfoArr,$locFields,$table) {
			//XML
			// start building XML file // TODO: Compatibility with PHP5 XML functs, maybe generate XML the old fashioned way...
			$dom = domxml_new_doc('1.0');
			$cxmlRules = $dom->append_child($dom->create_element('CXMLRules'));
			$ruleList = $cxmlRules->append_child($dom->create_element('RuleList'));
			$cxmlRule = $ruleList->append_child($dom->create_element('CXMLRule'));
			$cxmlRule->set_attribute('m_strName', $table); 
			$rootElements = $cxmlRule->append_child($dom->create_element('RootElements'));
			$rootElement = $rootElements->append_child($dom->create_element('RootElement'));
			$rootElement->set_attribute('Name', 't3_'.$table); 
			$resTypes = $cxmlRule->append_child($dom->create_element('ResTypes'));
			$ruleData = $cxmlRule->append_child($dom->create_element('RuleData'));
			$cxmlLanguage = $ruleData->append_child($dom->create_element('CXMLLanguage'));
			$cxmlLanguage->set_attribute('ElementName', 'region'); 
			$cxmlLanguage->set_attribute('LanguageAttributeName', 'xml:lang'); 
			$cxmlLanguage->set_attribute('LanguageCoding', '3'); // 3=ISO 639-1 (z.B. "fr")
			$cxmlGroup = $ruleData->append_child($dom->create_element('CXMLGroup'));
			$cxmlGroup->set_attribute('ElementName', 'dataSet'); 
			$cxmlGroup->set_attribute('IDAttributeName', 'id'); 
			$cxmlGroup->set_attribute('HandlingOfGroups', '1'); // 1=Neue Ressource erzeugen , Gruppierung nach dataSets
			for ($key = 0, $size = count($fieldInfoArr); $key < $size; $key++) {
				if (array_search($fieldInfoArr[$key]['name'], $locFields) !== FALSE) {
					// localizable
					$cxmlData = $ruleData->append_child($dom->create_element('CXMLData'));
					$cxmlData->set_attribute('ElementName', $fieldInfoArr[$key]['name']); 
					$cxmlData->set_attribute('IDAttributeName', '../attribute::id'); 
					$cxmlData->set_attribute('TElementName', ''); 
					$cxmlData->set_attribute('IsTElement', 'False'); 
					$cxmlData->set_attribute('HandlingOfWhiteSpaces', '0'); 
					$cxmlData->set_attribute('HandlingOfEmbeddedElements', '0'); 
					$cxmlData->set_attribute('AppendElementNameToID', 'True'); 
					if ($fieldInfoArr[$key]['name']!='bodytext') {
						$segmenter='False';
					} else {
						$segmenter='True';
					}
					$cxmlData->set_attribute('UseSegmenter', $segmenter); 
					$attributes = $cxmlData->append_child($dom->create_element('Attributes'));
					$cxmlAttributes = $attributes->append_child($dom->create_element('CXMLAttribute'));
					$cxmlAttributes->set_attribute('AttributeName', 'max-len'); 
					$cxmlAttributes->set_attribute('CopyAttributeName', 'False'); 
					$cxmlAttributes->set_attribute('AttributeAction', '6'); 
				}
			}
			$p6xfg = $dom->dump_mem(true, 'UTF-16'); // PASSOLO 6 Rules are UTF-16!
			
			return $p6xfg;
		}

		/**
		 * Make PASSOLO XFG rule (PHP5)
		 *
		 * @param	array		$fieldInfoArr: Information about table fields
		 * @param	array		$locFields: Localisable table fields
		 * @param	array		$params: export parameters
		 * @return	string		$p6xfg: XML configuration string
		 */
		function mkP6xfg2($fieldInfoArr,$locFields,$table) {
			//XML
			// start building XML file // TODO: Compatibility with PHP5 XML functs, maybe generate XML the old fashioned way...
			$dom = new DOMDocument('1.0', 'utf-16'); // PASSOLO 6 Rules are UTF-16!
			$cxmlRules = $dom->appendChild($dom->createElement('CXMLRules'));
			$ruleList = $cxmlRules->appendChild($dom->createElement('RuleList'));
			$cxmlRule = $ruleList->appendChild($dom->createElement('CXMLRule'));
			$cxmlRule->setAttribute('m_strName', $table); 
			$rootElements = $cxmlRule->appendChild($dom->createElement('RootElements'));
			$rootElement = $rootElements->appendChild($dom->createElement('RootElement'));
			$rootElement->setAttribute('Name', 't3_'.$table); 
			$resTypes = $cxmlRule->appendChild($dom->createElement('ResTypes'));
			$ruleData = $cxmlRule->appendChild($dom->createElement('RuleData'));
			$cxmlLanguage = $ruleData->appendChild($dom->createElement('CXMLLanguage'));
			$cxmlLanguage->setAttribute('ElementName', 'region'); 
			$cxmlLanguage->setAttribute('LanguageAttributeName', 'xml:lang'); 
			$cxmlLanguage->setAttribute('LanguageCoding', '3'); // 3=ISO 639-1 (z.B. "fr")
			$cxmlGroup = $ruleData->appendChild($dom->createElement('CXMLGroup'));
			$cxmlGroup->setAttribute('ElementName', 'dataSet'); 
			$cxmlGroup->setAttribute('IDAttributeName', 'id'); 
			$cxmlGroup->setAttribute('HandlingOfGroups', '1'); // 1=Neue Ressource erzeugen , Gruppierung nach dataSets
			for ($key = 0, $size = count($fieldInfoArr); $key < $size; $key++) {
				if (array_search($fieldInfoArr[$key]['name'], $locFields) !== FALSE) {
					// localizable
					$cxmlData = $ruleData->appendChild($dom->createElement('CXMLData'));
					$cxmlData->setAttribute('ElementName', $fieldInfoArr[$key]['name']); 
					$cxmlData->setAttribute('IDAttributeName', '../attribute::id'); 
					$cxmlData->setAttribute('TElementName', ''); 
					$cxmlData->setAttribute('IsTElement', 'False'); 
					$cxmlData->setAttribute('HandlingOfWhiteSpaces', '0'); 
					$cxmlData->setAttribute('HandlingOfEmbeddedElements', '0'); 
					$cxmlData->setAttribute('AppendElementNameToID', 'True'); 
					if ($fieldInfoArr[$key]['name']!='bodytext') {
						$segmenter='False';
					} else {
						$segmenter='True';
					}
					$cxmlData->setAttribute('UseSegmenter', $segmenter); 
					$attributes = $cxmlData->appendChild($dom->createElement('Attributes'));
					$cxmlAttributes = $attributes->appendChild($dom->createElement('CXMLAttribute'));
					$cxmlAttributes->setAttribute('AttributeName', 'max-len'); 
					$cxmlAttributes->setAttribute('CopyAttributeName', 'False'); 
					$cxmlAttributes->setAttribute('AttributeAction', '6'); 
				}
			}
			$p6xfg = $dom->saveXML(); 
			
			return $p6xfg;
		}


		/**
		 * Display sys_language information overview in xhtml
		 *
		 * @return	string		$sysLanguageInfo: sys_language info in XHTML
		 */
		function displaySysLanguageInfo () {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid,pid,title,static_lang_isocode,flag', 'sys_language', 'hidden=0', 'uid', '', '');
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$sysLanguageInfo .= '<img '.t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'],'gfx/flags/'.$row['flag'],'').' alt="'.$this->resolveStaticLangIsocode($row['static_lang_isocode']).'" /> '.$row['uid'].'&nbsp;&nbsp; - '.$row['title'].'&nbsp;&nbsp; ('.$this->resolveStaticLangIsocode($row['static_lang_isocode']).')';
				$sysLanguageInfo .= '<br/>';
			}
			return $sysLanguageInfo;
		}


		/**
		 * Display sys_language selection options
		 *
		 * @param	string		$name: 'slang' or 'tlang'
		 * @param	[type]		$params: ...
		 * @return	string		$sysLanguageSelection: sys_language selection options in XHTML
		 */
		function displaySysLanguageSelection ($name, $params) {
			global $LANG;
			$postname = $params[$name];
			$sysLanguageSelection .= '<select name="'.$name.'" size="1"><option value="">'.$LANG->getLL('plSel').'</option>';
			// select available system languages
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid,pid,title,static_lang_isocode,flag', 'sys_language', 'hidden=0', 'uid');
			$sysLangUids = array();
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$sysLangUids[].=$row['uid'];
				$sysLanguageSelection .= '<option value="'.$row['uid'].'" ';
				if ($row['uid'] == $postname) {
					$sysLanguageSelection .= 'selected="selected" ';
				}
				$sysLanguageSelection .= '>'.$row['title'].'&nbsp;('.$this->resolveStaticLangIsocode($row['static_lang_isocode']).') </option>';
			}
			// If default language not in DB
			if (!in_array('0',$sysLangUids)) {
				$sysLanguageSelection .= '<option value="0" ';
				if ($postname == "0") {
					$sysLanguageSelection .= 'selected="selected" ';
				}
				$sysLanguageSelection .= '>'.$LANG->getLL('default').'&nbsp;</option>';
			}
			$sysLanguageSelection .= '</select>';
			return $sysLanguageSelection;
		}


		/**
		 * Transform static_lang_isocode into iso2l
		 *
		 * @param	integer		$staticLangIsocode: as it says
		 * @return	string		$iso2l: ISO 2-Letter code
		 */
		function resolveStaticLangIsocode($staticLangIsocode) {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('lg_iso_2,lg_name_en', 'static_languages', 'uid='.$staticLangIsocode , '', '', '1' );
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$iso2l = $row['lg_iso_2'];
			}
			return $iso2l;
		}

		/**
		 * Show exported localization data to keep track of localisation process
 		 *
		 * @return	string		$expInfo: Info on exported data available in uploads/tx_t3locmanager/download/
		 */
		function showExportedLocData ($id) {
			global $LANG;
			$host=t3lib_div::getIndpEnv(TYPO3_SITE_URL);

			$d = dir(PATH_site.'uploads/tx_t3locmanager/download/') or die ($php_errormsg);
			$expInfo .= '<br/><br/>'.$LANG->getLL('fname').': t3_Host_StartPID_SourceSysLanguageID_TargetSysLanguageID<br/>';
			$expInfo .= '<table border="0" cellpadding="0" cellspacing="0" id="typo3-filelist">';
			$expInfo .= '<colgroup><col width="2%" /><col width="38%" /><col width="20%" /><col width="15%" /><col width="5%" /></colgroup>';
			$expInfo .= '<tr>';
			$expInfo .= '<td nowrap="nowrap" class="c-headLine"><strong></strong></td>';
			$expInfo .= '<td nowrap="nowrap" class="c-headLine"><strong>'.$LANG->getLL('fname').'</strong></td>';
			$expInfo .= '<td nowrap="nowrap" class="c-headLine"><strong>'.$LANG->getLL('date').'</strong></td>';
			$expInfo .= '<td nowrap="nowrap" class="c-headLine"><strong>'.$LANG->getLL('size').'</strong></td>';
			$expInfo .= '<td nowrap="nowrap" class="c-headLine"><strong>'.$LANG->getLL('info').'</strong></td>';
			$expInfo .= '</tr>';
			while (false !== ($f = $d->read())) {
				$filePath = $d->path.'/'.$f;
				if ((is_file($filePath)) && (preg_match("/.zip$/", $f))) {
					$expInfo .= '<tr>';
					$expInfo .= '<td><a href="'.$host.'uploads/tx_t3locmanager/download/'.$f.'" title="'.$LANG->getLL('rcldl').'" ><img'.t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'],'gfx/fileicons/zip.gif','').' alt="ZIP" /></a></td>';
					$expInfo .= '<td><a href="'.$host.'uploads/tx_t3locmanager/download/'.$f.'" title="'.$LANG->getLL('rcldl').'" >'.$f.'</a></td>';
					$expInfo .= '<td>'.date('M d Y H:i:s.', filemtime($filePath)).'</td>';
					$expInfo .= '<td>('.t3lib_div::formatSize(filesize($filePath)).'bytes)</td>';
					$expInfo .= '<td><a href="javascript:popup(\''.$host.'uploads/tx_t3locmanager/download/'.basename($f, ".zip").'.html\',\'Report\',\'350\',\'400\',\'yes\')"><img'.t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'],'gfx/info.gif','').' alt="HTML Report" /></a></td>';
					$expInfo .= '</tr>';
				}
			}
			$d->close();
			$expInfo .= '</table>';
			return $expInfo;
		}


		/**
		 * Print module/function description
		 *
		 * @param	integer		$function: ID of called module function
		 * @return	string		$expInfo: Info on exported data available in uploads/tx_t3locmanager/download/
		 */
		function modDesc($function) {
			global $LANG;
			if ($function == '1') {
				$modDesc = $LANG->getLL('funct1Desc');
			} elseif ($function == '2') {
				$modDesc = $LANG->getLL('funct2Desc');
			} elseif ($function == '3') {
				$modDesc = $LANG->getLL('funct3Desc');
			} elseif ($function == '4') {
				$modDesc = $LANG->getLL('funct4Desc');
			} elseif ($function == '5') {
				$modDesc = $LANG->getLL('funct5Desc');
			}
			return $modDesc;
		}

		/**
		 * Print debug information
		 *
		 * @param
		 * @return	string		$debug: Info on GET and POST vars send to the script
		 */
		function debug() {
			$debug = '<br/>This is the GET/POST vars sent to the script:<br/>'. 'GET:'.t3lib_div::view_array($_GET).'<br/>'. 'POST:'.t3lib_div::view_array($_POST).'<br/>';
			return $debug;
		}


		/**
		 * Check parameters before exporting
		 *
		 * @param	array		$params: POST/userPrefs params for controlling the export
		 * @return	array		$checkRes: checkRes['0']=Error messages; checkRes['1']=Parameter error (0|1)
		 */
		function checkParams($params) {
			global $LANG;
			if ($params['slang'] == $params['tlang'] && ($params['slang'] != '')) {
				$errorMsg .= $LANG->getLL('w_sltleq').'<br/>';
				$paramError = '1';
			}
			if (empty($params['slang']) && ($params['slang'] != '0')) {
				$errorMsg .= $LANG->getLL('w_nosl').'<br/>';
				$paramError = '1';
			}
			if (empty($params['tlang']) && ($params['tlang'] != '0')) {
				$errorMsg .= $LANG->getLL('w_nosl').'<br/>';
				$paramError = '1';
			}
			if ($params['slang']) {
				$idErr = $this->checkID($params['id'], $params['slang']);
				if ($idErr == 1) {
					$errorMsg .= $LANG->getLL('w_pidExist').'<br/>';
					$paramError = '1';
				}
			}
			$checkRes['0'] = $errorMsg;
			$checkRes['1'] = $paramError;
            //print_r($checkRes);die;
			return $checkRes;
		}

		/**
		 * Check if page ID is valid for given source language
		 *
		 * @param	integer		$id: Page ID
		 * @param	integer		$slang: ID of source language
		 * @return	integer		$err: 0 if page exists 1 otherwise
		 */
		function checkID($id, $slang) {
			if (($slang == 0) || empty($slang)) {
				$table = 'pages';
				$q = '`uid`='.$id.' AND `deleted`=0';
			} else {
				$table = 'pages_language_overlay';
				$q = '`pid`='.$id.' AND `sys_language_uid`='.$slang.'';
			}
			$res2 = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $table, $q, '', '', '1' );
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res2)) {
				$page = $row['uid'];
			}
			if (empty($page)) {
				return $err = 1;
			} else {
				return $err = 0;
			}
		}

		/**
		 * Print report in HTML and write it to download dir
		 *
		 * @param	array		$params
		 * @param	string	$report
		 * @return	string	$htmlreport
		 */
		function htmlReport($params,$report,$archivname) {
			global $LANG;
			global $BE_USER;
			$settings='<b>'.$LANG->getLL('expUser').'</b>: '.$BE_USER->user['username'].' (UID '.$BE_USER->user['uid'].')<br/>';
			foreach ($params as $key => $value) {
				if (is_array($value) && (!preg_match('/^sys/',$key))) {
					$settings.='<b>'.$LANG->getLL($key).'</b>: '.join(',',$value).'<br/>';
				}
				if (!is_array($value) && (!preg_match('/^submit/',$key))) {
					$settings.='<b>'.$LANG->getLL($key).'</b>: '.$value.'<br/>';
				}
			}
			$htmlReportTemplate=t3lib_div::getURL(t3lib_extMgm::extPath('t3_locmanager').'/report.html');
			$htmlReport=preg_replace('/###DATETIME###/', $LANG->getLL('generated').date('M d Y H:i:s.'), $htmlReportTemplate);
			$htmlReport=preg_replace('/###CLOSE###/', $LANG->getLL('close'), $htmlReport);
			$htmlReport=preg_replace('/###SET###/', $LANG->getLL('set'), $htmlReport);
			$htmlReport=preg_replace('/###EXPORT###/', $LANG->getLL('export'), $htmlReport);
			$htmlReport=preg_replace('/###CSS###/', t3lib_div::getIndpEnv(TYPO3_REQUEST_HOST).'/typo3_src/typo3/stylesheet.css', $htmlReport);
			$htmlReport=preg_replace('/###ICON###/', t3lib_div::getIndpEnv(TYPO3_REQUEST_HOST).'/typo3conf/ext/t3_locmanager/logo-typo3.gif', $htmlReport);
			$htmlReport=preg_replace('/###LOCDATA###/', $report, $htmlReport);
			$htmlReport=preg_replace('/###SETTINGS###/', $settings, $htmlReport);
			$success = t3lib_div::writeFile(PATH_site.'uploads/tx_t3locmanager/download/'.basename($archivname,".zip").'.html', $htmlReport);
			if ($success==0) {
				 $errorMsg .= 'REPORT::'.$LANG->getLL('w_reportNoWrite').'<br/>'; 
			}

			return $errorMsg;
		}

		/**
		 * Zip all files
		 *
		 * @param	array		$params
		 * @param	string	$report
		 * @return	array		$out	Error messages and report
		 */
		function zipLocPackage($params,$errorMsg,$filename,$archivname) {

			global $LANG;

			if ((empty($errorMsg)) && (!empty($filename))) {
				$zip = new tx_t3locmanager_zip();
				foreach ($filename as $file) {
					$ext=array('.xml');
					if ($params['sdl']) {
						$ext[].='.ini';
					}
					if ($params['passolo']) {
						$ext[].='.xfg';
					}
					foreach ($ext as $fType) {
						if (!is_readable($params['tempDir'].$file.$fType)) {
							$out['errorMsg'] .= 'ZIP::'.$LANG->getLL('w_zipCantRead').$params['tempDir'].$file.$fType.'<br />';
						} else { 
							// If no language associated to default language
							$srcLngSubdir=$this->sysLang2Iso2Letter($params['slang']);
							if (empty($srcLngSubdir)) {
								$srcLngSubdir = 'default';
							}
							$zip->addFile(file_get_contents($params['tempDir'].$file.$fType), ($params['downloads'] == '') ? $this->sysLang2Iso2Letter($params['slang']).'/'.$file.$fType : $srcLngSubdir.'/'.$file.$fType, time());
						}
						$zipped = 1;
						//$out['errorMsg'].=$LANG->getLL('zipping').$file.$fType.' ...<br />'; //debug
					}
				}
				$reportFile=$params['downloads'].basename($archivname,'zip').'html';
				if (!is_readable($reportFile)) {
					$out['errorMsg'] .= 'ZIP::'.$LANG->getLL('w_zipCantRead').$reportFile.'<br />';
				} else { 
					$zip->addFile(file_get_contents($reportFile), ($params['downloads'] == '') ? 'REPORT.html' : 'REPORT.html', time());
				}
				// If no language associated to default language
				$tgLngSubdir=$this->sysLang2Iso2Letter($params['tlang']);
				if (empty($tgLngSubdir)) {
					$tgLngSubdir = 'default';
				}
				$zip->addFile('', ($params['downloads'] == '') ? $this->sysLang2Iso2Letter($params['tlang']).'/' : $tgLngSubdir.'/', time());
				if ($zipped == 1) {
					// Add README
					$readme='README.txt';
					$zip->addFile(file_get_contents(t3lib_extMgm::extPath('t3_locmanager').'README.txt'), ($params['downloads'] == '') ? $readme : $readme, time());
					$s = $zip->file();
					if (!file_exists($params['downloads'].$archivname) || ($params['zipOwrite'] == 1)) {
					$success .= t3lib_div::writeFile($params['downloads'].$archivname, $s);
						if ($success==0) {
							 $out['errorMsg'] .= 'ZIP::'.$LANG->getLL('w_zipNoWrite').'<br/>'; 
						}
						$out['report'] .= $LANG->getLL('dl_zip').'<a href="'.t3lib_div::getIndpEnv(TYPO3_SITE_URL).'uploads/tx_t3locmanager/download/'.$archivname.'">'.$archivname.' <img'.t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'],'gfx/fileicons/zip.gif','').' alt="ZIP" /> </a>';
					} else {
						$out['errorMsg'] .= 'ZIP::'.$LANG->getLL('w_zipFileExists').'<br/>';
					}
				}
			}
			return $out;
		}

		/**
		 * Check filetype
		 *
		 * @param	array		$fileArr	Files to be checked
		 * @param	string	$ext	File extension
		 * @return	array		$passed	Files that passed test
		 */
		function checkFType($fileArr,$ext) {
			foreach ($fileArr as $file) {
				if (preg_match('/'.$ext.'$/',$file))  {
					$passed[].=$file;
				}
			}
			return $passed;
		}

		/**
		 * Import Localization data from XML file
		 *
		 * @param	array		$params	Import parameters
		 * @param	string	$xmlFilesArr	XML files
		 * @return	string	$errorMsg	Error messages
		 */
		function importLocFile($params,$xmlFileArr) {
			global $LANG;
			global $BE_USER;
			global $TYPO3_DB;
			$parseHTML = t3lib_div::makeInstance("t3lib_parseHTML_proc");
		// check CDATA
			$errorMsg.= '<table><colgroup><col width="17%" /><col width="83%" /></colgroup>';
			foreach ($xmlFileArr as $xmlfile) {
				$xml = t3lib_div::getURL($xmlfile);
				// make bodytext CDATA section to avoid tag inconsistency (HTML tags) crashing the XML parser and import procedure
				$xml=preg_replace("/<\/bodytext>/s",']]></bodytext>',$xml);
				$xml=preg_replace("/<bodytext ([^>]+)>/s",'<bodytext \1><![CDATA[',$xml);

				$cleanXml = $xml; //Trying without above  14/8/2007
      		$stack = array();

				$xml_parser = xml_parser_create();
				xml_set_element_handler($xml_parser, "startTag", "endTag");
				xml_set_character_data_handler($xml_parser, "cdata");

				$data = @xml_parse($xml_parser,$cleanXml);

				if(!$data) {
					$errorMsg.= '<tr><td class="error">XML</td><td class="error">'.basename($xmlfile).'::'.$LANG->getLL('noValidXml').'</br>';
					$errorMsg.= 'ERROR:: '.xml_error_string(xml_get_error_code($xml_parser));
					$errorMsg.= ' on line '.xml_get_current_line_number($xml_parser).'</td></tr>';
					xml_parser_free($xml_parser);
					continue;
				} else {
					xml_parser_free($xml_parser);
					$dom = domxml_open_mem($cleanXml,DOMXML_LOAD_DONT_KEEP_BLANKS,$error);
					$root = $dom->document_element();
					$srcTable = substr($root->node_name(),3);
					$tlangAttrib = $root->get_attribute_node('tlang');
					$tlang = $tlangAttrib->value();
					$slangAttrib = $root->get_attribute_node('slang');
					$slang = $slangAttrib->value(); 
					//$startPIDAttrib = $root->get_attribute_node('startPID');
					//$startPID = $startPIDAttrib->value(); 
					$dataSets = $dom->get_elements_by_tagname('dataSet');
					$dataSets = $dom->get_elements_by_tagname('dataSet');
// breaks here
					foreach ($dataSets as $dataSet) {
						$text_nodes = $dataSet->child_nodes();
						$insertArr = array();
						$insertFields = array();
						if ($srcTable == 'tt_content') {
							$hide = $params['hideImpCes'];
							$targetTable = $srcTable;
						} elseif (preg_match('/pages/',$srcTable)) {
							$hide = $params['hideImpPages'];
							if ($tlang != 0) {
								$targetTable = 'pages_language_overlay';
							} else {
								$targetTable = 'pages';
							}
						} else {
							$targetTable = $srcTable;
						}
						//print_r ($text_nodes); //Debug
						$l18n_parentAttrib = $dataSet->get_attribute_node('id');
						$l18n_parent= $l18n_parentAttrib->value();
						// Build the insert array from XML
						foreach ($text_nodes as $text) {
							if ($text->node_type() == XML_ELEMENT_NODE) {
								if ($text->node_name() == 'CType') {
									$cType=$text->get_content();
								}
								if ($text->node_name() == 'pid') {
									$pid=$text->get_content();
								}
								if ($text->node_name() == 'bodytext') {
									//print '####';
									//print 'BEFORE: '.$text->get_content();

									$newContent = preg_replace("/<br \/>/s",'__BR__',$text->get_content());
									$newContent = $parseHTML->TS_transform_db($newContent,$css=0); // removes links from content if not called first!
									//$newContent = $parseHTML->TS_transform_db($text->get_content(),$css=0); // removes links from content if not called first!
									$newContent = $parseHTML->TS_images_db($newContent);
									$newContent = $parseHTML->TS_links_db($newContent);
									$newContent = preg_replace("/&nbsp;/s",' ',$newContent);
									$this->replace_content($text, $newContent );
									//print '<br/>####AFTER: '.$text->get_content();
								}
								if ($text->node_name() == 'l18n_parent') {
									//print '####';
									//print 'BEFORE: '.$text->get_content();
									$this->replace_content($text, $l18n_parent );
								} elseif ($text->node_name() == 'uid') {
									$uid=$text->get_content();
									//print '####';
									//print 'BEFORE: '.$text->get_content();
									$this->replace_content($text, '' );
								} elseif ($text->node_name() == 'sys_language_uid') {
									//print '####';
									//print 'BEFORE: '.$text->get_content();
									$this->replace_content($text, $tlang);
									$sysLangSeen = 1;
								} elseif ($text->node_name() == 'hidden') {
									//print '####';
									//print 'BEFORE: '.$text->get_content();
									$this->replace_content($text, intval($hide));
									//print 'AFTER: '.$text->get_content();
								} elseif ($text->node_name() == 'cruser_id') {
									//print '####';
									//print 'BEFORE: '.$text->get_content();
									$this->replace_content($text, $BE_USER->user['uid']);
									//print 'AFTER: '.$text->get_content();
								} elseif (($text->node_name() == 'crdate') || ($text->node_name() == 'tstamp')) {
									//print '####';
									//print 'BEFORE: '.$text->get_content();
									$this->replace_content($text, time());
									//print 'AFTER: '.$text->get_content();
								}
								//print $text->node_name();
								//print '==>'.$text->get_content().' :: ';
								$insertArr[$text->node_name()]=$text->get_content();
								$insertFields[].=$text->node_name();
								// if src_table eq pages ==> sys_language_uid = $tlang
							}
						}
							//Substitute <br/> with <br>
							$insertArr['bodytext'] = preg_replace('/__BR__/is','<br />',$insertArr['bodytext']);
							//$insertArr['bodytext'] = preg_replace('/<br\/>/is','<br>',$insertArr['bodytext']);
							$insertArr['bodytext'] = preg_replace('/&apos;/is','\'',$insertArr['bodytext']);
							$insertArr['bodytext'] = preg_replace('/&quot;/is','"',$insertArr['bodytext']);

							// Reconvert previously masked links if CType != html
							if ($cType != "html") {
								//Remove unwanted linebreaks from tidy
								if (extension_loaded('tidy')) {
									$insertArr['bodytext'] = preg_replace('/\n<\/li>/si','</li>',$insertArr['bodytext']);
									$insertArr['bodytext'] = preg_replace('/<p([^>]+)?>\n/si','<p\1>',$insertArr['bodytext']);
									$insertArr['bodytext'] = preg_replace('/\n<\/p>/si','</p>',$insertArr['bodytext']);
									$insertArr['bodytext'] = preg_replace('/<h([^>]+)?>\n/si','<h\1>',$insertArr['bodytext']);
									$insertArr['bodytext'] = preg_replace('/\n<\/h([^>]+)?>/si','</h\1>',$insertArr['bodytext']);
									$insertArr['bodytext'] = preg_replace('/\n<td([^>]+)?>/si','<td\1>',$insertArr['bodytext']);
									$insertArr['bodytext'] = preg_replace('/\n<\/td>/si','</td>',$insertArr['bodytext']);
									$insertArr['bodytext'] = preg_replace('/\n<tr([^>]+)?>/si','<tr\1>',$insertArr['bodytext']);
									$insertArr['bodytext'] = preg_replace('/\n<\/tr>/si','</tr>',$insertArr['bodytext']);
									$insertArr['bodytext'] = preg_replace('/\n<th([^>]+)?>/si','<th\1>',$insertArr['bodytext']);
									$insertArr['bodytext'] = preg_replace('/\n<\/th>/si','</th>',$insertArr['bodytext']);
								}
							}
							//print $insertArr['bodytext']; exit;
							
					//$newXml = $dom->dump_mem(true);
			//print $newXml; // Debug
					//$dom->free(); // Here too early...
						if ($sysLangSeen != 1) {
							$insertArr['sys_language_uid']=$tlang;
							$insertFields[].= 'sys_language_uid';
						}
						//update DB
			//print_r($insertArr2); // Debug
			//print '<br>XML::'.count($insertArr).'<br/>'; // Debug
			//print_r($insertFields); // Debug
						// get fields from target DB table
			//print $targetTable; // Debug
						$tableFieldInfo=$this->getFieldInfo ($targetTable);
						$tableFields=array();
						for ($key = 0, $size = count($tableFieldInfo); $key < $size; $key++) {
							$tableFields[].= $tableFieldInfo[$key]['name'];
						}
						$allowedFields=array_intersect($insertFields,$tableFields);
						// remove items from insertArr that are not in allowed fields
						foreach ($insertArr as $key => $value) {
							if (in_array($key, $allowedFields)) {
								//print $key.' is in allowed<br/>';
							} else {
								//print $key.' is NOT in allowed<br/>';
								unset($insertArr[$key]);
							}
						}
						// Insert pages data
						if ($targetTable=='pages') {
							// uid not needed for update!
							$insertArr['uid']=$pid;
							unset($insertArr['pid']);
							$TYPO3_DB->exec_UPDATEquery($targetTable, 'uid='.$pid, $insertArr);
						} elseif ($targetTable=='tt_content') {
			//print "CONTENT"; // Debug
					// Typo3 v4 uses t3_origuid!
			// print $uid; // Debug
					if (isset($insertArr['t3_origuid'])){
						$insertArr['t3_origuid']= $uid;
					}
							if ($params['remExCes']==1) {
		//print "Go 4 remove all"; // Debug
								$updateArr = array('deleted' => "1");
								$TYPO3_DB->exec_UPDATEquery('tt_content','pid='.$pid.' AND sys_language_uid='.$tlang, $updateArr);
								//$TYPO3_DB->exec_INSERTquery($targetTable,$insertArr);
								//unset($insertArr);
								$errorMsg.= '<tr><td class="error"><span class="message">CONTENT::'.$pid.'</span></td><td><span class="message">'.$LANG->getLL('remAllCes').'</span></td></tr>';
								$remAll=1;
							} 
							// delete all content elements from target language page
							$exCe = 0;
							$res=$TYPO3_DB->exec_SELECTquery('uid','tt_content','pid='.$pid.' AND sys_language_uid='.$tlang.' AND l18n_parent='.$l18n_parent.' AND deleted=0 AND hidden=0', '', '', '1');
							while ($row = $TYPO3_DB->sql_fetch_assoc($res)) {
								$exCe = $row['uid'];
							}
							if (!empty($exCe)) {
			//print "NOT EMPTY CE $exCe"; // Debug
								if ($params['overwriteCeInfo']==1) {
			//print "Go 4 overwrite"; // Debug
									unset($insertArr['uid']);	
									$TYPO3_DB->exec_UPDATEquery('tt_content','pid='.$pid.' AND uid='.$exCe.' AND sys_language_uid='.$tlang, $insertArr);
									$errorMsg.= '<tr><td class="error"><span class="message">CONTENT::'.$exCe.'</span></td><td><span class="message">'.$LANG->getLL('overLocCe').'</span></td></tr>';
								} else {
			//print "Error"; // Debug
									$errorMsg.= '<tr><td class="error">CONTENT::'.$exCe.'</td><td>'.$LANG->getLL('ceTlangExists').'</td></tr>';
								} 
							} else {
			//print "Standard insert"; //Debug
								$TYPO3_DB->exec_INSERTquery($targetTable,$insertArr);
								unset($insertArr);
							}	
						} elseif ($targetTable=='pages_language_overlay') {
			//print "Overlay"; // Debug
							// Check if page in target language already exists then either insert or update data 
							if ($slang=='0') { //if l18n_parent eq default lang
								$pid=$uid;
			//print "SLANG DEFAULT"; // Debug
							}
							$exPagUid = 0;
							$res=$TYPO3_DB->exec_SELECTquery('uid','pages_language_overlay','pid='.$pid.' AND sys_language_uid='.$tlang, '', '', '1');
							while ($row = $TYPO3_DB->sql_fetch_assoc($res)) {
								$exPagUid = $row['uid'];
							}
							if (!empty($exPagUid)) { //page exists
							   //print $exPagUid." ";
							   //print "PAGE EXISTS <br/>";
			//print "Not empty exPagUid"; // Debug
								unset($insertArr['uid']);	
								if ($params['overwritePagesInfo']==1) {
									if ($slang == 0) {
										$insertArr['pid']=$uid;
									}
								//print_r ($insertArr);
			//print "OVERWRITE PAGES"; // Debug
								//print_r ($insertArr);
									$TYPO3_DB->exec_UPDATEquery('pages_language_overlay','uid='.$exPagUid.' AND sys_language_uid='.$tlang, $insertArr);
									unset($insertArr);
									$errorMsg.= '<tr><td class="error"><span class="message">PAGES::'.$exPagUid.'</span></td><td>'.$LANG->getLL('overLocPag').'</td></tr>';
								} else {
			//print "OVERWRITE PAGES > Else"; //Debug
									$errorMsg.= '<tr><td class="error">PAGES::'.$exPagUid.'</td><td>'.$LANG->getLL('pTlangExists').'</td></tr>';
								}
							} else { //new page
							   //print "INSERT PAGE<br/>";
								if ($slang == 0) {
									$insertArr['pid']=$uid;
								}
								//print_r ($insertArr);
								$TYPO3_DB->exec_INSERTquery($targetTable,$insertArr);
								unset($insertArr);
							}
						} else {
			//print "Nothing to do here"; // Debug
							// insert new translations
							$TYPO3_DB->exec_INSERTquery($targetTable,$insertArr);
						}
						//write status to ext_table
					}
					$errorMsg.= '<tr><td class="error"><span class="green">IMPORT::XML</span></td><td>'.basename($xmlfile).'::'.$LANG->getLL('sucProc').'</td></tr>';
				}
			}
			$errorMsg.= '</table>';
			return $errorMsg;
		}

		/**
		 * Import Localization data from XML file
		 *
		 * @param	array		$params	Import parameters
		 * @param	string	$xmlFilesArr	XML files
		 * @return	string	$errorMsg	Error messages
		 */
		function importLocFile2($params,$xmlFileArr) {
			global $LANG;
			global $BE_USER;
			global $TYPO3_DB;

			$parseHTML = t3lib_div::makeInstance("t3lib_parseHTML_proc");
		// check CDATA
			$errorMsg.= '<table><colgroup><col width="17%" /><col width="83%" /></colgroup>';

			foreach ($xmlFileArr as $xmlfile) {
				$xml = t3lib_div::getURL($xmlfile);
				//$xml = file_get_contents($xmlfile);
                //print_r($xml);die;
				// make bodytext CDATA section to avoid tag inconsistency (HTML tags) crashing the XML parser and import procedure
				$xml=preg_replace("/<\/bodytext>/s",']]></bodytext>',$xml);
				$xml=preg_replace("/<bodytext ([^>]+)>/s",'<bodytext \1><![CDATA[',$xml);

				$cleanXml = $xml; //Trying without above  14/8/2007

      		    $stack = array();

				$xml_parser = xml_parser_create();
				xml_set_element_handler($xml_parser, "startTag", "endTag");
				xml_set_character_data_handler($xml_parser, "cdata");

				$data = @xml_parse($xml_parser,$cleanXml);


				if(!$data) {
					$errorMsg.= '<tr><td class="error">XML</td><td class="error">'.basename($xmlfile).'::'.$LANG->getLL('noValidXml').'</br>';
					$errorMsg.= 'ERROR:: '.xml_error_string(xml_get_error_code($xml_parser));
					$errorMsg.= ' on line '.xml_get_current_line_number($xml_parser).'</td></tr>';
					xml_parser_free($xml_parser);
					continue;
				} else {
					xml_parser_free($xml_parser);
				        $dom = new DOMDocument();
					$dom->loadXML($cleanXml);	
					//echo $doc->saveXML();
					$root = $dom->documentElement;
					$srcTable = substr($root->nodeName,3);
					$slang = $root->getAttribute('slang');
					$tlang = $root->getAttribute('tlang');
					$dataSets = $dom->getElementsByTagName('dataSet');

// breaks here
					foreach ($dataSets as $dataSet) {

						$text_nodes = $dataSet->childNodes;
						$insertArr = array();
						$insertFields = array();
						if ($srcTable == 'tt_content') {
							$hide = $params['hideImpCes'];
							$targetTable = $srcTable;
						} elseif (preg_match('/pages/',$srcTable)) {
							$hide = $params['hideImpPages'];
							if ($tlang != 0) {
								$targetTable = 'pages_language_overlay';
							} else {
								$targetTable = 'pages';
							}
						} else {
							$targetTable = $srcTable;
						}
						//var_dump ($text_nodes); die;//Debug
						$l18n_parent= $dataSet->getAttribute('id');
						// Build the insert array from XML
						foreach ($text_nodes as $text) {
                            //var_dump ($text);
							if ($text->nodeType == XML_ELEMENT_NODE) {
                                $text->nodeValue = html_entity_decode($text->nodeValue);
								if ($text->nodeName == 'CType') {
									$cType=$text->nodeValue;
								}
								if ($text->nodeName == 'pid') {
									$pid=$text->nodeValue;
								}
								if ($text->nodeName == 'bodytext') {
									//print '####';
									//print '<br/>BEFORE: '.$text->nodeValue;

									$text->nodeValue = preg_replace("/<br \/>/s",'__BR__',$text->nodeValue);
									$text->nodeValue = $parseHTML->TS_transform_db($text->nodeValue,$css=0); // removes links from content if not called first!
									$text->nodeValue = $parseHTML->TS_images_db($text->nodeValue);
									$text->nodeValue = $parseHTML->TS_links_db($text->nodeValue);
									$text->nodeValue = preg_replace("/&nbsp;/s",' ',$text->nodeValue);
									//print '<br/>####AFTER: '.$newContent;
									//$this->replace_content(&$text, $newContent );
									//print '<br/>####AFTER: '.$text->nodeValue;
								}
								if ($text->nodeName == 'l18n_parent') {
									$this->replace_content($text, $l18n_parent );
								} elseif ($text->nodeName == 'uid') {
									$uid=$text->nodeValue;
									$this->replace_content($text, '' );
								} elseif ($text->nodeName == 'sys_language_uid') {
									$this->replace_content($text, $tlang);
									$sysLangSeen = 1;
								} elseif ($text->nodeName == 'hidden') {
									$this->replace_content($text, intval($hide));
								} elseif ($text->nodeName == 'cruser_id') {
									$this->replace_content($text, $BE_USER->user['uid']);
								} elseif (($text->nodeName == 'crdate') || ($text->nodeName == 'tstamp')) {
									$this->replace_content($text, time());
								}
								//print $text->nodeName;
								//print '==>'.$text->nodeValue.' :: ';die;
								$insertArr[$text->nodeName]=$text->nodeValue;
								$insertFields[].=$text->nodeName;
								// if src_table eq pages ==> sys_language_uid = $tlang
							}
						}
							//Substitute <br/> with <br>
							$insertArr['bodytext'] = preg_replace('/__BR__/is','<br />',$insertArr['bodytext']);
							//$insertArr['bodytext'] = preg_replace('/<br\/>/is','<br>',$insertArr['bodytext']);
							$insertArr['bodytext'] = preg_replace('/&apos;/is','\'',$insertArr['bodytext']);
							$insertArr['bodytext'] = preg_replace('/&quot;/is','"',$insertArr['bodytext']);

							// Reconvert previously masked links if CType != html
							if ($cType != "html") {
								//Remove unwanted linebreaks from tidy
								if (extension_loaded('tidy')) {
									$insertArr['bodytext'] = preg_replace('/\n<\/li>/si','</li>',$insertArr['bodytext']);
									$insertArr['bodytext'] = preg_replace('/<p([^>]+)?>\n/si','<p\1>',$insertArr['bodytext']);
									$insertArr['bodytext'] = preg_replace('/\n<\/p>/si','</p>',$insertArr['bodytext']);
									$insertArr['bodytext'] = preg_replace('/<h([^>]+)?>\n/si','<h\1>',$insertArr['bodytext']);
									$insertArr['bodytext'] = preg_replace('/\n<\/h([^>]+)?>/si','</h\1>',$insertArr['bodytext']);
									$insertArr['bodytext'] = preg_replace('/\n<td([^>]+)?>/si','<td\1>',$insertArr['bodytext']);
									$insertArr['bodytext'] = preg_replace('/\n<\/td>/si','</td>',$insertArr['bodytext']);
									$insertArr['bodytext'] = preg_replace('/\n<tr([^>]+)?>/si','<tr\1>',$insertArr['bodytext']);
									$insertArr['bodytext'] = preg_replace('/\n<\/tr>/si','</tr>',$insertArr['bodytext']);
									$insertArr['bodytext'] = preg_replace('/\n<th([^>]+)?>/si','<th\1>',$insertArr['bodytext']);
									$insertArr['bodytext'] = preg_replace('/\n<\/th>/si','</th>',$insertArr['bodytext']);
								}
							}
							//print $insertArr['bodytext']; //exit;
							
					//$newXml = $dom->dump_mem(true);
			//print $newXml; // Debug
					//$dom->free(); // Here too early...
						if ($sysLangSeen != 1) {
							$insertArr['sys_language_uid']=$tlang;
							$insertFields[].= 'sys_language_uid';
						}
						//update DB
			//print_r($insertArr2); // Debug
			//print '<br>XML::'.count($insertArr).'<br/>'; // Debug
			//print_r($insertFields); // Debug
						// get fields from target DB table
			//print $targetTable; // Debug
						$tableFieldInfo=$this->getFieldInfo ($targetTable);
						$tableFields=array();
						for ($key = 0, $size = count($tableFieldInfo); $key < $size; $key++) {
							$tableFields[].= $tableFieldInfo[$key]['name'];
						}
						$allowedFields=array_intersect($insertFields,$tableFields);
						// remove items from insertArr that are not in allowed fields
						foreach ($insertArr as $key => $value) {
							if (in_array($key, $allowedFields)) {
								//print $key.' is in allowed<br/>';
							} else {
								//print $key.' is NOT in allowed<br/>';
								unset($insertArr[$key]);
							}
						}
                        //print_r($targetTable);die;
						// Insert pages data
						if ($targetTable=='pages') {
							// uid not needed for update!
							$insertArr['uid']=$pid;
							unset($insertArr['pid']);

							$TYPO3_DB->exec_UPDATEquery($targetTable, 'uid='.$pid, $insertArr);

                            //print_r($TYPO3_DB->UPDATEquery($targetTable, 'uid='.$pid, $insertArr));die;
						} elseif ($targetTable=='tt_content') {
			//print "CONTENT"; // Debug
					// Typo3 v4 uses t3_origuid!
			 //print $uid; die;// Debug
					if (isset($insertArr['t3_origuid'])){
						$insertArr['t3_origuid']= $uid;
					}
							if ($params['remExCes']==1) {
		//print "Go 4 remove all"; // Debug
								$updateArr = array('deleted' => "1");
								$TYPO3_DB->exec_UPDATEquery('tt_content','pid='.$pid.' AND sys_language_uid='.$tlang, $updateArr);
								//$TYPO3_DB->exec_INSERTquery($targetTable,$insertArr);
								//unset($insertArr);
								$errorMsg.= '<tr><td class="error"><span class="message">CONTENT::'.$pid.'</span></td><td><span class="message">'.$LANG->getLL('remAllCes').'</span></td></tr>';
								$remAll=1;
							} 
							// delete all content elements from target language page
							$exCe = 0;
							$res=$TYPO3_DB->exec_SELECTquery('uid','tt_content','pid='.$pid.' AND sys_language_uid='.$tlang.' AND l18n_parent='.$l18n_parent.' AND deleted=0 AND hidden=0', '', '', '1');
							while ($row = $TYPO3_DB->sql_fetch_assoc($res)) {
								$exCe = $row['uid'];
							}
							if (!empty($exCe)) {
			//print "NOT EMPTY CE $exCe"; // Debug
								if ($params['overwriteCeInfo']==1) {
			//print "Go 4 overwrite"; // Debug
									unset($insertArr['uid']);	
									$TYPO3_DB->exec_UPDATEquery('tt_content','pid='.$pid.' AND uid='.$exCe.' AND sys_language_uid='.$tlang, $insertArr);
									$errorMsg.= '<tr><td class="error"><span class="message">CONTENT::'.$exCe.'</span></td><td><span class="message">'.$LANG->getLL('overLocCe').'</span></td></tr>';
								} else {
			//print "Error"; // Debug
									$errorMsg.= '<tr><td class="error">CONTENT::'.$exCe.'</td><td>'.$LANG->getLL('ceTlangExists').'</td></tr>';
								} 
							} else {
			//print "Standard insert"; //Debug
								$TYPO3_DB->exec_INSERTquery($targetTable,$insertArr);
								unset($insertArr);
							}	
						} elseif ($targetTable=='pages_language_overlay') {
			//print "Overlay"; // Debug
							// Check if page in target language already exists then either insert or update data 
							if ($slang=='0') { //if l18n_parent eq default lang
								$pid=$uid;
			//print "SLANG DEFAULT"; // Debug
							}
							$exPagUid = 0;
                            //print_r($insertArr);die;
                            //print_r($TYPO3_DB->SELECTquery('uid','pages_language_overlay','pid='.$pid.' AND sys_language_uid='.$tlang, '', '', '1'));die;
							$res=$TYPO3_DB->exec_SELECTquery('uid','pages_language_overlay','pid='.$pid.' AND sys_language_uid='.$tlang, '', '', '1');
							while ($row = $TYPO3_DB->sql_fetch_assoc($res)) {
								$exPagUid = $row['uid'];
							}
							if (!empty($exPagUid)) { //page exists
							   //print $exPagUid." ";
							   //print "PAGE EXISTS <br/>";
			//print "Not empty exPagUid"; // Debug
								unset($insertArr['uid']);	
								if ($params['overwritePagesInfo']==1) {
									if ($slang == 0) {
										$insertArr['pid']=$uid;
									}
								//print_r ($insertArr);
			//print "OVERWRITE PAGES"; // Debug
								//print_r ($insertArr);
									$TYPO3_DB->exec_UPDATEquery('pages_language_overlay','uid='.$exPagUid.' AND sys_language_uid='.$tlang, $insertArr);
									unset($insertArr);
									$errorMsg.= '<tr><td class="error"><span class="message">PAGES::'.$exPagUid.'</span></td><td>'.$LANG->getLL('overLocPag').'</td></tr>';
								} else {
			//print "OVERWRITE PAGES > Else"; //Debug
									$errorMsg.= '<tr><td class="error">PAGES::'.$exPagUid.'</td><td>'.$LANG->getLL('pTlangExists').'</td></tr>';
								}
							} else { //new page
							   //print "INSERT PAGE<br/>";
								if ($slang == 0) {
									$insertArr['pid']=$uid;
								}
								//print_r ($insertArr);
								$TYPO3_DB->exec_INSERTquery($targetTable,$insertArr);
								unset($insertArr);
							}
						} else {
			//print "Nothing to do here"; // Debug
							// insert new translations
							$TYPO3_DB->exec_INSERTquery($targetTable,$insertArr);
						}
						//write status to ext_table
					}
					$errorMsg.= '<tr><td class="error"><span class="green">IMPORT::XML</span></td><td>'.basename($xmlfile).'::'.$LANG->getLL('sucProc').'</td></tr>';
				}
			}
			$errorMsg.= '</table>';
			return $errorMsg;
		}

		/**
		 * Replace content in XML text nodes
		 *
		 * @param	object		&$node	Reference to node
		 * @param	string		$newContent	New node content
		 */
		function replace_content( &$node, $newContent ) {

			if (PHP_VERSION < 5) {
			   $dom = &$node->owner_document();
				$kids = &$node->child_nodes();
				foreach ( $kids as $kid ) {
					if ( $kid->node_type() == XML_TEXT_NODE ){
						$node->remove_child($kid);
						$node->set_content($newContent);
					}
				}
			} else {
	        		$dom = &$node->ownerDocument;
	                        $kids = &$node->childNodes;
        	                foreach ( $kids as $kid ) {
                               		if ( $kid->nodeType == XML_TEXT_NODE ){
                                        	$node->removeChild($kid);
	                                        $node->appendChild($dom->createTextNode($newContent));
        	                        }
                        	}
			}
		}


		/**
		 * Parser start tag
		 *
		 * @param	object		&parser	...
		 * @param	string		$name		...
		 * @param	string		$attrs		...
		 */
	    function startTag($parser, $name, $attrs)
     {
        global $stack;
        $tag=array("name"=>$name,"attrs"=>$attrs);
        array_push($stack,$tag);
     
     }
     
		/**
		 * Parser cdata
		 *
		 * @param	object		&parser	...
		 * @param	string		$cdata		...
		 */
        function cdata($parser, $cdata)
     {
        global $stack,$i;
    
        if(trim($cdata))
        {
            $stack[count($stack)-1]['cdata']=$cdata;
        }
     }
     
		/**
		 * Parser end tag
		 *
		 * @param	object		&parser	...
		 * @param	string		$name		...
		 */
        function endTag($parser, $name)
     {
        global $stack;
        $stack[count($stack)-2]['children'][] = $stack[count($stack)-1];
        array_pop($stack);
     }



}

if (defined("TYPO3_MODE") && $TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/t3_locmanager/class.tx_t3locmanager_main.php"])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/t3_locmanager/class.tx_t3locmanager_main.php"]);
}

?>
