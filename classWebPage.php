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

/*Class WebPage**********************************\
* Come along with me into the world of ooPHP.    *
*                                                *
*  WebPage is the main class in this script.  It *
* controls getting pages as well as headers and  *
* processing those pages.                        *
\************************************************/
class WebPage {

/*Var Descriptions*******************************\
* $URL The url we are proxying for               *
* $pageData The raw data from the file           *
* $headers Array containing the headers sent     *
* $static Boolean if we are working on only rel  *
*         links                                  *
* $currentServer String with the script's server *
* $scriptName Name of the main script            *
* $varName Name of the var for passed in file    *
* $updatedPageData The processed page data       *
* $relDir The directory we're in for rel links   *
* $fp The file pointer for reading the file      *
\************************************************/
    var $URL;
    var $pageData;
	var $headers;
	var $static;
	var $currentServer;
	var $scriptName;
	var $varName;
	var $updatedPageData;
	var $relDir;
	var $fp;
    
/*Class Constructor******************************\
* The main constructor, pass in varible are      *
* listed below.                                  *
*                                                *
* $URL The url we are proxying for               *
* $static Boolean if we are working on only rel  *
*         links                                  *
* $currentServer String with the script's server *
* $scriptName Name of the main script            *
* $varName Name of the var for passed in file    *
* $relDir The directory we're in for rel links   *
\************************************************/
	
	function WebPage($URL, $static, $currentServer, $scriptName, $varName, $relDir){
	   $this->URL = $URL;
	   $this->currentServer = $currentServer;
	   $this->static = $static;
	   $this->relDir = $relDir;
	   $this->pageData = "";
	   $this->varName = $varName;
	   $this->scriptName = $scriptName;
	}

/*openLink***************************************\
* Function for connecting to the URL and getting *
* the headers.                                   *
*                                                *
* Note: The connection is closed by              *
* getRawPageData or processPage, whichever comes *
* first.                                         *
\************************************************/
	function openLink(){
	    $this->fp = fopen($this->URL, "rb");
	    array_shift($http_response_header);
	    $this->headers = $http_response_header;
	}

/*getHeaders*************************************\
* Function returns an array containing the       *
* headers that resulted from the page.           *
\************************************************/
	function getHeaders(){
	    return $this->headers;
	}

/*getRawPageData*********************************\
* Prints out the $fp as a stream without         *
* processing (for images/such)                   *
\************************************************/
	function getRawPageData(){
	    fpassthru($this->fp);
	    fclose($this->fp);
	    return;
    }

/*getPageData************************************\
* Function returns a string containing the       *
* page data.  processPageData must be used       *
* before this function is called.                *
\************************************************/
	function getPageData(){
	    return $this->updatedPageData;
	}

/*processPageData********************************\
* Function process the page, fixing links and    *
* images to use the proxy as specified by the    *
* class.                                         *
*                                                *
* Note: Add items here if you want to add extra  *
* prefixes or delims.                            *
\************************************************/
    function processPageData(){
	    $this->pageData = "";
	    while( !feof( $this->fp)){
			$this->pageData .= fgets($this->fp, 4096);
	    }
	    fclose($this->fp); 
	    $delim[]='"';
	    $delim[]="'";
	    $delim[]="";
	    $pre[]="src\\s*=";
	    $pre[]="background\\s*=";
	    $pre[]="href\\s*=";
	    $pre[]="url\\s*\\(";
	    $pre[]="codebase\\s*=";
	    $pre[]="url\\s*=";
	    $pre[]="archive\\s*=";
	    $this->redirect($pre,$delim);
	}

/*fileName***************************************\
* Function returns the name of the file that the *
* URL specifies.                                 *
\************************************************/
	function fileName(){
	    return eregi_replace("[#?].*$", "", 
		eregi_replace("^.*/", "", $this->URL));
	}

/*encodeHTML*************************************\
* Fix Me:                                        *
* This is not done yet but the idea is to change *
* all the html charcters to their special char   *
* information (to keep people from stealing your *
* source and using it)                           *
\************************************************/
	function encodeHTML(){
	    // Not Done Yet
	}

/*encryptPage************************************\
* Function changes updatedPageData so that the   *
* file is encrypted while sending.               *
\************************************************/
	function encryptPage(){
		$data2 = "";
		foreach (split("\n",eregi_replace("\r","",$this->updatedPageData)) as $a){
			$data = $this->encrypt(chop($a));
			$data = str_replace( "\\", "\\\\", $data);
			$data = str_replace( "\"", "\\\"", $data);
			$data2 .= "document.write( c(\"".$data."\") );\n";
		}
        $this->updatedPageData = ""
			. "<!-- URL Proxy Server\n"
			. "Javascript by Bob Matcuk\n"
			. "BMatcuk@Users.SourceForge.Net -->\n"
			. "<script language=\"Javascript\">\n"
        	. " function c(s){\n"
        	. "    var fi = \"\";\n"
        	. "    for( var i = 0; i < s.length-1; i += 2 ){\n"
        	. "       fi = fi + s.charAt(i+1) + s.charAt(i);\n"
        	. "    }\n"
        	. "    if( i < s.length ){\n"
        	. "       fi = fi + s.charAt(i);\n"
        	. "    }\n"
        	. "    fi = fi + \"\\n\";\n"
        	. "    return fi;\n"
        	. " }\n" . " document.open();\n"
			. $data2
			. " document.close();\n"
			. "</script>\n";
	}
	
/*redirect***************************************\
* Private Function, DO NOT USE FROM PUBLIC SCOPE *
* Basically converts the prefixes in             *
* $prefixArray and the delim in $delimArray to a *
* string and uses it to fix links within the     *
* page.                                          *
\************************************************/
	function redirect($prefixArray,$delimArray){
	    $a = $this->pageData;
	    $name = $this->currentServer;
	    $fileDir = $this->relDir;
	    foreach($prefixArray as $prefix){
			$start  = "{$prefix}\\s*";
			$start2 = stripslashes(str_replace('\s*', '', $prefix));
			foreach($delimArray as $delim){
		    	if(preg_match("/{$start}{$delim}/i", $a) && ($delim == '' ? preg_match("/{$start}[a-zA-Z0-9\/_-]+/i", $a) : 1)){
					$a = preg_replace("/{$start}{$delim}\/\//i", "{$start2}{$delim}//", $a);
					
					$a = preg_replace("/{$start}{$delim}\//i",               "{$start2}\a{$name}/{$this->scriptName}?{$this->varName}=/", $a);
					$a = preg_replace("/{$start}{$delim}([a-z]{3,}):\/\//i", "{$start2}\a\\1://", $a);
					$a = preg_replace("/{$start}{$delim}([a-z]{3,}):/i",     "{$start2}\a\\1:",   $a);
					$a = preg_replace("/{$start}{$delim}#/i",                "{$start2}\a#",      $a);
					$a = preg_replace("/{$start}{$delim}/i",                 "{$start2}\a{$name}/{$this->scriptName}?{$this->varName}={$fileDir}", $a);
					
					$a = preg_replace("/{$start2}\\\\a/i", "{$start2}{$delim}", $a);
					
		    	}
	        }
	    }
	    $this->updatedPageData = $a;
	}

/*encrypt****************************************\
* This function encrypts the page before it is   *
* sent out.                                      *
* Returns the file as a string, encrypted        *
\************************************************/
	function encrypt( $string ){
	  for( $i = 0; $i < strlen( $string ) - 1; $i += 2 ){
	    $temp = $string[$i];
	    $string[$i] = $string[$i+1];
	    $string[$i+1] = $temp;
	  }
	  return $string;
	}
}
?>
