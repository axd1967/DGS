<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
      return $this->build_selectbox_elem( $prefix, FilterCountry::getFilterCountries() );
   }

   // ------------------ static functions ---------------------

   /*!
    * \brief Static (lazy-init) of filter-countries.
    * \internal
    */
   function getFilterCountries()
   {
      static $ARR_GLOBALS_FILTER_COUNTRY = null;

      // lazy-init
      if( !is_array($ARR_GLOBALS_FILTER_COUNTRY) )
      {
         $arr = getCountryText();

         // some shorter countries
         $arr['io'] = T_('British Indian Ocean#ccfilter');
         $arr['pm'] = T_('St. Pierre & Miquelon#ccfilter');
         $arr['vc'] = T_('St. Vincent & Grenadines#ccfilter');
         $arr['gs'] = T_('S-Georgia & Sandwich Isl.#ccfilter');
         $arr['va'] = T_('Vatican City (Holy See)#ccfilter');
         $arr['xf'] = T_('United Fed. of Planets#ccfilter');

         asort($arr);
         array_unshift( $arr, '0');
         $arr['0'] = T_('All countries#filter');
         $ARR_GLOBALS_FILTER_COUNTRY = $arr;
      }
      return $ARR_GLOBALS_FILTER_COUNTRY;
   }

} // end of 'FilterCountry'

?>
