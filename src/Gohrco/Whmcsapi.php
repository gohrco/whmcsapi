<?php

namespace Gohrco;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\ErrorHandler;

// Log level
defined( 'MPNLOGLEVEL' ) or define( "MPNLOGLEVEL", \Monolog\Logger :: WARNING );

/**
 * WHMCS API Handler
 * @since		2016 Apr 1
 */
class Whmcsapi
{
	private $log	=	null;
	private	$user	=	null;
	private $pass	=	null;
	private $apiurl	=	null;
	
	/**
	 * Constructor class
	 * @access		public
	 * @param		array		This should contain an array with our user settings
	 * 
	 * @since		2016 Mar 4
	 */
	public function __construct( $options )
	{
		$this->user		=	$options['user'];
		$this->pass		=	md5( $options['pass'] );
		$this->apiurl	=	$options['url'];
		
		$this->log	=	new Logger( 'whmcsapi' );
		$this->log->pushHandler( new StreamHandler( __DIR__ . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'mpnapi.log', MPNLOGLEVEL ) );
		$this->log->pushProcessor( new IntrospectionProcessor() );
		$this->log->addInfo( 'Initialized' );
		
		ErrorHandler :: register( $this->log );
	}
	
	
	/**
	 * Magic call method
	 * @access		public
	 * @param		string
	 * @param		array
	 *
	 * @return		array | false
	 * @since		2016 Apr 1
	 */
	public function __call( $name, $args )
	{
		$data		=	( count( $args ) > 0 ? array_shift( $args ) : array() );
		
		$this->log->addDebug( $name . ' Request Received', $data );
		
		if (! is_array( $data ) ) {
			$this->log->addWarning( 'Data passed to MPN API is not an array', array( 'notanarray' => $data ) );
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
	 * Method for parsing all returned data regardless of response type
	 * @desc		Provide uniform method of retrieving data from our system
	 * @access		private
	 * @param		string
	 * @param		string
	 * 
	 * @return		array | string | false
	 * @since		2016 Apr 1
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
	 * @since		2016 Apr 1
	 */
	private function _request( $action, $fields )
	{
		$fields['username']		=	$this->user;
		$fields['password']		=	$this->pass;
		$fields['action']		=	$action;
		
		$call	=	rtrim( $this->apiurl, '/' ) . '/includes/api.php';
		
		try {
			
			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $call );
			curl_setopt( $ch, CURLOPT_POST, 1 );
			curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $fields ) );
			$file = curl_exec( $ch );
			if ( curl_error( $ch ) ) {
				throw new \Exception('Unable to connect: ' . curl_errno( $ch ) . ' - ' . curl_error( $ch ) );
			}
			curl_close( $ch );
		}
		catch (\Exception $e ) {
			$arr	=	array(
							'message'	=>	$e->getMessage(),
							'call'		=>	$call,
							'result'	=>	$file,
						);
			foreach( $fields as $k => $f ) $arr["{$k}"]	=	$f;
			$this->log->addWarning( 'Error making call to MPN:  ' . $action, array( $arr ) );
			return false;
		}
		
		$results	=	$this->_parse( $file, $action );
		
		return $results;
	}
}