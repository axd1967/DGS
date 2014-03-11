<?php

/*
Dragon Go Server
Copyright (C) 2001-2014  Erik Ouchterlony

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

exit; ## for safety, as it's not clear if this (old) scripts still works

/* The code in this file is written by Ragnar Ouchterlony */

require_once 'include/std_functions.php';


function edit_file($file, $from_str, $to_str)
{
   $fd = fopen($file,'r') or die();
   $size = filesize ($file);
   $contents = fread ($fd, $size) or die();
   fclose($fd);

   $contents = str_replace($from_str, $to_str, $contents);

   $fd = fopen($file,'w+') or die();
   fwrite($fd, $contents) or die();
   fclose($fd);

   @chmod($file, decoct(666));
}


function edit_all_langs($from_str, $to_str)
{
   edit_file("cs_iso_8859_2.php", $from_str, $to_str);
   edit_file("de_iso_8859_1.php", $from_str, $to_str);
   edit_file("en_iso_8859_1.php", $from_str, $to_str);
   edit_file("es_iso_8859_1.php", $from_str, $to_str);
   edit_file("fr_iso_8859_1.php", $from_str, $to_str);
   edit_file("no_iso_8859_1.php", $from_str, $to_str);
   edit_file("pt_iso_8859_1.php", $from_str, $to_str);
   edit_file("sv_iso_8859_1.php", $from_str, $to_str);
   edit_file("zh_big5.php", $from_str, $to_str);
   edit_file("zh_gb2312.php", $from_str, $to_str);
}

{
   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if ( !$logged_in )
     error('not_logged_in', 'scripts.obsolete.lang_edit');

   if ( $player_row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'scripts.obsolete.lang_edit');
   if ( !(@$player_row['Adminlevel'] & ADMIN_DEVELOPER) )
     error("adminlevel_too_low", 'scripts.obsolete.lang_edit');

   $from_str = "once a week";
   $to_str = "once a month";

   chdir('translations');

   edit_all_langs('once a week', 'once a month');
   edit_all_langs('invite.php', 'message.php?mode=Invite');
   edit_all_langs('<a href=\"licence.php\">free</a>server', '<a href=\"licence.php\">free</a> server');

}
