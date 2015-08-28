<?php
/**
 * Modifies Joomshopping shipping types (PAC, SEDEX and Carta registrada) to calculate correios cost during checkout
 * - see components/com_jshopping/controllers/checkout.php for relavent events
 *
 * @copyright	Copyright (C) 2015 Aran Dunkley
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
defined('_JEXEC') or die;

/**
 * @package		Joomla.Plugin
 * @subpackage	System.correios
 * @since 2.5
 */
class plgSystemCorreios extends JPlugin {

	public static $cartaPrices = array();  // the table of prices per weight for carta registrada
	public static $cartaPrices2 = array(); // the table of prices per weight for carta registrada (módico)
	public static $allbooks;               // whether the order consists only of book or not (whether carta registrada is allowed or not)

	public function onAfterInitialise() {

		// And the Carta registrada prices (add the tracking price to each)
		$t = str_replace( ',', '.', $this->params->get( 'carta_track' ) );
		$t2 = str_replace( ',', '.', $this->params->get( 'carta_track_fast' ) );
		foreach( array( 100, 150, 200, 250, 300, 350, 400, 450 ) as $d ) {
				$p = str_replace( ',', '.', $this->params->get( "carta$d" ) );
				self::$cartaPrices[$d] = $p + $t;
				self::$cartaPrices2[$d] = $p + $t2;
		}

		// Install our extended shipping type if not already there
		// (should be done from onExtensionAfterInstall but can't get it to be called)
		// (or better, should be done from the xml with install/uninstall element, but couldn't get that to work either)
		$db = JFactory::getDbo();
		$tbl = '#__jshopping_shipping_ext_calc';
		$db->setQuery( "SELECT 1 FROM `$tbl` WHERE `name`='Correios'" );
		$row = $db->loadRow();
		if( !$row ) {

			// Add the shipping type extension
			$query = "INSERT INTO `$tbl` "
				. "(`name`, `alias`, `description`, `params`, `shipping_method`, `published`, `ordering`) "
				. "VALUES( 'Correios', 'sm_correios', 'Correios', '', '', 1, 1 )";
			$db->setQuery( $query );
			$db->query();

			// Add our freight cost cache table
			$tbl = '#__correios_cache';
			$query = "CREATE TABLE IF NOT EXISTS `$tbl` (
				id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
				cep    INT UNSIGNED NOT NULL,
				weight INT UNSIGNED NOT NULL,
				time   INT UNSIGNED NOT NULL,
				pac    DECIMAL(5,2) NOT NULL,
				sedex  DECIMAL(5,2) NOT NULL,
				PRIMARY KEY (id)
			)";
			$db->setQuery( $query );
			$db->query();

			// Copy the sm_ligmincha_freight class into the proper place
			// (there's probably a proper way to do this from the xml file)
			$path = JPATH_ROOT . '/components/com_jshopping/shippings/sm_correios';
			$file = 'sm_correios.php';
			if( !is_dir( $path ) ) mkdir( $path );
			copy( __DIR__ . "/$file", "$path/$file" );
		}

	}

	/**
	 * Called on removal of the extension
	 */
	public function onExtensionAfterUnInstall() {

		// Remove our extended shipping type
		$db = JFactory::getDbo();
		$tbl = '#__jshopping_shipping_ext_calc';
		$db->setQuery( "DELETE FROM `$tbl` WHERE `name`='Correios'" );
		$db->query();

		// Remove our freight cost cache table
		$tbl = '#__correios_cache';
		$db->setQuery( "DROP TABLE IF EXISTS `$tbl`" );
		$db->query();

		// Remove the script
		$path = JPATH_ROOT . '/components/com_jshopping/shippings/sm_correios';
		$file = 'sm_correios.php';
		if( file_exists( "$path/$file" ) ) unlink( "$path/$file" );
		if( is_dir( $path ) ) rmdir( $path );
	}

	/**
	 * If the order is not all books, remove the Carta registrada options
	 * (the $allbooks settings is updated in checkout by sm_correios class)
	 */
	public function onBeforeDisplayCheckoutStep4View( &$view ) {
		if( !self::$allbooks ) {
			$tmp = array();
			for( $i = 0; $i < count( $view->shipping_methods ); $i++ ) {
				if( !preg_match( '|carta\s*registrada|i', $view->shipping_methods[$i]->name ) ) {
					$tmp[] = $view->shipping_methods[$i];
				}
			}
			$view->shipping_methods = $tmp;
		}
	}
}
