<?php
/*
Dragon Go Server
Copyright (C) 2001-2008  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
require_once( "include/form_functions.php" );

{
   disable_cache();

   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   if( !(@$player_row['admin_level'] & (ADMIN_DEVELOPER|ADMIN_DATABASE)) )
      error('adminlevel_too_low');


   start_html( 'checkip', 0, '', '' );

   echo "<br><center>\n",
      "<h3>Check Block-IP</h3>\n";

   $val_syntax = get_request_arg('block');
   $val_ip = get_request_arg('ip');

   $ipform = new Form('ipform', 'check_block_ip.php', FORM_GET, true );
   $ipform->add_row( array(
      'DESCRIPTION', 'Block Syntax',
      'TEXTINPUT',   'block', 40, -1, $val_syntax ));
   $ipform->add_row( array(
      'DESCRIPTION', 'IP to check',
      'TEXTINPUT',   'ip', 40, -1, $val_ip ));
   $ipform->add_row( array(
      'SUBMITBUTTON', 'chk_syntax', 'Check Syntax',
      'SUBMITBUTTON', 'chk_conf',   'Check Config', ));
   $ipform->add_row( array( 'SPACE' ));

   $action = null; // no result
   $extra = array();
   $error = '';
   $subnet_chk = null;
   if( @$_REQUEST['chk_syntax'] )
   {
      if( (string)$val_syntax == '' )
         $error = 'Missing Syntax';
      if( preg_match( "/^[^\/].+\//", $val_syntax ) )
         $subnet_chk = check_subnet_ip( $val_syntax, $val_ip );

      $is_blocked = is_blocked_ip( $val_ip, array( $val_syntax ) );
      $action = $val_syntax;
   }
   elseif( @$_REQUEST['chk_conf'] )
   {
      $is_blocked = is_blocked_ip( $val_ip );
      $action = "read include/config.php";
   }

   global $ARR_BLOCK_IPLIST;
   if( count($ARR_BLOCK_IPLIST) == 0 )
      $extra[] = "Block-IP-config empty.";
   else
   {
      $extra[] = "<u>Block-IP-config:</u>";
      foreach( $ARR_BLOCK_IPLIST as $blockarg )
         $extra[] = $blockarg;
   }

   // print result
   if( !is_null($action) )
   {
      $ipform->add_row( array(
         'DESCRIPTION', 'Block Syntax',
         'TEXT',        $action ));
      $ipform->add_row( array(
         'DESCRIPTION', 'Checked IP',
         'TEXT',        $val_ip ));
      if( $error )
      {
         $ipform->add_row( array(
            'DESCRIPTION', 'Error',
            'TEXT',        sprintf( '<font color="red">%s</font>', $error ) ));
      }
      $ipform->add_row( array(
         'DESCRIPTION', 'Subnet Check',
         'TEXT',        (is_null($subnet_chk) ? '---' : $subnet_chk) ));
      $ipform->add_row( array(
         'DESCRIPTION', 'Result',
         'TEXT', sprintf( '<font color="%s">%s</font>',
                     ($is_blocked ? 'darkred' : 'darkgreen'),
                     ($is_blocked ? 'IP blocked' : 'IP ok (not blocked)' )) ));

      if( count($extra) > 0 )
      {
         $ipform->add_row( array( 'SPACE' ));
         $ipform->add_row( array( 'SPACE' ));
         $ipform->add_row( array(
            'DESCRIPTION', 'Extra Info',
            'TEXT',        implode("<br>\n", $extra) ));
      }
   }

   $ipform->echo_string(1);

   echo "<p><p>Syntax: '127.0.0.1' (=ip), '127.0.0.1/32' (=subnet), '/^127\.0\.0\.1$/' (=regex)<br>\n";
   echo "</center>\n";

   end_html();
}
?>
