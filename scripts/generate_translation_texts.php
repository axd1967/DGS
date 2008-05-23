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

chdir( '../' );
require_once( "include/std_functions.php" );
require_once( "include/make_translationfiles.php" );
chdir( 'scripts' );

{
   disable_cache();

   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   if( !(@$player_row['admin_level'] & ADMIN_TRANSLATORS) )
      error('adminlevel_too_low');

   $page = $_SERVER['PHP_SELF'];
   $page_args = array();

   $TheErrors->set_mode(ERROR_MODE_PRINT);

   start_html('generate_translation_texts', 0);

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


   $result = mysql_query(
         "SELECT TranslationPages.*,TranslationGroups.Groupname"
         ." FROM TranslationPages"
         ." LEFT JOIN TranslationGroups"
            ." ON TranslationGroups.ID=TranslationPages.Group_ID"
         );

   while( $row = mysql_fetch_array($result) )
   {
      $Filename = $row['Page'];
      $Group_ID = $row['Group_ID'];
      $GroupName = @$row['Groupname'];

      echo "<hr><p>$Filename - Group $Group_ID [$GroupName]</p><hr>\n";

      $fd = fopen( $main_path . $Filename, 'r' )
         or error( 'couldnt_open_file', 'generate_translation_texts:'.$Filename);

      $contents = fread($fd, filesize ($main_path . $Filename));

      $pattern = "%T_\((['\"].*?['\"])\)[^'\"]%s";
      preg_match_all( $pattern, $contents, $matches );

      foreach( $matches[1] as $str )
      {
         unset($string);
         //$e= error_reporting(E_ALL & ~(E_WARNING | E_NOTICE | E_PARSE));
         eval( "\$string = trim($str);" );
         //error_reporting($e);
         if( !isset($string) || $string == '' )
         {
            $tmp = textarea_safe($str);
            echo "<br>*** Error: something went wrong with [$tmp]<br>";
            continue;
         }
         $tmp= add_text_to_translate('generate_translation_texts'
               , $string, $Group_ID, $do_it); //$do_it => dbg_query
         if( $tmp )
         {
            if( $do_it )
               $tmp= '++ '.$string;
            echo textarea_safe($tmp)."<br>";
         }
      }
   }
   mysql_free_result($result);

   echo "<hr>Done!!!\n";
   end_html();
}

?>
