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

header ("Cache-Control: no-cache, must-revalidate, max_age=0"); 

include( "std_functions.php" );
include( "connect2mysql.php" );


connect2mysql();

$logged_in = is_logged_in($handle, $sessioncode, $player_row);

if( !$logged_in )
{
   header("Location: error.php?err=not_logged_in");
   exit;
}

start_page("Edit profile", true, $logged_in, $player_row );



?>

<CENTER>
  <FORM name="profileform" action="change_profile.php" method="POST">

    <TABLE>

      
      <TR>
        <TD align=right>Userid:</TD>
        <TD align=left> <?php echo $player_row["Handle"]; ?></TD>
      </TR>

      <TR>
        <TD align=right>Full name:</TD>
        <TD align=left> <input type="text" name="name" value="<?php echo $player_row["Name"]; ?>" size="16" maxlength="80"></TD>
      </TR>

      <TR>
        <TD align=right>Email:</TD>
        <TD align=left> <input type="text" name="email" value="<?php echo $player_row["Email"]; ?>" size="16" maxlength="80"></TD>
      </TR>

      <TR>
        <TD align=right>Rank:</TD>
        <TD align=left> <input type="text" name="rank" value="<?php echo $player_row["Rank"]; ?>" size="16" maxlength="80"></TD>
      </TR>


      <TR>
        <TD align=right>Stone size:</TD>
        <TD align=left>  
<?php $s = $player_row["Stonesize"]; ?>
          <select name="stonesize">
            <option<?php if($s == 13) echo " selected"; ?>>13</option>
            <option<?php if($s == 17) echo " selected"; ?>>17</option>
            <option<?php if($s == 21) echo " selected"; ?>>21</option>
            <option<?php if($s == 25) echo " selected"; ?>>25</option>
            <option<?php if($s == 29) echo " selected"; ?>>29</option>
            <option<?php if($s == 35) echo " selected"; ?>>35</option>
            <option<?php if($s == 42) echo " selected"; ?>>42</option>
            <option<?php if($s == 50) echo " selected"; ?>>50</option>
          </select>
      </TR>

      <TR>
        <TD align=right>Boardfont size:</TD>
        <TD align=left>  
<?php $s = $player_row["Boardfontsize"]; ?>
          <select name="boardfontsize">
            <option<?php if($s == '+4') echo " selected"; ?>>+4</option>
            <option<?php if($s == '+3') echo " selected"; ?>>+3</option>
            <option<?php if($s == '+2') echo " selected"; ?>>+2</option>
            <option<?php if($s == '+1') echo " selected"; ?>>+1</option>
            <option<?php if($s == '+0') echo " selected"; ?>>+0</option>
            <option<?php if($s == '-1') echo " selected"; ?>>-1</option>
            <option<?php if($s == '-2') echo " selected"; ?>>-2</option>
            <option<?php if($s == '-3') echo " selected"; ?>>-3</option>
          </select>
          <TD><input type=submit name="action" value="Change profile"></TD>
      </TR>
    </table>  
    <HR>
      <table>
      <TR>
        <TD align=right>Password:</TD>
        <TD align=left><input type="password" name="passwd" size="16" maxlength="16"></TD>
      </TR>
      
      <TR>
        <TD align=right>Confirm Password:</TD>
        <TD align=left><input type="password" name="passwd2" size="16" maxlength="16"></TD>
        <TD><input type=submit name="action" value="Change password"></TD>
      </TR>
      
    </TABLE>
  </FORM>
</CENTER>  

<?php

end_page(false);

?>
