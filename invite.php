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

connect2mysql();

$logged_in = is_logged_in($handle, $sessioncode, $player_row);

if( !$logged_in )
   error("not_logged_in");


if( $uid )
{
    $result = mysql_query( "SELECT Handle FROM Players WHERE ID=$uid" );

    if( mysql_num_rows( $result ) == 1 )
        {
            extract(mysql_fetch_array($result));
        }
}


start_page("Send Invitation", true, $logged_in, $player_row );

?>

<FORM name="loginform" action="send_message.php" method="POST">
      
      
      <center><B><font size=+1>New Message:</font></B></center>
      <HR>
      <TABLE align="center">
        
          <TR>
            <TD align=right>To (userid):</TD>
            <TD align=left> <input type="text" name="to" size="50" value="<?php echo $Handle ?>" maxlength="80"></TD>
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
        <TD align=right>My color:</TD>
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
          <input type="text" value="6.5" name="komi" size="5" maxlength="5">
      </TR>

      <TR>
        <TD align=right>Main time:</TD>
        <TD align=left>  
          <input type="text" value="3" name="timevalue" size="5" maxlength="5">
              <select name="timeunit">
              <option>hours</option>
              <option>days</option>
              <option selected>months</option>
          </select>
        </TD>
      </TR>


      <TR>
        <TD align=right>
          <input type="radio" name="byoyomitype" value="JAP" checked>
            Japanese byo-yomi:</TD>
        <TD align=left>  

            <input type="text" value="1" name="byotimevalue_jap" size="5" maxlength="5">
              <select name="timeunit_jap">
                <option>hours</option>
                <option selected>days</option>
                <option>months</option>
              </select>

            with &nbsp;
            <input type="text" value="10" name="byoperiods_jap" size="5" maxlength="5">
              extra periods.
        </TD>
      </TR>

      <TR>
        <TD align=right>
          <input type="radio" name="byoyomitype" value="CAN">
            Canadian byo-yomi:</TD>
        <TD align=left>  

            <input type="text" value="15" name="byotimevalue_can" size="5" maxlength="5">
              <select name="timeunit_can">
                <option>hours</option>
                <option selected>days</option>
                <option>months</option>
              </select>

            for&nbsp;
            <input type="text" value="15" name="byostones_can" size="5" maxlength="5">
              stones.
        </TD>
      </TR>

      <TR>
        <TD align=right>
          <input type="radio" name="byoyomitype" value="FIS">
            Fischer time:</TD>
        <TD align=left>  

            <input type="text" value="1" name="byotimevalue_fis" size="5" maxlength="5">
              <select name="timeunit_fis">
                <option>hours</option>
                <option selected>days</option>
                <option>months</option>
              </select>

            extra&nbsp;per move.
        </TD>
      </TR>

      <TR>
        <TD align=right>Rated:</TD>
        <TD align=left>  <input type="checkbox" name="rated" value="Y" checked></TD>

      </TR>

      <TR>
        <TD></TD>
        <TD>
            <input type=hidden name="type" value="INVITATION">
            <input type=submit name="send" value="Send invitation"></TD>
      </TR>

    </TABLE>
</FORM>

<?php
end_page();
?>