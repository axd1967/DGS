<?php
/*
Dragon Go Server
Copyright (C) 2001-2014  Erik Ouchterlony, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Game";

require_once 'include/std_functions.php';
require_once 'include/form_functions.php';
require_once 'include/rating.php';


 /*!
  * \class RankConverter
  *
  * \brief Class to manage rank-converter
  */
class RankConverter
{
   // ------------ static functions ----------------------------

   public static function buildForm( $page, $method=FORM_GET )
   {
      // build Rank Converter Form
      $rcform = new Form( 'convrank', $page, $method );

      $rcform->add_row( array( 'HEADER', T_('DGS Rank Converter'), ));
      $rcform->add_row( array(
            'TEXT', T_('See also') . ': ' . anchor('http://senseis.xmp.net/?RankWorldwideComparison'), ));

      $converted_rank = '';
      $conv_rating = trim( get_request_arg('conv_rating') );
      if ( @$_REQUEST['convert'] && (string)$conv_rating != '' )
      {
         $conv_ratingtype = get_request_arg('conv_ratingtype');
         $conv_newrating = convert_to_rating($conv_rating, $conv_ratingtype, MAX_ABS_RATING);
         $converted_rank = ($conv_newrating != NO_RATING)
            ? sprintf( "=> %s: %s<br>\n=> %s: %d",
                       T_('DGS-rank'), echo_rating($conv_newrating, true, 0, true),
                       T_('DGS-ELO'), $conv_newrating )
            : T_('No valid rating');
      }
      $rcform->add_empty_row();
      $rcform->add_row( array(
            'TEXTINPUT', 'conv_rating', 16, 16, get_request_arg('conv_rating'),
            'SELECTBOX', 'conv_ratingtype', 1, getRatingTypes(), get_request_arg('conv_ratingtype'), false,
            'SUBMITBUTTON', 'convert', T_('Convert'), ));
      if ( $converted_rank )
         $rcform->add_row( array( 'TEXT', $converted_rank, ));

      return $rcform;
   }//buildForm

} // end of 'RankConverter'

?>
