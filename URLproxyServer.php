<?php

/*
This program is free software; you can redistribute it and/or modify
under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

/************************************************\
* URL proxy v1.3 Pre-Alpha                       *
* a co-authored script                           *
*  by Scott Atkins <atkinssc@engr.orst.edu>      *
*     Bob Matcuk <bmatcuk@users.sourceforge.net> *
* Copyright (C) 2002 All rights reserved         *
*  Released under the GPL, see the README        *
*                                                *
* Edit config.php instead of this file please    *
* If you change this file and break your proxy   *
* don't ask for help, as you were warned         *
\************************************************/

// User should not have to edit this file
include("config.php");
include("classWebPage.php");

// Constants
$STATIC = 1;
$DYNAMIC = 2;

// Mode of the script
$proxyMode = ((isset($server) AND isset($redirectIP)) ? $STATIC : $DYNAMIC);


// Version Number
$phase = "pre-alpha";
$version = "1.3 " . $phase;

/*Flags******************************************\
* $isHTML;  true if mimetype is html             *
* $isImage; true if mimetype is an image         *
* $isDown;  true if specified mimetype is to be  *
*           downloaded                           *
* $isAuth;  true if the page is protected by     *
*           .htaccess                            *
* $isForm;  true if the page contains a form     *
\************************************************/
$isHTML  = false;
$isImage = false;
$isDown  = false;
$isAuth  = false;
$isForm  = false;
$isError = false;

/*getContentType*********************************\
* Function for finding the mime type of the file *
* Returns the content type                       *
\************************************************/
function getContentType($headers){
	foreach($headers as $h){
		if(preg_match('/^Content-Type:\s+(.*)$/i',$h,$A1))
			return $A1[1];
	}
}

/*processHeaders*********************************\
* Function for handling the headers that apache  *
* sends out.                                     *
* Returns an array with the headers that should  *
* be sent out                                    *
\************************************************/
function processHeaders($headers, $fileName, $mime_dl, &$type,
			  &$isDown, &$isHTML, &$isImage){
	array_shift($headers);
	$type = getContentType($headers);
	$type = $type ? explode(';', $type)[0] : $type;
	$isDown = (isset($mime_dl[$type]) ? $mime_dl[$type] : true);
	if(preg_match('/image/i',$type))
		$isImage = true;
	else if(preg_match('/text\/html/i',$type))
		$isHTML  = true;
	else if($isDown)
		$headers[] = "Content-Disposition: attachment;" . 
				" filename=$fileName";
	return $headers;
}

/************************************************\
* Reparse query string using custom parser to    *
* prevent the annoying '+' → ' ' substitution    *
\************************************************/
/**
* Breaks a string into a pair for a common parsing function.
*
* The string passed in is truncated to the left half of the string pair, if any, and the right half, if anything, is returned.
*
* An example of using this would be:
* <code>
* $path = "Account.Balance";
* $field = string_pair($path);
*
* $path is "Account"
* $field is "Balance"
*
* $path = "Account";
* $field = string_pair($path);
*
* $path is "Account"
* $field is false
* </code>
*
* @return string The "right" portion of the string is returned if the delimiter is found.
* @param string $a A string to break into a pair. The "left" portion of the string is returned here if the delimiter is found.
* @param string $delim The characters used to delimit a string pair
* @param mixed $default The value to return if the delimiter is not found in the string
* @desc
*/
function string_pair(&$a, $delim='.', $default=false)
{
    $n = strpos($a, $delim);
    if ($n === false)
        return $default;
    $result = substr($a, $n+strlen($delim));
    $a = substr($a, 0, $n);
    return $result;
}

/**
* Similar to parse_str. Returns false if the query string or URL is empty. Because we're not parsing to
* variables but to array key entries, this function will handle ?[]=1&[]=2 "correctly."
*
* @return array Similar to the $_GET formatting that PHP does automagically.
* @param string $url A query string or URL
* @param boolean $qmark Find and strip out everything before the question mark in the string
*/
function parse_query_string($url, $qmark=true)
{
    if ($qmark) {
        $pos = strpos($url, "?");
        if ($pos !== false) {
            $url = substr($url, $pos + 1);
        }
    }
    if (empty($url))
        return false;
    $tokens = explode("&", $url);
    $urlVars = array();
    foreach ($tokens as $token) {
        $value = string_pair($token, "=", "");
        $urlVars[rawurldecode($token)] = rawurldecode($value);
    }
    return $urlVars;
}

$_GET = parse_query_string($_SERVER['QUERY_STRING'], false);

/************************************************\
* This block of code gets the directory we are   *
* currently in, for rel links.                   *
\************************************************/
preg_match('/^[a-z]{3,}:\/\/[^\/]*\/(.+)$/i', $_GET[$fileVar], $result);
if($result){
    $relDir = $result[1];
}else{
    $relDir = $_GET[$fileVar];
}

/************************************************\
* We create a new object of type WebPage and     *
* pass it the url we are being a proxy for and   *
* other information about the current state.     *
\************************************************/
$page = new WebPage($redirectIP."/".str_replace('%2F', '/', rawurlencode($_GET[$fileVar])),true,$server,basename(__FILE__),$fileVar,$relDir);

/************************************************\
* This tells the WebPage object to open up a     *
* connection to the URL.                         *
*                                                *
* Note:                                          *
* This does not actually get the web page, just  *
* opens the connection for the headers.          *
\************************************************/
$page->openLink();

/************************************************\
* Process the headers so we know what kind of    *
* data we have (html/other)                      *
\************************************************/
$head = processHeaders($page->getHeaders(),basename($_GET[$fileVar]),$mime_dl,$type,$isDown,$isHtml,$isImage);

/************************************************\
* This code replicates the headers that were     *
* sent when the class connected to the url.      *
*                                                *
* FIXME: extra headers need to be sent if we are *
* downloading the file.                          *
*                                                *
* GOTCHA?: need to check if http 1.1 will work   *
* correctly                                      *
\************************************************/
foreach($head as $h) header($h);

/************************************************\
* This block of code displays the page to the    *
* user.                                          *
*                                                *
* Note: Both processPageData and getRawPageData  *
* close the connection to the URL when they      *
* return.  You must re-open a connection with    *
* openLink to use them again.                    *
\************************************************/
if($isHtml){
    $page->processPageData();
    if($encryptPage)
        $page->encryptPage();
    echo $page->getPageData();

}else{
    $page->getRawPageData();
}
?>
