<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

require_once( "include/filter.php" );
require_once( "include/countries.php" );


// Inits internal static country-array
{
   global $COUNTRIES, $_FILTERC;
   $_FILTERC = $COUNTRIES;
   // some shorter countries
   $_FILTERC['va'] = T_('Vatican City (Holy See)#filter');
   $_FILTERC['vc'] = T_('St. Vincent & Grenadines#filter');
   asort($_FILTERC);
   array_unshift( $_FILTERC, '0');
   $_FILTERC['0'] = T_('All countries#filter');
}


 /*!
  * \class FilterCountry
  * \brief Filter for country as selectbox; SearchFilter-Type: Country.
  * <p>GUI: selectbox
  *
  * <p>supported common config (restrictions or defaults):
  *    FC_FNAME, FC_STATIC, FC_GROUP_SQL_OR, FC_DEFAULT (country-code),
  *    FC_SQL_TEMPLATE, FC_ADD_HAVING, FC_HIDE
  */
class FilterCountry extends Filter
{
   /*! \brief Constructs Country-Filter. */
   function FilterCountry($name, $dbfield, $config)
   {
      parent::Filter($name, $dbfield, null, $config);
      $this->type = 'Country';
      $this->syntax_help = T_('COUNTRY#filterhelp');
      $this->syntax_descr = ''; // action: select country
   }

   /*! \brief Handles index to select all or specific country. */
   function parse_value( $name, $val )
   {
      $val = $this->handle_default( $name, $val );
      $this->init_parse($val);
      $this->p_value = ( empty($val) ) ? '' : $val; // handle '0'

      $this->query = $this->build_query_text(false); // no wildcard, b/c '__' =earth
      return true;
   }

   /*! \brief Returns selectbox form-element. */
   function get_input_element( $prefix, $attr = array() )
   {
      global $_FILTERC;
      return $this->build_selectbox_elem( $prefix, $_FILTERC);
   }
} // end of 'FilterCountry'

?>
