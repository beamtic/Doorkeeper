<?php

// ********** The Doorkeeper CMS **********


// Simple HTTP client for sending HTTP requests


require $_SERVER["DOCUMENT_ROOT"] .'lib/core_classes/core_helpers_class.php';

class http_client
{
    private $request_headers = array();
    private $helpers;
    protected $host;


    public function __construct() {
        $this->helpers = new core_helpers(); // Helper methods
    }
    public function httpRequest($arguments_arr)
    {
        // Set default values of arguments
        $default_argument_values_arr = array(
            'url' => 'REQUIRED',
            'handle_cookies' => 'false',
            'request_type' => 'GET',
            'postdata' => 'false'
        );
        $arguments_arr = $this->helpers->default_arguments($arguments_arr, $default_argument_values_arr);
        // Arguments recently replaced:
        // $request_url, $handle_cookies=false, $request_type='GET', $postdata=false
        // Make sure you update to the new array-format
        // This allows us to use arguments in any order we want
        
        // preg_match('|^([a-z]{1,5}://[^/]+/)|', $arguments_arr['url'], $matches);
        $parsed_url_arr = parse_url($arguments_arr['url']);
        $this->host = $parsed_url_arr['host'];
        
        $ch = curl_init($arguments_arr['url']);
        
        // Only Handle cookies if explicitly requested
        if ($arguments_arr['handle_cookies'] == true) {
            
            // Get Cookies, if any...
            $cookies = $this->findFiles('txt', $_SERVER["DOCUMENT_ROOT"] . '/data/');
            
            // We can't use cURLs build-in cookie handling due to cURL not picking up cookies delivered with a redirect response
            // We also can't auto-follow rediects for the same reason.
            if ($cookies !== false) {
                // If we got any cookies, set them if valid. I.e. If they are not expired
                $cookieString = $this->giveCookies($cookies);
                if ($cookieString !== false) {
                    // If valid cookies where found, set the request header
                    $request_headers[] = 'Cookie: ' . $cookieString;
                }
            }
        }
        // Return Response headers with body
        curl_setopt($ch, CURLOPT_HEADER, true);
        
        // Set the SSL certificate thing
        // File from http://curl.haxx.se/ca/cacert.pem
        // pcurl_setopt($ch, CURLOPT_CAINFO, $_SERVER["DOCUMENT_ROOT"] . '/data/cacert.pem');
        
        // Make cURL auto-handle supported encodings
        curl_setopt($ch, CURLOPT_ENCODING, "");
        // Return result rather than true on success
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        // If a POST is to be performed
        if ($arguments_arr['request_type'] == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($arguments_arr['postdata'] !== false) {
                // When performing POST requests with cURL, i encountered lots of problems.
                // 1. it was sending "multipart/form-data" with a boundary, instead of sending it as "application/x-www-form-urlencoded"
                // 2. using http_build_query on the array i was passing somehow messed up the post fields
                // Finally i just passed the POST data as a naked string – this seems to solve all the submission problems
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
            }
        } else if ($arguments_arr['request_type'] == 'HEAD') {
            curl_setopt($ch, CURLOPT_NOBODY, true);
        }
        // Set Request Headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->request_headers);
        
        // If error we will re-try a couple of times after waiting a bit, before finally failing
        $i = 0;
        while ($i < 3) {
            sleep(1);
            $result = curl_exec($ch);
            if (curl_errno($ch)) {
                ++ $i;
            } else {
                $aResult = explode("\r\n\r\n", $result, 2);
                $aResult['code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($arguments_arr['handle_cookies'] == true) {
                    // Save/Update cookies
                    $this->handleCookies($aResult[0]);
                }
                return $aResult;
            }
        }
        $aResult['error'] = curl_errno($ch);
        return $aResult['error'];
    }

    public function parse_response_headers($headers)
    {
        $headers_arr = array();
        $response_headers = array();
        
        $headers_arr = explode("\r\n", $headers); // Create an array with response headers
        if (isset($headers_arr[0])) {
            $response_headers['status'] = $headers_arr[0]; // Grab the status code from the $headers_arr
            unset($headers_arr[0]); // Unset before parsing the rest of the headers
                                    
            // *******************************
            // CREATE THE ASSOCIATIVE ARRAY WITH RESPONSE HEADERS
            // *******************************
            foreach ($headers_arr as $value) {
                preg_match("/^([^:]+):(.*)/", $value, $matches); // The last part " .* " may need improving to catch all headers.
                if ((isset($matches[1])) && (isset($matches[2]))) { // Only include the header if both parts could be grabbed
                    $matches[1] = trim($matches[1]);
                    $matches[2] = trim($matches[2]);
                    $response_headers["{$matches[1]}"] = $matches[2];
                }
            }
            return $response_headers; // Return the associative array
        } else {
            return false; // Return false if no response headers could be grabbed
        }
    }

    private function findFiles($filetype = 'txt', $dir)
    {
        // Function to list files in directory
        
        // Consider moving this to core_helpers_class.php or file_handler_class.php !!
        $regex = '/.' . $filetype . '$/i';
        $files_found_arr = array();
        if ($handle = opendir($dir)) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != "..") {
                    if (preg_match($regex, $entry)) {
                        $files_found_arr[] = $entry;
                    }
                }
            }
            closedir($handle);
        }
        if (count($files_found_arr) >= 1) {
            return $files_found_arr;
        } else {
            return false; // Return false if we did not find any files
        }
    }

    private function handleCookies($input)
    {
        // Function to Handle Cookies
        if (preg_match_all("/set-cookie: ([^=]+)([^\r\n]*)/i", $input, $cookies, PREG_SET_ORDER) !== false) {
            foreach ($cookies as $value) {
                // Takes each cookie and saves it to a local file for later access
                $fp = fopen($_SERVER["DOCUMENT_ROOT"] . '/data/' . $value[1] . '.txt', 'w');
                fwrite($fp, $value[1] . $value[2]);
                fclose($fp);
            }
            return true;
        } else {
            return false;
        }
    }

    private function giveCookies($cookies)
    {
        // Function to give Cookies back to the server
        // This function is work-in-progress, and should be improved to take into account expiration dates, etc.
        $CookieString = '';
        foreach ($cookies as $cookie) {
            $CookieContent = file_get_contents($_SERVER["DOCUMENT_ROOT"] . '/data/' . $cookie);
            if (preg_match("/([^;]+);/", $CookieContent, $aGrabIt) !== 0) {
                if ($CookieString !== '') {
                    $CookieString .= ';';
                }
                $CookieString .= $aGrabIt[1];
            }
        }
        if ($CookieString !== '') {
            return $CookieString;
        } else {
            return false;
        }
    }

    public function set_request_headers($arrayInput)
    {
        foreach ($arrayInput as $key => $value) {
            $this->request_headers[] = $key . ': ' . $value;
        }
    }
}