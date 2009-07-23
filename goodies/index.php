<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as
published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

{
   $NOW = time();
   header('Expires: ' . gmdate('D, d M Y H:i:s',$NOW) . ' GMT');
   header('Last-Modified: ' . gmdate('D, d M Y H:i:s',$NOW-30) . ' GMT');
   header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0'); // HTTP/1.1
   header('Pragma: no-cache');                                              // HTTP/1.0
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
                      "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr">

<head>
 <title>DGS goodies</title>
 <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>

 <style type="text/css">
   li {
      margin-top: 2em;
      border-top: 1px solid gray;
   }
   pre {
      margin: 0.5em 0px;
      background-color: #f0f0f0;
      overflow: visible;
   }
 </style>
</head>

<body>
 <h3>Dragon Go goodies</h3>
 <div>
  <h4>GreaseMonkey scripts</h4>
  <em>
   Depending of your GreaseMonkey version, either Click or Right-Click
   a desired name link to install it:
  </em>
  <ul>
<?php
{
   $files = array();
   if( $fh = opendir('.') )
   {
      while( false !== ($file = readdir($fh)) )
      {
         if( substr($file,-8) == '.user.js' )
            $files[] = $file;
      }
      closedir($fh);
   } else echo "Error: open dir fails<br />";
   asort( $files);

   foreach( $files as $file )
   {
      //echo "<p>$file</p>\n";
      $fh = fopen($file, 'r');
      $txt = fread($fh, filesize($file));
      fclose($fh);

      // @name        DGS Section Hide
      foreach( array('name','description') as $field )
      {
         $r = '%@'.$field.'\\s+(.*)%i';
         preg_match($r, $txt, $m);
         $$field = @$m[1];
      }
      $r = '%<scriptinfos>(.*?)</scriptinfos>%ism';
      preg_match($r, $txt, $m);
      $infos = @$m[1];
      $infos = trim($infos, "\n\r");
      $infos = preg_replace(
         '%(http://[^\\s]+)%is',
         "<a href='\\1'>\\1</a>",
         $infos);

      $txt = '';

      $str = "- $description\n";
      if( $infos )
         $str.= "<br />- Script infos:\n";
      $txt.= "<dt>\n$str</dt>\n";

      if( !$infos ) $str = ''; else
         $str = "<pre>$infos</pre>\n";
      $txt.= "<dd>\n$str</dd>\n";

      $txt = "<dl>\n$txt</dl>\n";

      //disabling the caches while installing the script cause me some problems.
      //the ?date=$NOW ensure to reload the file (fake no-cache)
      //the $NOW.user.js give a fake extension to feed the GreaseMonkey install
      $str = "<p><strong>Name: <a href='./$file?date=$NOW.user.js'>$name</a></strong></p>\n";
      //if the .htaccess modules could accept:
      //ExpiresActive On
      //ExpiresDefault A1
      //use the simple:
      //$str = "<p><strong>Name: <a href='./$file'>$name</a></strong></p>\n";

      $txt = "<li>$str$txt</li>\n";

      echo $txt;
   } //$files
}
?>
  </ul>
 </div>
</body></html>
