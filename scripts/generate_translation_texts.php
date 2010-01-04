<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
         "SELECT TP.*, TG.Groupname "
         . "FROM TranslationPages AS TP "
         . "LEFT JOIN TranslationGroups AS TG ON TG.ID=TP.Group_ID "
         . "ORDER BY TP.Page" );

   $errorfmt = "<br><a name=\"err%s\"></a><font color=\"red\">*** Error:</font> %s<br>\n"; //err-no, err-msg
   echo "<p><b>NOTE:</b> Check at <a href=\"#errors\">bottom</a> if errors or new entries are detected.<br>\n";

   $php5_strip_php_comments = (function_exists('php_strip_whitespace'));
   $errcnt = 0;
   $newcnt = 0;
   while( $row = mysql_fetch_array($result) )
   {
      $Filename = $row['Page'];
      $Group_ID = $row['Group_ID'];
      $GroupName = @$row['Groupname'];

      echo "<hr><p>$Filename - Group $Group_ID [$GroupName]</p><hr>\n";


      if( $php5_strip_php_comments )
         $contents = php_strip_whitespace($Filename); // strips also LFs
      else
      {
         //FIXME: what is $main_path ?
         $fd = fopen( $main_path . $Filename, 'r' )
            or error( 'couldnt_open_file', "generate_translation_texts.open_file($Filename)");
         $contents = fread($fd, filesize($main_path . $Filename));
         fclose($fd);
      }
      if( (string)$contents == '' )
      {
         printf( $errorfmt, ++$errcnt, "no content found reading file [$Filename]" );
         continue;
      }

      // NOTE: this old pattern does not cover all T_-texts.
      // Not possible with this pattern to ensure, if all wrong occurences are correctly formulated.
      //$pattern = "%T_\((['\"].*?['\"])\)[^'\"]%s";
      //preg_match_all( $pattern, $contents, $matches );

      // find and check all T_- and $T_-texts
      $matches = find_translation_texts( $contents );

      foreach( $matches as $resultmatch )
      {
         $tstring = '';
         list( $transl_text, $errormsg, $pos ) = $resultmatch;
         if( is_numeric($errormsg) && $errormsg == 0 )
         {
            $result_eval = eval( "\$tstring = $transl_text;" );
            if( $result_eval === false || strlen($tstring) == 0 )
               $errormsg = "Evaluation error";
            else
            {
               $orig_tstr = $tstring;
               $tstring = trim($tstring);
               if( strlen($orig_tstr) != strlen($tstring) )
                  $errormsg = "Found forbidden leading or trailing white-space";
            }
         }

         if( $errormsg )
         {
            printf( $errorfmt, ++$errcnt,
               "Something went wrong at parse pos #$pos [".textarea_safe($errormsg)
               . "] with [".textarea_safe($transl_text)."]" );
         }
         else
         {// $tstring defined
            $tmp = add_text_to_translate('generate_translation_texts',
               $tstring, $Group_ID, $do_it); //$do_it => dbg_query
            if( $tmp )
            {
               $newcnt++;
               if( $do_it )
                  $tmp= '++ '.$tstring;
               echo textarea_safe($tmp)."<br>\n";
            }
         }
      }
   }
   mysql_free_result($result);

   echo "<a name=\"errors\"></a><hr><p>\n";
   if( $errcnt > 0 )
   {
      printf( "Found %s errors. Please correct them before going on!!!<br>\nErrors: ", $errcnt );
      for( $i=1; $i <= $errcnt; $i++)
         echo "<a href=\"#err$i\">Error #$i</a>, ";
   }
   else
      echo "No errors occured.<br>\n";

   if( $newcnt > 0 )
      echo "<p>Found $newcnt new entries to insert!!!\n";
   else
      echo "<p>No new entries found.\n";

   echo "<p>Done!!!\n";
   end_html();
}


/*!
 * \brief Extracts translation-texts T_ and $T_ from given contents.
 * \param $contents content of php-file to scan for translation-texts.
 *        Expects, that PHP-comments are removed from $contents.
 *        if not, comments are searched for translation-texts as well.
 * \return array of [ translation-text, errmsg|0, found-pos ) ]
 */
function find_translation_texts( $contents )
{
   // NOTE: avoid T_... here to prevent this function find examples here
   // - valid: t( text [. text]* )
   // - invalid: text contains " ... $unquoted_var ... "
   // - invalid: text contains text . non-constant

   $result = array(); // arr( arr( tstr, errmsg|0 ), ...)

   $pos = 0;
   $clen = strlen($contents);
   // PARSE: find $T_ or T_
   while( preg_match('%(.*?)\b\$?T_(\s*)\(\s*%s', $contents, $matches, 0, $pos) )
   {
      $spos = $pos + strlen($matches[1]);
      if( strlen($matches[2]) > 0 )
         $result[] = array( substr($contents,$spos,30), "No space allowed after T_", $spos );
      $pos += strlen($matches[0]);

      $text = '';
      $endtext = false;
      $errpos = -1; // last error-pos
      $allow = 1|2; // bit0(1)=allow-text, bit1(2)=expect-text
      while( $pos < $clen && !$endtext )
      {
         eat_comments($contents, $clen, $pos); // PARSE: eat all comments
         if( $pos >= $clen) break;

         $ch = $contents[$pos];
         if( ($allow & 1) && ($ch == "'" || $ch == '"') )
         {
            list( $ttext, $errmsg, $tpos ) = eat_translation_text( $contents, $clen, $pos );
            if( $errmsg )
               $result[] = array( $ttext, $errmsg, $errpos = $spos );
            else
               $text .= $ttext;
            $allow = 0;
         }
         elseif( !$allow && $ch == '.' )
         {
            $pos++;
            $text .= ' . ';
            $allow = 1|2;
         }
         elseif( $ch == ')' )
         {
            $pos++;
            $endtext = true; // end of T_ found
            break;
         }
         else
         {
            $result[] = array( substr($contents,$pos,20),
               sprintf( "Unexpected character found [{$contents[$pos]}] for translation text [%s]",
                  substr($contents,$spos,$pos - $spos + 20)), $errpos = $spos );
            break;
         }
      }

      if( !$endtext )
      {
         if( $errpos != $spos )
            $result[] = array( $text, "Missing end ')' for translation text", $spos );
      }
      elseif( $allow & 2 )
      {
         if( $errpos != $spos )
            $result[] = array( $text, sprintf( "Expected translation text, but found [%s]",
               substr($contents,$spos,$pos - $spos + 20)), $spos );
      }
      elseif( strlen($text) == 0 )
      {
         if( $errpos != $spos )
            $result[] = array( substr($contents,$spos,$pos-$spos), "Found empty translation text", $spos );
      }
      else
         $result[] = array( $text, 0, $spos );
   }

   return $result;
} //find_translation_texts

// eat comments "/*..*/", "//...", "#..."
function eat_comments( $contents, $clen, &$pos )
{
   while( $pos < $clen )
   {
      eat_whitespace( $contents, $clen, $pos );

      if( $contents[$pos] == '#' || substr($contents,$pos,2) == '//' )
      {
         while( $pos < $clen ) // skip #... and //...
         {
            if( $contents[$pos++] == "\n" ) break; // ... till EOL
         }
      }
      elseif( substr($contents,$pos,2) == '/*' )
      {
         $pos += 2;
         while( $pos < $clen ) // skip /* ... */
         {
            if( substr($contents,$pos,2) == '*/' )
            {
               $pos += 2;
               break;
            }
            $pos++;
         }
      }
      else
         break;
   }

   eat_whitespace( $contents, $clen, $pos );
} //eat_comments

function eat_whitespace( $contents, $clen, &$pos )
{
   while( $pos < $clen && ctype_space($contents[$pos]) )
      $pos++;
}

// return arr( translation-text, 0) parsed from $contents at $pos; or array( text, errmsg ) on error
function eat_translation_text( $contents, $clen, &$pos )
{
   $spos = $pos;
   $quote = $contents[$pos++];

   // eat "..." or '...'
   $endquote = false;
   while( $pos < $clen )
   {
      $ch1 = $contents[$pos++];
      if( $ch1 == '\\' && $pos < $clen )
      {
         $ch2 = $contents[$pos];
         if( $ch2 == '\\' || $ch2 == $quote )
            $pos++;
      }
      elseif( $ch1 == $quote )
      {
         $endquote = true;
         break;
      }
   }
   $text = substr( $contents, $spos, $pos - $spos );
   if( !$endquote )
      return array( $text, "Missing end quote [$quote] for translation text", $spos );

   // check for "..$var.."
   if( $quote == '"' && preg_match('/(?<!\\\)(\$[a-z_][a-z0-9_]+)/si', $text, $match) )
      return array( $text, "Found invalid variable-usage in translation-text [{$match[1]}]", $spos );

   return array( $text, 0, $spos );
} //eat_translation_text

?>
