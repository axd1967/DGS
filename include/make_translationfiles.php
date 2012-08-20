<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

// The Sourceforge devel server need a soft link:
// ln -s -d /tmp/persistent/dragongoserver/translations /home/groups/d/dr/dragongoserver/htdocs/translations

define('TRANS_ROW_PER_PAGE', 30);
define('TRANS_FULL_ADMIN', 1); //allow all languages access to ADMIN_TRANSLATORS

// TranslationTexts.Status
define('TRANSL_STAT_USED', 'USED');
define('TRANSL_STAT_CHECK', 'CHECK');
define('TRANSL_STAT_ORPHAN', 'ORPHAN');


function make_known_languages() //must be called from main dir
{
   $result = db_query( 'make_translationfiles.make_knownlanguages',
      "SELECT * FROM TranslationLanguages ORDER BY Language" );

   $Filename = 'translations/known_languages.php'; //must be called from main dir

   //$e= error_reporting(E_ALL & ~(E_WARNING | E_NOTICE));
   $fd = @fopen( $Filename, 'w');
   //error_reporting($e);
   if( !$fd )
   {
      //echo "couldnt_open_file ". $Filename; exit;
      global $TheErrors;
      $TheErrors->set_mode(ERROR_MODE_PRINT);
      error('couldnt_open_file', "make_known_languages.err1($Filename)");
   }

   $group= 'Common';
   global $TranslateGroups, $known_languages;
   $TranslateGroups[]= $group;
   $known_languages = array();

   fwrite( $fd, "<?php\n\n"
."\$TranslateGroups[] = \"$group\"; //local use\n"
."// The \$T_ are required for 'scripts/generate_translation_texts.php'.\n"
."\$T_ = 'fnop';\n" //or 'trim'... never translated in known_languages.php
."\n\$known_languages = array(\n" );

   $prev_lang = '';
   $first = true;
   while( $row = mysql_fetch_array($result) )
   {
      @list($browsercode,$charenc) = explode( LANG_CHARSET_CHAR, $row['Language'], 2);
      $browsercode = strtolower(trim($browsercode));
      $charenc = strtolower(trim($charenc));

      $tmp = slashed($row['Name']);
      if( $browsercode === $prev_lang )
         fwrite( $fd, ",\n                 '$charenc' => \$T_('$tmp')");
      else
         fwrite( $fd, ( $first ? '' : " ),\n" ) .
                      "  '$browsercode' => array( '$charenc' => \$T_('$tmp')");

      $known_languages[$browsercode][$charenc] = $row['Name']; //not translated here
      $prev_lang = $browsercode;
      $first = false;
   }
   mysql_free_result($result);
   fwrite( $fd, ( $first ? '' : ' )' ) . "\n);\n\n?>" );
   fclose($fd);
   unset($fd);
} //make_known_languages

function slashed($string)
{
   //all that can disturb a PHP string quoted with ''
   return str_replace( array( '\\', '\''), array( '\\\\', '\\\''), $string );
   /*
   //all that can disturb a PHP string quoted with ""
   return str_replace( array( "\\", "\"", "\$" ), array( "\\\\", "\\\"", "\\\$" ), $string );
   */
}

function make_include_files($language=null, $group=null) //must be called from main dir
{
   global $NOW;

   chdir( 'translations'); //must be called from main dir

   $query = "SELECT Translations.Text, TG.Groupname, TL.Language, TT.Text AS Original " .
      "FROM Translations " .
         "INNER JOIN TranslationTexts AS TT ON TT.ID=Translations.Original_ID " .
         "INNER JOIN TranslationFoundInGroup AS TFIG ON TFIG.Text_ID=Translations.Original_ID " .
         "INNER JOIN TranslationGroups AS TG ON TG.ID=TFIG.Group_ID " .
         "INNER JOIN TranslationLanguages AS TL " .
      "WHERE Translations.Language_ID = TL.ID ";
      //"AND Translations.Text!='' "; //else a file containing only '' will not be reseted

   if( !empty($group) )
      $query .= "AND TG.Groupname='$group' ";
   if( !empty($language) )
      $query .= "AND TL.Language='$language' ";

   $query .= "ORDER BY Language,Groupname";

   $result = db_query( 'make_translationfiles.make_include_files', $query );

   $grp = '';
   $lang = '';
   while( $row = mysql_fetch_array($result) )
   {
      if( $row['Groupname'] !== $grp || $row['Language'] !== $lang )
      {
         $grp = $row['Groupname'];
         $lang = $row['Language'];

//         echo "<p></p>$lang -- $grp\n";

         if( isset( $fd ) )
         {
            fwrite( $fd , "\n?>");
            fclose( $fd );
         }

         $Filename = $lang . '_' . $grp . '.php';

         //$e= error_reporting(E_ALL & ~(E_WARNING | E_NOTICE));
         $fd = @fopen( $Filename, 'w');
         //error_reporting($e);
         if( !$fd )
            error('couldnt_open_file', "make_include_files.err2($Filename)");

         fwrite( $fd, "<?php\n\n/* Automatically generated at " .
                 gmdate('Y-m-d H:i:s T', $NOW) . " */\n\n");
      }

      if( !empty($row['Text']) )
         fwrite( $fd, "\$Tr['" . slashed($row['Original']) . "'] = '" .
              slashed($row['Text']) . "';\n" );
   }


   if( isset($fd) )
   {
      fwrite( $fd , "\n?>");
      fclose( $fd );
   }

   chdir('../');
} //make_include_files


function translations_query( $translate_lang, $untranslated, $group, $from_row=-1, $alpha_order=false, $filter_en='')
{
   /* Note: Some items appear two or more times within the untranslated set
      when from different groups. But we can't use:
          ( $untranslated ? "DISTINCT " : "")
      because the Group_ID column makes the rows distinct.
      Workaround: using "ORDER BY Original_ID LIMIT 50";
       and filter the rows on Original_ID while computing.
      The previous sort was:
          "ORDER BY TranslationFoundInGroup.Group_ID LIMIT 50";
      As the Original are identical when the Original_ID are identical,
      an other possible sort is "ORDER BY Original,Original_ID";
   */
   if( $alpha_order )
      $order = ' ORDER BY Original,Original_ID';
   else
      $order = ' ORDER BY Original_ID';
   if( $from_row >= 0 )
      $limit = " LIMIT $from_row,".(TRANS_ROW_PER_PAGE+1);
   else
      $limit = '';

   $query = "SELECT Translations.Text"
          . ",TT.ID AS Original_ID"
          . ",TL.ID AS Language_ID"
          . ",TFIG.Group_ID"
          . ",TT.Text AS Original"
          . ",TT.Translatable"
          . ",Translations.Translated"
   . " FROM TranslationTexts AS TT"
      . " INNER JOIN TranslationGroups AS TG"
      . " INNER JOIN TranslationFoundInGroup TFIG ON TFIG.Group_ID=TG.ID AND TFIG.Text_ID=TT.ID"
      . " INNER JOIN TranslationLanguages AS TL"
      . " LEFT JOIN Translations ON Translations.Original_ID=TT.ID AND Translations.Language_ID=TL.ID"
   . " WHERE TL.Language='".mysql_addslashes($translate_lang)."'"
      . " AND TT.Text>''"
      . " AND TT.Translatable!='N'" ;

   if( $filter_en )
      $query .= " AND TT.Text LIKE '%".mysql_addslashes($filter_en)."%'";

   if( $untranslated )
   {
      // Translations.Translated IS NULL means "never translated" (LEFT JOIN fails).
      $query .= " AND (Translations.Translated IS NULL OR Translations.Translated='N')";
   }

   if( $group != 'allgroups' )
      $query .= " AND TG.Groupname='".mysql_addslashes($group)."'";
   $query .= $order.$limit;

   $result = db_query( 'translations_query', $query );

   return $result;
} //translations_query


// IMPORTANT NOTE: caller needs to open TA with HOT-section if used with other db-writes!!
// return arr( error|false, found/inserted-text_id, sql-insert )
function add_text_to_translate( $debugmsg, $orig_text, $Group_ID, $do_it=true)
{
   global $NOW;

   $orig_text = trim($orig_text);
   if( !$orig_text || $Group_ID <= 0 )
      return array( false, 0, '' );

   $text_sql = mysql_addslashes( latin1_safe($orig_text) );
   $error = false;

   $result = db_query( $debugmsg.'.find_transltext',
      "SELECT TT.ID, TT.Text, TT.Status, COALESCE(TFIG.Text_ID,0) AS X_tfig " .
      "FROM TranslationTexts AS TT " .
         "LEFT JOIN TranslationFoundInGroup AS TFIG ON TFIG.Text_ID=TT.ID AND TFIG.Group_ID=$Group_ID " .
      "WHERE TT.Text='$text_sql'" ); // case-insensitive find

   $Text_ID = 0;
   $new_tfig = true;
   $found_rows = (int)@mysql_num_rows($result);
   if( $found_rows == 0 )
      $action = 'I'; // insert (new text)
   else // #>0 entries
   {
      $action = '';
      $err = array();
      while( $row = mysql_fetch_assoc($result) )
      {
         $Text_ID = $row['ID'];
         if( strcmp($row['Text'], $orig_text) != 0 ) // compare case of orig-text
         {
            if( $found_rows == 1 && $row['Status'] == TRANSL_STAT_ORPHAN )
               $action = 'U'; // re-use orphan text
            else
               $err[] = $row['Text'];
         }
         else
            $new_tfig = ( $row['X_tfig'] == 0 ); // exact match
      }
      if( count($err) )
         $error = sprintf( 'Text [%s] must be unique, but found [{%s}]. Need fix in db and/or code!',
            $orig_text, implode('}, {', $err) );
      elseif( $found_rows > 1 )
         $error = sprintf( 'Text [%s] must be unique, but found it %s times in db. Need fix in db!',
            $orig_text, $found_rows );
   }
   mysql_free_result($result);

   if( $error )
      return array( $error, 0, '' );

   if( $action == 'I' )
   {
      $sql = "INSERT INTO TranslationTexts SET Translatable='Y', Updated=FROM_UNIXTIME($NOW), Text='$text_sql'";
      if( $do_it )
      {
         db_query( "$debugmsg.new_transltext", $sql );
         $Text_ID = mysql_insert_id();
      }
   }
   elseif( $action == 'U' ) // only one (orphan) row found
   {
      $sql = "UPDATE TranslationTexts SET Translatable='Y', Updated=FROM_UNIXTIME($NOW), Text='$text_sql' WHERE ID=$Text_ID LIMIT 1";
      if( $do_it )
         db_query( "$debugmsg.reuse_orphan_transltext", $sql );
   }
   else
      $sql = '';

   if( $do_it && $new_tfig && $Text_ID > 0 )
   {
      db_query( "$debugmsg.update_translfig($Text_ID,$Group_ID)",
         "INSERT INTO TranslationFoundInGroup SET Text_ID=$Text_ID, Group_ID=$Group_ID" );
   }

   return array( false, $Text_ID, $sql );
}//add_text_to_translate


function generate_translation_texts( $do_it, $echo=true )
{
   $result = db_query( "generate_translation_texts.find",
         "SELECT TP.*, TG.Groupname "
         . "FROM TranslationPages AS TP "
         . "LEFT JOIN TranslationGroups AS TG ON TG.ID=TP.Group_ID "
         . "ORDER BY TP.Page" );

   $errorfmt = "<br><a name=\"err%s\"></a><font color=\"red\">*** Error:</font> %s<br>\n"; //err-no, err-msg
   if( $echo )
      echo "<p><b>NOTE:</b> Check at <a href=\"#errors\">bottom</a> if errors or new entries are detected.<br>\n";

   $php5_strip_php_comments = (function_exists('php_strip_whitespace'));
   $errcnt = 0;
   $newcnt = 0;
   $arr_text_id = array();
   while( $row = mysql_fetch_array($result) )
   {
      $Filename = $row['Page'];
      $Group_ID = $row['Group_ID'];
      $GroupName = @$row['Groupname'];

      if( $echo )
         echo "<hr><p>$Filename - Group $Group_ID [$GroupName]</p><hr>\n";


      if( $php5_strip_php_comments )
         $contents = php_strip_whitespace($Filename); // strips also LFs
      else
      {
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

      // NOTE: this old pattern does not cover all T_-texts, so parse it on our own.
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
            list( $error, $text_id, $sql ) =
               add_text_to_translate('generate_translation_texts', $tstring, $Group_ID, $do_it);
            if( $error )
            {
               if( $echo ) // non-critical error not printed
                  printf( $errorfmt, ++$errcnt, $error );
            }
            elseif( $sql )
            {
               $newcnt++;
               $tmp = ( $do_it ) ? '++ '.$tstring : $sql;
               if( $echo )
                  echo textarea_safe($tmp), "<br>\n";
            }
            if( $text_id > 0 )
               $arr_text_id[] = $text_id;
         }
      }
   }
   mysql_free_result($result);

   if( $echo )
   {
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
   }

   /* TODO
   // mark entries as USED for later consistency-checks
   if( count($arr_text_id) )
   {
      echo sprintf( "<p>There are %s text entries, that will be marked as USED.<br>\n", count($arr_text_id) );
      if( $do_it )
      {
         db_query( 'generate_translation_texts.mark_used',
            "UPDATE TranslationTexts SET Status='".TRANSL_STAT_USED."' " .
            "WHERE ID IN (" . implode(',', $arr_text_id) . ")"); // IN restricted by mysql-var 'max_allowed_packet'
         echo "MARKED all used TranslationTexts as USED!<br>\n";
      }
   }
   */

   return ( $errcnt > 0 ) ? 0 : $arr_text_id;
}//generate_translation_texts

/*!
 * \brief Extracts translation-texts T_ and $T_ from given contents.
 * \param $contents content of php-file to scan for translation-texts.
 *        Expects, that PHP-comments are removed from $contents.
 *        if not, comments are searched for translation-texts as well.
 * \return array of [ ( translation-text, errmsg|0, found-pos ) ]
 */
function find_translation_texts( $contents )
{
   // NOTE: avoid T_... here to prevent this function find examples here
   // - valid: t( text [. text]* )
   // - invalid: text contains " ... $unquoted_var ... "
   // - invalid: text contains text . non-constant

   $result = array(); // arr( arr( tstr, errmsg|0, pos ), ...)

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
