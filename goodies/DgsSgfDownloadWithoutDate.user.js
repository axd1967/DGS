// ==UserScript==
// @name        Dragon Go Server SGF filenames without date
// @namespace   http://www.dragongoserver.net/goodies
// @description SGF files downloaded from dragongoserver.net will have the following filename by default: "white_player-black_player-game_id.sgf"
// @match       http://*.dragongoserver.net/*
// @grant       none
// @version     1.0
// ==/UserScript==

/* <scriptinfos>
 Creator:
   admiralnlson
      http://www.dragongoserver.net/userinfo.php?uid=80346

 Version 0.1.0.20130820: admiralnlson
   first version
</scriptinfos> */

for (var i=0,link; (link=document.links[i]); i++)
{
  link.href = link.href.replace('/sgf.php?', '/sgf.php?filefmt=$w-$b-$g&');
}

