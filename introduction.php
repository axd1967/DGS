<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Intro";

require_once 'include/std_functions.php';
require_once 'include/admin_faq_functions.php';

$GLOBALS['ThePage'] = new Page('Intro', 0, ROBOTS_NO_FOLLOW, DGS_DESCRIPTION );


{
   connect2mysql();
   $logged_in = who_is_logged($player_row);

   start_page(T_('Introduction'), true, $logged_in, $player_row );

   // show Intro from database, or static intro if no entries found in db
   if ( !load_intro() )
      show_static_intro();

   end_page();
}//main


function show_static_intro()
{
   section('Intro', T_('Introduction to Dragon') );

   echo sprintf( T_("Welcome to %s, a %sfree%s server for playing %sgo%s, where the games tends to 'drag on'."),
          FRIENDLY_LONG_NAME, '<a href="licence.php">', '</a>', '<a href="links.php">', '</a>' );

   echo "<p></p>\n" ;

   echo T_("You can look at it as kind of play-by-email, " .
          "where a web-interface is used to make the board look prettier." .
          " To start playing you should first get yourself an " .
          "<a href=\"register.php\">account</a>, if you haven't got one already. " .
          "Thereafter you could <a href=\"edit_profile.php\">edit your profile</a> " .
          "and <a href=\"edit_bio.php\">enter some biographical info</a>, especially " .
          "the fields 'Open for matches?', 'Rating' and 'Rank info' are useful for " .
          "finding opponents. Next you can study the <a href=\"users.php\">user list</a> " .
          "and use the <a href=\"forum/index.php\">forums</a> to find suitable opponents " .
          "to <a href=\"message.php?mode=Invite\">invite</a> for a game.") . "\n";

   echo "<p></p>\n";

   echo T_('More information can be found in the <a href="/faq.php">FAQ</a>.'
      . ' When you have questions you are also encouraged to submit them in one'
      . ' of the <a href="/forum/index.php">forums</a>.')
      , "<p></p>\n"
      , T_("Once again welcome, and enjoy your visit here!") . "\n";
}//show_static_intro

function load_intro()
{
   $TW_ = 'T_'; // for non-const translation-texts

   $result = db_query( 'intro.load_intro',
      "SELECT entry.Level, entry.SortOrder, entry.Reference, " .
         "Question.Text AS Q, Answer.Text AS A, " .
         "IF(entry.Level=1, entry.SortOrder, parent.SortOrder) AS CatOrder " .
      "FROM Intro AS entry " .
         "INNER JOIN Intro AS parent ON parent.ID=entry.Parent " .
         "INNER JOIN TranslationTexts AS Question ON Question.ID=entry.Question " .
         "LEFT JOIN TranslationTexts AS Answer ON Answer.ID=entry.Answer " .
      "WHERE (entry.Level BETWEEN 1 AND 2) " .
         "AND entry.Hidden='N' AND parent.Hidden='N'" . //need a viewable root
      "ORDER BY CatOrder, entry.Level, entry.SortOrder" );

   $last_level = 0;
   while ( $row = mysql_fetch_assoc($result) )
   {
      if ( $row['Level'] == 1 ) // section
      {
         if ( $last_level > 0 )
            echo "</dl>\n";
         section( 'IntroTitle'.$row['SortOrder'], $TW_($row['Q']) );
         echo "<dl>\n";
      }
      elseif ( $row['Level'] == 2 ) // link-entry
         echo "<dt>", $TW_($row['Q']), "</dt>\n<dd>", $TW_($row['A']), "</dd>\n";

      $last_level = $row['Level'];
   }
   if ( $last_level > 0 )
      echo "</dl>\n";
   mysql_free_result($result);

   return (bool)$last_level;
}//load_intro

?>
