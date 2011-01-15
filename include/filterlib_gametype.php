<?php
/*
Dragon Go Server
Copyright (C) 2001-2011  Erik Ouchterlony, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Common";

require_once 'include/filter.php';
require_once 'include/gui_functions.php';
require_once 'include/game_functions.php';


/*!
 * \brief for GameType-Filter: controls usage of boolean-flag to search for multi-player-games (checkbox).
 * values: true = enable checkbox to select multi-player-games to allow mp-game-search
 *         '' (empty) -> feature disabled
 * default is not to show checkbox (like using ''-value for config)
 */
define('FC_MPGAME', 'mpgame');

define('FGTNAME_MPGAME', 'mp'); // field-name for mp-game-option


 /*!
  * \class FilterGameType
  * \brief Filter for selecting from different choices with additional check-box; SearchFilter-Type: GameType.
  * <p>GUI: selectbox + optional checkbox(if FC_MPGAME set)
  *
  * <p>supported common config (restrictions or defaults):
  *    FC_FNAME, FC_STATIC, FC_GROUP_SQL_OR, FC_SYNTAX_HELP, FC_HIDE
  *    FC_ADD_HAVING (makes no sense with dbfield = QuerySQL (could use SQLP_HAVING),
  *         dbfield should be where-clause instead)
  *
  * <p>supported filter-specific config:
  *    FC_MPGAME - bool to enable/disable multi-player-game-search checkbox
  *    FC_SIZE (multi-line) - rows of selectbox, must be >1 (otherwise default is 2)
  *    FC_DEFAULT - default-index that should be selected for single selection-mode (index 0..)
  *
  * \see parent class FilterSelection
  */
class FilterGameType extends FilterSelection
{
   /*! \brief Constructs Selection-Filter. */
   function FilterGameType($name, $dbfield, $config)
   {
      parent::FilterSelection($name, $dbfield, $config);
      $this->type = 'GameType';

      if( $this->get_config(FC_MULTIPLE) )
         error('invalid_filter', "filter.FilterGameType.conf.no_multiple({$this->id},$name)");

      $this->add_element_name( FGTNAME_MPGAME );
      $this->values[FGTNAME_MPGAME] = ''; // default (unchecked)
   }

   function use_prefix_fieldname( $fname )
   {
      return ( $fname != FGTNAME_MPGAME );
   }

   /*!
    * \brief Parses single- and multi-value selection, and mp-game-checkbox.
    * param val: string | array (only for multiple-support)
    */
   function parse_value( $name, $val )
   {
      if( $name == FGTNAME_MPGAME )
      {
         $val = $this->handle_default( $name, $val );
         $this->init_parse($val, $name);
         return true;
      }
      else
         return parent::parse_value( $name, $val );
   }

   /*! \brief Returns selectbox form-element. */
   function get_input_element($prefix, $attr = array() )
   {
      // selectbox
      $r = parent::get_input_element($prefix, $attr);

      // check-box for mp-game
      if( $this->get_config(FC_MPGAME) )
      {
         $r .= $this->build_generic_checkbox_elem(
               '', FGTNAME_MPGAME, $this->values[FGTNAME_MPGAME],
               MINI_SPACING . echo_image_game_players(-1),
               T_('Show multi-player-games only#filter') );
      }
      return $r;
   }
} // end of 'FilterGameType'

?>
