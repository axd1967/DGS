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

chdir( '../' );
require_once( "include/std_functions.php" );
require_once( "include/table_columns.php" );
require_once( "include/form_functions.php" );

{
   disable_cache();

   connect2mysql();

  $logged_in = who_is_logged( $player_row);

  if( !$logged_in )
    error("not_logged_in");

  $player_level = (int)$player_row['admin_level'];
  if( !($player_level & ADMIN_ADMINS) )
    error("adminlevel_too_low");


   $encoding_used= get_request_arg( 'charset', 'iso-8859-1'); //iso-8859-1 UTF-8

   $Email= get_request_arg( 'email', '');
   $From= get_request_arg( 'from', $EMAIL_FROM);
   $Subject= get_request_arg( 'subject', 'Mail test');
   $Text= "This is a test\nLine 2\nLine 3\n";
   $sendit= @$_REQUEST['sendit'];


   start_html( 'mailtest', 0,
      "  pre { background: #c0c0c0; }" );

   $dform = new Form('dform', 'mailtest.php', FORM_POST, true );

   $dform->add_row( array(
      'DESCRIPTION', 'To',
      'TEXTINPUT', 'email', 80, 100, $Email,
      ) );
   $dform->add_row( array(
      'DESCRIPTION', 'From',
      'TEXTINPUT', 'from', 80, 100, $From,
      ) );
   $dform->add_row( array(
      'DESCRIPTION', 'Subject',
      'TEXTINPUT', 'subject', 80, 100, $Subject,
      ) );
   $dform->add_row( array(
      'DESCRIPTION', 'Text',
      'CELL', 1, '',
      'OWNHTML', "<pre>$Text</pre>",
      ) );
   $dform->add_row( array(
      'CELL', 9, 'align="center"',
      'HIDDEN', 'charset', $encoding_used,
      'OWNHTML', '<INPUT type="submit" name="sendit" accesskey="s" value="S-end it">',
      ) );

   $dform->echo_string(1);


   if( !function_exists('mail') )
   {
      echo "<br>mail() function not found.<br>";
   }
   else if( $sendit && $Email )
   {
      $headers = "From: $From\n";

      $msg = str_pad('', 47, '-') . "\n" .
          "Date: ".date($date_fmt, $NOW) . "\n" .
          "From: DragonGo admin staff\n" .
          "Subject: ".strip_tags( $Subject, '') . "\n\n" .
          strip_tags( $Text, '') . "\n";

      $res= mail( trim($Email), 'Dragon Go Server mail test', $msg, $headers );
      if( !$res )
      {
         echo "<br>mail() function failed.<br>";
      }
      else
      {
         echo "<br>Message sent to $Email:<br>";
         echo "<pre>$msg</pre>";
      }
   }

   end_html();
}
?>