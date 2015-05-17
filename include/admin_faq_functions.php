<?php
/*
Dragon Go Server
Copyright (C) 2001-2015  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

$TranslateGroups[] = "FAQ";

require_once 'include/globals.php';
require_once 'include/connect2mysql.php';
require_once 'include/utilities.php';


/*!
 * \brief Static class with method to support admin of FAQ and Links.
 */
class AdminFAQ
{
   // ------------ static functions ----------------------------

   public static function get_faq_group_id( $dbgmsg, $tr_group )
   {
      $row = mysql_single_fetch( "$dbgmsg.get_faq_group_id($tr_group)",
         "SELECT ID FROM TranslationGroups WHERE Groupname='$tr_group' LIMIT 1" );
      if ( !$row )
         error('internal_error', "$dbgmsg.get_faq_group_id($tr_group)");
      return $row['ID'];
   }

   // returns row with fields: FAQ/Links/Intro.* + Q + A + (Q|A)Translatable + (Q|A)Updated
   public static function get_faq_entry_row( $dbtable, $id )
   {
      $row = mysql_single_fetch( "AdminFAQ:get_faq_entry_row($id)",
           "SELECT DBT.*, Question.Text AS Q, Answer.Text AS A".
               ", Question.Translatable AS QTranslatable".
               ", UNIX_TIMESTAMP(Question.Updated) AS QUpdated".
               ", Answer.Translatable AS ATranslatable".
               ", IF(ISNULL(Answer.Updated),0,UNIX_TIMESTAMP(Answer.Updated)) AS AUpdated ".
           "FROM $dbtable AS DBT " .
               "INNER JOIN TranslationTexts AS Question ON Question.ID=DBT.Question " .
               "LEFT JOIN TranslationTexts AS Answer ON Answer.ID=DBT.Answer " .
           "WHERE DBT.ID='$id' LIMIT 1" )
         or error('internal_error', "AdminFAQ:get_faq_entry_row($id)");

      return $row;
   }//get_faq_entry_row

   /*!
    * \brief Saves new FAQ-entry.
    * \param $fid parent entry, 1=root
    * \param $chk_mode 0=std-entry (Answer), 1=Question, 2=reference (for Links)
    */
   public static function save_new_faq_entry( $dbgmsg, $dbtable, $tr_group, $fid, $is_cat, $question, $answer, $reference,
         $append=false, $translatable='N', $do_log=true, $chk_mode=0 )
   {
      global $NOW, $player_row;

      $dbgmsg .= ".save_new_faq_entry($dbtable,$tr_group,$fid)";
      if ( !preg_match("/^(FAQ|Links|Intro)$/", $dbtable) )
         error('invalid_args', "$dbgmsg.check.bad_dbtable");
      $db_type = strtoupper($dbtable);

      $tr_group_id = self::get_faq_group_id( $dbgmsg, $tr_group );

      $ReferenceSql = ($reference) ? mysql_addslashes($reference) : $reference;
      if ( $chk_mode == 2 && $reference )
      {
         // check if URL already existing
         $row = mysql_single_fetch( "$dbgmsg.do_new.find_ref",
            "SELECT ID FROM $dbtable WHERE Reference='$ReferenceSql' LIMIT 1" );
         if ( $row )
            return $row['ID'];
      }
      elseif ( $chk_mode == 1 )
      {
         // check if Question already existing
         $Qsql = mysql_addslashes( latin1_safe($question) );
         $row = mysql_single_fetch( "$dbgmsg.do_new.find_q1",
            "SELECT TT.ID FROM TranslationTexts AS TT " .
            "INNER JOIN TranslationFoundInGroup AS TFG ON TFG.Text_ID=TT.ID " .
            "WHERE TT.Text='$Qsql' AND TFG.Group_ID=$tr_group_id LIMIT 1" );
         if ( $row )
         {
            $row2 = mysql_single_fetch( "$dbgmsg.do_new.find_q2",
               "SELECT ID FROM $dbtable WHERE Question={$row['ID']} LIMIT 1" );
            if ( $row2 )
               return $row2['ID'];
         }
      }

      ta_begin();
      {//HOT-section to add new FAQ-entry
         // add ROOT-element (=seed) for $dbtable
         $query = "SELECT * FROM $dbtable WHERE ID=$fid LIMIT 1";
         $row = mysql_single_fetch( "$dbgmsg.do_new.find1($fid)", $query );
         if ( $fid==1 && (!$row || ($row['Flags'] & FLAG_HELP_HIDDEN)) )
         {
            //adjust the seed. must be NOT hidden even if invisible
            db_query( "$dbgmsg.do_new.replace_seed($fid)",
               "REPLACE INTO $dbtable (ID,Parent,Level,SortOrder,Question,Answer,Flags)"
                        . " VALUES (1,1,0,0,0,0,0)" );
            //reload it:
            $row = mysql_single_fetch( "$dbgmsg.do_new.find2($fid)", $query );
         }
         if ( !$row )
            error('admin_no_such_entry', "$dbgmsg.do_new.find3($fid)");

         if ( $row['Level'] == 0 ) // category
            $row = array('Parent' => $row['ID'], 'SortOrder' => 0, 'Level' => 1);
         elseif ( $row['Level'] == 1 && !$is_cat ) // entry
            $row = array('Parent' => $row['ID'], 'SortOrder' => 0, 'Level' => 2);

         if ( $append )
         {
            $order_row = mysql_single_fetch( "$dbgmsg.do_new.find_order($fid)",
               "SELECT MAX(SortOrder) AS X_MaxSortOrder FROM $dbtable " .
               "WHERE Parent={$row['Parent']} AND Level={$row['Level']}" );
            if ( $order_row )
               $new_sortorder = $order_row['X_MaxSortOrder'] + 1;
            else
               $new_sortorder = 1;
         }
         else
         {
            db_query( "$dbgmsg.do_new.update_sortorder",
               "UPDATE $dbtable SET SortOrder=SortOrder+1 " .
                        'WHERE Parent=' . $row['Parent'] . ' ' .
                        'AND SortOrder>' . $row['SortOrder'] );
            $new_sortorder = $row['SortOrder'] + 1;
         }

         db_query( "$dbgmsg.do_new.insert",
            "INSERT INTO $dbtable SET " .
            "SortOrder=$new_sortorder, Parent={$row['Parent']}, Level={$row['Level']}, Reference='$ReferenceSql'" );
         $faq_id = mysql_insert_id(); // FAQ | Intro | Links

         $Qsql = mysql_addslashes( latin1_safe($question) );
         $q_id = 0;
         if ( $chk_mode )
         {
            $q_row = mysql_single_fetch( "$dbgmsg.do_new.find_qtext",
               "SELECT TT.ID FROM TranslationTexts AS TT " .
               "INNER JOIN TranslationFoundInGroup AS TFG ON TFG.Text_ID=TT.ID " .
               "WHERE TT.Text='$Qsql' AND TFG.Group_ID=$tr_group_id LIMIT 1" );
            if ( $q_row )
               $q_id = $q_row['ID'];
         }
         if ( $q_id == 0 )
         {
            db_query( "$dbgmsg.do_new.transltexts1",
               "INSERT INTO TranslationTexts SET Text='$Qsql', Type='$db_type', Translatable='$translatable', " .
               "Updated=FROM_UNIXTIME($NOW)" );
            $q_id = mysql_insert_id();
            db_query( "$dbgmsg.do_new.translfoundingrp1",
               "INSERT INTO TranslationFoundInGroup SET Text_ID=$q_id, Group_ID=$tr_group_id" );
         }

         $a_id = 0;
         if ( $row['Level'] > 1 )
         {
            $Asql = mysql_addslashes( latin1_safe($answer) );
            if ( $chk_mode )
            {
               $a_row = mysql_single_fetch( "$dbgmsg.do_new.find_atext",
                  "SELECT TT.ID FROM TranslationTexts AS TT " .
                  "INNER JOIN TranslationFoundInGroup AS TFG ON TFG.Text_ID=TT.ID " .
                  "WHERE TT.Text='$Asql' AND TFG.Group_ID=$tr_group_id LIMIT 1" );
               if ( $a_row )
                  $a_id = $a_row['ID'];
            }
            if ( $a_id == 0 )
            {
               db_query( "$dbgmsg.do_new.transltexts2",
                  "INSERT INTO TranslationTexts SET Text='$Asql', Type='$db_type', Translatable='$translatable', " .
                  "Updated=FROM_UNIXTIME($NOW)" );
               $a_id = mysql_insert_id();
               db_query( "$dbgmsg.do_new.translfoundingrp2",
                  "INSERT INTO TranslationFoundInGroup SET Text_ID=$a_id, Group_ID=$tr_group_id" );
            }
         }
         else
            $Asql = '';

         db_query( "$dbgmsg.do_new.update",
            "UPDATE $dbtable SET Question=$q_id, Answer=$a_id WHERE ID=$faq_id LIMIT 1" );

         if ( $do_log )
         {
            db_query( "$dbgmsg.do_new.faqlog",
               "INSERT INTO FAQlog SET Type='$dbtable', Ref_ID=$fid, uid={$player_row['ID']}, " .
                  "Question='$Qsql', Answer='$Asql', Reference='$ReferenceSql'" ); //+ Date= timestamp
         }
      }
      ta_end();

      return $faq_id;
   }//save_new_faq_entry

   public static function move_faq_entry_same_level( $dbgmsg, $dbtable, $fid, $direction )
   {
      $dbgmsg .= ".move_faq_entry_same_level($dbtable,$fid,$direction)";

      $row = mysql_single_fetch( "$dbgmsg.find($fid)",
                "SELECT Parent, SortOrder FROM $dbtable WHERE ID=$fid LIMIT 1" )
          or error('admin_no_such_entry', "$dbgmsg.find2($fid)");
      $parent = $row['Parent'];
      $start = $row['SortOrder'];

      $row2 = mysql_single_fetch( "$dbgmsg.find_max($parent)",
                "SELECT COUNT(*) AS max FROM $dbtable WHERE Parent=$parent")
          or error('mysql_query_failed', "$dbgmsg.find_max2($parent)");
      $max = $row2['max'];

      $end = $new_sortorder = max( 1, min( $max, $start + $direction ));
      $cnt = abs($end - $start);
      if ( $cnt )
      {
         $dir = ($direction > 0) ? 1 : -1;
         $start += $dir;

         ta_begin();
         {//HOT-section to move FAQ-entry
            // shift the neighbours backward, reference by SortOrder
            if ( $start > $end )
               swap( $start, $end );
            db_query( "$dbgmsg.update_sortorder1",
               "UPDATE $dbtable SET SortOrder=SortOrder-($dir) " .
               "WHERE (SortOrder BETWEEN $start AND $end) AND Parent=$parent LIMIT $cnt" );

            // move the entry forward, reference by ID
            db_query( "$dbgmsg.update_sortorder2",
               "UPDATE $dbtable SET SortOrder=$new_sortorder WHERE ID=$fid LIMIT 1" );
         }
         ta_end();
      }
   }//move_faq_entry_same_level

   public static function move_faq_entry_to_new_category( $dbgmsg, $dbtable, $fid, $direction )
   {
      $dbgmsg .= ".move_faq_entry_to_new_category($dbtable,$fid,$direction)";

      $row = mysql_single_fetch( "$dbgmsg.find($fid)",
            "SELECT Entry.SortOrder, Entry.Parent, Parent.SortOrder AS ParentOrder " .
            "FROM $dbtable AS Entry " .
               "INNER JOIN $dbtable AS Parent ON Parent.ID=Entry.Parent " .
            "WHERE Entry.ID=$fid LIMIT 1" )
         or error('admin_no_such_entry', "$dbgmsg.find2($fid)");

      $query = "SELECT ID FROM $dbtable WHERE Level=1 AND SortOrder " .
            ( $direction > 0 ? '>' : '<' ) .
            " {$row['ParentOrder']} ORDER BY SortOrder " .
            ( $direction > 0 ? 'ASC' : 'DESC' ) .
            " LIMIT 1";
      $row2 = mysql_single_fetch("$dbgmsg.newparent", $query );
      if ( $row2 )
      {
         $newparent = $row2['ID'];

         $row3 = mysql_single_fetch( "$dbgmsg.max_sortorder",
            "SELECT MAX(SortOrder) + 1 AS max FROM $dbtable WHERE Parent=$newparent LIMIT 1" );
         if ( $row3 )
         {
            $max = $row3['max'];
            if ( !is_numeric($max) || $max < 1 )
               $max = 1;
         }
         else
            $max = 1;

         ta_begin();
         {//HOT-section to move FAQ-entry to new category
            // shift down the neighbours above
            db_query( "$dbgmsg.update_sortorder1",
               "UPDATE $dbtable SET SortOrder=SortOrder-1 " .
               "WHERE Parent={$row['Parent']} AND SortOrder > {$row['SortOrder']}" );

            // move the entry to the new category
            db_query( "$dbgmsg.update_sortorder2",
               "UPDATE $dbtable SET Parent=$newparent, SortOrder=$max WHERE ID=$fid LIMIT 1" );
         }
         ta_end();
      }
   }//move_faq_entry_to_new_category

   //$row = row from AdminFAQ::get_faq_entry_row()
   public static function transl_toggle_state( $row )
   {
      //Warning: for toggle, Answer follow Question
      //         but they could be translated independently
      if ( $row['Question'] <= 0 )
         return ''; //can't be toggled
      $transl = @$row['QTranslatable'];
      if ( $transl == 'Done' || $transl == 'Changed' )
         return ''; //can't be toggled
      if ( $row['Answer']>0 )
      {
         $transl = @$row['ATranslatable'];
         if ( $transl == 'Done' || $transl == 'Changed' )
            return ''; //can't be toggled
      }
      if ( $transl === 'Y' || $transl === 'N' )
         return $transl; //toggle state
      return ''; //can't be toggled
   }//transl_toggle_state

   public static function toggle_hidden_faq_entry( $dbgmsg, $dbtable, $fid )
   {
      $dbgmsg .= ".toggle_hidden_faq_entry($dbtable,$fid)";

      $row = self::get_faq_entry_row( $dbtable, $fid );
      $faqhidden = ( @$row['Flags'] & FLAG_HELP_HIDDEN );

      ta_begin();
      {//HOT-section to toggle hidden FAQ-entry
         $qpart = ( $faqhidden ) ? 'Flags & ~' . FLAG_HELP_HIDDEN : 'Flags | ' . FLAG_HELP_HIDDEN;

         db_query( "$dbgmsg.upd_entry",
            "UPDATE $dbtable SET Flags=$qpart WHERE ID=$fid LIMIT 1" );

         $transl = self::transl_toggle_state($row);
         if ( $faqhidden && $transl == 'Y' )
         {
            //remove it from translation. No need to adjust Translations.Translated
            $arr = array( $row['Question'] );
            if ( $row['Level'] != 1 )
               $arr[] = $row['Answer'];
            db_query( "$dbgmsg.upd_transltext",
               "UPDATE TranslationTexts SET Translatable='N' " .
               "WHERE ID IN ('" . implode("','", $arr) . "') LIMIT " . count($arr) );
         }
      }
      ta_end();
   }//toggle_hidden_faq_entry

   public static function toggle_translatable_faq_entry( $dbgmsg, $dbtable, $row, $curr_translatable )
   {
      $dbgmsg .= ".toggle_translatable_faq_entry($dbtable)";

      // not yet translated: may toggle it. No need to adjust Translations.Translated
      $arr = array( $row['Question'] );
      if ( $row['Level'] != 1 )
         $arr[] = $row['Answer'];
      db_query( "$dbgmsg.upd_transltext",
         "UPDATE TranslationTexts SET Translatable='" . ($curr_translatable == 'Y' ? 'N' : 'Y' ) . "' " .
         "WHERE ID IN ('" . implode("','", $arr) . "') LIMIT " . count($arr) );
   }//toggle_translatable_faq_entry

   //$row = row from AdminFAQ::get_faq_entry_row()
   public static function delete_faq_entry( $dbgmsg, $dbtable, $fid, $row )
   {
      $dbgmsg .= ".delete_faq_entry($dbtable,$fid)";
      $QID = $row['Question'];
      $AID = $row['Answer'];

      $exists_category = mysql_single_fetch( 'admin_faq.do_edit.empty',
         "SELECT ID FROM $dbtable WHERE Parent=$fid LIMIT 1");
      if ( $row['Level'] <= 1 && $exists_category )
         return false;

      ta_begin();
      {//HOT-section to delete FAQ-entry
         db_query( "$dbgmsg.delete_entry",
            "DELETE FROM $dbtable WHERE ID=$fid LIMIT 1" );
         db_query( "$dbgmsg.update_sortorder",
            "UPDATE $dbtable SET SortOrder=SortOrder-1 " .
            "WHERE Parent={$row['Parent']} AND SortOrder > " . $row['SortOrder'] );

         db_query( "$dbgmsg.delete_tranlsgrps",
            "DELETE FROM TranslationFoundInGroup WHERE Text_ID IN ('$QID','$AID') LIMIT 2" );
         db_query( "$dbgmsg.delete_tranlstexts",
            "DELETE FROM TranslationTexts WHERE ID IN ('$QID','$AID') LIMIT 2" );
      }
      ta_end();

      return true;
   }//delete_faq_entry

   //$row = row from AdminFAQ::get_faq_entry_row()
   public static function update_faq_entry( $dbgmsg, $dbtable, $fid, $row, $q_change, $a_change, $question, $answer, $reference )
   {
      global $NOW, $player_row;

      $dbgmsg .= ".update_faq_entry($dbtable,$fid)";
      if ( !preg_match("/^(FAQ|Links|Intro)$/", $dbtable) )
         error('invalid_args', "$dbgmsg.check.bad_dbtable");
      $QID = $row['Question'];
      $AID = $row['Answer'];
      $log = 0;

      ta_begin();
      {//HOT-section to update FAQ-entry
         $ReferenceSql = mysql_addslashes($reference);
         if ( $row['Reference'] != $reference )
         {
            db_query( "$dbgmsg.update_reference",
               "UPDATE $dbtable SET Reference='$ReferenceSql' WHERE ID=$fid LIMIT 1" );
            $log |= 0x8;
         }

         $Qchanged = ( $q_change && $question && $row['QTranslatable'] === 'Done' );
         if ( $row['Q'] != $question || $Qchanged )
         {
            if ( $Qchanged )
            {
               $QchangedSql = ", Translatable='Changed', Updated=FROM_UNIXTIME($NOW)";
               db_query( "$dbgmsg.update_Qflags",
                  "UPDATE Translations SET Translated='N' WHERE Original_ID=$QID" ); // #>1
               $log |= 0x4;
            }
            else
               $QchangedSql = '';

            $Qsql = mysql_addslashes( latin1_safe($question) );
            db_query( 'admin_faq.do_edit.update_Qtexts',
               "UPDATE TranslationTexts SET Text='$Qsql' $QchangedSql WHERE ID=$QID LIMIT 1" );
            $log |= 0x1;
         }
         else
            $Qsql = '';

         $Achanged = ( $a_change && $answer && $row['ATranslatable'] === 'Done' );
         if ( $AID>0 && ( $row['A'] != $answer || $Achanged ) )
         {
            if ( $Achanged )
            {
               $AchangedSql = ", Translatable='Changed', Updated=FROM_UNIXTIME($NOW)";;
               db_query( "$dbgmsg.update_Aflags",
                  "UPDATE Translations SET Translated='N' WHERE Original_ID=$AID" ); // #>1
               $log |= 0x8;
            }
            else
               $AchangedSql = '';

            $Asql = mysql_addslashes( latin1_safe($answer) );
            db_query( "$dbgmsg.update_Atexts",
               "UPDATE TranslationTexts SET Text='$Asql' $AchangedSql WHERE ID=$AID LIMIT 1" );
            $log |= 0x2;
         }
         else
            $Asql = '';

         if ( $log )
         {
            db_query( "$dbgmsg.faqlog",
               "INSERT INTO FAQlog SET Type='$dbtable', Ref_ID=$fid, uid={$player_row['ID']}, " .
                  "Question='$Qsql', Answer='$Asql', Reference='$ReferenceSql'" ); //+ Date= timestamp
         }
      }
      ta_end();

      return $log;
   }//update_faq_entry

} // end of 'AdminFAQ'

?>
