<?php
   $NOW = time();
   header('Expires: ' . gmdate('D, d M Y H:i:s',$NOW) . ' GMT');
   header('Last-Modified: ' . gmdate('D, d M Y H:i:s',$NOW-30) . ' GMT');
   header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0'); // HTTP/1.1
   header('Pragma: no-cache');                                              // HTTP/1.0
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

      //the ?date=$NOW ensure to reload the file (fake no-cache)
      //$str = "<p><strong>Name: <a href='./$file?date=$NOW'>$name</a></strong></p>\n";
      $str = "<p><strong>Name: <a href='./$file'>$name</a></strong></p>\n";
      $txt = "<li>$str$txt</li>\n";
      
      echo $txt;
   } //$files
  ?>
  </ul>
 </div>
</body></html>