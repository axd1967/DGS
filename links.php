<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Jim Heiney, Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Docs";

require_once 'include/std_functions.php';
require_once 'include/admin_faq_functions.php';

$GLOBALS['ThePage'] = new Page('Links');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   start_page(T_('Links'), true, $logged_in, $player_row, "a.blue:visited{color:purple;}" );
   echo "<h3 class=Header>", T_('Link collection'), "</h3>\n";

   // init
   $TW_ = 'T_'; // for non-const translation-texts
   $link_class = 'DocLinkNarrow';

   // show Links from database, or static links if no entries found in db
   if( !load_links() )
   {
      ta_begin();
      {//HOT-section to seed database with link-texts
         make_static_links( true );
      }
      ta_end();

      make_static_links();
   }

   end_page();
}//main


function make_static_links( $create=false )
{
   if( $create )
   {
      $sect = 'save_link_section';
      $link = 'save_link_entry';
      $linkend = 'nop';
   }
   else
   {
      $sect = '_section';
      $link = '_add_link_page_link';
      $linkend = 'add_link_page_link';
   }

   $sect( 'General', 'General Info');

   $link("http://senseis.xmp.net", 'Sensei\'s Library', 'A collaboration web site. Read and contribute!');
   $link("http://gobase.org", 'Jan van der Steens Pages', 'Lots of info on go');
   $link("http://www.gogod.co.uk/", 'GoGoD database and encyclopaedia', 'A large database of professional games');
   $link("http://senseis.xmp.net/?RGGFAQ", 'Go FAQ',
         'Frequently asked questions about go for the rec.games.go newsgroup');
   $link('http://www.intergofed.org/members.htm', 'IGF',
         'International Go Federation. Find federations and associations in the world.');
   $link("http://go-news.blogspot.com/", 'Go News', 'World news about game of Go');
   $link('http://gosensations.com/', 'Go Sensations', 'Go News and Sensations from other Go servers');
   $link("http://senseis.xmp.net/?HikaruNoGo", 'Hikaru no Go', 'A manga about go. Recommended!');
   $linkend();


   $sect( 'Rules', 'Rules');

   $link("http://playgo.to/interactive/index.html", 'An Interactive Introduction',
         'This is a very nice site to learn with.');
   $link("http://www.britgo.org/intro/intro1.html", 'Introduction',
         'Very well written introduction by the British Go Association.');
   $link("http://www.pandanet.co.jp/English/introduction_of_go/", 'Introduction to Go', 'You can master Go in 10 days');
   $linkend();


   $sect( 'Strategy', 'Strategy and terms');

   $link("http://www.goproblems.com/", 'Go Problems', 'Working through these can help out your game.');
   $link("http://gtl.jeudego.org/", 'Go Teaching Ladder',
         'Submit your games for comments to see where you might have played better.');
   $link("http://www.yomiuri.co.jp/dy/columns/0001/", 'The Magic of Go', 'A weekly go column by Rob Van Zeijst.');
   $link("http://senseis.xmp.net/?EssentialGoTerms", 'Common Japanese Go Terms',
         'You have to know what other players are talking about.');
   $linkend();


   $sect( 'History', 'History');

   $link("http://www.britgo.org/intro/intro1.html#bh", 'A Brief History', 'For you people with short attention spans.');
   $link("http://gobase.org/information/history/", 'The Extended History', 'In case you\'re an aspiring know-it-all.');
   $linkend();


   $sect( 'Equipment', 'Go books, equipment and software');

   $link("http://www.gobooks.info/", 'Annotated Go Bibliographies', 'A large collection of go book reviews');
   $link("http://www.slateandshell.com/", 'Slate & Shell');
   $link("http://www.kiseido.com/", 'Kiseido');
   $link("http://www.yutopian.com/go/", 'Yutopian');
   $link("http://www.schaakengo.nl/", 'Het Paard', 'European shop');
   $linkend();


   $sect( 'Servers', 'Other go servers');

   $link("http://www.itsyourturn.com/", 'IYT - It\'s your turn', 'Also turn based. Has several other games.');
   $link("http://www.online-go.com/", 'OGS', 'Another turn based. Focus on organised tournament play.');
   $link("http://www.gokgs.com/", 'KGS', 'Kiseido Go Server with java interface');
   $link("http://www.pandanet.co.jp/English/", 'IGS', 'A large server for realtime play');
   $link("http://senseis.xmp.net/?GoServers", 'Server list', 'A more complete list of servers');
   $linkend();
}//make_static_links

function _section( $id, $header )
{
   global $TW_;
   section( $id, $TW_($header) );
}//_section

function _add_link_page_link( $link=false, $linkdesc='', $extra='' )
{
   global $TW_;
   add_link_page_link( $link, $TW_($linkdesc), $TW_($extra) );
}//_add_link_page_link


function nop()
{
}

function save_link_section( $arg, $title )
{
   global $f;

   //save_new_faq_entry(dbgmsg,dbtable,tr_group,fid,is_cat,Q,A,Ref, append,translatable,log,chk_mode)
   $f = AdminFAQ::save_new_faq_entry( 'links.save_link_section', 'Links', 'Docs', 1, true,
      $title, '', '', true, 'Y', false, 1 );
}

function save_link_entry( $url='', $text='', $extra='' )
{
   global $f;

   //save_new_faq_entry(dbgmsg,dbtable,tr_group,fid,is_cat,Q,A,Ref, append,translatable,log,chk_mode)
   AdminFAQ::save_new_faq_entry( 'links.save_link_entry', 'Links', 'Docs', /*parent*/$f, false,
      $text, $extra, $url, true, 'Y', false, 2 );
}

function load_links()
{ // show only faq-titles
   global $TW_;

   $result = db_query( 'links.load_links',
      "SELECT entry.Level, entry.SortOrder, entry.Reference, " .
         "Question.Text AS Q, Answer.Text AS A, " .
         "IF(entry.Level=1, entry.SortOrder, parent.SortOrder) AS CatOrder " .
      "FROM Links AS entry " .
         "INNER JOIN Links AS parent ON parent.ID=entry.Parent " .
         "INNER JOIN TranslationTexts AS Question ON Question.ID=entry.Question " .
         "LEFT JOIN TranslationTexts AS Answer ON Answer.ID=entry.Answer " .
      "WHERE (entry.Level BETWEEN 1 AND 2) " .
         "AND entry.Hidden='N' AND parent.Hidden='N'" . //need a viewable root
      "ORDER BY CatOrder, entry.Level, entry.SortOrder" );

   $last_level = 0;
   while( $row = mysql_fetch_assoc($result) )
   {
      if( $last_level > 0 && $row['Level'] != $last_level )
         add_link_page_link();

      if( $row['Level'] == 1 ) // section
         section( 'LinkTitle'.$row['SortOrder'], $TW_($row['Q']) );
      elseif( $row['Level'] == 2 ) // link-entry
         add_link_page_link( $row['Reference'], $TW_($row['Q']), $TW_($row['A']) );

      $last_level = $row['Level'];
   }
   if( $last_level > 0 )
      add_link_page_link();
   mysql_free_result($result);

   return (bool)$last_level;
}//load_links

?>
