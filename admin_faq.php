<?php
/*
Dragon Go Server
Copyright (C) 2001-2003  Erik Ouchterlony

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

{
  connect2mysql();

  $logged_in = is_logged_in($handle, $sessioncode, $player_row);

  if( !$logged_in )
    error("not_logged_in");

  if( !($player_row['admin_level'] & ADMIN_FAQ) )
    error("adminlevel_too_low");

  $id = is_numeric($_GET["id"]) ? $_GET["id"] : 0;

  $show_list = true;

  function get_faq_group()
     {
        $result = mysql_query("SELECT ID FROM TranslationGroups WHERE Groupname='FAQ'");

        if( mysql_num_rows($result) != 1 )
           error("mysql_query_failed", "Group 'FAQ' Missing in TranslationGroups");

        $row = mysql_fetch_array( $result );
        return $row['ID'];
     }

  // ***********        Edit entry       ****************

  if( $_GET["edit"] == 'c' or  $_GET["edit"] == 'e')
  {
     if( $_GET["edit"] == 'c' )
        start_page(T_("FAQ Admin").' - '.T_('Edit category'), true, $logged_in, $player_row );
     else
        start_page(T_("FAQ Admin").' - '.T_('Edit entry'), true, $logged_in, $player_row );

     $show_list = false;

     echo "<center>\n";

     $result = mysql_query(
        "SELECT FAQ.*, Answer.Text AS A, Question.Text AS Q, Question.Translatable ".
        "FROM FAQ, TranslationTexts AS Answer " .
        "LEFT JOIN TranslationTexts AS Question ON Question.ID=FAQ.Question " .
        "WHERE FAQ.ID='$id' AND Answer.ID=FAQ.Answer" );

     if( mysql_num_rows($result) != 1 )
        error("admin_no_such_entry");

     $row = mysql_fetch_array( $result );

     $faq_edit_form = new Form( 'faqeditform', "admin_faq.php?do_edit=t&id=$id", FORM_POST );

     if( $row["Level"] == 1 )
     {
        $faq_edit_form->add_row( array( 'HEADER', T_('Edit category') ) );
        $faq_edit_form->add_row( array( 'DESCRIPTION', T_('Category'),
                                        'TEXTINPUT', 'question', 80, 80, $row["Q"] ) );
     }
     else
     {
        $faq_edit_form->add_row( array( 'HEADER', T_('Edit FAQ entry') ) );
        $faq_edit_form->add_row( array( 'DESCRIPTION', T_('Question'),
                                        'TEXTINPUT', 'question', 80, 80, $row["Q"] ) );
        $faq_edit_form->add_row( array( 'DESCRIPTION', T_('Answer'),
                                        'TEXTAREA', 'answer', 80, 20, $row["A"] ) );
     }

     $faq_edit_form->add_row( array( 'SUBMITBUTTON', 'submit', T_('Submit') ) );
     $faq_edit_form->echo_string();

  }


  // ***********        Move entry       ****************

  else if( $_GET["move"] == 'u' or $_GET["move"] == 'd' )
  {
     $result = mysql_query( "SELECT * FROM FAQ WHERE ID=$id" );

     if( mysql_num_rows($result) != 1 )
        error("admin_no_such_entry");

     $row = mysql_fetch_array( $result );

     $result = mysql_query( "SELECT MAX(SortOrder) as max FROM FAQ " .
                            "WHERE Parent=" . $row["Parent"] );
     $row2 = mysql_fetch_array( $result );
     $max = $row2["max"];

     if( ( $_GET["move"] != 'u' or $row["SortOrder"] > 1 ) and
         ( $_GET["move"] != 'd' or $row["SortOrder"] < $max ) )
     {
        $dir = ($_GET["move"] == 'd' ? 1 : -1 );

        mysql_query( "UPDATE FAQ SET SortOrder=SortOrder-($dir) " .
                     'WHERE Parent=' . $row["Parent"] . ' ' .
                     'AND SortOrder=' . ($row["SortOrder"]+$dir) );
        mysql_query( "UPDATE FAQ SET SortOrder=SortOrder+($dir) " .
                     "WHERE ID=" . $row["ID"] );
     }
     jump_to("admin_faq.php");
  }



  // ***********        Save edited entry       ****************

  else if( $_GET["do_edit"] == 't' )
  {
     $result = mysql_query( "SELECT *,Translatable FROM FAQ, TranslationTexts " .
                            "WHERE FAQ.ID=$id AND TranslationTexts.ID=FAQ.Question" );

     if( mysql_num_rows($result) != 1 )
        error("admin_no_such_entry");

     $row = mysql_fetch_array( $result );

     if( !isset( $_POST["question"] ) )
        error("No data");

     $question = trim( $_POST["question"] );
     $answer = trim( $_POST["answer"] );

     // Delete or update ?
     if( empty($question) and empty($answer) and $row["Translatable"] != 'Done' and
         ($row["Level"] == 2 or
          mysql_num_rows(mysql_query("SELECT ID FROM FAQ WHERE Parent=$id LIMIT 1")) == 0 ))
     {
        mysql_query("DELETE FROM FAQ WHERE ID=$id LIMIT 1");
        mysql_query("UPDATE FAQ SET SortOrder=SortOrder-1 " .
                    "WHERE Parent=" . $row["Parent"] . " AND SortOrder>" . $row["SortOrder"]);

        mysql_query("DELETE FROM TranslationFoundInGroup " .
                    "WHERE Text_ID='" . $row['Question'] . "' " .
                    "OR Text_ID='" . $row['Answer'] . "'");
        mysql_query("DELETE FROM TranslationTexts " .
                    "WHERE ID='" . $row['Question'] . "' OR ID='" . $row['Answer'] . "'");
     }
     else
     {
        mysql_query("UPDATE TranslationTexts SET Text=\"$question\" " .
                    "WHERE ID=" . $row['Question'] . " LIMIT 1");

        if( $row['Level'] == 2 )
           mysql_query("UPDATE TranslationTexts SET Text=\"$answer\" " .
                       "WHERE ID=" . $row['Answer'] . " LIMIT 1");

        mysql_query("INSERT INTO FAQlog SET uid=" . $player_row["ID"] . ", FAQID=$id, " .
                    "Question=\"$question\", Answer=\"$answer\"");
     }

     if( $row['Translatable'] !== 'N' )
        make_include_files(null, 'FAQ');

     jump_to("admin_faq.php");
  }



  // ***********        New entry       ****************

  else if( $_GET["new"] == 'e' or $_GET["new"] == 'c')
  {
     if( $_GET["new"] == 'c' )
        start_page(T_("FAQ Admin").' - '.T_('New category'), true, $logged_in, $player_row );
     else
        start_page(T_("FAQ Admin").' - '.T_('New entry'), true, $logged_in, $player_row );

     $show_list = false;

     echo "<center>\n";

     $faq_edit_form = new Form( 'faqnewform', "admin_faq.php?do_new=" .
                                $_GET["new"] . "&id=$id", FORM_POST );

     if( $_GET["new"] == 'c' )
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

     $faq_edit_form->add_row( array( 'SUBMITBUTTON', 'submit', T_('Submit') ) );
     $faq_edit_form->echo_string();
  }


  // ***********        Save new entry       ****************

  else if( $_GET["do_new"] == 'c' or $_GET["do_new"] == 'e' )
  {
     $result = mysql_query( "SELECT * FROM FAQ WHERE ID=$id" );

     if( mysql_num_rows($result) != 1 )
        error("admin_no_such_entry");

     $row = mysql_fetch_array( $result );

     // First entry
     if( $row["Level"] == 1 and $_GET["do_new"] == 'e' )
        $row = array("Parent" => $row["ID"], "SortOrder" => 0, "Level" => 2);

     // First category
     if( $row["Level"] == 0 )
        $row = array("Parent" => $row["ID"], "SortOrder" => 0, "Level" => 1);

     if( !isset( $_POST["question"] ) )
        error("No data");

     $question = trim( $_POST["question"] );
     $answer = trim( $_POST["answer"] );

     $FAQ_group = get_faq_group();

     if( !empty($question) or !empty($answer))
     {
        mysql_query("UPDATE FAQ SET SortOrder=SortOrder+1 " .
                    'WHERE Parent=' . $row["Parent"] . ' ' .
                    'AND SortOrder>' . $row["SortOrder"] ) or die(mysql_error());

        mysql_query("INSERT INTO FAQ SET " .
                    "SortOrder=" . ($row["SortOrder"]+1) . ', ' .
                    "Parent=" . $row["Parent"] . ', ' .
                    "Level=" . $row["Level"] ) or die(mysql_error());

        $faq_id = mysql_insert_id();
        mysql_query("INSERT INTO FAQlog SET uid=" . $player_row["ID"] . ', ' .
                    "FAQID=$faq_id," .
                    "Question=\"$question\", Answer=\"$answer\"");

        mysql_query( "INSERT INTO TranslationTexts SET Text=\"$question\", " .
                     "Ref_ID=$faq_id, Translatable = 'N' " )
           or die(mysql_error());

        $q_id =  mysql_insert_id();
        $a_id = 'NULL';
        mysql_query("INSERT INTO TranslationFoundInGroup " .
                    "SET Text_ID=$q_id, Group_ID=$FAQ_group" )
           or die(mysql_error());

        if( $row['Level'] != 1 )
        {
           mysql_query( "INSERT INTO TranslationTexts SET Text=\"$answer\", " .
                        "Ref_ID=$faq_id, Translatable = 'N' " )
              or die(mysql_error());

         $a_id =  mysql_insert_id();
         mysql_query("INSERT INTO TranslationFoundInGroup " .
                     "SET Text_ID=$a_id, Group_ID=$FAQ_group" )
            or die(mysql_error());
        }


        mysql_query( "UPDATE FAQ SET Answer=$a_id, Question=$q_id WHERE ID=$faq_id" )
           or die(mysql_error());

     }

     jump_to("admin_faq.php");
  }


  // ***********       Toggle translatable     ****************

  if( $_GET["transl"] === 't' )
  {
     $result = mysql_query(
        "SELECT FAQ.*, Answer.Text AS A, Question.Text AS Q, Question.Translatable ".
        "FROM FAQ, TranslationTexts AS Question " .
        "LEFT JOIN TranslationTexts AS Answer ON Answer.ID=FAQ.Answer " .
        "WHERE FAQ.ID='$id' AND Question.ID=FAQ.Question" )
        or die(mysql_error());

     if( mysql_num_rows($result) != 1 )
        error("admin_no_such_entry");

     $row = mysql_fetch_array( $result );

     $FAQ_group = get_faq_group();

     if( $row['Translatable'] == 'Done' )
        error('admin_already_translated');
     else
     {
        $query = "UPDATE TranslationTexts " .
                    "SET Translatable='" . ($row['Translatable'] == 'Y' ? 'N' : 'Y' ) . "' " .
                    "WHERE ID=" . $row['Question'] .
           ( $row['Level'] == 1 ? ' LIMIT 1' : " OR ID=" . $row['Answer'] . " LIMIT 2" );

        mysql_query( $query ) or die(mysql_error());
     }

     make_include_files(null, 'FAQ');
  }



  // ***********       Show FAQ list       ****************

  if( $show_list )
  {
     start_page(T_("FAQ Admin"), true, $logged_in, $player_row );

     echo "<table align=center width=\"85%\" border=0><tr><td>\n";

     echo "<h3 align=left><a name=\"general\"></a><font color=$h3_color>" .
        T_('FAQ Admin') . "</font></h3>\n";


     $result = mysql_query(
        "SELECT entry.*, Question.Text AS Q, Question.Translatable, " .
        "IF(entry.Level=1,entry.SortOrder,parent.SortOrder) AS CatOrder " .
        "FROM FAQ AS entry, FAQ AS parent, TranslationTexts AS Question " .
        "WHERE entry.Parent = parent.ID AND Question.ID=entry.Question " .
        "AND entry.Level<3 AND entry.Level>0 " .
        "ORDER BY CatOrder,entry.Level,entry.SortOrder")
        or die(mysql_error());


     echo "<table>\n";

     echo "<tr><td><a href=\"admin_faq.php?new=c&id=1" . '"><img border=0 title="' .
        T_('Add new category') . '" src="images/new.png"></a>';

     while( $row = mysql_fetch_array( $result ) )
     {
        $question = (empty($row['Q']) ? '-' : $row['Q']);

        if( $row['Level'] == 1 )
        {
           echo '<tr><td colspan=2>';
           $typechar = 'c';
        }
        else
        {
           echo '<tr><td width=20><td>';
           $typechar = 'e';
        }

        echo "<A href=\"admin_faq.php?edit=$typechar&id=" . $row['ID'] .
           '" title="' . T_("Edit") . "\">$question</A>\n";

        echo '<td width=40 align=right><a href="admin_faq.php?move=u&id=' .
           $row['ID'] . '"><img border=0 title="' . T_("Move up") . '" src="images/up.png"></a>';
        echo '<td><a href="admin_faq.php?move=d&id=' .
           $row['ID'] . '"><img border=0 title="' . T_("Move down") . '" src="images/down.png"></a>';

        echo "<td><a href=\"admin_faq.php?new=$typechar&id=" . $row['ID'] .
           '"><img border=0 title="' .
           ($typechar == 'e' ? T_('Add new entry') : T_('Add new category')) .
           '" src="images/new.png"></a>';

        $transl = $row['Translatable'];
        if( $transl !== 'Done' )
           echo "<td><a href=\"admin_faq.php?transl=t&id=" . $row['ID'] .
           '"><img border=0 title="' .
           ($transl == 'Y' ? T_('Make untranslatable') : T_('Make translatable')) .
           '" src="images/transl' . ( $transl == 'Y' ? '' : '_no' ) . '.png"></a>';


        if( $row["Level"] == 1 )
           echo "<tr><td witdh=20><td><a href=\"admin_faq.php?new=e&id=" .
              $row['ID'] . '"><img border=0 title="' . T_('Add new entry') .
              '" src="images/new.png"></a>';
     }


     echo "</table></table>\n";
  }

  end_page();
}
?>
