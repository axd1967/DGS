<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Rod Ival

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
require_once( "include/translation_functions.php" );
require_once( "include/make_translationfiles.php" );

define('BRLF', "<br>\n");
define('SEP_CHECK', BRLF . '<hr>' . BRLF);
define('ERR_CHECK', '<font color="red">*** Error: </font>');
define('VAL_CHECK', '<font color="blue">-- <b>Need Validate!:</b> </font>');


{
   disable_cache();
   connect2mysql();
   set_time_limit(60); //max. 1min

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in', 'scripts.translation_consistency');
   if( $player_row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'scripts.translation_consistency');
   if( !(@$player_row['admin_level'] & ADMIN_DATABASE) )
      error('adminlevel_too_low', 'scripts.translation_consistency');

   $page = $_SERVER['PHP_SELF'];
   $page_args = array();

   start_html( 'translation_consistency', 0);

   echo ">>>> Most of them needs manual fixes.";
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


   // Subject for consistency-checks:
   // - PHP-sources T_... entries
   // - db-tables: FAQ, Intro, Links, TranslationTexts, TranslationFoundInGroup, Translations
   // - related tables: Translationlog, TranslationLanguages, TranslationPages, TranslationGroups
   $errcnt = 0;

   // Checks on FAQ/Intro/Links-table
   $errcnt += check_consistency_faq('FAQ', $do_it);
   $errcnt += check_consistency_faq('Intro', $do_it);
   $errcnt += check_consistency_faq('Links', $do_it);

   // Checks on TranslationFoundInGroup-table
   $errcnt += check_consistency_tfig($do_it);

   // Checks on Translations-table (must be called before TranslationTexts-checks)
   $errcnt += check_consistency_transl($do_it);

   // Checks on TranslationTexts-table
   $errcnt += check_consistency_texts($do_it);


   echo SEP_CHECK, BRLF, "Found $errcnt errors.", BRLF, BRLF, "Done!!!\n";

   end_html();
}//main

function commit_query( $dbgmsg, $commit, $sql )
{
   if( $commit )
   {
      db_query($dbgmsg, $sql );
      echo "-- Fixed: $sql", BRLF;
   }
   else
      echo VAL_CHECK, "$sql ;", BRLF;
}//commit_query


// Checks $dbtable = FAQ/Intro/Links
function check_consistency_faq( $dbtable, $commit )
{
   static $FIELDS_QA = array( 'Question', 'Answer' );
   $dbgmsg = "check_consistency_faq($dbtable,$commit)";
   $errcnt = 0;

   echo SEP_CHECK;

   // check that FK dbtable.Parent exists
   echo BRLF, "[$dbtable] Checking FK for Parent ...", BRLF;
   $result = db_query("$dbgmsg.check.parent",
      "SELECT D.ID, D.Parent " .
      "FROM $dbtable AS D " .
         "LEFT JOIN $dbtable AS D2 ON D2.ID=D.Parent " .
      "WHERE D2.ID IS NULL" );
   while( $row = mysql_fetch_assoc($result) )
   {
      $errcnt++;
      echo ERR_CHECK, "[$dbtable] Found entry without existing Parent [{$row['Parent']}]: Needs manual fix! ", BRLF,
         "-- SELECT * FROM $dbtable WHERE ID={$row['ID']} ;", BRLF;
   }
   mysql_free_result($result);

   echo BRLF, "[$dbtable] Checking SortOrder ...", BRLF;
   $result = db_query("$dbgmsg.check.sortorder",
      "SELECT ID, Parent, SortOrder " .
      "FROM $dbtable " .
      "WHERE Level>0 " .
      "ORDER BY Parent, SortOrder" );
   $curr_parent = 1;
   $curr_order = 1;
   while( $row = mysql_fetch_assoc($result) )
   {
      $ID = $row['ID'];
      $Parent = $row['Parent'];
      $Order = $row['SortOrder'];
      if( $Parent != $curr_parent )
      {
         $curr_parent = $Parent;
         $curr_order = 1;
      }
      if( $Order != $curr_order )
      {
         $errcnt++;
         echo ERR_CHECK, "[$dbtable] Found wrong SortOrder [$Order] for entry with Parent [$Parent] and ID [$ID]: Needs fix! ", BRLF;
         commit_query("$dbgmsg.fix.sortorder($ID)", $commit,
            "UPDATE $dbtable SET SortOrder=$curr_order WHERE ID=$ID AND Parent=$Parent AND SortOrder=$Order LIMIT 1" );
      }
      $curr_order++;
   }
   mysql_free_result($result);

   // check for valid range of dbtable.Level (0..2)
   echo BRLF, "[$dbtable] Checking for valid Level ...", BRLF;
   $result = db_query("$dbgmsg.check.level",
      "SELECT ID, Level FROM $dbtable WHERE NOT (Level BETWEEN 0 AND 2 )" );
   while( $row = mysql_fetch_assoc($result) )
   {
      $errcnt++;
      echo ERR_CHECK, "[$dbtable] Found invalid Level [{$row['Level']}]: Needs manual fix! ", BRLF,
         "-- SELECT * FROM $dbtable WHERE ID={$row['ID']} ;", BRLF;
   }
   mysql_free_result($result);

   // check that FK dbtable.Question/Answer exists if >0
   echo BRLF, "[$dbtable] Checking FK for Question/Answer ...", BRLF;
   $result = db_query("$dbgmsg.check.QA.FK",
      "SELECT D.ID, D.Question, D.Answer, COALESCE(TTQ.ID,0) AS TTQ_ID, COALESCE(TTA.ID,0) AS TTA_ID " .
      "FROM $dbtable AS D " .
         "LEFT JOIN TranslationTexts AS TTQ ON TTQ.ID=D.Question " .
         "LEFT JOIN TranslationTexts AS TTA ON TTA.ID=D.Answer " .
      "WHERE (D.Question>0 AND TTQ.ID IS NULL) OR (D.Answer>0 AND TTA.ID IS NULL)" );
   while( $row = mysql_fetch_assoc($result) )
   {
      $errcnt++;
      $ID = $row['ID'];

      $fields = array();
      if( $row['Question'] > 0 && $row['TTQ_ID'] == 0 )
         $fields[] = 'Question='.$row['Question']; // missing Q
      if( $row['Answer'] > 0 && $row['TTA_ID'] == 0 )
         $fields[] = 'Answer='.$row['Answer']; // missing A

      echo ERR_CHECK, "[$dbtable] Found entry with ID [$ID] without existing text-id [", implode(', ', $fields), "]",
         ": Needs manual fix! ", BRLF,
         "-- SELECT * FROM $dbtable WHERE ID=$ID ORDER BY Parent, SortOrder ;", BRLF, BRLF;
   }
   mysql_free_result($result);


   // (1) read all texts of type dbtable
   echo BRLF, "[$dbtable] Checking for bad Type ...", BRLF;
   $db_type = strtoupper($dbtable);
   $arr_remove_type = array(); // TT.ID => 1 (if db_type matches)
   $result = db_query("$dbgmsg.check.QA.Type.1",
      "SELECT TT.ID FROM TranslationTexts AS TT WHERE TT.Type='$db_type'" );
   while( $row = mysql_fetch_assoc($result) )
      $arr_remove_type[$row['ID']] = 1;
   mysql_free_result($result);

   // (2) find all texts for dbtable fixing wrong type
   $arr_set_type = array();
   foreach( $FIELDS_QA as $field )
   {
      $result = db_query("$dbgmsg.check.QA.Type.2($field)",
         "SELECT TT.ID, TT.Type " .
         "FROM $dbtable AS D " .
            "INNER JOIN TranslationTexts AS TT ON TT.ID=D.$field " .
         "WHERE D.$field>0" );
      while( $row = mysql_fetch_assoc($result) )
      {
         $ID = $row['ID'];
         unset($arr_remove_type[$ID]);
         if( $row['Type'] != $db_type )
            $arr_set_type[] = $ID;
      }
      mysql_free_result($result);
   }
   if( count($arr_remove_type) )
   {
      $arr_rm = array_keys($arr_remove_type);
      echo ERR_CHECK, "[$dbtable] Found ", count($arr_rm), " entries with bad type: Needs fix! ", BRLF;
      commit_query("$dbgmsg.fix.QA.Type.3", $commit,
         "UPDATE TranslationTexts SET Type='NONE' WHERE ID IN (" . implode(', ', $arr_rm) . ")" );
   }
   if( count($arr_set_type) )
   {
      echo ERR_CHECK, "[$dbtable] Found ", count($arr_set_type), " entries missing correct type [$db_type]: Needs fix! ", BRLF;
      commit_query("$dbgmsg.fix.QA.Type.4", $commit,
         "UPDATE TranslationTexts SET Type='$db_type' WHERE ID IN (" . implode(', ', $arr_set_type) . ")" );
   }


   // check that all dbtable.Question/Answer-texts (if set) have a TFIG with the right TranslationGroup
   $group_id = get_translation_group( $dbtable );
   foreach( $FIELDS_QA as $field )
   {
      echo BRLF, "[$dbtable] Checking TranslationFoundInGroup for $field ...", BRLF;
      $result = db_query("$dbgmsg.check.TFIG.QA.$field",
         "SELECT D.ID, D.$field " .
         "FROM $dbtable AS D " .
            "INNER JOIN TranslationTexts AS TT ON TT.ID=D.$field " .
            "LEFT JOIN TranslationFoundInGroup AS TFIG ON TFIG.Text_ID=TT.ID AND TFIG.Group_ID=$group_id " .
         "WHERE D.$field>0 AND TFIG.Text_ID IS NULL" );
      while( $row = mysql_fetch_assoc($result) )
      {
         $errcnt++;
         $ID = $row['ID'];
         echo ERR_CHECK, "[$dbtable] Found entry for $dbtable.$field with ID [$ID] ",
            "without expected translation-group [$group_id=$dbtable]: Needs manual fix! ", BRLF,
            "-- INSERT INTO TranslationFoundInGroup SET Text_ID=$ID, Group_ID=$group_id ;", BRLF;
      }
      mysql_free_result($result);
   }

   if( $dbtable == 'Links' )
   {
      // check that Links.Reference is set only for Links.Level 2
      $result = db_query("$dbgmsg.check.TFIG.QA.$field",
         "SELECT ID, Level, Reference FROM Links " .
         "WHERE (Level=2 AND Reference ='' ) OR (Level<>2 AND Reference>'')" );
      while( $row = mysql_fetch_assoc($result) )
      {
         $errcnt++;
         $ID = $row['ID'];
         if( $row['Level'] == 2 )
            echo ERR_CHECK, "[$dbtable] Found bad entry (missing Reference): Needs manual fix! ", BRLF,
               "-- SELECT * FROM $dbtable WHERE ID=$ID ;", BRLF;
         else
            echo ERR_CHECK, "[$dbtable] Found bad entry (delete Reference): Needs manual fix! ", BRLF,
               "-- SELECT * FROM $dbtable WHERE ID=$ID ;", BRLF,
               "-- UPDATE $dbtable SET Reference='' WHERE ID=$ID AND Level<>2 LIMIT 1 ;", BRLF;
      }
      mysql_free_result($result);
   }

   return $errcnt;
}//check_consistency_faq

function check_consistency_tfig( $commit )
{
   $dbgmsg = "check_consistency_tfig($commit)";
   $errcnt = 0;

   echo SEP_CHECK;

   // check that all TFIG.Text_ID exists as TT
   echo BRLF, "Checking FK TranslationFoundInGroup.Text_ID ...", BRLF;
   $result = db_query("$dbgmsg.check.FK.TFIG.Text_ID",
      "SELECT TFIG.Text_ID " .
      "FROM TranslationFoundInGroup AS TFIG " .
         "LEFT JOIN TranslationTexts AS TT ON TT.ID=TFIG.Text_ID " .
      "WHERE TT.ID IS NULL" );
   while( $row = mysql_fetch_assoc($result) )
   {
      $errcnt++;
      $text_id = $row['Text_ID'];
      echo ERR_CHECK, "Found TranslationFoundInGroup with non-existing Text_ID [$text_id].", BRLF;
      commit_query("$dbgmsg.fix.FK.TFIG.Text_ID.del($text_id)", $commit,
         "DELETE FROM TranslationFoundInGroup WHERE Text_ID=$text_id" );
   }
   mysql_free_result($result);

   // check that all TFIG.Group_ID exists as TG
   echo BRLF, "Checking FK TranslationFoundInGroup.Group_ID ...", BRLF;
   $result = db_query("$dbgmsg.check.FK.TFIG.Group_ID",
      "SELECT TFIG.Group_ID " .
      "FROM TranslationFoundInGroup AS TFIG " .
         "LEFT JOIN TranslationGroups AS TG ON TG.ID=TFIG.Group_ID " .
      "WHERE TG.ID IS NULL" );
   while( $row = mysql_fetch_assoc($result) )
   {
      $errcnt++;
      $group_id = $row['Group_ID'];
      echo ERR_CHECK, "Found TranslationFoundInGroup with non-existing Group_ID [$group_id].", BRLF,
      commit_query("$dbgmsg.fix.FK.TFIG.Group_ID.del($group_id)", $commit,
         "DELETE FROM TranslationFoundInGroup WHERE Group_ID=$group_id" );
   }
   mysql_free_result($result);

   return $errcnt;
}//check_consistency_tfig

function check_consistency_transl( $commit )
{
   global $NOW, $cnt_translations_no_updated; // pass on to TranslationTexts-check-func

   $dbgmsg = "check_consistency_transl($commit)";
   $errcnt = 0;

   echo SEP_CHECK;

   $row = mysql_single_fetch("$dbgmsg.count.T.upd0",
      "SELECT COUNT(*) AS X_Count FROM Translations WHERE Updated=0" );
   $cnt_translations_no_updated = ( $row ) ? (int)@$row['X_Count'] : 0;

   // check that all Translations.Language_ID exists
   echo BRLF, "Checking FK Translations.Language_ID ...", BRLF;
   $result = db_query("$dbgmsg.check.FK.T.Language_ID",
      "SELECT T.Language_ID, COUNT(*) AS X_Count " .
      "FROM Translations AS T " .
         "LEFT JOIN TranslationLanguages AS TL ON TL.ID=T.Language_ID " .
      "WHERE TL.ID IS NULL " .
      "GROUP BY T.Language_ID" );
   while( $row = mysql_fetch_assoc($result) )
   {
      $errcnt++;
      $lang_id = $row['Language_ID'];
      echo ERR_CHECK, "Found {$row['X_Count']} Translations entries with non-existing Language_ID [$lang_id]: Needs fix!", BRLF,
         "-- SELECT * FROM Translations WHERE Language_ID=$lang_id ;", BRLF;
      commit_query("$dbgmsg.fix.FK.T.Language_ID($lang_id).del", $commit,
         "DELETE FROM Translations WHERE Language_ID=$lang_id" );
   }
   mysql_free_result($result);

   // check that all Translations.Original_ID exists
   echo BRLF, "Checking FK Translations.Original_ID ...", BRLF;
   $result = db_query("$dbgmsg.check.FK.T.Original_ID",
      "SELECT T.Original_ID, COUNT(*) AS X_Count " .
      "FROM Translations AS T " .
         "LEFT JOIN TranslationTexts AS TT ON TT.ID=T.Original_ID " .
      "WHERE TT.ID IS NULL " .
      "GROUP BY T.Original_ID" );
   while( $row = mysql_fetch_assoc($result) )
   {
      $errcnt++;
      $orig_id = $row['Original_ID'];
      echo ERR_CHECK, "Found {$row['X_Count']} Translations entries with non-existing text [$orig_id]: Needs fix!", BRLF,
         "-- SELECT * FROM Translations WHERE Original_ID=$orig_id ;", BRLF;
      commit_query("$dbgmsg.fix.FK.T.Original_ID($orig_id).del", $commit,
         "DELETE FROM Translations WHERE Original_ID=$orig_id" );
   }
   mysql_free_result($result);

   // check if Translations.Updated needs fix
   echo BRLF, "Checking Translations.Updated ...", BRLF;
   $row = mysql_single_fetch("$dbgmsg.check.T.need_Updated",
      "SELECT COUNT(*) AS X_Count FROM Translations WHERE Updated=0" );
   if( @$row['X_Count'] )
   {
      $errcnt += $row['X_Count'];
      echo BRLF, "Found {$row['X_Count']} entries with unset Translations.Updated. Trying to fix automatically ...", BRLF;

      // fix Translations.Updated from Translationlog
      echo BRLF, "Fixing Translations.Updated from Translationlog ...", BRLF;
      $visited = array(); // Orig_ID:Lang_ID => 1
      $arr_lang = array(); // Lang_ID => max-date
      $result = db_query("$dbgmsg.fix.T.Updated.1",
         "SELECT TL.ID AS TL_ID, TL.Original_ID, TL.Language_ID, UNIX_TIMESTAMP(TL.Date) AS T_Date " .
         "FROM Translationlog AS TL " .
            "INNER JOIN Translations AS T ON T.Original_ID=TL.Original_ID AND T.Language_ID=TL.Language_ID " .
         "WHERE TL.Translation>'' AND T.Updated=0 " .
         "ORDER BY TL.ID DESC" ); // start from last translation
      while( $row = mysql_fetch_assoc($result) )
      {
         $lang_id = $row['Language_ID'];
         $orig_id = $row['Original_ID'];
         $t_date = $row['T_Date'];
         if( !isset($arr_lang[$lang_id]) || ($t_date > $arr_lang[$lang_id]) )
            $arr_lang[$lang_id] = $t_date;
         $key = $orig_id . ':' . $lang_id;
         if( isset($visited[$key]) )
            continue;
         $visited[$key] = 1;

         commit_query("$dbgmsg.fix.T.Updated.2", $commit,
            "UPDATE Translations SET Updated=FROM_UNIXTIME($t_date) " .
            "WHERE Original_ID=$orig_id AND Language_ID=$lang_id AND Updated=0 LIMIT 1" );
      }
      mysql_free_result($result);

      // fix remaining Translations.Updated with latest date for specific language
      echo BRLF, "Fixing Translations.Updated from other Translations ...", BRLF;
      $result = db_query("$dbgmsg.check.T.Updated.3",
         "SELECT T.Language_ID, UNIX_TIMESTAMP(MAX(T.Updated)) AS X_Updated " .
         "FROM Translations AS T " .
         "WHERE T.Updated > 0 " .
         "GROUP BY T.Language_ID" );
      while( $row = mysql_fetch_assoc($result) )
      {
         $lang_id = $row['Language_ID'];
         commit_query("$dbgmsg.fix.T.Updated.4", $commit,
            "UPDATE Translations SET Updated=FROM_UNIXTIME({$row['X_Updated']}) " .
            "WHERE Language_ID=$lang_id AND Updated=0" );
         unset($arr_lang[$lang_id]);
      }
      mysql_free_result($result);

      // fix remaining Translations.Updated with current or latest Translationlog-date for remaining languages
      echo BRLF, "Fixing remaining Translations.Updated ...", BRLF;
      $result = db_query("$dbgmsg.check.T.Updated.5",
         "SELECT T.Language_ID, COUNT(*) AS X_Count FROM Translations AS T WHERE T.Updated=0 GROUP BY T.Language_ID" );
      while( $row = mysql_fetch_assoc($result) )
      {
         $lang_id = $row['Language_ID'];
         $t_date = (isset($arr_lang[$lang_id])) ? $arr_lang[$lang_id] : $NOW;
         commit_query("$dbgmsg.fix.T.Updated.5", $commit,
            "UPDATE Translations SET Updated=FROM_UNIXTIME($t_date) " .
            "WHERE Language_ID=$lang_id AND Updated=0 LIMIT {$row['X_Count']}" );
      }
      mysql_free_result($result);
   }

   return $errcnt;
}//check_consistency_transl

function check_consistency_texts( $commit )
{
   global $NOW, $cnt_translations_no_updated;

   $dbgmsg = "check_consistency_texts($commit)";
   $errcnt = 0;

   echo SEP_CHECK;

   // check that all TranslationTexts have at least one TFIG
   echo BRLF, "Check TranslationTexts for missing TranslationFoundInGroup ...", BRLF;
   $result = db_query("$dbgmsg.check.TT.empty_text",
      "SELECT TT.ID " .
      "FROM TranslationTexts AS TT " .
      "LEFT JOIN TranslationFoundInGroup AS TFIG ON TFIG.Text_ID=TT.ID " .
      "WHERE TFIG.Text_ID IS NULL AND TT.Type<>'NONE'" );
   while( $row = mysql_fetch_assoc($result) )
   {
      $errcnt++;
      $ID = $row['ID'];
      echo ERR_CHECK, "Found TranslationTexts-entry without assigned group with ID [$ID]: Needs manual fix!", BRLF,
         "-- SELECT * FROM TranslationTexts WHERE ID=$ID LIMIT 1 ;", BRLF;
   }
   mysql_free_result($result);


   // identify orphan texts (not used by sources or referenced by faq/intro/links)
   // - (1) reset check-status for all texts, mark texts USED for FAQ/Intro/Links
   db_query("$dbgmsg.check.TT.Status.2",
      "UPDATE TranslationTexts SET Status='".TRANSL_STAT_USED."' WHERE Type IN ('FAQ','LINKS','INTRO')" );
   db_query("$dbgmsg.check.TT.Status.1",
      "UPDATE TranslationTexts SET Status='".TRANSL_STAT_CHECK."' WHERE Type IN ('NONE','SRC')" );

   // - (2) identify and mark texts from PHP-sources as USED
   list( $error, $arr_text_id, $arr_tfig, $arr_double_id ) = generate_translation_texts( /*commit*/false, /*echo*/false );
   if( !$error )
   {
      if( count($arr_text_id) )
      {
         echo sprintf( "... There are %s text entries, that will be marked as USED and of type=SRC.", count($arr_text_id) ), BRLF, BRLF;
         db_query("$dbgmsg.check.TT.Status.3",
            "UPDATE TranslationTexts SET Status='".TRANSL_STAT_USED."', Type='SRC' " .
            "WHERE ID IN (" . implode(', ', $arr_text_id) . ")" ); // IN restricted by mysql-var 'max_allowed_packet'
      }

      // - (3) mark remaining as sure orphans (without doubles)
      db_query("$dbgmsg.check.TT.Status.3",
         "UPDATE TranslationTexts SET Status='".TRANSL_STAT_ORPHAN."' WHERE Status='".TRANSL_STAT_CHECK."'" );

      $arr_orphans = array();
      $result = db_query("$dbgmsg.check.TT.Status.4",
         "SELECT TT.ID FROM TranslationTexts AS TT WHERE TT.Status='".TRANSL_STAT_ORPHAN."'" );
      while( $row = mysql_fetch_assoc($result) )
         $arr_orphans[] = $row['ID'];
      mysql_free_result($result);
      if( count($arr_orphans) )
      {
         $errcnt += count($arr_orphans);
         echo ERR_CHECK, "Found ".count($arr_orphans)." potential orphan texts:", BRLF,
            "-- SELECT * FROM TranslationTexts WHERE Status='".TRANSL_STAT_ORPHAN."' AND ID IN (" .
               implode(', ', $arr_orphans) . ") ;", BRLF, BRLF,
            "-- DELETE FROM TranslationTexts WHERE Status='".TRANSL_STAT_ORPHAN."' AND ID IN (" .
               implode(', ', $arr_orphans) . ") ;", BRLF;
      }
      else
         echo BRLF, "Found no orphan texts.", BRLF;

      // find all SRC-typed texts
      $arr_type_src = array(); // TT.ID => 1
      $result = db_query("$dbgmsg.find.TT.type_src",
         "SELECT TT.ID FROM TranslationTexts AS TT WHERE TT.Type='SRC'" );
      while( $row = mysql_fetch_assoc($result) )
         $arr_type_src[$row['ID']] = 1;
      mysql_free_result($result);
      foreach( $arr_text_id as $text_id )
         unset($arr_type_src[$text_id]);
      if( count($arr_type_src) )
      {
         $arr_id = array_keys($arr_type_src);
         $errcnt += count($arr_id);
         echo ERR_CHECK, "Found ".count($arr_id)." texts with wrong SRC-type: Needs manual fix!", BRLF,
            "-- SELECT * FROM TranslationTexts WHERE Type='SRC' AND ID IN (" . implode(', ', $arr_id) . ") ;", BRLF, BRLF,
            "-- DELETE FROM TranslationTexts WHERE Type='SRC' AND ID IN (" . implode(', ', $arr_id) . ") ;", BRLF;
      }
      else
         echo BRLF, "Found no texts with wrong SRC-type.", BRLF;

      // check for unused TranslationFoundInGroup for SRC/NONE-typed texts
      $result = db_query("$dbgmsg.find.TFIG.unused",
         "SELECT TFIG.Text_ID, TFIG.Group_ID, TT.Text FROM TranslationFoundInGroup AS TFIG " .
            "INNER JOIN TranslationTexts AS TT ON TT.ID=TFIG.Text_ID " .
         "WHERE TT.Type IN ('SRC','NONE')" ); // exclude FAQ/Intro/Links-type
      $arr_unused = array(); // [ text_id => [ group_id, ... ] ]
      $arr_unused_texts = array(); // [ text_id => text, ... ]
      $cnt_unused = 0;
      while( $row = mysql_fetch_assoc($result) )
      {
         $text_id = $row['Text_ID'];
         $group_id = $row['Group_ID'];
         if( !isset($arr_tfig[$group_id]) || !in_array($text_id, $arr_tfig[$group_id]) )
         {
            $arr_unused_texts[$text_id] = $row['Text'];
            if( !isset($arr_unused[$text_id]) )
               $arr_unused[$text_id] = array();
            $arr_unused[$text_id][] = $group_id;
            $cnt_unused++;
         }
      }
      mysql_free_result($result);
      if( $cnt_unused )
      {
         $errcnt += $cnt_unused;
         foreach( $arr_unused as $text_id => $arr_groups )
         {
            echo BRLF, ERR_CHECK, "Found <font color=red><b>", count($arr_groups), "</b></font> unused TranslationFoundInGroup-entries for text [",
               "<font color=blue>", basic_safe(cut_str($arr_unused_texts[$text_id], 50)), "</font>]: Needs manual fix!", BRLF,
               "-- SELECT TT.*, TFIG.Group_ID, TG.Groupname FROM TranslationTexts AS TT INNER JOIN TranslationFoundInGroup AS TFIG ON TFIG.Text_ID=TT.ID " .
                     "INNER JOIN TranslationGroups AS TG ON TG.ID=TFIG.Group_ID WHERE TT.ID=$text_id AND TFIG.Group_ID IN (" . implode(', ', $arr_groups) . ") ;", BRLF;
            commit_query("$dbgmsg.fix.TFIG.unused", $commit,
               "DELETE FROM TranslationFoundInGroup WHERE Text_ID=$text_id AND Group_ID IN (" . implode(', ', $arr_groups) . ")" );
         }
      }
   }

   // identify double texts (case-insensitive)
   echo BRLF, "Check for case-insensitive double TranslationTexts ...", BRLF;
   $result = db_query("$dbgmsg.check.TT.double.1",
      "SELECT COUNT(*) AS X_Count, Text " .
      "FROM TranslationTexts " .
      "WHERE Type IN ('NONE','SRC') " .
      "GROUP BY Text " .
      "HAVING X_Count > 1" );
   $dbl_count = 0;
   while( $row = mysql_fetch_assoc($result) )
   {
      $errcnt++;
      $fmt_text = preg_replace("/\\n/s", ' ', substr($row['Text'], 0, 20));
      $text = mysql_addslashes($row['Text']);
      echo ERR_CHECK, "Found double TranslationTexts-entry: Needs manual fix!", BRLF;
      $result2 = db_query("$dbgmsg.check.TT.double.2",
         "SELECT TT.ID, TT.Status, TT.Type, SUM(IF(ISNULL(T.Original_ID),0,1)) AS X_Sum " .
         "FROM TranslationTexts AS TT " .
            "LEFT JOIN Translations AS T ON T.Original_ID=TT.ID " .
         "WHERE TT.Text='$text' AND TT.Type IN ('NONE','SRC') " .
         "GROUP BY TT.ID, TT.Status, TT.Type" );
      while( $row2 = mysql_fetch_assoc($result2) )
      {
         $dbl_count++;
         $ID = $row2['ID'];
         $sum = $row2['X_Sum'];
         echo sprintf("-- Found text [%s] on ID [%s] with %s translations on status [%s] and of type [%s]:",
                      cut_str($row['Text'], 20, false), $ID, $sum, $row2['Status'], $row2['Type'] ),
            BRLF,
            "-- SELECT * FROM TranslationTexts WHERE ID=$ID LIMIT 1 ;", BRLF,
            "-- SELECT * FROM TranslationFoundInGroup WHERE Text_ID=$ID ORDER BY Group_ID ;", BRLF,
            "-- SELECT * FROM Translations WHERE Original_ID=$ID ORDER BY Language_ID ;", BRLF,
            "-- DELETE FROM TranslationTexts WHERE ID=$ID LIMIT 1 ;", BRLF,
            BRLF;
      }
      mysql_free_result($result2);
   }
   mysql_free_result($result);
   echo "Found $dbl_count double TranslationTexts-entries.", BRLF;

   // check for unset TranslationTexts.Updated
   echo BRLF, "Check TranslationTexts.Updated ...", BRLF, BRLF;
   if( $cnt_translations_no_updated > 0 )
   {
      $errcnt++;
      echo ERR_CHECK, "To check and fix TranslationTexts.Updated all Translations.Updated must be set (found ",
         $cnt_translations_no_updated, " entries with Updated=0): Needs 2nd-run fix!", BRLF;
   }
   else
   {
      $result = db_query("$dbgmsg.check.TT.upd.1",
         "SELECT TT.ID, TT.Translatable, IF(ISNULL(T.Original_ID),0,1) AS T_Exists, " .
            "MIN(IF(T.Translated='Y',UNIX_TIMESTAMP(T.Updated),$NOW)) AS MIN_Updated, " . // $NOW for upper-limit for 'N'
            "MAX(UNIX_TIMESTAMP(T.Updated)) AS MAX_Updated " .
         "FROM TranslationTexts AS TT " .
         "LEFT JOIN Translations AS T ON T.Original_ID=TT.ID " .
         "WHERE TT.Updated=0 " .
         "GROUP BY TT.ID" );
      while( $row = mysql_fetch_assoc($result) )
      {
         $ID = $row['ID'];
         $transl_exists = $row['T_Exists']; // false = no translations

         // NOTE: For a new or changed text all existing translations for the text are marked with Translations.Translated='N'
         if( $transl_exists )
         {
            if( $row['Translatable'] == 'Done' )
            {
               // TranslationTexts.Translatable=Done means, there is at least one translation of newer date
               // => so take MIN of Translations.Updated (oldest translation) with Translated=Y - 1 hour
               $new_upd = $row['MIN_Updated'] - SECS_PER_HOUR;
            }
            else // Y,N,Changed
            {
               // TranslationTexts.Translatable=Y|N|Changed (<> Done) means, there is no translation yet of the new/changed text
               // => so take MAX of all Translations.Updated (those are older translations) + 1 hour
               $new_upd = $row['MAX_Updated'] + SECS_PER_HOUR;
            }
         }
         else // take NOW if there are no translations
            $new_upd = $NOW;

         $errcnt++;
         echo ERR_CHECK, "Found unset TranslationTexts.Updated for ID [$ID]: Needs manual fix!", BRLF,
            "-- SELECT * FROM TranslationTexts WHERE ID=$ID LIMIT 1 ;", BRLF,
            "-- SELECT Original_ID, Language_ID, Updated, Translated, Text FROM Translations WHERE Original_ID=$ID ORDER BY Updated ;", BRLF;
         commit_query("$dbgmsg.fix.TT.upd.1($ID)", $commit,
            "UPDATE TranslationTexts SET Updated=FROM_UNIXTIME($new_upd) WHERE ID=$ID LIMIT 1" );
      }
      mysql_free_result($result);
   }

   return $errcnt;
}//check_consistency_texts

?>
