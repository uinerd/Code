<?php
$wgArticlePath   = '/$1';
$wgCookieDomain  = '.ligmincha.org';
$wgLogo          = '/files/d/d5/Ligmincha-international.png';
$wgRawHtml       = true;
$wgDefaultSkin   = 'monobook';
$wgEnableUploads = true;

// Bounce clear to https
if( !array_key_exists( 'HTTPS', $_SERVER ) || $_SERVER['HTTPS'] != 'on' ) {
	header( "Location: https://wiki.ligmincha.org" . $_SERVER['REQUEST_URI'] );
	exit;
}

// Turn on outline numbering
//$wgDefaultUserOptions['numberheadings'] = 1;

// Force users to use old changes format
$wgExtensionFunctions[] = 'wfOldChanges';
function wfOldChanges() {
	global $wgUser;
	$wgUser->setOption( 'usenewrc', false );
}

// Permissions
$wgGroupPermissions['*']['edit']                   = false;
$wgGroupPermissions['*']['createaccount']          = false;
$wgGroupPermissions['*']['read']                   = true;
$wgGroupPermissions['*']['upload']                 = false;
$wgGroupPermissions['user']['edit']                = true;
$wgGroupPermissions['user']['upload']              = true;
$wgGroupPermissions['user']['upload_by_url']       = true;
$wgGroupPermissions['*']['createpage']             = false;
$wgGroupPermissions['user']['createpage']          = true;
$wgGroupPermissions['sysop']['createpage']         = true;
$wgGroupPermissions['sysop']['createaccount']      = true;

// Set up a private sysop-only Admin namespace
define( 'NS_ADMIN', 1020 );
$wgExtraNamespaces[NS_ADMIN]     = 'Admin';
$wgExtraNamespaces[NS_ADMIN + 1] = 'Admin_talk';
Hooks::register( 'ParserFirstCallInit', 'wfProtectAdminNamespace' );
function wfProtectAdminNamespace( Parser $parser ) {
	global $wgTitle, $wgUser, $wgOut, $mediaWiki;
	if( is_object( $wgTitle) && $wgTitle->getNamespace() == NS_ADMIN && !in_array( 'bureaucrat', $wgUser->getEffectiveGroups() ) ) {
		if( is_object( $mediaWiki ) ) $mediaWiki->restInPeace();
		$wgOut->disable();
		wfResetOutputBuffers();
		header( "Location: http://wiki.ligmincha.org/" );
	}
	return true;
}

// Wiki editor extension
wfLoadExtension( 'WikiEditor' );
$wgDefaultUserOptions['usebetatoolbar']            = 1;
$wgDefaultUserOptions['usebetatoolbar-cgd']        = 1;
$wgDefaultUserOptions['wikieditor-preview']        = 1;
$wgDefaultUserOptions['watchdefault']              = false;

// Extensions
wfLoadExtensions( array(
	'ParserFunctions',
	'ExtraMagic',
	'HighlightJS',
	'DynamicPageList',
) );
