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

require( "include/std_functions.php" );
require( "include/timezones.php" );
require( "include/rating.php" );

$categories = array('Other:', 'Country', 'City','State', 'Club', 'Homepage', 'Email',
               'ICQ-number', 'Game preferences', 'Hobbies', 'Occupation');

function find_cat($cat)
{
  global $categories;

  if( in_array($cat, $categories) )
    return $cat;
  else
    return 'Other:';
}

connect2mysql();

$logged_in = is_logged_in($handle, $sessioncode, $player_row);

if( !$logged_in )
{
   header("Location: error.php?err=not_logged_in");
   exit;
}



$result = mysql_query("SELECT * FROM Bio where uid=" . $player_row["ID"]);

start_page("Edit biopgraphical info", true, $logged_in, $player_row );

echo '<CENTER>
<FORM name="bioform" action="change_profile.php" method=post>
';

while( $row = mysql_fetch_array( $result ) )
{
  $cat = find_cat($row["Category"]);
?>


 <table>
    <tr>
     <TD align=right><?php 
     echo html_build_select_box_from_array($categories,"category" . $row["ID"],$cat,true);
?></TD>
     <TD align=left rowspan=2>
          <textarea name="text<?php echo $row["ID"]?>" cols="40" rows="4" wrap="virtual"><?php 
echo $row["Text"]?></textarea></TD></TR>
     <TR><TD align=left>
      <input type="text" name="other<?php echo $row["ID"]?>" size="15" maxlength="40"<?php 
if( $cat == "Other:" ) echo ' value="' . $row["Category"] . '"' ?>></TD>
      </TR>
 </table>
<?php
}

// And now three empty ones:

for($i=1;$i<=3;$i++)
{
  ?>
 <table>
    <tr>
     <TD align=right><?php 
     echo html_build_select_box_from_array($categories,"newcategory" . $i,'Other:',true);
?></TD>
     <TD align=left rowspan=2>
          <textarea name="newtext<?php echo $i?>" cols="40" rows="4" wrap="virtual"></textarea></TD></TR>
     <TR><TD align=left>
      <input type="text" name="newother<?php echo $i?>" size="15" maxlength="40"></TD>
      </TR>
 </table>
<?php 
}

?>


  <input type=submit name="action" value="Change profile">
</FORM>
</CENTER>  
<BR>

<?php

end_page(false);

?>
