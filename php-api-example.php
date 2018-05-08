<?php

/*
MisterTango API PHP wrapper

Example:

// your api credentials
$key = 'your-API-key';
$secret = 'your-API-secret';

$url = 'https://api.mistertango.com:8445';
$sslverify = true;

$mt = new MTapi($key, $secret, $url, $sslverify);

$res = $mt->QueryPublic('/v1/utility/getCountriesList');
print_r($res);

$res = $mt->QueryPrivate('/v1/user',array('username'=>'user@domain'));
print_r($res);
*/

class MTapiException extends \ErrorException {};

class MTapi
{
    protected $key;     // API key
    protected $secret;  // API secret
    protected $url;     // API base URL
    protected $curl;    // curl handle

    /**
     * Constructor
     *
     * @param string $key API key
     * @param string $secret API secret
     * @param string $url base URL for API
     */
    function __construct($key='', $secret='', $url='', $sslverify=true)
    {

        /* check we have curl */
        if(!function_exists('curl_init')) {
         print "[ERROR] The API client requires that PHP is compiled with 'curl' support.\n";
         exit(1);
        }

        $this->key = $key;
        $this->secret = $secret;
        $this->url = $url;
        $this->curl = curl_init();

        curl_setopt_array($this->curl, array(
            CURLOPT_SSL_VERIFYPEER => $sslverify,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'MTapi',
            CURLOPT_RETURNTRANSFER => true)
        );
    }

    function __destruct()
    {
    	if(function_exists('curl_close')) {
         curl_close($this->curl);
	}
    }

    /**
     * Query public methods
     *
     * @param string $method method name
     * @param array $request request parameters
     * @return array request result on success
     * @throws MTapiException
     */
    function QueryPublic($method, array $request = array())
    {
        curl_setopt_array($this->curl, array(CURLOPT_POST => false)     );
		
		
		// build the POST data string
        $postdata = http_build_query($request, '', '&');
        if ($postdata)   $postdata='?'.$postdata;
        // make request
        curl_setopt($this->curl, CURLOPT_URL, $this->url . $method.$postdata);
        $result = curl_exec($this->curl);
        if($result===false)
            throw new Exception('CURL error: ' . curl_error($this->curl));

        // decode results
        $result = json_decode($result, true);
        if(!is_array($result))
            throw new MTapiException('JSON decode error');

        return $result;
    }

    /**
     * Query private methods
     *
     * @param string $method method path
     * @param array $request request parameters
     * @return array request result on success
     * @throws MTapiException
     */
    function QueryPrivate($method, array $request = array())
    {
        curl_setopt_array($this->curl, array(CURLOPT_POST => true)     );
		 
		if(!isset($request['nonce'])) {
            // generate a 64 bit nonce using a timestamp at microsecond resolution
            // string functions are used to avoid problems on 32 bit systems
            $nonce = explode(' ', microtime());
            $request['nonce'] = $nonce[1] . str_pad(substr($nonce[0], 2, 6), 6, '0');
        }

        // build the POST data string
        $postdata = http_build_query($request, '', '&');

        // set API key and sign the message
        $path =  $method;
        $sign = hash_hmac('sha512', $path . hash('sha256', $request['nonce'] . $postdata, true), $this->secret, true) ;
 
		$headers = array(
            'X-API-KEY: ' . $this->key,
            'X-API-SIGN: ' . base64_encode($sign),
            'X-API-NONCE: ' . $request['nonce']
        );
		
		
        // make request
        curl_setopt($this->curl, CURLOPT_URL, $this->url . $path);
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($this->curl);
        if($result===false)
            throw new MTapiException('CURL error: ' . curl_error($this->curl));

        // decode results
        $result = json_decode($result, true);
        if(!is_array($result))
            throw new MTapiException('JSON decode error');

        return $result;
    }
}
