<?php
/*
Dragon Go Server
Copyright (C) 2001-2003  Jim Heiney and Erik Ouchterlony

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

$TranslateGroups[] = "Docs";

require( "include/std_functions.php" );

{
  connect2mysql();

  $logged_in = is_logged_in($handle, $sessioncode, $player_row);

  start_page(T_("Links"), true, $logged_in, $player_row );

  echo "<table align=center width=\"85%\"><tr><td>\n";
  echo "<h3 align=left><a name=\"general\"></a><font color=$h3_color>" .
    T_('General Info') . "</font></h3>\n";

  add_link_page_link("http://gobase.org",
                     T_('Jan van der Steens Pages'), T_('Lots of info on go'));
  add_link_page_link("http://www.igoweb.org/~pahle/go-faq/index.html",
                     T_('Go FAQ'),
                     T_('Frequently asked questions about go for the rec.games.go newsgroup'));
  add_link_page_link("http://www.msoworld.com/mindzine/news/orient/go/go.html",
                     T_('Mind Sport Zine'),
                     T_('An excellent, but unfortunately no longer updated site.'));
  add_link_page_link("http://home.san.rr.com/rafgo/go.html",
                     T_('Rafael\'s Go Page'),
                     T_('Another go site with lots of useful info.'));
  add_link_page_link("http://senseis.xmp.net",
                     T_('Sensei\'s Library'),
                     T_('A collaboration web site. Read and contribute!'));
  add_link_page_link("http://www.kyoto.zaq.ne.jp/momoyama/news/news.html",
                     T_('Go News'), T_('News and games from the professional scene'));
  add_link_page_link("http://finance.baylor.edu/rich/go/goguild.html",
                     T_('Turn-based go guild'), T_('Meet other turn-based go players'));
  add_link_page_link("http://www.toriyamaworld.com/hikago",
                     T_('Hikaru no Go'), T_('A manga about go. Recommended!'));


  echo "<p>&nbsp;\n";
  echo "<h3 align=left><a name=\"rules\"></a><font color=$h3_color>" .
    T_('Rules') . "</font></h3>\n";

  add_link_page_link("http://playgo.to/interactive/index.html",
                     T_('An Interactive Introduction'),
                     T_('This is a very nice site to learn with.'));
  add_link_page_link("http://www.britgo.org/intro/intro1.html",
                     T_('Introduction'),
                     T_('Very well written introduction by the British Go Association.'));
  add_link_page_link("http://sentex.net/~mmcadams/teachgo/index.html",
                     T_('How to Teach Go'),
                     T_('This is all you need to get started. Very basic stuff'));
  add_link_page_link("http://home.earthlink.net/~scotmc/",
                     T_('Scot\'s Go Page'),
                     T_('This is more in-depth.'));

  echo "<p>&nbsp;\n";
  echo "<h3 align=left><a name=\"strategy\"></a><font color=$h3_color>" .
    T_('Strategy and terms') . "</font></h3>\n";

  add_link_page_link("http://www.igoweb.org/~pahle/go-stuff/shape.html",
                     T_('An Introduction to Shape'));
  add_link_page_link("http://www.goproblems.com/",
                     T_('Go Problems'), T_('Working through these can help out your game.'));
  add_link_page_link("http://gtl.jeudego.org/",
                     T_('Go Teaching Ladder'),
                     T_('Submit your games for comments to see where you might have played better.'));
  add_link_page_link("http://nngs.cosmic.org/hmkw/stuff/definitions.html",
                     T_('Common Japanese Go Terms'),
                     T_('You have to know what other players are talking about.'));
  add_link_page_link("http://www.gbgo.nu/GobanKen/palund/glossary/term_000.htm",
                     T_('More Japanese Go Terms'),
                     T_('Translated and explained.'));


  echo "<p>&nbsp;\n";
  echo "<h3 align=left><a name=\"history\"></a><font color=$h3_color>" .
    T_('History') . "</font></h3>\n";

  add_link_page_link("http://www.britgo.org/intro/intro1.html#bh",
                     T_('A Brief History'),
                     T_('For you people with short attention spans.'));
  add_link_page_link("http://gobase.org/information/history/",
                     T_('The Extended History'),
                     T_('In case you\'re an aspiring know-it-all.'));
  
  echo "<p>&nbsp;\n";
  echo "<h3 align=left><a name=\"stuff\"></a><font color=$h3_color>" .
    T_('Go books, equipment and software') . "</font></h3>\n";

  add_link_page_link("http://math.stanford.edu/~carlton/go/",
                     T_('Annotated Go Bibliographies'),
                     T_('A large collection of go book reviews'));
  add_link_page_link("http://www.slateandshell.com/", 'Slate & Shell');
  add_link_page_link("http://www.kiseido.com/", 'Kiseido');
  add_link_page_link("http://www.yutopian.com/go/", 'Yutopian');
  add_link_page_link("http://www.samarkand.net/", 'Samarkand');
  add_link_page_link("http://www.xs4all.nl/~paard/", 'Het Paard', T_('European shop'));

  echo "<p>&nbsp;\n";
  echo "<h3 align=left><a name=\"servers\"></a><font color=$h3_color>" .
    T_('Other go servers') . "</font></h3>\n";

  add_link_page_link("http://www.itsyourturn.com/",
                     'It\'s your turn',
                     T_('Also turn based. Has several other games.'));
  add_link_page_link("http://kgs.kiseido.com",
                     'Kiseido Go Server',
                     T_('Server with java interface'));
  add_link_page_link("http://panda-igs.joyjoy.net/English/contents.html",
                     'IGS', T_('A large server for realtime play'));
  add_link_page_link("http://nngs.cosmic.org",
                     'NNGS', T_('An open sourced go server'));
  add_link_page_link("http://www.britgo.org/gopcres/play.html",
                     T_('Server list'), T_('A more complete list of servers'));

  echo "</td></tr></table>\n";

  end_page();
}

?>
