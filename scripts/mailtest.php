<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival

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

chdir( '../' );
require_once( "include/std_functions.php" );
require_once( "include/table_columns.php" );
require_once( "include/form_functions.php" );

{
   disable_cache();

   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   if( !(@$player_row['admin_level'] & ADMIN_SUPERADMIN) )
      error('adminlevel_too_low');


   $encoding_used= get_request_arg( 'charset', 'iso-8859-1'); //iso-8859-1 UTF-8

   $sendit= @$_REQUEST['sendit'];
   $Email= trim(get_request_arg( 'email', ''));
   $From= get_request_arg( 'from', '');
   if( !$sendit && !$From )
      $From= $EMAIL_FROM;
   $Subject= get_request_arg( 'subject', 'Mail test');
   $Text = 'Mail test from '.$FRIENDLY_LONG_NAME.' - ignore it';
   $Text.= "\nLine 2\nLine 3\n";
   $Text.= str_repeat("This is a test - ", 10);
   $Text.= "\nLast line\n";


   start_html( 'mailtest', 0, '',
      "  pre { background: #c0c0c0; }" );

   echo '&nbsp;<br>';

   $dform = new Form('dform', 'mailtest.php', FORM_POST, true );

   $dform->add_row( array(
      'DESCRIPTION', 'To',
      'TEXTINPUT', 'email', 80, 100, $Email,
      'TEXT', textarea_safe(
            'user@example.com, anotheruser@example.com'
            ),
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
      'OWNHTML', '<INPUT type="submit" name="sendit" accesskey="x" value="Send it [&x]">',
      ) );

   $dform->echo_string(1);


   if( !function_exists('mail') )
   {
      echo "<br>mail() function not found.<br>";
   }
   else if( $sendit && $Email )
   {
      $err = 0;
      foreach( explode( ',', $Email) as $addr )
      {
         if( !verify_email( false, $addr) )
         {
            echo "<br>bad mail address: ".textarea_safe($addr)."<br>";
            $err = 1;
         }
      }
      if( !$err )
      {
         if( $From )
            $headers = "From: $From\n";
         else
            $headers = "";

         $msg = str_pad('', 47, '-') . "\n" .
             "Date: ".date($date_fmt, $NOW) . "\n" .
             "From: $FRIENDLY_LONG_NAME admin staff\n" .
             "Subject: ".strip_tags( $Subject, '') . "\n\n" .
             strip_tags( $Text, '') . "\n";

         $res= send_email( false, $Email, $msg
                     , $FRIENDLY_LONG_NAME.' mail test', $headers);
         if( !$res )
         {
            echo "<br>mail() function failed.<br>";
         }
         else
         {
            echo "<br>Message sent to $Email / $From:<br>";
            echo "<pre>$msg</pre>";
         }
      }
   }

   end_html();
}
?>
