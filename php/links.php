<?php
/*
Dragon Go Server
Copyright (C) 2001  Jim Heiney and Erik Ouchterlony

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

header ("Cache-Control: no-cache, must-revalidate, max_age=0"); 

require( "include/std_functions.php" );

connect2mysql();

$logged_in = is_logged_in($handle, $sessioncode, $player_row);

start_page("Links", true, $logged_in, $player_row );

?>

<p align="left">&nbsp;</p>
<p align="left">&nbsp;</p>
<h3>
  <CENTER><a href="#rules">Rules of the Game</a> |
    <a href="#Strat"> Strategy &amp; Terms</a> | <a href="#history"> History</a> | <a href="http://"> Go Equipment&nbsp;&amp;&nbsp;Software</a>
  </CENTER>
</h3>
<p>&nbsp;</p>



<h3 align="left"><a name="rules"></a><font color="#800000">Rules</font> (you have to
  know how to play after all...)</h3>

<p align="left"><a href="http://playgo.to/interactive/index.html">An Interactive
    Introduction</a> -- This is a very nice site to learn with.</p>

<p align="left"><a href="http://sentex.net/~mmcadams/teachgo/index.html">How to
    Teach Go</a> -- This is all you need to get started.&nbsp; Very basic stuff</p>

<p align="left"><a href="http://home.earthlink.net/~scotmc/">Scot's Go Page</a>
  -- This is more in-depth.</p>

<p align="left">&nbsp;</p>



<h3 align="left"><font color="#800000"><a name="Strat"></a>Strategy &amp;
    Definitions</font>&nbsp;</h3>

<p align="left"><a href="http://www.igoweb.org/~pahle/go-stuff/shape.html">An
    Introduction to Shape</a></p>

<p align="left"><a href="http://nngs.cosmic.org/hmkw/stuff/definitions.html">Definitions</a>
  -- You have to know what other players are talking about.</p>

<p align="left"><a href="http://www.goproblems.com/">Go Problems</a> -- Working
  through these can help out your game.</p>

<p align="left"><a href="http://gtl.jeudego.org/">Go Teaching Ladder</a> --
  Submit your games for comments to see where you might have played better.</p>

<p align="left">&nbsp;</p>



<h3 align="left"><font color="#800000"><a name="history"></a>History</font></h3>

<p align="left"><a href="http://www.britgo.org/intro/intro1.html#bh">A Brief
    History</a> -- for you people with short attention spans.</p>
<p align="left"><a href="http://www.cwi.nl/~jansteen/go/history/">The Extended
    History</a> -- In case you're an aspiring know-it-all.</p>

<p align="left">&nbsp;</p>



<h3 align="left"><font color="#800000"><a name="stuff"></a>Go Equipment &amp;
    Software</font></h3>
<p align="left"><a href="http://www.kiseido.com/">Kiseido</a><b> -- </b>Software,
  books, lessons, etc.</p>
<p align="left"><a href="http://www.yutopian.com/go/">Yutopian</a> -- Same as
  above.&nbsp; It's just another choice.</p>
<p align="left">&nbsp;</p>




<p align="left">&nbsp;</p>

<?php
end_page();
?>
