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

include( "config.php" );

function connect2mysql()
{
    $dbcnx = @mysql_connect( $HOST, $USER, $PASSWORD);
    if (!$dbcnx) 
      {
          header("Location: error.php?err=mysql_connect_failed");
          exit;
      }

    if (! @mysql_select_db($DB_NAME) ) 
      {
          header("Location: error.php?err=mysql_select_db_failed");
        exit;
      }    
}

?>