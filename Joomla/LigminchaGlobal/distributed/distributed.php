<?php
/**
 * This class encapsulates all the distributed database functionality for the LigminchaGlobal extension
 */

// TYPE-SPECIFIC FLAGS (top eight bits - only need to be unique within the scope of their type)

class LigminchaGlobalDistributed {

	// Make singleton available if we need it
	public static $instance;

	// The query-string command for routing changes
	private static $cmd = 'changes';

	// The queue of changes to route at the end of the request
	private static $queue = array();

	// Our distributed data table
	public static $table = '#__ligmincha_global';

	// Table structure
	public static $tableStruct = array(
		'id'       => 'BINARY(20) NOT NULL',
		'ref1'     => 'BINARY(20)',
		'ref2'     => 'BINARY(20)',
		'type'     => 'INT UNSIGNED NOT NULL',
		'creation' => 'INT UNSIGNED',
		'modified' => 'INT UNSIGNED',
		'expire'   => 'INT UNSIGNED',
		'flags'    => 'INT UNSIGNED',
		'owner'    => 'BINARY(20)',
		'group'    => 'TEXT',
		'tag'      => 'TEXT',
		'data'     => 'TEXT',
	);

	function __construct() {

		// Make singleton available if we need it
		self::$instance = $this;

		// Check that the local distributed database table exists and has a matching structure
		$this->checkTable();

		// Delete any objects that have reached their expiry time
		$this->expire();

		// Instantiate the main global objects
		LigminchaGlobalServer::getCurrent();
		LigminchaGlobalUser::getCurrent();
		LigminchaGlobalSession::getCurrent();

		// If this is a changes request commit the data (and re-route if master)
		if( array_key_exists( self::$cmd, $_POST ) ) {
			self::recvQueue( $_POST['changes'] );
			exit;
		}
	}

	/**
	 * Check that the local distributed database table exists and has a matching structure
	 */
	private function checkTable() {
		$db = JFactory::getDbo();
		$table = '`' . self::$table . '`';

		// Create the table if it doesn't exist
		$def = array();
		foreach( self::$tableStruct as $field => $type ) $def[] = "`$field` $type";
		$query = "CREATE TABLE IF NOT EXISTS $table (" . implode( ',', $def ) . ",PRIMARY KEY (id))";
		$db->setQuery( $query );
		$db->query();
		$this->log( LG_LOG, 'ligmincha_global table added' );

		// Get the current structure
		$db->setQuery( "DESCRIBE $table" );
		$db->query();

		// If the table exists, check that it's the correct format
		if( $db ) {
			$curFields = $db->loadAssocList( null, 'Field' );

			// For now only adding missing fields is supported, not removing, renaming or changing types
			$alter = array();
			foreach( self::$tableStruct as $field => $type ) {
				if( !in_array( $field, $curFields ) ) $alter[$field] = $type;
			}
			if( $alter ) {
				$cols = array();
				foreach( $alter as $field => $type ) $cols[] = "ADD COLUMN `$field` $type";
				$db->setQuery( "ALTER TABLE $table " . implode( ',', $cols ) );
				$db->query();
				$this->log( LG_LOG, 'ligmincha_global table fields added: (' . implode( ',', array_keys( $alter ) ) . ')' );
			}
		}
	}

	/**
	 * Send all queued changes
	 */
	public static function sendQueue() {

		// Get all LG_REVISION items, bail if none
		if( !$revs = LigminchaGlobalObject::find( array( 'type' => LG_REVISION ) ) ) return false;

		// If this is the master, then use zero for session ID
		$server = LigminchaGlobalServer::getCurrent();
		$master = LigminchaGlobalServer::masterDomain();
		$sid = $master ? 0 : $server->id;

		// Otherwise just one stream to the master domain
		else $streams = array( $master => array( $server, $sid ) );

		// Add all the revision data to the streams
		foreach( $revs as $rev ) {

			// Determine the recipient domain of this revision (no tagrget server id in ref1 means use master server)
			$target = $rev->ref1 ? LigminchaGlobalObject::newFromId( $rev->ref1 )->tag : $master;

			// Add revision to this domains stream (create if no stream yet)
			if( array_key_exists( $target, $streams ) ) $streams[$target] = array( $sid );
			else $streams[$target][] = $rev;

			// TODO: If we're the master, then we check this revision to see if it's for all streams, or just one
			if( $master ) {

				// TODO: If its a delete we have to select the cond and check if any are private
				// if just upd, check local object private flag
				if( 1 ) {

					// This is private so it only goes to the owner's domain
					$owner = $rev
					$stream

				} else {

					foreach( $streams as $stream ) $stream[] = $rev;
				}
			}

			// If we're not master, then this just 
			else {
			}

			$streams[$master][] = array( $rev->tag, $rev->getData() );
		}

		foreach( $streams as $stream ) {

			// Zip up the data in JSON format
			// TODO: encrypt using shared secret or public key
			$data = gzcompress( json_encode( $queue ) );

		foreach( $queue as $i ) { print_r($i); print "<br>"; }

			// Post the queue data to the server
			if( LigminchaGlobalServer::getCurrent()->isMaster ) {
			}else {
			$result = self::post( LigminchaGlobalServer::masterDomain(), $data );
			}

			// TODO: if result is success, remove all LG_REVISION items
			if( $result == 200 ) {
				$db = JFactory::getDbo();
				$table = '`' . self::$table . '`';
				$db->setQuery( "DELETE FROM $table WHERE `type`=" . LG_REVISION );
				$db->query();
			}
		}

		return true;
	}

	/**
	 * Receive changes from remote queue
	 */
	private static function recvQueue( $data ) {

		// Unzip and decode the data
		// TODO: decrypt using shared secret or public key
		$queue =  json_decode( gzuncompress( $data ), true );
		$origin = array_shift( $queue );
		$session = array_shift( $queue );

		// Process each of the revisions (this may lead to further re-routing revisions being made)
		foreach( $queue as $rev ) LigminchaGlobalRevision::process( $rev[0], $rev[1], $origin );
	}

	/**
	 * Remove all expired items (these changes are not routed because all servers handle expiration themselves)
	 */
	private function expire() {
		$db = JFactory::getDbo();
		$table = '`' . self::$table . '`';
		$db->setQuery( "DELETE FROM $table WHERE `expire` > 0 AND `expire`<" . time() );
		$db->query();
	}

	/**
	 * POST data to the passed URL
	 */
	private static post( $url, $data ) {
		$options = array(
			CURLOPT_POST => 1,
			CURLOPT_HEADER => 0,
			CURLOPT_URL => $url,
			CURLOPT_FRESH_CONNECT => 1,
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_FORBID_REUSE => 1,
			CURLOPT_TIMEOUT => 5,
			CURLOPT_POSTFIELDS => http_build_query( $data )
		);
		$ch = curl_init();
		curl_setopt_array( $ch, $options );
		if( !$result = curl_exec( $ch ) ) new LigminchaGlobalLog( "POST request to \"$url\" failed", 'Error' );
		curl_close( $ch );
		return $result;
	}
}

