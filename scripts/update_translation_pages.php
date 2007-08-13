<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software Foundation,
Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
*/

// Search the php files to see which are to be included in TranslationPages




chdir( '../' );
require_once( "include/std_functions.php" );

if (!function_exists('glob')) {
//glob exists by default since PHP version 4.3.9 (The PHP Group)
/* Valid flags:
Note : GLOB_ONLYDIR is not available on Windows.
*/
define('GLOB_MARK'    ,0x01); //+ Adds a slash to each item returned
define('GLOB_NOSORT'  ,0x02); //+ Return files as they appear in the directory (no sorting)
define('GLOB_NOCHECK' ,0x04); //+ Return the search pattern if no files matching it were found
define('GLOB_NOESCAPE',0x08); // Backslashes do not quote metacharacters
define('GLOB_BRACE'   ,0x10); // Expands {a,b,c} to match 'a', 'b', or 'c'
define('GLOB_ONLYDIR' ,0x20); //+ Return only directory entries which match the pattern
function glob($pat, $flg=0)
{
   //$pat= realpath($pat);
   $dir= pathinfo($pat);
   $rexp=filepat2preg($dir['basename']);
   $dir= $dir['dirname'];
   $dir=dir_slashe( $dir, 1);
   //echo "glob: dir='$dir' rexp=$rexp<br>";

   $res= array();
   if (is_dir($dir)) {
     if ($dh = opendir($dir)) {
       while (($file = readdir($dh)) !== false) {
         if (preg_match($rexp,$file)) {
           if (!($flg & GLOB_ONLYDIR) || is_dir($dir.$file))
             $res[]=$dir.$file.(($flg & GLOB_MARK)?'/':'');
         }
       }
       closedir($dh);
     }
   }
   if (count($res)) {
     if (!($flg & GLOB_NOSORT)) sort($res);
   } else {
     if ( ($flg & GLOB_NOCHECK)) $res=$rexp;
     else $res=false;
   }
   return $res;
}//glob

function dir_slashe( $fn, $trail=false)
{
  if( $fn == '.' )
    return '';
  $fn=str_replace('\\','/',$fn);
  if( $trail )
    if( substr($fn, -1) !== '/' )
      $fn.='/';
  return $fn;
}//dir_slashe

function filepat2preg($p, $flg=0)
{
  $p=preg_quote($p);
  $p=strtr($p, array(
      '\\?'=>'[^/\\\\]',
      '\\*'=>'[^/\\\\]*?',
      '\\['=>'[',
      '\\]'=>']',
    ));
  if( ($flg & GLOB_BRACE) )
    $p=preg_replace('%\\\\{([^}]*)\\\\}%e'
      ,"'('.strtr('\\1',array('\\\\\\\\\\\\\\\\,'=>',',','=>'|')).')'"
      ,$p);
  //the '+' are antislashed and unused in replacements
  $p='+^'.$p.'$+is';
  return $p;
}//filepat2preg

}//function_exists('glob')


function find_php_files( )
{
   $directories = array( ''
         , 'include/'
         , 'forum/'
         , 'translations/'
         , 'rss/'
         , 'wap/'
         );

   $array = array();

   foreach( $directories as $dir )
   {
      foreach (glob("$dir*.php") as $filename)
      {
         //echo "filename=$filename<br>";
         $array[] = $filename;
      }
   }
   return $array;
}

function grep_file($regexp, $file, &$matches)
{
   $fd = fopen($file, 'r');

   $contents = fread($fd, filesize($file));
   
   fclose($fd);

   return preg_match($regexp.'m', $contents, $matches);
}



{
   disable_cache();

   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   if( !(@$player_row['admin_level'] & ADMIN_DATABASE) )
      error('adminlevel_too_low');

   $page = $_SERVER['PHP_SELF'];
   $page_args = array();

   start_html('update_translation_pages', 0);

//echo ">>>> One shot fix. Do not run it again."; end_html(); exit;
   if( $do_it=@$_REQUEST['do_it'] )
   {
      function dbg_query($s) {
        if( !mysql_query( $s) )
           die("<BR>$s;<BR>" . mysql_error() );
        echo " --- fixed. ";
      }
      echo "<p>*** Fixes errors ***"
         ."<br>".anchor(make_url($page, $page_args), 'Just show it')
         ."</p>";
   }
   else
   {
      function dbg_query($s) { echo " --- query:<BR>$s; ";}
      $tmp = array_merge($page_args,array('do_it' => 1));
      echo "<p>(just show needed queries)"
         ."<br>".anchor(make_url($page, $page_args), 'Show it again')
         ."<br>".anchor(make_url($page, $tmp), '[Validate it]')
         ."</p>";
   }


   $query = "SELECT * FROM TranslationGroups";
   $result = mysql_query($query) or die(mysql_error());

   $translationgroups = array();
   while( $row = mysql_fetch_assoc( $result ) )
   {
      $translationgroups[$row['Groupname']] = $row['ID'];
   }


// first search the php pages to find which should be translated.

   $files = find_php_files();
   $translationpages_found = array();
   foreach( $files as $file )
   {
      //note: only keep the first match of the file (starting at column 1)
      //TODO: is this wanted?
      if( grep_file('/^\$TranslateGroups\[\] = \"(\w+)\";/', $file, $matches) )
      {
         $tmp = $matches[1];
         //echo $file . " " . $tmp . ", " . $translationgroups[$tmp] . "<br>\n";
         if( !isset($translationgroups[$tmp]) )
         {
            echo "<hr>Should be adjusted NOW: '$tmp' from $file ... OR be added:<br>\n";
            dbg_query("INSERT INTO TranslationGroups"
               ." SET Groupname='" . mysql_addslashes($tmp) . "'");
            echo "<hr>Fatal error: re-run the script!!!\n";
            end_html();
            exit;
         }
         $translationpages_found[$file] = array($translationgroups[$tmp], false);
      }
   }


// now, compare with the database to see which entries should be updated.

   $query = "SELECT * FROM TranslationPages";

   $result = mysql_query($query) or die(mysql_error());

   $n= (int)mysql_num_rows($result);
   echo "\n<br>=&gt; result: $n rows<p></p>\n";

   if( $n > 0 )
   while( $row = mysql_fetch_assoc( $result ) )
   {
      if( !isset($translationpages_found[$row['Page']]) )
      {
         echo "<hr>Should be deleted: " . $row['Page'] . "<br>\n";
         //TODO: could we drop the ID column of TranslationPages: it is only used in this script, isn't it?
         dbg_query("DELETE FROM TranslationPages WHERE ID=" . $row['ID'] . " LIMIT 1");
      }
      else
      {
         $ref = &$translationpages_found[$row['Page']];
         if( $ref[1] === true )
         {
            echo "<hr><font color=red>Error: Duplicate entry: " . $row['Page'] .
               "</font><br>\n";
            continue;
         }
         $ref[1] = true;
         if( $ref[0] !== $row['Group_ID'] )
         {
            echo "<hr>Group changed: " . $row['Page'] .
               ": " . $row['Group_ID'] . " --> " . $ref[0] . "<br>\n";
            dbg_query("UPDATE TranslationPages SET Group_ID=" . $ref[0] .
                      " WHERE ID=" . $row['ID'] . " LIMIT 1");
         }
      }
   }


   foreach( $translationpages_found as $page => $val )
   {
      if( $val[1] === false )
      {
         echo "<hr>To be added: " . $page . "<br>\n";
         dbg_query("INSERT INTO TranslationPages " .
            "SET Page='" . mysql_addslashes($page) . "', Group_ID=" . $val[0]);
      }
   }

   echo "<hr>Done!!!\n";
   end_html();
}

?>
