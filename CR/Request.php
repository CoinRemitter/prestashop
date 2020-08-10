<?php 
/**
 * 
 */
class Request 
{
	
	public function post($url, $postVars = array()){
		$version = "v2";
		$moduleVersion = '1.0.1';
	    //Transform our POST array into a URL-encoded query string.
	    $postStr = http_build_query($postVars);
	   	$userAgent = 'CR@' . $version . ',prestashop coinremitter@'.$moduleVersion;
	    //Create an $options array that can be passed into stream_context_create.
	    $options = array(
	        'http' =>
	            array(
	                'method'  => 'POST', //We are using the POST HTTP method.
	                'header' => "Content-type: application/x-www-form-urlencoded\r\n"."User-agent:".$userAgent ,
	                'content' => $postStr //Our URL-encoded query string.
	            ),
	           
	    );
	    //Pass our $options array into stream_context_create.
	    //This will return a stream context resource.
	    $streamContext  = stream_context_create($options);
	    //Use PHP's file_get_contents function to carry out the request.
	    //We pass the $streamContext variable in as a third parameter.
	    $result = file_get_contents($url, false, $streamContext);
	    //If $result is FALSE, then the request has failed.
	    if($result === false){
	        //If the request failed, throw an Exception containing
	        //the error.
	        $error = error_get_last();
	        throw new Exception('POST request failed: ' . $error['message']);
	    }
	    //If everything went OK, return the response.
	    if(!is_array($result)){
   			$result = json_decode($result,true);
       	}
	    return $result;
	}
	public function get($url){
		$version = "v2";
		$moduleVersion = '1.0.1';
		$userAgent = 'CR@' . $version . ',prestashop coinremitter@'.$moduleVersion;
	    $options = array(
	        'http' =>
	            array(
	                'method'  => 'GET', //We are using the GET HTTP method.
	            	 'header' => "User-agent:".$userAgent ,
	            ),

	    );
	    $streamContext  = stream_context_create($options);
	    //Use PHP's file_get_contents function to carry out the request.
	    //We pass the $streamContext variable in as a third parameter.
	    $result = file_get_contents($url, false, $streamContext);
	    //If $result is FALSE, then the request has failed.
	    if($result === false){
	        $error = error_get_last();
	        throw new Exception('GET request failed: ' . $error['message']);
	    }
	    if(!is_array($result)){
   			$result = json_decode($result,true);
       	}
	    //If everything went OK, return the response.
	    return $result;	
	}
}