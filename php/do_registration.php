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

require( "include/std_functions.php" );


if( $passwd != $passwd2 )
{
    header("Location: error.php?err=password_missmatch");
    exit;
}
else if( strlen($passwd) < 6 )
{
    header("Location: error.php?err=password_too_short");
    exit;
}

if( strlen( $userid ) < 3 )
{
    header("Location: error.php?err=userid_too_short");
    exit;
}

if( strlen( $name ) < 1 )
{
    header("Location: error.php?err=name_not_given");
    exit;
}


connect2mysql();

$result = mysql_query( "SELECT * FROM Players WHERE Handle='" . $userid . "'" );

if( mysql_num_rows($result) > 0 )
{
    header("Location: error.php?err=userid_in_use");
    exit;
}




# Userid and password are fine, now do the registration to the database

$code = make_session_code();

$result = mysql_query( "INSERT INTO Players SET " .
                       "Handle='$userid', " .
                       "Name='$name', " .
                       "Password=PASSWORD('$passwd'), " .
                       "Sessioncode='$code', " .
                       "Sessionexpire=DATE_ADD(NOW(),INTERVAL $session_duration second)" );

$new_id = mysql_insert_id();

if( mysql_affected_rows() != 1 )
{
    header("Location: error.php?err=mysql_insert_player");
    exit;
}

$result = mysql_query( "CREATE TABLE Messages$new_id (  ID int(11) DEFAULT '0' NOT NULL auto_increment, From_ID int(11), Type enum('NORMAL','INVITATION','ACCEPTED','DECLINED') DEFAULT 'NORMAL', Info enum('NONE','NEW','REPLIED','REPLY REQUIRED') DEFAULT 'NEW', Game_ID int(11), Time timestamp(14), Subject varchar(80), Text text, PRIMARY KEY (ID) )" );


set_cookies( $userid, $code );

header("Location: status.php");
