<?php
/*
Dragon Go Server
Copyright (C) 2001  Erik Ouchterlony

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

include( "std_functions.php" );

connect2mysql();

$result = mysql_query( "SELECT *, UNIX_TIMESTAMP(Sessionexpire) AS Expire FROM Players WHERE Handle='" . $userid . "'" );

if( mysql_num_rows($result) != 1 )
{
    header("Location: error.php?err=wrong_userid");
    exit;
}

$row = mysql_fetch_array($result);
$passwd_encrypt = mysql_fetch_row( mysql_query( "SELECT PASSWORD('$passwd')" ) );

if( $row["Password"] != $passwd_encrypt[0] )
{
    // Check if there is a new password

    if( empty($row["Newpassword"]) or $row["Newpassword"] != $passwd_encrypt[0] )
        {
            header("Location: error.php?err=wrong_password");
            exit;
        }

}

// Remove the new password.
if( !empty($row["Newpassword"]) )
{
    mysql_query( 'UPDATE Players ' .
                 "SET Password='$passwd_encrypt[0]', " .
                 'Newpassword=NULL ' .
                 "Where Handle='$handle'" );
}

$code = $row["Sessioncode"];

if( !$code or $row["Expire"] < time() )
{
    $code = make_session_code();
    $result = mysql_query( "UPDATE Players SET " . 
                           "Sessioncode='$code', " .
                           "Sessionexpire=DATE_ADD(NOW(),INTERVAL $session_duration second) " .
                           "WHERE Handle='$userid'" );

}

if( $handle != $userid or $sessioncode != $code )
{
    set_cookies( $userid, $code );
}
header("Location: status.php");
