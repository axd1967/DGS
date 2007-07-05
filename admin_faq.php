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


$info_box = '<table border="2">
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
       Please, avoid to check the box if you have just fixed a typo ;)
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

 ---------:---------------------:--------------:-------------------:-------------------
  N       : not translatable    : change to Y  : -                 : -
  Y       : to be translated    : change to N  : -                 : change to Started
  Started : some are translated : -            : change to Changed : change to Done
  Done    : all  are translated : -            : change to Changed : -
  Changed : to be re-translated : -            : -                 : change to Started

  When both Q & A (if any) are not translated (both Translatable == ('Y' or 'N'))
   - admin_toggle is enabled
   - Answer.Translatable is always equal to Question.Translatable
  When one of them have already been translated (any Translatable(s) != ('Y' or 'N'))
   - admin_toggle is disabled
   - Q & A are independently translated and reversed to *be re-translated*

  When an admin change a Translatable flag to 'Changed'
   - all *language* Translateit flags are set to 'Changed'
  When a translator change a *language* Translateit flag to 'Done'
   - if all *language* Translateit flags are 'Done'
       the Translatable flag is set to 'Done' 
     else
       the Translatable flag is set to 'Started'
*/

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   if( !($player_row['admin_level'] & ADMIN_FAQ) )
      error('adminlevel_too_low');

   $id = is_numeric(@$_GET["id"]) ? $_GET["id"] : 0;

   $show_list = true;
   $page = 'admin_faq.php';


  // ***********        Edit entry       ****************

  if( ($action=@$_GET["edit"]) == 'c' or  $action == 'e')
  {
     if( $action == 'c' )
        start_page(T_("FAQ Admin").' - '.T_('Edit category'), true, $logged_in, $player_row );
     else
        start_page(T_("FAQ Admin").' - '.T_('Edit entry'), true, $logged_in, $player_row );

     $show_list = false;

     echo "<center>\n";

     $row = get_entry_row( $id );

     $faqhide = ( @$row['Hidden'] == 'Y' );
     $faq_edit_form = new Form( 'faqeditform', "$page?do_edit=t".URI_AMP."id=$id", FORM_POST );

     if( $row["Level"] == 1 ) //i.e. Category
     {
        $faq_edit_form->add_row( array( 'HEADER', T_('Edit category') ) );
        $faq_edit_form->add_row( array( 'DESCRIPTION', T_('Category'),
                                        'TEXTINPUT', 'question', 80, 80, $row["Q"] ) );
        if( !$faqhide && $row['QTranslatable'] === 'Done' )
        {
           $faq_edit_form->add_row( array( 'OWNHTML', '<td>',
                                           'CHECKBOX', 'Qchanged', 'Y',
                                           T_('Mark entry as changed for translators'), false) );
        }
     }
     else //i.e. Question/Answer
     {
        $faq_edit_form->add_row( array( 'HEADER', T_('Edit FAQ entry') ) );
        $faq_edit_form->add_row( array( 'DESCRIPTION', T_('Question'),
                                        'TEXTINPUT', 'question', 80, 80, $row["Q"] ) );
        if( !$faqhide && $row['QTranslatable'] === 'Done' )
        {
           $faq_edit_form->add_row( array( 'OWNHTML', '<td>',
                                           'CHECKBOX', 'Qchanged', 'Y',
                                           T_('Mark question as changed for translators'), false) );
        }
        $faq_edit_form->add_row( array( 'DESCRIPTION', T_('Answer'),
                                        'TEXTAREA', 'answer', 80, 20, $row["A"] ) );
        if( !$faqhide && $row['ATranslatable'] === 'Done' )
        {
           $faq_edit_form->add_row( array( 'OWNHTML', '<td>',
                                           'CHECKBOX', 'Achanged', 'Y',
                                           T_('Mark answer as changed for translators'), false) );
        }
     }

      $faq_edit_form->add_row( array(
                           'SUBMITBUTTON', 'submit', T_('Submit'),
                           'TEXT', anchor( $page, T_('Back')),
                           ) );
      $faq_edit_form->echo_string();
  }


  // ***********        Move entry       ****************

  else if( ($action=@$_GET["move"]) == 'u' or $action == 'd' )
  {
     $row = mysql_single_fetch( 'admin_faq.move.find',
               "SELECT * FROM FAQ WHERE ID=$id")
         or error('admin_no_such_entry',"admin_faq.move.$action.read($id)");

     $row2 = mysql_single_fetch( 'admin_faq.move.max',
               "SELECT MAX(SortOrder) as max FROM FAQ " .
               "WHERE Parent=" . $row["Parent"])
         or error('mysql_query_failed','admin_faq.move.max');
     $max = $row2["max"];

     if( ( $action != 'u' or $row["SortOrder"] > 1 ) and
         ( $action != 'd' or $row["SortOrder"] < $max ) )
     {
        $dir = ($action == 'd' ? 1 : -1 );

        mysql_query( "UPDATE FAQ SET SortOrder=SortOrder-($dir) " .
                     'WHERE Parent=' . $row["Parent"] . ' ' .
                     'AND SortOrder=' . ($row["SortOrder"]+$dir) . ' LIMIT 1' )
           or error("mysql_query_failed",'admin_faq.move.update_sortorder1');
        mysql_query( "UPDATE FAQ SET SortOrder=SortOrder+($dir) " .
                     "WHERE ID=" . $row["ID"] . ' LIMIT 1')
           or error("mysql_query_failed",'admin_faq.move.update_sortorder2');
     }
     jump_to($page);
  }



  // ***********        Move entry to new category      ****************

  else if( ($action=@$_GET["move"]) == 'uu' or $action == 'dd' )
  {
     $query = "SELECT Entry.SortOrder, Entry.Parent, Parent.SortOrder AS ParentOrder " .
              " FROM FAQ AS Entry, FAQ AS Parent" .
              " WHERE Entry.ID='$id' AND Parent.ID=Entry.Parent";

     $row= mysql_single_fetch('admin_faq.bigmove.find', $query)
        or error('admin_no_such_entry',"admin_faq.move.$action.read($id)");

     $query = 'SELECT ID as NewParent FROM FAQ ' .
        'WHERE Level=1 ' .
        ( $action == 'dd' ?
          'AND SortOrder > ' . $row['ParentOrder'] . ' ORDER BY SortOrder' :
          'AND SortOrder < ' . $row['ParentOrder'] . ' ORDER BY SortOrder DESC' ) .
        ' LIMIT 1';

     if( $newparent_row=mysql_single_fetch('admin_faq.bigmove.newparent', $query) )
     {
        $newparent = $newparent_row["NewParent"];

        if( $max_row=mysql_single_fetch( 'admin_faq.bigmove.max_sortorder',
               "SELECT MAX(SortOrder) as max FROM FAQ" .
               " WHERE Parent=$newparent LIMIT 1") )
        {
           $max = $max_row['max'];
           if( !is_numeric($max) ) $max = 0;
        }
        else
           $max = 0;


        mysql_query("UPDATE FAQ SET SortOrder=SortOrder-1 " .
                    "WHERE Parent=" . $row["Parent"] . " AND SortOrder>" . $row["SortOrder"])
           or error("mysql_query_failed",'admin_faq.bigmove.update_sortorder1');

        mysql_query( "UPDATE FAQ SET Parent=$newparent, SortOrder=" . ($max+1) . ' ' .
                     "WHERE ID='$id' LIMIT 1")
           or error("mysql_query_failed",'admin_faq.bigmove.update_sortorder2');
     }
     jump_to($page);
  }



  // ***********        Save edited entry       ****************

   else if( ($action=@$_GET["do_edit"]) == 't' )
   {

      $row = get_entry_row( $id );

      if( !isset( $_POST["question"] ) )
         error('no_data','admin_faq.do_edit');

      $question = trim( $_POST["question"] );
      $answer = trim( @$_POST["answer"] );

      // Delete or update ?
      if( empty($question) and empty($answer)
         and $row["QTranslatable"] != 'Done'
         and $row["ATranslatable"] != 'Done'
         and ($row["Level"] > 1 or
            !mysql_single_fetch( 'admin_faq.do_edit.id',
                  "SELECT ID FROM FAQ WHERE Parent=$id LIMIT 1") )
         )
      {
         mysql_query("DELETE FROM FAQ WHERE ID=$id LIMIT 1")
            or error("mysql_query_failed",'admin_faq.do_edit.delete');
         mysql_query("UPDATE FAQ SET SortOrder=SortOrder-1 " .
                     "WHERE Parent=" . $row["Parent"] .
                     " AND SortOrder>" . $row["SortOrder"])
            or error("mysql_query_failed",'admin_faq.do_edit.update_sortorder');

         mysql_query("DELETE FROM TranslationFoundInGroup " .
                     "WHERE Text_ID='" . $row['Question'] . "' " .
                     "OR Text_ID='" . $row['Answer'] . "'")
            or error("mysql_query_failed",'admin_faq.do_edit.delete_tranlsgrps');
         mysql_query("DELETE FROM TranslationTexts " .
                     "WHERE ID='" . $row['Question'] . "' " .
                     "OR ID='" . $row['Answer'] . "'")
            or error("mysql_query_failed",'admin_faq.do_edit.delete_tranlstexts');
      }
      else //Update
      {
         $log = 0;
         if( $row['Q'] != $question )
         {
            $Qchanged = ( @$_POST['Qchanged'] === 'Y' && $row['QTranslatable'] === 'Done'
                        && $question
                        ) ? ", Translatable='Changed'" : '';
            $Qsql = mysql_addslashes($question);
            mysql_query("UPDATE TranslationTexts SET Text='$Qsql'$Qchanged"
                     . " WHERE ID=" . $row['Question'] . " LIMIT 1")
               or error("mysql_query_failed",'admin_faq.do_edit.update_tranlsgrps');
            $log|= 1;
         }
         else
            $Qsql = '';

         if( $row['Answer'] && $row['A'] != $answer )
         {
            $Achanged = ( @$_POST['Achanged'] === 'Y' && $row['ATranslatable'] === 'Done'
                        && $answer
                        ) ? ", Translatable='Changed'" : '';
            $Asql = mysql_addslashes($answer);
            mysql_query("UPDATE TranslationTexts SET Text='$Asql'$Achanged"
                     . " WHERE ID=" . $row['Answer'] . " LIMIT 1")
               or error("mysql_query_failed",'admin_faq.do_edit.update_tranlstexts');
            $log|= 2;
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

     if( $row['QTranslatable'] !== 'N' 
         || ( $row['Answer'] && $row['ATranslatable'] !== 'N' ) )
        make_include_files(null, 'FAQ'); //must be called from main dir

     jump_to($page);
  }



  // ***********        New entry       ****************

  else if( ($action=@$_GET["new"]) == 'e' or $action == 'c')
  {
     if( $action == 'c' )
        start_page(T_("FAQ Admin").' - '.T_('New category'), true, $logged_in, $player_row );
     else
        start_page(T_("FAQ Admin").' - '.T_('New entry'), true, $logged_in, $player_row );

     $show_list = false;

     echo "<center>\n";

     $faq_edit_form = new Form( 'faqnewform', "$page?do_new=" .
                                $action . URI_AMP."id=$id", FORM_POST );

     if( $action == 'c' )
     {
        $faq_edit_form->add_row( array( 'HEADER', T_('New category') ) );
        $faq_edit_form->add_row( array( 'DESCRIPTION', T_('Category'),
                                        'TEXTINPUT', 'question', 80, 80, '' ) );
     }
     else
     {
        $faq_edit_form->add_row( array( 'HEADER', T_('New entry') ) );
        $faq_edit_form->add_row( array( 'DESCRIPTION', T_('Question'),
                                        'TEXTINPUT', 'question', 80, 80, '' ) );
        $faq_edit_form->add_row( array( 'DESCRIPTION', T_('Answer'),
                                        'TEXTAREA', 'answer', 80, 20, '' ) );
     }

      $faq_edit_form->add_row( array(
                           'SUBMITBUTTON', 'submit', T_('Submit'),
                           'TEXT', anchor( $page, T_('Back')),
                           ) );
      $faq_edit_form->echo_string();
   }


   // ***********        Save new entry       ****************

   else if( ($action=@$_GET["do_new"]) == 'c' or $action == 'e' )
   {
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

      // First entry
      if( $row["Level"] == 1 and $action == 'e' )
         $row = array("Parent" => $row["ID"], "SortOrder" => 0, "Level" => 2);

      // First category
      if( $row["Level"] == 0 )
         $row = array("Parent" => $row["ID"], "SortOrder" => 0, "Level" => 1);

      if( !isset( $_POST["question"] ) )
         error('no_data','admin_faq.do_new');

      $question = trim( $_POST["question"] );
      $answer = trim( @$_POST["answer"] );

      $FAQ_group = get_faq_group_id();

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

         mysql_query( "UPDATE FAQ SET Answer=$a_id, Question=$q_id WHERE ID=$faq_id LIMIT 1" )
            or error('mysql_query_failed','admin_faq.do_new.update');

         mysql_query("INSERT INTO FAQlog SET FAQID=$id, uid=" . $player_row["ID"]
                  . ", Question='$Qsql', Answer='$Asql'") //+ Date= timestamp
            or error('mysql_query_failed','admin_faq.do_new.faqlog');
      }

      jump_to($page);
   }


  // ***********       Toggle hidden           ****************

  else if( ($action=@$_GET["hidden"]) === 't' )
  {
     $row = get_entry_row( $id );

     $faqhide = ( @$row['Hidden'] == 'Y' );

        $query = "UPDATE FAQ " .
                    "SET Hidden='" . ($faqhide == 'Y' ? 'N' : 'Y' ) . "' " .
                    "WHERE ID=" . $row["ID"] . ' LIMIT 1';

        mysql_query( $query ) or error('mysql_query_failed','admin_faq.hidden.update');

     if( $faqhide && $row['QTranslatable'] == 'Y' )
     {
        $query = "UPDATE TranslationTexts " .
                    "SET Translatable='N' " .
                    "WHERE ID=" . $row['Question'] .
           ( $row['Level'] == 1 ? ' LIMIT 1' : " OR ID=" . $row['Answer'] . " LIMIT 2" );

        mysql_query( $query ) or error('mysql_query_failed','admin_faq.hidden.transl');
     }

  }


  // ***********       Toggle translatable     ****************

  else if( ($action=@$_GET["transl"]) === 't' )
  {

     $row = get_entry_row( $id );

     //$FAQ_group = get_faq_group_id();

//Warning: for toggle, Answer follow Question
//         but they could be translated independently
     if( $row['QTranslatable'] == 'Done' or $row['QTranslatable'] == 'Changed'
         || ( $row['Answer'] && (
           $row['ATranslatable'] == 'Done' or $row['ATranslatable'] == 'Changed' ) )
       )
        error('admin_already_translated','admin_faq.transl');
     else
     {
        $query = "UPDATE TranslationTexts " .
                    "SET Translatable='" . ($row['QTranslatable'] == 'Y' ? 'N' : 'Y' ) . "' " .
                    "WHERE ID=" . $row['Question'] .
           ( $row['Level'] == 1 ? ' LIMIT 1' : " OR ID=" . $row['Answer'] . " LIMIT 2" );

        mysql_query( $query ) or error('mysql_query_failed','admin_faq.transl');
     }

     make_include_files(null, 'FAQ'); //must be called from main dir
  }



  // ***********       Show FAQ list       ****************

  if( $show_list )
  {
      start_page(T_("FAQ Admin"), true, $logged_in, $player_row );
      echo "<CENTER>\n";
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

     echo "<h3 align=left><a name=\"general\"></a><font color=$h3_color>" .
        T_('FAQ Admin') . "</font></h3>\n";


     $result = mysql_query(
        "SELECT entry.*, Question.Text AS Q".
        ", Question.Translatable AS QTranslatable, Answer.Translatable AS ATranslatable ".
        ", IF(entry.Level=1,entry.SortOrder,parent.SortOrder) AS CatOrder " .
        "FROM (FAQ AS entry, FAQ AS parent, TranslationTexts AS Question) " .
        "LEFT JOIN TranslationTexts AS Answer ON Answer.ID=entry.Answer " .
        "WHERE entry.Parent = parent.ID AND Question.ID=entry.Question " .
        "AND entry.Level<3 AND entry.Level>0 " .
        "ORDER BY CatOrder,entry.Level,entry.SortOrder")
        or error('mysql_query_failed','admin_faq.list');


     echo "<a name=\"general\"></a><table>\n";
     $nbcol = 9;

     echo "<tr><td align=left colspan=$nbcol><a href=\"$page?new=c".URI_AMP."id=1"
        . '"><img border=0 title="'. T_('Add new category')
        . '" src="images/new.png" alt="N"></a></td></tr>';

     while( $row = mysql_fetch_assoc( $result ) )
     {
        $question = (empty($row['Q']) ? '(empty)' : $row['Q']);
        $faqhide = ( @$row['Hidden'] == 'Y' );

        if( $row['Level'] == 1 )
        {
           echo '<tr><td align=left colspan=2><a name="e'.$row['ID'].'"></a>';
           $typechar = 'c'; //category
        }
        else
        {
           echo '<tr><td width=20>&nbsp;</td><td align=left><a name="e'.$row['ID'].'"></a>';
           $typechar = 'e'; //entry
        }

         if( $faqhide )
            echo "(hidden) ";
         echo "<A href=\"$page?edit=$typechar".URI_AMP."id=" . $row['ID'] .
              '" title="' . T_("Edit") . "\">$question</A>\n";
        echo "</td>";

        echo "<td width=40 align=right><a href=\"$page?move=u".URI_AMP.'id=' .
           $row['ID'] . '"><img border=0 title="' . T_("Move up") . '" src="images/up.png" alt="u"></a></td>';
        echo "<td><a href=\"$page?move=d".URI_AMP.'id=' .
           $row['ID'] . '"><img border=0 title="' . T_("Move down") . '" src="images/down.png" alt="d"></a></td>';

        if( $row['Level'] > 1 )
        {
           echo "<td align=right><a href=\"$page?move=uu".URI_AMP.'id=' .
              $row['ID'] . '"><img border=0 title="' . T_("Move to previous category") . '" src="images/up_up.png" alt="U"></a></td>';
           echo "<td><a href=\"$page?move=dd".URI_AMP.'id=' .
              $row['ID'] . '"><img border=0 title="' . T_("Move to next category") . '" src="images/down_down.png" alt="D"></a></td>';
        }
        else
           echo '<td colspan=2>&nbsp;</td>';

        echo "<td><a href=\"$page?new=$typechar".URI_AMP."id=" . $row['ID'] .
           '"><img border=0 title="' .
           ($typechar == 'e' ? T_('Add new entry') : T_('Add new category')) .
           '" src="images/new.png" alt="N"></a></td>';

        echo "<td><a href=\"$page?hidden=t".URI_AMP."id=" . $row['ID'] .
           '"><img border=0 title="' .
           ( $faqhide ? T_('Show') : T_('Hide') ) .
           '" src="images/hide' . ( $faqhide ? '_no.png" alt="h' : '.png" alt="H' ) . '"></a></td>';

        $transl = $row['ATranslatable'];
        if( !$row['Answer'] or ( $transl !== 'Done' and $transl !== 'Changed') )
            $transl = $row['QTranslatable'];
        if( !$faqhide and $transl !== 'Done' and $transl !== 'Changed' )
           echo "<td><a href=\"$page?transl=t".URI_AMP."id=" . $row['ID'] .
           '"><img border=0 title="' .
           ($transl == 'Y' ? T_('Make untranslatable') : T_('Make translatable')) .
           '" src="images/transl' . ( $transl == 'Y' ? '.png" alt="T' : '_no.png" alt="t' ) . '"></a></td>';
        else
           echo '<td>&nbsp;</td>';

        echo '</tr>';

        if( $row["Level"] == 1 )
           echo "<tr><td width=20></td><td align=left colspan=".($nbcol-1)
              . "><a href=\"$page?new=e".URI_AMP."id=" .
              $row['ID'] . '"><img border=0 title="' . T_('Add new entry') .
              '" src="images/new.png" alt="N"></a></td></tr>';
     }
     mysql_free_result($result);

     echo "</table></td></tr></table>\n";
  }

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
      or error('internal_error','get_entry_row');

   return $row;
}

?>
