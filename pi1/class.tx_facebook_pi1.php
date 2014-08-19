<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009 Olivier Schopfer <ops@wcc-coe.org>, Roberto Presedo <rpresedo@cobweb.ch>
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

require_once (PATH_tslib . 'class.tslib_pibase.php');

/**
 * Plugin 'Facebook plugin' for the 'facebook' extension.
 *
 * @author	Olivier Schopfer <ops@wcc-coe.org>, Roberto Presedo <rpresedo@cobweb.ch>
 * @package	TYPO3
 * @subpackage	tx_facebook
 */
class tx_facebook_pi1 extends tslib_pibase {
	var $prefixId = 'tx_facebook_pi1'; // Same as class name
	var $scriptRelPath = 'pi1/class.tx_facebook_pi1.php'; // Path to this script relative to the extension dir.
	var $extKey = 'facebook'; // The extension key.
	var $pi_checkCHash = true;
	
	/**
	 * The main method of the PlugIn
	 *
	 * @param	string		$content: The PlugIn content
	 * @param	array		$conf: The PlugIn configuration
	 * @return	The content that is displayed on the website
	 */
	function main($content, $conf) {
		
		$this->conf = $conf;
		$this->pi_setPiVarDefaults ();
		$this->pi_loadLL ();
		$this->init ( $conf );
		
		// Reading the Facebook Connexion data in the Extension Config
		$confArr = unserialize ( $GLOBALS ['TYPO3_CONF_VARS'] ['EXT'] ['extConf'] [$this->extKey] );
	
		// Defines the Api and Secret Key
		if (isset ( $conf ['apiKey'] ) ) $apikey = $conf ['apiKey'];
		else $apikey = $confArr ['apiKey'];
		
		if (isset ( $conf ['secretKey'] ) ) $apikey = $conf ['secretKey'];
		else $secret = $confArr ['secretKey'];
		
		// loading the Facebook API library
		require_once (t3lib_extMgm::extPath ( $this->extKey ) . '/fb_lib/facebook.php');
		$facebook = new Facebook ( $apikey, $secret );
		
		// Checks if user is logged in or not
		// Get the current user's ID (or the Page ID)
		if (@$_POST ['fb_sig_user']) {
			// fb_sig_user is set when we are not authorized
			$fb_user = $_POST ['fb_sig_user'];
		
		} else if (@$_POST ['fb_sig_canvas_user']) {
			// fb_sig_canvas_user is set when we are authorized
			$fb_user = $_POST ['fb_sig_canvas_user'];
		
		} else if (@$_POST ['fb_sig_page_id']) {
			// the request is on behalf of a page
			$fb_user = $_POST ['fb_sig_page_id'];
		
		} else {
			// if no user is found, login box is displayed
			if (! isset ( $_POST ['fb_sig_in_profile_tab'] )) {
				$toto = false;
				//avoid a tab:redirect error
				$fb_user = $facebook->require_login ();
			}
		}
		
		// Builds the content
		$FBML = '';
		
		// Options
		// Optional CSS Rules are added if specified
		if ($this->conf ['FBCssRules'] != '') {
			$FBML .= '<style type="text/css">' . $this->conf ['FBCssRules'] . '</style>';
		}
		
		// More information link
		if ($this->conf ['FBShowMoreInfo']) {
			$moreInfo = '<br /><a href="http://apps.facebook.com/' . $this->conf ['FBCanvasUrl'] . '/">' . $this->conf ['FBMoreInfoLabel'] . '</a>';
		} else
			$moreInfo = '';
			
		// Gets the differents contents
		$objConf = array ();
		
		// Application page content
		$objConf ['source'] = 'tt_content_' . $this->conf ['FBContentAppPage'];
		$objConf ['tables'] = 'tt_content';
		$content ['FBContentAppPage'] = $this->cObj->RECORDS ( $objConf );
		
		// Application Dedicated Tab in the user profile
		if (isset ( $_POST ['fb_sig_in_profile_tab'] )) {
			// Profile dedicated tab content
			if ($this->conf ['FBContentProfilTab'] > 0) {
				$objConf ['source'] = 'tt_content_' . $this->conf ['FBContentProfilTab'];
				$content ['FBContentProfilTab'] = $this->cObj->RECORDS ( $objConf );
			} else
				$content ['FBContentProfilTab'] = $content ['FBContentAppPage'];
				
			// If the application is displayed in a dedicated tab
			return $FBML . $content ['FBContentProfilTab'] . $moreInfo . $this->share_button ( $content ['FBContentProfilTab'] );
		}
		
		// Profile Narrow column content
		if ($this->conf ['FBContentProfilNarrow'] > 0) {
			$objConf ['source'] = 'tt_content_' . $this->conf ['FBContentProfilNarrow'];
			$content ['FBContentProfilNarrow'] = $this->cObj->RECORDS ( $objConf );
		} else
			$content ['FBContentProfilNarrow'] = $content ['FBContentAppPage'];
			
		// Profile Wide column content
		if ($this->conf ['FBContentProfilWide'] > 0) {
			$objConf ['source'] = 'tt_content_' . $this->conf ['FBContentProfilWide'];
			$content ['FBContentProfilWide'] = $this->cObj->RECORDS ( $objConf );
		} else
			$content ['FBContentProfilWide'] = $content ['FBContentAppPage'];
			
		// Outputs the content depending the context
		$FBML .= '
		    <fb:if-is-app-user>
		        ' . $content ['FBContentAppPage'] . $this->share_button ( $content ['FBContentAppPage'] ) . '
		    </fb:if-is-app-user>
		    <fb:wide>
		        ' . $content ['FBContentProfilWide'] . $moreInfo . $this->share_button ( $content ['FBContentProfilWide'] ) . '
		    </fb:wide>
		    <fb:narrow>
		        ' . $content ['FBContentProfilNarrow'] . $moreInfo . $this->share_button ( $content ['FBContentProfilNarrow'] ) . '
		    </fb:narrow>';
		
		// update each fb:ref handle to the new content
		try {
			$facebook->api_client->fbml_setRefHandle ( "content", $FBML );
		} catch ( Exception $ex ) {
			// Sometimes calling this script standalone (like from cron)
			// caused setRefHandle to throw an exception about an invalid
			// session key.
			

			// Seems to be fixed now (11/13/08), but it's good to catch
			// exceptions anyhow.
			

			// Possible source of this problem:
			// http://forum.developers.facebook.com/viewtopic.php?id=24208
			

			$facebook->set_user ( null, null );
			$facebook->api_client->fbml_setRefHandle ( "content", $FBML );
		}
		
		// Set the FBML for the user currently viewing our canvas page
		$boxfbml = "<fb:ref handle='content' />";
		
		// Set the profile box FBML for the user currently viewing the canvas page (but only if we have a UID)
		

		if ($fb_user) {
			$facebook->api_client->profile_setFBML ( NULL, $fb_user, $boxfbml, NULL, $boxfbml, $boxfbml );
		}
		
		$content = '<fb:header>' . $this->conf ['FBTitle'] . '</fb:header>' . $boxfbml;
		
		// Displays a "Add to profile" button on the application page, if not hided
		if (! $this->conf ['FBHideAddToProfilButton']) {
			$content .= '<br />
                <fb:if-section-not-added section="profile">
                    <fb:add-section-button section="profile" />
                </fb:if-section-not-added>';
		}
		
		// Returns the output
		return $content;
	}
	
	/**
	 * Creates a FaceBook reference for a "Share" button
	 * @param <type> $content
	 * @param <type> $title
	 * @return <type>
	 */
	function share_button($content, $title = false) {
		if ($this->conf ['FBShowShareButton']) {
			if (! $title)
				$title = $this->conf ['FBTitle'];
				
			$share = '

                <fb:share-button class="meta">
				    <meta name="title" content="'.$title.'" />
				    <meta name="description" content="'.strip_tags ( $content ).'" />
				    <link rel="target_url" href="http://apps.facebook.com/'.$this->conf['FBCanvasUrl'].'/" />
				</fb:share-button>
            ';
			return $share;
		}
		return false;
	
	}
	
	/*********************************************************
	 * INIT VALUES
	 *********************************************************/
	
	/**
	 * This method performs various initialisations
	 *
	 * @param	array		$conf: plugin configuration, as received by the main() method
	 * @return	void
	 */
	function init($conf) {
		$this->conf = $conf; // Base configuration is equal the the plugin's TS setup
		

		// Load the flexform and loop on all its values to override TS setup values
		// Some properties use a different test (more strict than not empty) and yet some others no test at all
		

		$this->pi_initPIflexForm ();
		if (is_array ( $this->cObj->data ['pi_flexform'] ['data'] )) {
			foreach ( $this->cObj->data ['pi_flexform'] ['data'] as $sheet => $langData ) {
				foreach ( $langData as $lang => $fields ) {
					foreach ( $fields as $field => $value ) {
						$value = $this->pi_getFFvalue ( $this->cObj->data ['pi_flexform'], $field, $sheet );
						$this->conf [$field] = $value;
					}
				}
			}
		}
	}
}

if (defined ( 'TYPO3_MODE' ) && $TYPO3_CONF_VARS [TYPO3_MODE] ['XCLASS'] ['ext/facebook/pi1/class.tx_facebook_pi1.php']) {
	include_once ($TYPO3_CONF_VARS [TYPO3_MODE] ['XCLASS'] ['ext/facebook/pi1/class.tx_facebook_pi1.php']);
}

?>