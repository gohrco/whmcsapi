<?php

namespace Gohrco;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\ErrorHandler;

// Log level
defined( 'WHMCSAPILOGLEVEL' ) or define( "WHMCSAPILOGLEVEL", \Monolog\Logger :: WARNING );

/**
 * WHMCS API Handler
 * @since		2016 Apr 1
 */
class Whmcsapi
{
	private $accesskey	=	null;
	private $log		=	null;
	private $logpath	=	null;
	private	$username	=	null;
	private $password	=	null;
	private $url		=	null;
	
	private $_info		=	null;
	private $_result	=	null;
	/**
	 * Constructor class
	 * @access		public
	 * @param		array		This should contain an array with our user settings
	 * 
	 * @since		2016 Apr 4
	 */
	public function __construct( $o = array() )
	{
		$init	=	false;
		if (	isset( $o['username'] ) 
			&&	isset( $o['password'] )
			&&	isset( $o['url'] )
			&&	isset( $o['logpath'] ) ) $init = true;
		
		foreach ( array( 'username', 'password', 'url', 'logpath' ) as $key ) {
			if ( $key == 'password' && isset( $o[$key] ) ) $o[$key] = md5( $o[$key] );
			if ( isset( $o[$key] ) ) $this->$key	=	$o[$key];
		}
		
		if ( $init ) {
			$this->init();
		}
	}
	
	
	/**
	 * Magic call method
	 * @access		public
	 * @param		string
	 * @param		array
	 *
	 * @return		array | false
	 * @since		2016 Apr 4
	 */
	public function __call( $name, $args )
	{
		if ( in_array( $name, array( 'setUsername', 'setPassword','setUrl', 'setLogpath', 'setAccesskey' ) ) ) {
			return $this->setitem( $name, $args );
		}
		
		$data		=	( count( $args ) > 0 ? array_shift( $args ) : array() );
		
		$this->log->addInfo( $name . ' Request Received', $data );
		
		if (! is_array( $data ) ) {
			$this->log->addWarning( 'Data passed to WHMCS API is not an array', array( 'notanarray' => $data ) );
			$data	=	array( $data );
		}
		
		if (! isset( $data['responsetype'] ) ) $data['responsetype'] = 'json';
		$this->responsetype	=	$data['responsetype'];
		
		$response	=	$this->_request( $name, $data );
		
		if (! is_array( $response ) ) {
			$res	=	array( 'response' => $response );
		}
		else {
			$res	=	$response;
		}
		
		$this->log->addDebug( $name . ' Response', $res );
		
		return $response;
	}
	
	
	/**
	 * Method for initializing the object
	 * @access		public
	 * @version		@fileVers@
	 *
	 * @since		2016 Apr 5
	 */
	public function init()
	{
		$this->log	=	new Logger( 'whmcsapi' );
		$this->log->pushHandler( new StreamHandler( $this->logpath, WHMCSAPILOGLEVEL ) );
		$this->log->pushProcessor( new IntrospectionProcessor() );
		$this->log->addInfo( 'Initialized' );
		ErrorHandler :: register( $this->log );
	}
	
	
	/**
	 * Method to set an item on this object
	 * @access		public
	 * @version		@fileVers@
	 * @param		string		method called
	 * @param		array		passed by magic __call method
	 *
	 * @since		2016 Apr 5
	 */
	public function setitem( $name, $args )
	{
		$name	=	strtolower( str_replace( 'set', '', $name ) );
		$value	=	$args[0];
		if ( $name == 'password' ) $value = md5( $value );
		$this->$name	=	$value;
	}
	
	
	/**
	 * Method for parsing all returned data regardless of response type
	 * @desc		Provide uniform method of retrieving data from our system
	 * @access		private
	 * @param		string
	 * @param		string
	 * 
	 * @return		array | string | false
	 * @since		2016 Apr 4
	 */
	private function _parse( $data, $action = null )
	{
		switch ( $this->responsetype ):
		case 'xml' :
			libxml_use_internal_errors( true );
			$xml	=	simplexml_load_string( $data );
			if ( $xml === false ) {
				foreach( libxml_get_errors() as $error ) $this->log->addAlert( 'XML Parsing Error! ' . $error->message, array( 'data' => $data ) );
				return false;
			}
			
			$result	=	json_decode( json_encode( $xml ) );
			break;
		case 'json' :
			$result	=	json_decode( trim( $data ) );
			break;
		case 'nvp' :
			$result	=	array();
			$parts	=	explode( "&", trim( $data, "\"" ) );
			foreach ( $parts as $piece ) {
				$tmp	=	explode( "=", $piece );
				$result[$tmp[0]] = $tmp[1];
			}
			break;
		endswitch;
		
		return $result;
	}
	
	
	/**
	 * Method to make a request to the WHMCS API
	 * @access		private
	 * @param		string
	 * @param		array
	 *
	 * @return		mixed|false
	 * @since		2016 Apr 4
	 */
	private function _request( $action, $fields )
	{
		$fields['username']		=	$this->username;
		$fields['password']		=	$this->password;
		
		if (! is_null( $this->accesskey ) ) {
			$fields['accesskey']	=	$this->accesskey;
		}
		
		$fields['action']		=	$action;
		
		$call	=	rtrim( $this->url, '/' ) . '/includes/api.php';
		
		try {
			
			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $call );
			curl_setopt( $ch, CURLOPT_POST, 1 );
			curl_setopt( $ch, CURLOPT_TIMEOUT, 300 );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $fields ) );
			$file = curl_exec( $ch );
			if ( curl_error( $ch ) ) {
				throw new \Exception('Unable to connect: ' . curl_errno( $ch ) . ' - ' . curl_error( $ch ) );
			}
			$this->_result	=	$file;
			$this->_info	=	curl_getinfo( $ch );
			curl_close( $ch );
		}
		catch (\Exception $e ) {
			$arr	=	array(
							'message'	=>	$e->getMessage(),
							'call'		=>	$call,
							'result'	=>	$file,
						);
			foreach( $fields as $k => $f ) $arr["{$k}"]	=	$f;
			$this->log->addWarning( 'Error making call to API:  ' . $action, array( $arr ) );
			return false;
		}
		
		$results	=	$this->_parse( $file, $action );
		
		return $results;
	}
}