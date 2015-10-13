<?php
/**
 * This class encapsulates all the Single-sign-on functionality for the LigminchaGlobal extension
 */

// Global session duration (refreshed by visiting source site again)
define( 'LG_SESSION_DURATION', 10 );


class LigminchaGlobalSSO {

	private $cmd = 'getcookie';
	private $cookie = 'LigminchaSession';

	function __construct() {
		$this->server = LigminchaGlobalServer::getCurrent();

		// If this is an SSO token request and this is the master site, return the key
		if( LigminchaGlobalServer::getCurrent()->isMaster && array_key_exists( $this->cmd, $_REQUEST ) ) {
			setcookie( $this->cookie, $_REQUEST[$this->cmd] );
			exit;
		}
	}

	/**
	 * If there is a new session for this user/server, append the token request to the page
	 */
	public function appendTokenRequest() {

		// If this is the main site, just set the cookie now,
		if( LigminchaGlobalServer::getCurrent()->isMaster ) setcookie( $this->cookie, $session->obj_id );
		else {

			// If there is a current session,
			if( $session = LigminchaGlobalSession::getCurrent() ) {

				// If the session is newly created, get an SSO cookie under ligmincha.org for this session ID
				// - newly created sessions have no expiry
				// - this is done by appending a 1x1pixel iFrame to the output that will request a token cookie from ligmincha.org
				if( $session->flags | LG_NEW ) {
					$url = plgSystemLigminchaGlobal::$instance->params->get( 'lgCookieServer' );
					$iframe = "<iframe src=\"$url?{$this->cmd}={$session->obj_id}\" frameborder=\"0\" width=\"1\" height=\"1\"></iframe>";
					$app = JFactory::getApplication( 'site' );
					$app->setBody( str_replace( '</body>', "$iframe\n</body>", $app->getBody() ) );

					// Set the expiry to a longer time that distributed sessions last
					// - after it expires, user needs to come back to have another made (may not need to log in again)
					$session->expire = time() + LG_SESSION_DURATION;

					// Write changes to the session object into the distributed database
					$session->update();
				}
			}
		}
	}
}