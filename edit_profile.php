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
require( "include/timezones.php" );
require( "include/rating.php" );

connect2mysql();

$logged_in = is_logged_in($handle, $sessioncode, $player_row);

if( !$logged_in )
   error("not_logged_in");


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
        <TD align=left> <input type="text" name="name" value="<?php echo $player_row["Name"]; ?>" size="16" maxlength="40"></TD>
      </TR>

      <TR>
        <TD align=right>Email:</TD>
        <TD align=left> <input type="text" name="email" value="<?php echo $player_row["Email"]; ?>" size="16" maxlength="80"></TD>
      </TR>

      <TR>
        <TD align=right>Open for matches:</TD>
        <TD align=left> <input type="text" name="open" value="<?php echo $player_row["Open"]; ?>" size="16" maxlength="40"></TD>
      </TR>


      <TR>
        <TD align=right>Rank info:</TD>
        <TD align=left> <input type="text" name="rank" value="<?php echo $player_row["Rank"]; ?>" size="16" maxlength="40"></TD>
      </TR>

      <TR>
        <TD align=right>Rating:</TD><TD align=left>
        <?php if( $player_row["RatingStatus"] != 'RATED' ) 
{?>
        <input type="text" name="rating" value="<?php echo_rating( $player_row["Rating"], true); ?>" size="16" maxlength="16">
<?php

 $vals = array('dragonrating', 'eurorank', 'eurorating','aga', 'agarating', 'igs', 'igsrating',
               'iytgg', 'nngs', 'nngsrating', 'japan', 'china', 'korea');
 
 echo html_build_select_box_from_array($vals, 'ratingtype', 'dragonrating', true);
} 
else echo_rating( $player_row["Rating"] );
?>
         </TD>
      </TR>


      <TR>
        <TD align=right>Send email notifications:</TD>
        <TD align=left>  <INPUT type="checkbox" name="wantemail" <?php 
if( $player_row["flags"] & WANT_EMAIL ) echo "checked " ?> value="true"> 
        </TD>
      </TR> 

      <TR>
        <TD align=right>Timezone:</TD>
        <TD align=left>
          <?php echo html_get_timezone_popup('timezone', $player_row['Timezone']); ?>
        </TD>        
      </TR>

      <TR>
        <TD align=right>Nighttime:</TD>
        <TD align=left>
<?php $s = $player_row["Nightstart"]; ?>
          <select name="nightstart">
<?php
          for($i=0; $i<24; $i++)
{
    echo "<option"; 
    if($s == $i) echo " selected";
    echo " value=$i>";
    printf('%02d-%02d',$i,($i+9)%24) . "</option>\n";
}
?>
          </select>

        </TD>        
      </tr>
         <tr><td><h3><font color="#800000">Board graphics:</font></h3></td></tr>
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
        <TD align=right>Wood color:</TD>
        <TD align=left>
          <?php $s = $player_row["Woodcolor"]; ?>
          <INPUT type="radio" name="woodcolor" value=1 <?php if($s == 1) echo " checked"; ?>> 
            <img width=30 height=30 src="images/smallwood1.gif">
          <INPUT type="radio" name="woodcolor" value=2 <?php if($s == 2) echo " checked"; ?>> 
            <img width=30 height=30 src="images/smallwood2.gif">
          <INPUT type="radio" name="woodcolor" value=3 <?php if($s == 3) echo " checked"; ?>> 
            <img width=30 height=30 src="images/smallwood3.gif">
          <INPUT type="radio" name="woodcolor" value=4 <?php if($s == 4) echo " checked"; ?>> 
            <img width=30 height=30 src="images/smallwood4.gif">
          <INPUT type="radio" name="woodcolor" value=5 <?php if($s == 5) echo " checked"; ?>> 
            <img width=30 height=30 src="images/smallwood5.gif">
        </TD>
      </TR>

      <TR>
        <TD align=right>Coordinate sides:</TD>
        <TD align=left>
          <?php $s = $player_row["Boardcoords"]; ?>
          <INPUT type="checkbox" name="coordsleft" value=1 
            <?php if($s & 1) echo " checked"; ?> > Left
          <INPUT type="checkbox" name="coordsup" value=1 
            <?php if($s & 2) echo " checked"; ?>>  Up
          <INPUT type="checkbox" name="coordsright" value=1 
            <?php if($s & 4) echo " checked"; ?>> Right

          <INPUT type="checkbox" name="coordsdown" value=1 
            <?php if($s & 8) echo " checked"; ?>> Down
        </TD>
      </TR>

    </table>  


          <input type=submit name="action" value="Change profile">

  </FORM>
</CENTER>  

<?php

end_page(false);

?>
