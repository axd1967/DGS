<?php
/*
Dragon Go Server
Copyright (C) 2001-2006  Erik Ouchterlony, Rod Ival

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

$TranslateGroups[] = "Admin";

require_once( "include/std_functions.php" );
require_once( "include/form_functions.php" );
require_once( "include/make_translationfiles.php" );
require_once( "include/faq_functions.php" );


$info_box = '<table class=InfoBox>
<tr><td>
<ul>
  <li> You may delete an entry by emptying its text-box(es)... while it had
       not been translated. Then you will only be allowed to hide it, waiting
       to re-use it later.
  <li> You may toggle the \'translatable\' status of an entry with the right
       side button. But as soon as one translator had translated it, this
       button will disappear and you will have to use the inside check-box(es)
       (\'Mark as changed for translators\') to signal to the translators
       when your edition is finished and that they have some work to do again.
       This button is also not shown as long as the entry is hidden.
       Please, avoid to check the box if you have just fixed a typo ;)
  <li> Hidden entries - marked by \'(H)\' - are not shown in the live-FAQ.
  <li> When adding a new entry or editing an existing entry, select the
       \'Preview\' checkbox to see how the FAQ-entry will look like.
       The new or changed text are saved regardless of the preview-checkbox.
  <li> For links homed at DGS, use not &lt;a href=".."&gt;, but the
       home-tag, e.g. &lt;home users.php&gt;Users&lt;/home&gt;
  <li> You may use the note-tag &lt;note&gt;(removed from entry)&lt;/note&gt;
       to add some notes seen only by faq admins and translators.
  <li> Setting the move distance (for Q/A-entries) allows to control how
       many lines an entry can be moved up or down. A saved value is
       stored for one hour in a cookie. Invalid or 0 resets to the
       default of 1.
  <li> About FAQ Search: Search finds text in Question or Answers of all
       categories and texts, also in the hidden ones. The search-term
       is implicitly prefixed and suffixed with a wildcard \'%\'-pattern,
       so is always a substring-search. Found entries will be marked with
       a red \'<font color="red">#</font>\'.
  <li> The last used entry is marked with a blue \'<font color="blue"><b>&gt;</b></font>\'.
</ul>
</td></tr>
</table>
<p></p>
';

/* Translatable flag meaning - See also translate.php
  Value   : Meaning             : admin_toggle : admin_mark_box    : translator_page
 ---------:---------------------:--------------:-------------------:-------------------
  N       : not translatable    : change to Y  : -                 : -
  Y       : to be translated    : change to N  : -                 : change to Done
  Done    : already translated  : -            : change to Changed : -
  Changed : to be re-translated : -            : -                 : change to Done

  When both Q & A (if any) are not translated (both Translatable == ('Y' or 'N'))
   - admin_toggle is enabled
   - admin_mark_box is disabled
   - Answer.Translatable is always equal to Question.Translatable
  When one of them have already been translated (any Translatable(s) != ('Y' or 'N'))
   - admin_toggle is disabled
   - Q & A are independently translated
     and reversed to *be re-translated* by the way of the admin_mark_box
   - Answer.Translatable may be different of Question.Translatable

  When an admin change a Translatable flag to 'Changed'
   - all the *language* Translated flags are set to 'N'
  As soon as one translator change a *language* Translated flag to 'Y'
   - the Translatable flag is set to 'Done'
*/

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   if( !(@$player_row['admin_level'] & ADMIN_FAQ) )
      error('adminlevel_too_low');

   $id = is_numeric(@$_REQUEST['id']) ? max(0,$_REQUEST['id']) : 0;
   $term = get_request_arg('term', '');

   // read/write move-distance for entries using cookie
   $movedist = (int)@$_REQUEST['movedist'];
   if ( $movedist < 1 )
      $movedist = max( 1, (int)safe_getcookie('admin_faq_movedist'));
   if ( @$_REQUEST['setmovedist'] ) // write into cookie
      safe_setcookie( 'admin_faq_movedist', $movedist, 3600 ); // save for 1h

   $show_list = true;
   $page = 'admin_faq.php';


   // ***********        Move entry       ****************

   // args: id, move=u|d, dir=length of the move (int, pos or neg)
   if( ($action=@$_REQUEST['move']) == 'u' or $action == 'd' )
   {
      $dir = isset($_REQUEST['dir']) ? (int)$_REQUEST['dir'] : 1;
      $dir = $action == 'd' ? $dir : -$dir; //because ID top < ID bottom

      $row = mysql_single_fetch( 'admin_faq.move.find',
                "SELECT * FROM FAQ WHERE ID=$id")
          or error('admin_no_such_entry',"admin_faq.move.$action.read($id)");
      $parent = $row['Parent'];

      $row2 = mysql_single_fetch( 'admin_faq.move.max',
                "SELECT COUNT(*) as max FROM FAQ WHERE Parent=$parent")
          or error('mysql_query_failed',"admin_faq.move.$action.max");
      $max = $row2['max'];

      $start = $row["SortOrder"];
      $end = max( 1, min( $max, $start + $dir));
      $cnt = abs($end - $start);
      if( $cnt )
      {
         $dir = $dir>0 ? 1 : -1;
         $start+= $dir;

         //shift the neighbours backward, reference by SortOrder
         mysql_query("UPDATE FAQ SET SortOrder=SortOrder-($dir)"
                     . " WHERE Parent=$parent"
                     . " AND SortOrder BETWEEN "
                        .($start>$end?"$end AND $start":"$start AND $end")
                     . " LIMIT $cnt" )
            or error("mysql_query_failed",'admin_faq.move.update_sortorder1');

         //move the entry forward, reference by ID
         mysql_query("UPDATE FAQ SET SortOrder=$end"
                     . " WHERE ID=$id LIMIT 1")
            or error("mysql_query_failed",'admin_faq.move.update_sortorder2');
      }
      jump_to("$page?id=$id#e$id"); //clean URL
   } //move



   // ***********        Move entry to new category      ****************

   // args: id, move=uu|dd
   else if( ($action=@$_REQUEST['move']) == 'uu' or $action == 'dd' )
   {
      $query = "SELECT Entry.SortOrder,Entry.Parent,Parent.SortOrder AS ParentOrder"
            . " FROM FAQ AS Entry,FAQ AS Parent"
            . " WHERE Entry.ID=$id AND Parent.ID=Entry.Parent";

      $row= mysql_single_fetch('admin_faq.bigmove.find', $query)
         or error('admin_no_such_entry',"admin_faq.move.$action.read($id)");

      $query = 'SELECT ID FROM FAQ'
            . ' WHERE Level=1 AND SortOrder'
            . ( $action == 'dd'
               ? '>' . $row['ParentOrder'] . ' ORDER BY SortOrder'
               : '<' . $row['ParentOrder'] . ' ORDER BY SortOrder DESC' )
            . ' LIMIT 1';

      if( $newparent=mysql_single_fetch('admin_faq.bigmove.newparent', $query) )
      {
         $newparent = $newparent['ID'];

         if( $max=mysql_single_fetch( 'admin_faq.bigmove.max_sortorder',
               "SELECT MAX(SortOrder)+1 as max FROM FAQ"
               ." WHERE Parent=$newparent LIMIT 1") )
         {
            $max = $max['max'];
            if( !is_numeric($max) or $max<1 ) $max = 1;
         }
         else
            $max = 1;

         //shift down the neighbours above
         mysql_query("UPDATE FAQ SET SortOrder=SortOrder-1"
                  . " WHERE Parent=" . $row["Parent"]
                  . " AND SortOrder>" . $row["SortOrder"])
            or error("mysql_query_failed",'admin_faq.bigmove.update_sortorder1');

         //move the entry in the new category
         mysql_query( "UPDATE FAQ SET Parent=$newparent, SortOrder=$max"
                  . " WHERE ID=$id LIMIT 1")
            or error("mysql_query_failed",'admin_faq.bigmove.update_sortorder2');
      }
      jump_to("$page?id=$id#e$id"); //clean URL
   } //bigmove



   // ***********        Edit entry       ****************

   // args: id, edit=t type=c|e [ do_edit=?, preview=t ]
   // keep it tested before 'do_edit' for the preview feature
   else if( @$_REQUEST['edit'] &&
      ( ($action=@$_REQUEST['type']) == 'c' or  $action == 'e' ) )
   {
      if( $action == 'c' )
         start_page('FAQ Admin - Edit category', true, $logged_in, $player_row );
      else
         start_page('FAQ Admin - Edit entry', true, $logged_in, $player_row );
      echo "<center>\n";

      $show_list = false;

      $row = get_entry_row( $id );
      $faqhide = ( @$row['Hidden'] == 'Y' );
      if( @$_REQUEST['preview'] )
      {
         $question = trim( get_request_arg('question') );
         $answer = trim( get_request_arg('answer') );
      }
      else
      {
         $question = $row['Q'];
         $answer = $row['A'];
      }

      $faq_edit_form = new Form( 'faqeditform', "$page?id=$id#e$id", FORM_POST );

      if( $row['Level'] == 1 ) //i.e. Category
      {
         $faq_edit_form->add_row( array( 'HEADER', 'Edit category' ) );
         $faq_edit_form->add_row( array( 'DESCRIPTION', 'Category',
                                         'TEXTINPUT', 'question', 80, 80, $question ) );
         if( !$faqhide && $row['QTranslatable'] === 'Done' )
         {
            $faq_edit_form->add_row( array( 'OWNHTML', '<td>',
                                            'CHECKBOX', 'Qchanged', 'Y',
                                            'Mark entry as changed for translators', false) );
         }
      }
      else //i.e. Question/Answer
      {
         $faq_edit_form->add_row( array( 'HEADER', 'Edit FAQ entry' ) );
         $faq_edit_form->add_row( array( 'DESCRIPTION', 'Question',
                                         'TEXTINPUT', 'question', 80, 80, $question ) );
         if( !$faqhide && $row['QTranslatable'] === 'Done' )
         {
            $faq_edit_form->add_row( array( 'OWNHTML', '<td>',
                                            'CHECKBOX', 'Qchanged', 'Y',
                                            'Mark question as changed for translators', false) );
         }
         $faq_edit_form->add_row( array( 'DESCRIPTION', 'Answer',
                                         'TEXTAREA', 'answer', 80, 20, $answer ) );
         if( !$faqhide && $row['ATranslatable'] === 'Done' )
         {
            $faq_edit_form->add_row( array( 'OWNHTML', '<td>',
                                            'CHECKBOX', 'Achanged', 'Y',
                                            'Mark answer as changed for translators', false) );
         }
      }

      $faq_edit_form->add_empty_row();
      $faq_edit_form->add_row( array(
                           'TAB',
                           'CELL', 1, 'align=left',
                           'HIDDEN', 'type', $action,
                           'HIDDEN', 'preview', 1,
                           'SUBMITBUTTONX', 'do_edit', 'Update entry',
                              array('accesskey'=>'x'),
                           'SUBMITBUTTONX', 'edit', 'Preview',
                              array('accesskey'=>'w'),
                           'SUBMITBUTTON', 'back', 'Back to list',
                           ));
      $faq_edit_form->echo_string(1);

      show_preview( $row['Level'], $question, $answer);
   } //edit


   // ***********        Save edited entry       ****************

   // args: id, do_edit=t type=c|e, question, answer [ preview='' ]
   // keep it tested after 'edit' for the preview feature
   else if( @$_REQUEST['do_edit'] &&
      ( ($action=@$_REQUEST['type']) == 'c' or  $action == 'e' ) )
   {
      $row = get_entry_row( $id );
      $transl = transl_toggle_state( $row);

      if( !isset( $_POST['question'] ) )
         error('no_data','admin_faq.do_edit');

      $question = trim( get_request_arg('question') );
      $answer = trim( get_request_arg('answer') );
      $QID = $row['Question'];
      $AID = $row['Answer'];

      $log = 0;
      $ref_id = $id; // anchor-ref
      $is_delete = false;
      // Delete or update ?
      if( empty($question) && empty($answer) && $transl
         && ($row["Level"] > 1 or
            !mysql_single_fetch( 'admin_faq.do_edit.id',
                  "SELECT ID FROM FAQ WHERE Parent=$id LIMIT 1") )
         )
      { // Delete
         mysql_query("DELETE FROM FAQ WHERE ID=$id LIMIT 1")
            or error("mysql_query_failed",'admin_faq.do_edit.delete');
         mysql_query("UPDATE FAQ SET SortOrder=SortOrder-1 " .
                     "WHERE Parent=" . $row["Parent"] .
                     " AND SortOrder>" . $row["SortOrder"])
            or error("mysql_query_failed",'admin_faq.do_edit.update_sortorder');

         mysql_query("DELETE FROM TranslationFoundInGroup " .
                     "WHERE Text_ID='$QID' " .
                     "OR Text_ID='$AID'")
            or error("mysql_query_failed",'admin_faq.do_edit.delete_tranlsgrps');
         mysql_query("DELETE FROM TranslationTexts " .
                     "WHERE ID='$QID' " .
                     "OR ID='$AID'")
            or error("mysql_query_failed",'admin_faq.do_edit.delete_tranlstexts');

         $ref_id = $row['Parent']; // anchor-ref to former, parent category
         $is_delete = true;
      }
      else //Update
      {
         $Qchanged = ( @$_POST['Qchanged'] === 'Y' //only if not hidden
                     && $question && $row['QTranslatable'] === 'Done' );
         if( $row['Q'] != $question or $Qchanged )
         {
            if( $Qchanged )
            {
               $Qchanged = ", Translatable='Changed'";
               mysql_query( "UPDATE Translations SET Translated='N'"
                     . " WHERE Original_ID=$QID")
                  or error('mysql_query_failed','admin_faq.do_edit.update_Qflags');
               $log|= 0x4;
            }
            else
               $Qchanged = '';

            $Qsql = mysql_addslashes($question);
            mysql_query("UPDATE TranslationTexts SET Text='$Qsql'$Qchanged"
                     . " WHERE ID=$QID LIMIT 1")
               or error("mysql_query_failed",'admin_faq.do_edit.update_Qtexts');
            $log|= 0x1;
         }
         else
            $Qsql = '';

         $Achanged = ( @$_POST['Achanged'] === 'Y' //only if not hidden
                     && $answer && $row['ATranslatable'] === 'Done' );
         if( $AID>0 && ( $row['A'] != $answer or $Achanged ) )
         {
            if( $Achanged )
            {
               $Achanged = ", Translatable='Changed'";
               mysql_query( "UPDATE Translations SET Translated='N'"
                     . " WHERE Original_ID=$AID")
                  or error('mysql_query_failed','admin_faq.do_edit.update_Aflags');
               $log|= 0x8;
            }
            else
               $Achanged = '';

            $Asql = mysql_addslashes($answer);
            mysql_query("UPDATE TranslationTexts SET Text='$Asql'$Achanged"
                     . " WHERE ID=$AID LIMIT 1")
               or error('mysql_query_failed','admin_faq.do_edit.update_Atexts');
            $log|= 0x2;
         }
         else
            $Asql = '';

         if( $log )
         {
            mysql_query("INSERT INTO FAQlog SET FAQID=$id, uid=" . $player_row["ID"]
                     . ", Question='$Qsql', Answer='$Asql'") //+ Date= timestamp
               or error("mysql_query_failed",'admin_faq.do_edit.faqlog');
         }
      }

      if( $log ) //i.e. modified except deleted
         if( $row['QTranslatable'] !== 'N'
            || ( $AID>0 && $row['ATranslatable'] !== 'N' ) )
            make_include_files(null, 'FAQ'); //must be called from main dir

      //clean URL (focus on edited entry or parent category if entry deleted)
      jump_to( "$page?id=$ref_id#e$ref_id" );
   } //do_edit



   // ***********        New entry       ****************

   // args: id, new=t type=c|e [ do_new=?, preview=t ]
   // keep it tested before 'do_new' for the preview feature
   else if( @$_REQUEST['new'] &&
      ( ($action=@$_REQUEST['type']) == 'c' or  $action == 'e' ) )
   {
      if( $action == 'c' )
         start_page('FAQ Admin - New category', true, $logged_in, $player_row );
      else
         start_page('FAQ Admin - New entry', true, $logged_in, $player_row );
      echo "<center>\n";

      $show_list = false;

      if( @$_REQUEST['preview'] )
      {
         $question = trim( get_request_arg('question') );
         $answer = trim( get_request_arg('answer') );
      }
      else
      {
         $question = '';
         $answer = '';
      }

      $faq_edit_form = new Form( 'faqnewform', "$page?id=$id#e$id", FORM_POST );

      if( $action == 'c' )
      {
         $faq_edit_form->add_row( array( 'HEADER', 'New category' ) );
         $faq_edit_form->add_row( array( 'DESCRIPTION', 'Category',
                                         'TEXTINPUT', 'question', 80, 80, $question ) );
      }
      else
      {
         $faq_edit_form->add_row( array( 'HEADER', 'New entry' ) );
         $faq_edit_form->add_row( array( 'DESCRIPTION', 'Question',
                                         'TEXTINPUT', 'question', 80, 80, $question ) );
         $faq_edit_form->add_row( array( 'DESCRIPTION', 'Answer',
                                         'TEXTAREA', 'answer', 80, 20, $answer ) );
      }

      $faq_edit_form->add_empty_row();
      $faq_edit_form->add_row( array(
                           'TAB',
                           'CELL', 1, 'align=left',
                           'HIDDEN', 'type', $action,
                           'HIDDEN', 'preview', 1,
                           'SUBMITBUTTONX', 'do_new', 'Add entry',
                              array('accesskey'=>'x'),
                           'SUBMITBUTTONX', 'new', 'Preview',
                              array('accesskey'=>'w'),
                           'SUBMITBUTTON', 'back', 'Back to list',
                           ));
      $faq_edit_form->echo_string(1);

      show_preview( $action=='c' ? 1 : 2, $question, $answer);
   } //new


   // ***********        Save new entry       ****************

   // args: id, do_new=t type=c|e, question, answer, [ preview='' ]
   // keep it tested after 'new' for the preview feature
   else if( @$_REQUEST['do_new'] &&
      ( ($action=@$_REQUEST['type']) == 'c' or  $action == 'e' ) )
   {
      if( !isset( $_POST['question'] ) )
         error('no_data','admin_faq.do_new');

      $query = "SELECT * FROM FAQ WHERE ID=$id";
      $row = mysql_single_fetch( 'admin_faq.do_new.find1', $query );

      if( $id==1 && (!$row or $row['Hidden']=='Y') )
      {
         //adjust the seed
         mysql_query(
            "REPLACE INTO FAQ (ID,Parent,Level,SortOrder,Question,Answer,Hidden)"
                     . " VALUES (1,1,0,0,0,0,'N')"
            ) or error('mysql_query_failed','admin_faq.do_new.replece_into');
         $row = mysql_single_fetch( 'admin_faq.do_new.find2', $query );
      }

      if( !$row )
         error('admin_no_such_entry','admin_faq.do_new');

      if( $row["Level"] == 0 ) // First category
         $row = array("Parent" => $row["ID"], "SortOrder" => 0, "Level" => 1);
      else if( $row["Level"] == 1 and $action == 'e' ) // First entry
         $row = array("Parent" => $row["ID"], "SortOrder" => 0, "Level" => 2);

      $FAQ_group = get_faq_group_id();

      $question = trim( get_request_arg('question') );
      $answer = trim( get_request_arg('answer') );

      $ref_id = $id; // anchor-ref
      if( !empty($question) )
      {
         mysql_query("UPDATE FAQ SET SortOrder=SortOrder+1 " .
                     'WHERE Parent=' . $row["Parent"] . ' ' .
                     'AND SortOrder>' . $row["SortOrder"] )
            or error('mysql_query_failed','admin_faq.do_new.update_sortorder');

         mysql_query("INSERT INTO FAQ SET " .
                     "SortOrder=" . ($row["SortOrder"]+1) . ', ' .
                     "Parent=" . $row["Parent"] . ', ' .
                     "Level=" . $row["Level"] )
            or error('mysql_query_failed','admin_faq.do_new.insert');

         $faq_id = mysql_insert_id();
         $ref_id = $faq_id;

         $Qsql = mysql_addslashes($question);
         mysql_query("INSERT INTO TranslationTexts SET Text='$Qsql'" .
                     ", Ref_ID=$faq_id, Translatable = 'N' " )
            or error('mysql_query_failed','admin_faq.do_new.transltexts1');

         $q_id = mysql_insert_id();
         mysql_query("INSERT INTO TranslationFoundInGroup " .
                     "SET Text_ID=$q_id, Group_ID=$FAQ_group" )
            or error('mysql_query_failed','admin_faq.do_new.translfoundingrp1');

         $a_id = 0;
         if( $row['Level'] > 1 )
         {
            $Asql = mysql_addslashes($answer);
            mysql_query("INSERT INTO TranslationTexts SET Text='$Asql'" .
                        ", Ref_ID=$faq_id, Translatable = 'N' " )
               or error('mysql_query_failed','admin_faq.do_new.transltexts2');

            $a_id = mysql_insert_id();
            mysql_query("INSERT INTO TranslationFoundInGroup " .
                        "SET Text_ID=$a_id, Group_ID=$FAQ_group" )
               or error('mysql_query_failed','admin_faq.do_new.translfoundingrp2)');
         }
         else
            $Asql = '';

         mysql_query("UPDATE FAQ SET Answer=$a_id, Question=$q_id WHERE ID=$faq_id LIMIT 1" )
            or error('mysql_query_failed','admin_faq.do_new.update');

         mysql_query("INSERT INTO FAQlog SET FAQID=$id, uid=" . $player_row["ID"]
                  . ", Question='$Qsql', Answer='$Asql'") //+ Date= timestamp
            or error('mysql_query_failed','admin_faq.do_new.faqlog');
      }

      jump_to( "$page?id=$ref_id#e$ref_id" ); //clean URL (focus on new entry)
   } //do_new


   // ***********       Toggle hidden           ****************

   // args: id, toggleH=Y|N
   else if( ($action=@$_REQUEST['toggleH']) )
   {
      $row = get_entry_row( $id );
      $faqhide = ( @$row['Hidden'] == 'Y' );

      if( ($action=='Y') xor $faqhide )
      {
         $query = "UPDATE FAQ " .
                  "SET Hidden='" . ( $faqhide ? 'N' : 'Y' ) . "' " .
                  "WHERE ID=" . $row["ID"] . ' LIMIT 1';

         mysql_query( $query ) or error('mysql_query_failed','admin_faq.hidden_update');

         $transl = transl_toggle_state( $row);
         if( $faqhide && $transl == 'Y' )
         {
            //remove it from translation. No need to adjust Translations.Translated
            $query = "UPDATE TranslationTexts " .
                     "SET Translatable='N' " .
                     "WHERE ID=" . $row['Question'] .
                     ( $row['Level'] == 1 ? ' LIMIT 1'
                        : " OR ID=" . $row['Answer'] . " LIMIT 2" );

            mysql_query( $query ) or error('mysql_query_failed','admin_faq.hidden_flags');
         }
      }
      //jump_to($page); //clean URL
   } //toggleH


   // ***********       Toggle translatable     ****************

   // args: id, toggleT=Y|N
   else if( ($action=@$_REQUEST['toggleT']) )
   {
      $row = get_entry_row( $id );
      $transl = transl_toggle_state( $row);

      if( !$transl )
         error('admin_already_translated','admin_faq.transl');
      else if( ($action=='Y') xor ($transl == 'Y') )
      {
         //not yet translated: may toggle it. No need to adjust Translations.Translated
         $query = "UPDATE TranslationTexts " .
                  "SET Translatable='" . ($transl == 'Y' ? 'N' : 'Y' ) . "' " .
                  "WHERE ID=" . $row['Question'] .
                  ( $row['Level'] == 1 ? ' LIMIT 1'
                     : " OR ID=" . $row['Answer'] . " LIMIT 2" );
         mysql_query( $query ) or error('mysql_query_failed','admin_faq.transl');

         make_include_files(null, 'FAQ'); //must be called from main dir
      }
      //jump_to($page); //clean URL
   } //toggleT



   // ***********       Show FAQ list       ****************

   if( $show_list )
   {
      start_page('FAQ Admin', true, $logged_in, $player_row );
      echo "<center>\n";

      $str = 'Read this before editing';
      if( (bool)@$_REQUEST['infos'] )
      {
         echo '<h3 class=Header>' . $str . ':</h3>';
         echo $info_box;
      }
      else
      {
         echo '<h3 class=Header>' . anchor( $page.'?infos=1', $str) . '</h3>';
      }


      echo "<table align=center width=\"85%\" border=0><tr><td>\n";

      echo "<h3 class=Header align=left><a name=\"general\">" .
         'FAQ Admin' . "</a></h3>\n";


      // FAQ-search
      $faq_search_form = new Form( 'faqsearchform', $page, FORM_POST );
      $faq_search_form->add_row( array(
            'DESCRIPTION',  'Search Term',
            'TEXTINPUT',    'term', 30, -1, $term,
            'SUBMITBUTTONX', 'submit', 'Search',
                        array('accesskey'=>'x'),
            ));
      $faq_search_form->add_row( array(
            'TAB',
            'TEXT', '(_=any char, %=any number of chars, \=escape char)' ));
      $faq_search_form->add_row( array(
            'DESCRIPTION',  'Move distance (entry)',
            'TEXTINPUT',    'movedist', 4, 2, $movedist,
            'SUBMITBUTTON', 'setmovedist', 'Set move length' ));
      $faq_search_form->add_hidden( 'id',  $id ); // current entry
      $faq_search_form->echo_string(1);


      $qterm = ( $term != '' ) ? mysql_addslashes("%$term%") : ''; // implicit wildcards
      $query =
         "SELECT entry.*, Question.Text AS Q".
         ", Question.Translatable AS QTranslatable, Answer.Translatable AS ATranslatable ".
         ", IF(entry.Level=1,entry.SortOrder,parent.SortOrder) AS CatOrder " .
         ( ($qterm != '')
            ? ", IF(Question.Text LIKE '$qterm',1,0) AS MatchQuestion " .
              ", IF(entry.Level>1 AND Answer.Text LIKE '$qterm',1,0) AS MatchAnswer "
            : ", 0 AS MatchQuestion " .
              ", 0 AS MatchAnswer "
         ) .
         "FROM (FAQ AS entry, FAQ AS parent, TranslationTexts AS Question) " .
         "LEFT JOIN TranslationTexts AS Answer ON Answer.ID=entry.Answer " .
         "WHERE entry.Parent = parent.ID AND Question.ID=entry.Question " .
         "AND entry.Level<3 AND entry.Level>0 " .
         "ORDER BY CatOrder,entry.Level,entry.SortOrder";

      #echo "<br>QUERY: $query<br>\n"; // debug
      $result = mysql_query($query)
         or error('mysql_query_failed','admin_faq.list');


      echo "<a name=\"general\"></a><table>\n";

      // table-columns:
      // curr-entry | match-term | Q/New | A | move-up | ~down | cat-up | ~down | New | Hide | Transl
      $nbcol = 11;

      echo "<tr><td colspan=2>&nbsp;</td><td align=left colspan=".($nbcol-2).">"
         . "<a href=\"$page?new=1".URI_AMP."type=c".URI_AMP."id=1"
         . '"><img border=0 title="'. 'Add new category'
         . '" src="images/new.png" alt="N"></a></td></tr>';

      while( $row = mysql_fetch_assoc( $result ) )
      {
         $question = (empty($row['Q']) ? '(empty)' : $row['Q']);
         $faqhide = ( @$row['Hidden'] == 'Y' );
         $transl = transl_toggle_state( $row);

         $entry_ref = "#e{$row['ID']}";

         // mark 'current' entry and matched-terms (2 cols)
         echo '<tr><td with=10>';
         if ( $row['MatchQuestion'] || $row['MatchAnswer'] )
            echo '<font color="red">#</font>';
         else
            echo '&nbsp;';
         echo '</td><td with=10>';
         echo ( $id == $row['ID'] )
            ? '<font color="blue"><b>&gt;</b></font>'
            : '&nbsp;';
         echo '</td>';

         // anchor-label + td-start for cat/entry
         if( $row['Level'] == 1 )
         {
            echo '<td align=left colspan=2><a name="e'.$row['ID'].'"></a>';
            $typechar = 'c'; //category
         }
         else
         {
            echo '<td width=20>&nbsp;</td><td align=left><a name="e'.$row['ID'].'"></a>';
            $typechar = 'e'; //entry
         }

         // question/answer (1 col)
         if( $faqhide )
            echo "(H) ";
         echo "<A href=\"$page?edit=1".URI_AMP."type=$typechar".URI_AMP."id=" . $row['ID'] .
              "\" title=\"Edit\">$question</A>";
         echo "\n</td>";

         // move entry up/down (focus parent category)
         echo "<td width=40 align=right>"
            . "<a href=\"$page?move=u".URI_AMP.'id=' . $row['ID'] . URI_AMP . "dir=$movedist" . "$entry_ref\">"
            . '<img border=0 title="Move up" src="images/up.png" alt="u"></a></td>';
         echo "<td><a href=\"$page?move=d".URI_AMP.'id=' . $row['ID'] . URI_AMP . "dir=$movedist" . "$entry_ref\">"
            . '<img border=0 title="Move down" src="images/down.png" alt="d"></a></td>';

         if( $row['Level'] > 1 )
         {
            // move entry up/down to other category (focus current entry)
            echo "<td align=right>"
               . "<a href=\"$page?move=uu".URI_AMP.'id=' . $row['ID'] . "$entry_ref\">"
               . '<img border=0 title="Move to previous category" src="images/up_up.png" alt="U"></a></td>';
            echo "<td><a href=\"$page?move=dd".URI_AMP.'id=' . $row['ID'] . "$entry_ref\">"
               . '<img border=0 title="Move to next category" src="images/down_down.png" alt="D"></a></td>';
         }
         else
            echo '<td colspan=2>&nbsp;</td>';

         // new entry
         echo "<td><a href=\"$page?new=1".URI_AMP."type=$typechar".URI_AMP."id=" . $row['ID'] .
            '"><img border=0 title="' .
            ($typechar == 'e' ? 'Add new entry' : 'Add new category') .
            '" src="images/new.png" alt="N"></a></td>';

         // hide (focus parent category)
         echo "<td><a href=\"$page?toggleH="
            . ( $faqhide == 'Y' ? 'N' : 'Y' )
            . URI_AMP."id=" . $row['ID']
            . "$entry_ref\"><img border=0 title=\""
            . ( $faqhide ? 'Show' : 'Hide' )
            . '" src="images/hide'
            . ( $faqhide ? '_no.png" alt="h' : '.png" alt="H' )
            . '"></a></td>';

         // translatable (focus parent category)
         if( !$faqhide and $transl )
            echo "<td><a href=\"$page?toggleT="
               . ( $transl == 'Y' ? 'N' : 'Y' )
               . URI_AMP."id=" . $row['ID']
               . "$entry_ref\"><img border=0 title=\""
               . ( $transl == 'Y' ? 'Make untranslatable' : 'Make translatable')
               . '" src="images/transl'
               . ( $transl == 'Y' ? '.png" alt="T' : '_no.png" alt="t' )
               . '"></a></td>';
         else
            echo '<td>&nbsp;</td>';

         echo '</tr>';

         // new category (below section-title)
         if( $row["Level"] == 1 )
            echo "<tr><td colspan=2>&nbsp;</td><td width=20></td><td align=left colspan=".($nbcol-3)
               . "><a href=\"$page?new=1".URI_AMP."type=e".URI_AMP."id=" .
               $row['ID'] . '"><img border=0 title="Add new entry"' .
               ' src="images/new.png" alt="N"></a></td></tr>';
      }
      mysql_free_result($result);

      echo "</table></td></tr></table>\n";
   } //show_list

   echo "</center>\n";
   end_page();
}


function get_faq_group_id()
{
   $row = mysql_single_fetch( 'admin_faq.get_faq_group_id',
            "SELECT ID FROM TranslationGroups WHERE Groupname='FAQ'" )
      or error('internal_error', 'admin_faq.get_faq_group_id');

   return $row['ID'];
}

function get_entry_row( $id )
{
   $row = mysql_single_fetch( 'admin_faq.get_entry_row',
        "SELECT FAQ.*, Question.Text AS Q, Answer.Text AS A".
        ", Question.Translatable AS QTranslatable".
        ", Answer.Translatable AS ATranslatable ".
        "FROM (FAQ, TranslationTexts AS Question) " .
        "LEFT JOIN TranslationTexts AS Answer ON Answer.ID=FAQ.Answer " .
        "WHERE FAQ.ID='$id' AND Question.ID=FAQ.Question" )
      or error('internal_error','admin_faq.get_entry_row');

   return $row;
}

//$row = row from get_entry_row(), for example
function transl_toggle_state( $row)
{
   //Warning: for toggle, Answer follow Question
   //         but they could be translated independently
   if( $row['Question'] <= 0 )
      return ''; //can't be toggled
   $transl = @$row['QTranslatable'];
   if( $transl == 'Done' or $transl == 'Changed' )
      return ''; //can't be toggled
   if( $row['Answer']>0 )
   {
      $transl = @$row['ATranslatable'];
      if( $transl == 'Done' or $transl == 'Changed' )
         return ''; //can't be toggled
   }
   if( $transl === 'Y' or $transl === 'N' )
      return $transl; //toggle state
   return ''; //can't be toggled
}

function show_preview( $level, $question, $answer)
{
   echo "<table class=FAQ><tr><td class=FAQread>\n";
   echo faq_item_html( 0);
   echo faq_item_html( $level, $question, $answer,
               $level == 1 ? 'href="#preview"' : 'name="preview"');
   echo faq_item_html(-1);
   echo "</td></tr></table>\n";
}

?>
