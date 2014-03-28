README.txt t3_locManager localization export data

/********************************************************************
        *  Copyright notice
        *
        *  (c) 2008 Daniel Zielinski (d.zielinski@l10ntech.de)
        *  All rights reserved
        *
*********************************************************************/

	/**
	 * CONTENTS OF ZIP-ARCHIVE
	 */

	This directory contains the following files:

		- t3LocData_TABLENAME_STARTPID_SOURCE_TARGET.xml	(Localization data file)
		- t3LocData_TABLENAME_STARTPID_SOURCE_TARGET.ini	(SDL TRADOS Tag Settings file based on user settings in t3_locManager)
		- README.txt	This file

	...where:

		- TABLENAME is the name of the source database table inside TYPO3,
		- STARTPID is the ID of the page used as starting point for the export,
		- SOURCE is the ID of the source language for the translation defined in the TYPO3 database table sys_language,
		- TARGET is the ID of the target language for the translation defined in the TYPO3 database table sys_language,


	/**
	 * XML FORMAT
	 */

	The structure of the XML files reflects the structure of the source database table.
	The database name is used as root element prefixed by t3_.
	The first child element is a grouping element called region with an attribute xml:lang indicating the language of text.
	Every row from the database is grouped into a child element called dataSet with the uid of the record.
	The localizable data is contained as child elements in dataSet.
	The element names correspond to the field names of the database table.
	Depending on the user preferences the following attributes are set:

		- max-len [INT]: Maximum length of field (to be used in software localization tools)
		- type [STRING]: Type of database field (blob, text, varchar, int, ...)
		- localizable [INT]: 1= Indicates that content is localizable
		- cdata [INT]: used to wrap content on import

	/**
	 * INSTRUCTIONS 
	 */

	Translation of XML files exported from TYPO3 by t3_locManager

	I. SDL TRADOS TagEditor

	In order to be able to translate the files contained in the ZIP file you need to perform the following steps:

	1. Unzip all files from the ZIP archive to a folder on your harddisk
	2. Be aware of not changing the filename because it is needed for later import of translations.
	3. When starting SDL TRADOS TagEditor make sure that the Workbench is not running.
	4. Open SDL TRADOS TagEditor and click on FILE > OPEN and select the XML file to be translated.
	5. When asked for the tag settings file click on YES.
	6. In the opening TAG SETTINGS MANAGER click on OPEN and select the INI file included in the same directory as your source documents. Make sure that you select the INI file with the same basename as your source document. Confirm your selection by clicking on SELECT.
	7. In the dialogue telling you that the DOCTYPE declarations/root elements from the document do not match those in your settings file click on YES to use the settings file anyway.
	8. After the document has been opened in TagEditor set the right encooding by clicking on VIEW > ENCODING > UNIVERSAL ALPHABET (UTF-8).
	9. Start the workbench and open your translation memory.
	10. Start translating ;-)

		NOTE: [If you do not translate the whole file at once, always save your current translation status as TTX file by clicking on FILE > Save. When restarting the translation, open directly the TTX file.]

	11. When finished the translation, save the target file as TTX (FILE > SAVE) AND as XML by clicking on FILE > SAVE TARGET AS... Make sure that you do not overwrite the source file ;-)

		NOTE: [If you want to make changes to a translation you can do so by opening the TTX file and then generating a new target file.]

	12. Finally, open the files in an XML editor or in a browser to check if the XML is valid.
	[13.] Upload your translated XML files in a ZIP package into TYPO3 module t3_locManager.
