<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Jim Heiney, Erik Ouchterlony and Rod Ival

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

require_once( "include/std_functions.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   $ThePage['class']= 'Links'; //temporary solution to CSS problem
   start_page(T_('Links'), true, $logged_in, $player_row
      , "a.blue:visited{color:purple;}" );

/* see also:
http://www.usgo.org
http://linkexplorer.net/go/go.html
http://news.world-go.org/
Go News, Go Scene (2000-2003): http://www.kyoto.zaq.ne.jp/momoyama/index.html
*/


   section( 'General', T_('General Info'));

   add_link_page_link("http://senseis.xmp.net",
                     T_('Sensei\'s Library'),
                     T_('A collaboration web site. Read and contribute!'));
   add_link_page_link("http://gobase.org",
                     T_('Jan van der Steens Pages'), T_('Lots of info on go'));
   add_link_page_link("http://www.gogod.co.uk/",
                     T_('GoGoD database and encyclopaedia'), T_('A large database of professional games'));
   add_link_page_link("http://senseis.xmp.net/?RGGFAQ",
                     T_('Go FAQ'),
                     T_('Frequently asked questions about go for the rec.games.go newsgroup'));
   add_link_page_link("http://www.intgofed.org/members.htm",
                     'IGF',
                     T_('International Go Federation. Find federations and associations in the world.'));
   //  add_link_page_link("http://www.msoworld.com/mindzine/news/orient/go/go.html",
   //                     T_//('Mind Sport Zine'),
   //                     T_//('An excellent, but unfortunately no longer updated site.'));
   add_link_page_link("http://igo-kisen.hp.infoseek.co.jp/topics.html",
                     T_('Go News'), T_('News and games from the professional scene'));
   add_link_page_link("http://finance.baylor.edu/rich/go/goguild.html",
                     T_('Turn-based go guild'), T_('Meet other turn-based go players'));
   add_link_page_link("http://senseis.xmp.net/?HikaruNoGo",
                     T_('Hikaru no Go'), T_('A manga about go. Recommended!'));

   add_link_page_link();


   section( 'Rules', T_('Rules'));

   add_link_page_link("http://playgo.to/interactive/index.html",
                     T_('An Interactive Introduction'),
                     T_('This is a very nice site to learn with.'));
   add_link_page_link("http://www.britgo.org/intro/intro1.html",
                     T_('Introduction'),
                     T_('Very well written introduction by the British Go Association.'));
   add_link_page_link("http://sentex.net/~mmcadams/teachgo/index.html",
                     T_('How to Teach Go'),
                     T_('This is all you need to get started. Very basic stuff'));

   add_link_page_link();


   section( 'Strategy', T_('Strategy and terms'));

   add_link_page_link("http://www.goproblems.com/",
                     T_('Go Problems'), T_('Working through these can help out your game.'));
   add_link_page_link("http://gtl.jeudego.org/",
                     T_('Go Teaching Ladder'),
                     T_('Submit your games for comments to see where you might have played better.'));
   add_link_page_link("http://www.yomiuri.co.jp/dy/columns/0001/",
                     T_('The Magic of Go'), T_('A weekly go column by Rob Van Zeijst.'));
   add_link_page_link("http://senseis.xmp.net/?EssentialGoTerms",
                     T_('Common Japanese Go Terms'),
                     T_('You have to know what other players are talking about.'));

   add_link_page_link();


   section( 'History', T_('History'));

   add_link_page_link("http://www.britgo.org/intro/intro1.html#bh",
                     T_('A Brief History'),
                     T_('For you people with short attention spans.'));
   add_link_page_link("http://gobase.org/information/history/",
                     T_('The Extended History'),
                     T_('In case you\'re an aspiring know-it-all.'));

   add_link_page_link();


   section( 'Equipment', T_('Go books, equipment and software'));

   add_link_page_link("http://www.gobooks.info/",
                     T_('Annotated Go Bibliographies'),
                     T_('A large collection of go book reviews'));
   add_link_page_link("http://www.slateandshell.com/", 'Slate & Shell');
   add_link_page_link("http://www.kiseido.com/", 'Kiseido');
   add_link_page_link("http://www.yutopian.com/go/", 'Yutopian');
   add_link_page_link("http://www.samarkand.net/", 'Samarkand');
   add_link_page_link("http://www.schaakengo.nl/", 'Het Paard', T_('European shop'));

   add_link_page_link();


   section( 'Servers', T_('Other go servers'));

   add_link_page_link("http://www.itsyourturn.com/",
                     'It\'s your turn',
                     T_('Also turn based. Has several other games.'));
   add_link_page_link("http://www.online-go.com/",
                     'OGS',
                     T_('Another turn based. Focus on organised tournament play.'));
   add_link_page_link("http://www.gokgs.com/",
                     'KGS',
                     T_('Kiseido Go Server with java interface'));
   add_link_page_link("http://www.pandanet.co.jp/English/",
                     'IGS', T_('A large server for realtime play'));
   add_link_page_link("http://senseis.xmp.net/?GoServers",
                     T_('Server list'), T_('A more complete list of servers'));

   add_link_page_link();

   end_page();
}

?>
