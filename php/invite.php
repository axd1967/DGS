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


start_page("Send Message", true, $logged_in, $player_row );

?>

<FORM name="loginform" action="send_message.php" method="POST">
      
      
      <center><B><font size=+1>New Message:</font></B></center>
      <HR>
      <TABLE align="center">
        
          <TR>
            <TD align=right>To (userid):</TD>
            <TD align=left> <input type="text" name="to" size="50" maxlength="80"></TD>
          </TR>

          <TR>
            <TD align=right>Message:</TD>
            <TD align=left>  
              <textarea name="message" cols="50" rows="8" wrap="virtual"></textarea></TD>
          </TR>


          <TR>
            <TD align=right>Board size:</TD>
            <TD align=left>  
          <select name="size">
            <option>5</option>
            <option>6</option>
            <option>7</option>
            <option>8</option>
            <option>9</option>
            <option>10</option>
            <option>11</option>
            <option>12</option>
            <option>13</option>
            <option>14</option>
            <option>15</option>
            <option>16</option>
            <option>17</option>
            <option>18</option>
            <option selected>19</option>
            <option>20</option>
            <option>21</option>
            <option>22</option>
            <option>23</option>
            <option>24</option>
            <option>25</option>
          </select>
      </TR>
      
      <TR>
        <TD align=right>Color:</TD>
        <TD align=left>  
          <select name="color">
            <option selected>White</option>
            <option>Black</option>
          </select>
      </TR>

      <TR>
        <TD align=right>Handicap:</TD>
        <TD align=left>  
          <select name="handicap">
            <option selected>0</option>
            <option>2</option>
            <option>3</option>
            <option>4</option>
            <option>5</option>
            <option>6</option>
            <option>7</option>
            <option>8</option>
            <option>9</option>
            <option>10</option>
            <option>11</option>
            <option>12</option>
            <option>13</option>
            <option>14</option>
            <option>15</option>
            <option>16</option>
            <option>17</option>
            <option>18</option>
            <option>19</option>
            <option>20</option>
          </select>
      </TR>
 
      <TR>
        <TD align=right>Komi:</TD>
        <TD align=left>  
          <input type="text" value="5.5" name="komi" size="5" maxlength="5">
      </TR>

      <TR>
        <TD></TD>
        <TD>
            <input type=hidden name="type" value="INVITATION">
            <input type=submit name="send" value="Send invitation"></TD>
      </TR>

    </TABLE>


<?php
end_page();
?>