<?php
/*
Dragon Go Server
Copyright (C) 2001 Jim Heiney and Erik Ouchterlony

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

require( "include/std_functions.php" );

connect2mysql();

$logged_in = is_logged_in($handle, $sessioncode, $player_row);

start_page("Links", true, $logged_in, $player_row );

?>


<table align=center width="85%"><tr><td>

<h3 align=left><a name="general"></a><font color="#800000">General Info</font> </h3>



<p><a href="http://gobase.org">Jan van der Steens Pages</a> --- Lots of info on go 

<p><a href="http://www.igoweb.org/~pahle/go-faq/index.html">Go FAQ</a> --- Frequently asked questions about go for the rec.games.go newsgroup

<p><a href="http://www.msoworld.com/mindzine/news/orient/go/go.html">Mind Sport Zine</a> --- An excellent, but unfortunately no longer updated site. 

<p><a href="http://senseis.xmp.net">Sensei's Library</a> --- A collaboration web site. Read and contribute!  

<p><a href="http://www.kyoto.zaq.ne.jp/momoyama/news/news.html">Go News</a> --- News and games from the professional scene 

<p><a href="http://finance.baylor.edu/rich/go/iyt_go.html">IYT go guild</a> --- Meet other turn based go players 

<p><a href="http://www.toriyamaworld.com/hikaru.html">Hikaru no Go</a> --- A manga about go. Recommended! 



<p>&nbsp;

<h3 align=left><a name="rules"></a><font color="#800000">Rules</font></h3>

<p><a href="http://playgo.to/interactive/index.html">An Interactive
    Introduction</a> --- This is a very nice site to learn with.

<p><a href="http://www.britgo.org/intro/intro1.html">Introduction</a> --- Very well written introduction by the British Go Association.



<p><a href="http://sentex.net/~mmcadams/teachgo/index.html">How to
    Teach Go</a> --- This is all you need to get started.&nbsp; Very basic stuff

<p><a href="http://home.earthlink.net/~scotmc/">Scot's Go Page</a>
  --- This is more in-depth.

<p>&nbsp;



<h3 align=left><font color="#800000"><a name="strategy"></a>Strategy &amp; Terms</font>&nbsp;</h3>

<p><a href="http://www.igoweb.org/~pahle/go-stuff/shape.html">An
    Introduction to Shape</a>


<p><a href="http://www.goproblems.com/">Go Problems</a> --- Working
  through these can help out your game.

<p><a href="http://gtl.jeudego.org/">Go Teaching Ladder</a> --
  Submit your games for comments to see where you might have played better.

<p><a href="http://nngs.cosmic.org/hmkw/stuff/definitions.html">Common Japanese Go Terms</a>
  --- You have to know what other players are talking about.

<p><a href="http://www.algonet.se/~palund/glossary/term_000.htm">More Japanese Go Terms</a> --- Translated and explained.

<p>&nbsp;



<h3 align=left><font color="#800000"><a name="history"></a>History</font></h3>

<p><a href="http://www.britgo.org/intro/intro1.html#bh">A Brief
    History</a> --- For you people with short attention spans.
<p><a href="http://www.cwi.nl/~jansteen/go/history/">The Extended
    History</a> --- In case you're an aspiring know-it-all.

<p>&nbsp;



<h3 align=left><font color="#800000"><a name="stuff"></a>Go Books, Equipment &amp; Software</font></h3>
<p><a href="http://www.kiseido.com/">Kiseido</a>
<p><a href="http://www.yutopian.com/go/">Yutopian</a>
<p><a href="http://www.samarkand.net/">Samarkand</a>
<p><a href="http://www.xs4all.nl/~paard//">Het Paard</a> --- European shop

<p>&nbsp;



<h3 align=left><font color="#800000"><a name="servers"></a>Other go servers</font></h3>
<p><a href="http://www.itsyourturn.com/">It's your turn</a> --- Also turn based. Has several other games.
<p><a href="http://kgs.kiseido.com">Kiseido Go Server</a> --- Server with java interface
<p><a href="http://panda-igs.joyjoy.net/English/contents.html">IGS</a> --- A large server for realtime play
<p><a href="http://nngs.cosmic.org">NNGS</a> --- An open sourced go server
<p><a href="http://www.britgo.org/gopcres/play.html">Server list</a> --- A more complete list of servers

</td></tr></table>

<?php
end_page();
?>
