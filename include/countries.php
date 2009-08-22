<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Countries";

require_once( 'include/utilities.php' );


/*!
 * \file countries.php
 *
 * \brief list of countries
 *
 * Countries:
 * Table of: ISO 3166 code => country name
 *     (plus some international languages (ISO 639 code))
 */

// use lazy-init to assure, that translation-language has been initialized !!
$ARR_GLOBALS_COUNTRIES = array();


/*!
 * \brief Returns country-text or all countries (if code=null); '' if code is unknown country.
 *
 * \note
 * Flag should have an entry in the ISO-3166 country code table
 * - http://www.iso.org/iso/iso-3166-1_decoding_table
 * - updates on: http://www.iso.org/iso/country_codes/updates_on_iso_3166.htm
 * - http://en.wikipedia.org/wiki/Flags#External_links
 *
 * International languages (ISO 639 code):
 *   - http://www.oasis-open.org/cover/iso639a.html
 *   - http://www.loc.gov/standards/iso639-2/php/code_list.php
 *
 * Sites with flags:
 * - http://www.atlasgeo.net/fotw/flags/country.html or Wikipedia
 * - good scaling from SVGs:
 *   http://de.wikipedia.org/wiki/Flaggen_und_Wappen_nichtselbst%C3%A4ndiger_Gebiete
 * - http://www.atlasgeo.net/fotw/flags/country.html
 * - https://www.cia.gov/cia/publications/factbook/
 * - http://setiathome.free.fr/images/flags/iso3166-1.html
 */
function getCountryText( $code=null )
{
   // lazy-init of texts
   $key = 'COUNTRIES';
   if( !isset($ARR_GLOBALS_COUNTRIES[$key]) )
   {
      // Legend for flag:
      // - "[country]" = territory of given country
      // - "same as" = same flag as other [country],
      // - "(unofficial)" = most likely unofficial flag, keep flag because of separate country-code

      $arr = array(
         'af' => T_('Afghanistan'),
         'ax' => T_('Aaland Islands'), // [FI] (unofficial)
         'al' => T_('Albania'),
         'dz' => T_('Algeria'),
         'as' => T_('American Samoa'), // [US]
         'ad' => T_('Andorra'),
         'ao' => T_('Angola'),
         'ai' => T_('Anguilla'), // [UK]
         'aq' => T_('Antarctica'),
         'ag' => T_('Antigua and Barbuda'),
         'ar' => T_('Argentina'),
         'am' => T_('Armenia'),
         'aw' => T_('Aruba'), // [NL]
         'au' => T_('Australia'),
         'at' => T_('Austria'),
         'az' => T_('Azerbaijan'),
         'bs' => T_('Bahamas'),
         'bh' => T_('Bahrain'),
         'bb' => T_('Barbados'),
         'bd' => T_('Bangladesh'),
         'by' => T_('Belarus'),
         'be' => T_('Belgium'),
         'bz' => T_('Belize'),
         'bj' => T_('Benin'),
         'bm' => T_('Bermuda'),
         'bt' => T_('Bhutan'),
         'bo' => T_('Bolivia'),
         'ba' => T_('Bosnia and Herzegovina'),
         'bw' => T_('Botswana'),
         //'bv' => T_//('Bouvet Island'), // same as [NO], not inhabited
         'br' => T_('Brazil'),
         'io' => T_('British Indian Ocean Territory'), // [UK] (unofficial)
         'bn' => T_('Brunei Darussalam'),
         'bg' => T_('Bulgaria'),
         'bf' => T_('Burkina Faso'),
         'bi' => T_('Burundi'),
         'kh' => T_('Cambodia'),
         'cm' => T_('Cameroon'),
         'ca' => T_('Canada'),
         'cv' => T_('Cape Verde'),
         'ky' => T_('Cayman Islands'), // [UK]
         'cf' => T_('Central African Republic'),
         'td' => T_('Chad'),
         'cl' => T_('Chile'),
         'cn' => T_('China'),
         'cx' => T_('Christmas Island'), // [AU] (unofficial)
         'cc' => T_('Cocos (Keeling) Islands'), // [AU] (unofficial)
         'co' => T_('Colombia'),
         'km' => T_('Comoros'),
         'cg' => T_('Congo-Brazzaville'),
         'cd' => T_('Congo-Kinshasa'),
         'ck' => T_('Cook Islands'), // [UK] (unofficial)
         'cr' => T_('Costa Rica'),
         'hr' => T_('Croatia'),
         'cu' => T_('Cuba'),
         'cy' => T_('Cyprus'),
         'cz' => T_('Czech Republic'),
         'dk' => T_('Denmark'),
         'dj' => T_('Djibouti'),
         'dm' => T_('Dominica'),
         'do' => T_('Dominican Republic'),
         'ec' => T_('Ecuador'),
         'eg' => T_('Egypt'),
         'sv' => T_('El Salvador'),
         'gq' => T_('Equatorial Guinea'),
         'er' => T_('Eritrea'),
         'ee' => T_('Estonia'),
         'et' => T_('Ethiopia'),
         'eu' => T_('European Union'), // exception
         'fk' => T_('Falkland Islands'), // [UK] (unofficial)
         'fo' => T_('Faroe Islands'), // [DK]
         'fj' => T_('Fiji'),
         'fi' => T_('Finland'),
         'fr' => T_('France'),
         'gf' => T_('French Guinea'), // [FR] (unofficial)
         'pf' => T_('French Polynesia'), // [FR] (unofficial)
         'ga' => T_('Gabon'),
         'gm' => T_('Gambia'),
         'ge' => T_('Georgia'),
         'de' => T_('Germany'),
         'gh' => T_('Ghana'),
         'gi' => T_('Gibraltar'), // [UK] (unofficial)
         'gr' => T_('Greece'),
         'gl' => T_('Greenland'), // [DK] (unofficial)
         'gd' => T_('Grenada'),
         'gp' => T_('Guadeloupe'), // [FR] (unofficial)
         'gu' => T_('Guam'),
         'gt' => T_('Guatemala'),
         'gn' => T_('Guinea'),
         'gg' => T_('Guernsey'), // [UK] (unofficial)
         'gw' => T_('Guinea-Bissau'),
         'gy' => T_('Guyana'),
         'ht' => T_('Haiti'),
         'hn' => T_('Honduras'),
         'hk' => T_('Hong Kong'),
         'hu' => T_('Hungary'),
         'is' => T_('Iceland'),
         'in' => T_('India'),
         'id' => T_('Indonesia'),
         'ci' => T_('Ivory Coast'), // Cote d'Ivoire
         'ir' => T_('Iran'),
         'iq' => T_('Iraq'),
         'ie' => T_('Ireland'),
         'il' => T_('Israel'),
         'im' => T_('Isle of Man'), // [UK] (unofficial)
         'it' => T_('Italy'),
         'jm' => T_('Jamaica'),
         'jp' => T_('Japan'),
         'je' => T_('Jersey'), // [UK] (unofficial)
         'jo' => T_('Jordan'),
         'kz' => T_('Kazakhstan'),
         'ke' => T_('Kenya'),
         'ki' => T_('Kiribati'),
         'kp' => T_('Korea,North'),
         'kr' => T_('Korea,South'),
         'kw' => T_('Kuwait'),
         'kg' => T_('Kyrgyzstan'),
         'la' => T_('Laos'),
         'lv' => T_('Latvia'),
         'lb' => T_('Lebanon'),
         'ls' => T_('Lesotho'),
         'lr' => T_('Liberia'),
         'ly' => T_('Libya'),
         'li' => T_('Liechtenstein'),
         'lt' => T_('Lithuania'),
         'lu' => T_('Luxembourg'),
         'mo' => T_('Macao'),
         'mk' => T_('Macedonia'),
         'mg' => T_('Madagascar'),
         'mw' => T_('Malawi'),
         'my' => T_('Malaysia'),
         'mv' => T_('Maldives'),
         'ml' => T_('Mali'),
         'mt' => T_('Malta'),
         'mh' => T_('Marshall Islands'),
         'mq' => T_('Martinique'), // [FR] (unofficial)
         'mr' => T_('Mauritania'),
         'mu' => T_('Mauritius'),
         'yt' => T_('Mayotte'), // [FR] (unofficial)
         'mx' => T_('Mexico'),
         'fm' => T_('Micronesia'),
         'md' => T_('Moldova'),
         'mc' => T_('Monaco'),
         'mn' => T_('Mongolia'),
         'me' => T_('Montenegro'),
         'ms' => T_('Montserrat'), // [UK] (unofficial)
         'ma' => T_('Morocco'),
         'mz' => T_('Mozambique'),
         'mm' => T_('Myanmar'),
         'na' => T_('Namibia'),
         'nr' => T_('Nauru'),
         'np' => T_('Nepal'),
         'nl' => T_('Netherlands'),
         'an' => T_('Netherlands Antilles'), // [NL] (unofficial)
         'nc' => T_('New Caledonia'), // [FR] (unofficial)
         'nz' => T_('New Zealand'),
         'ni' => T_('Nicaragua'),
         'ne' => T_('Niger'),
         'ng' => T_('Nigeria'),
         'nu' => T_('Niue'), // [NZ] (unofficial)
         'nf' => T_('Norfolk Island'), // [AU] (unofficial)
         'mp' => T_('Northern Mariana Islands'), // [US] (unofficial)
         'no' => T_('Norway'),
         'om' => T_('Oman'),
         'pk' => T_('Pakistan'),
         'pw' => T_('Palau'),
         'ps' => T_('Palestinian Territory'),
         'pa' => T_('Panama'),
         'pg' => T_('Papua New Guinea'),
         'py' => T_('Paraguay'),
         'pe' => T_('Peru'),
         'ph' => T_('Philippines'),
         'pn' => T_('Pitcairn'), // [UK]
         'pl' => T_('Poland'),
         'pt' => T_('Portugal'),
         'pr' => T_('Puerto Rico'),
         'qa' => T_('Qatar'),
         're' => T_('Reunion'), // [FR] (unofficial)
         'ro' => T_('Romania'),
         'ru' => T_('Russia'),
         'rw' => T_('Rwanda'),
         'bl' => T_('Saint Barthelemy'), // [FR] (unofficial)
         'sh' => T_('Saint Helena'), // [UK] (unofficial)
         'kn' => T_('Saint Kitts and Nevis'),
         'lc' => T_('Saint Lucia'),
         'mf' => T_('Saint Martin'), // [FR] (unofficial)
         'pm' => T_('Saint Pierre and Miquelon'), // [FR] (unofficial)
         'vc' => T_('Saint Vincent and the Grenadines'),
         'ws' => T_('Samoa'),
         'sm' => T_('San Marino'),
         'st' => T_('Sao Tome and Principe'),
         'sa' => T_('Saudi Arabia'),
         'sn' => T_('Senegal'),
         //'yu' => T_//('Serbia and Montenegro (Yugoslavia)'), //obsolete: YU -> CS -> RS/ME
         'rs' => T_('Serbia'),
         'sc' => T_('Seychelles'),
         'sl' => T_('Sierra Leone'),
         'sg' => T_('Singapore'),
         'sk' => T_('Slovakia'),
         'si' => T_('Slovenia'),
         'sb' => T_('Solomon Islands'),
         'so' => T_('Somalia'),
         'za' => T_('South Africa'),
         'gs' => T_('South Georgia and Sandwich Islands'), // [UK] (unofficial)
         'es' => T_('Spain'),
         'lk' => T_('Sri Lanka'),
         'sd' => T_('Sudan'),
         'sr' => T_('Suriname'),
         //'sj' => T_//('Svalbard and Jan Mayen'), // same as [NO]
         'sz' => T_('Swaziland'),
         'se' => T_('Sweden'),
         'ch' => T_('Switzerland'),
         'sy' => T_('Syria'),
         'tw' => T_('Taiwan'),
         'tj' => T_('Tajikistan'),
         'tz' => T_('Tanzania'),
         'th' => T_('Thailand'),
         'tl' => T_('Timor Leste'),
         'tg' => T_('Togo'),
         'tk' => T_('Tokelau'), // [NZ] (unofficial)
         'to' => T_('Tonga'),
         'tt' => T_('Trinidad and Tobago'),
         'tn' => T_('Tunisia'),
         'tr' => T_('Turkey'),
         'tc' => T_('Turks and Caicos Islands'), // [UK] (unofficial)
         'tm' => T_('Turkmenistan'),
         'tv' => T_('Tuvalu'),
         'ug' => T_('Uganda'),
         'ua' => T_('Ukraine'),
         'ae' => T_('United Arab Emirates'),
         'gb' => T_('United Kingdom'),
         //'um' => T_//('United States Minor Outlying Islands'), // same as [US]
         'us' => T_('United States'),
         'uy' => T_('Uruguay'),
         'uz' => T_('Uzbekistan'),
         'vu' => T_('Vanuatu'),
         'va' => T_('Vatican City State (Holy See)'),
         've' => T_('Venezuela'),
         'vn' => T_('Vietnam'),
         'vg' => T_('Virgin Islands (British)'), // [UK] (unofficial)
         'vi' => T_('Virgin Islands (US)'), // [US] (unofficial)
         'wf' => T_('Wallis and Futuna'), // [FR] (unofficial)
         'eh' => T_('Western Sahara'),
         'ye' => T_('Yemen'),
         'zm' => T_('Zambia'),
         'zw' => T_('Zimbabwe'),

         // use user-defined codes in ISO-3166-1 for specials: (qm..qz, xa..xz, zz)
         'xe' => T_('Earth'), // not a nation
         'xo' => T_('Esperanto'), // international language
         'xi' => T_('Interlingua'), // international language
         'xk' => T_('Klingon Empire'), // not a nation ... but who knows for sure?
         'xf' => T_('United Federation of Planets'), // not a nation ... yet
      );
      $ARR_GLOBALS_COUNTRIES[$key] = $arr;
   }

   if( is_null($code) )
      return $ARR_GLOBALS_COUNTRIES[$key];

   return ( isset($ARR_GLOBALS_COUNTRIES[$key][$code]) )
      ? $ARR_GLOBALS_COUNTRIES[$key][$code]
      : '';
}//getCountryText

/*! \brief Returns image-tag; '' if unknown country-code used. */
function getCountryFlagImage( $ccode )
{
   global $base_path;
   $cText = basic_safe( getCountryText($ccode) );
   return (empty($cText))
      ? ''
      : "<img src=\"{$base_path}images/flags/$ccode.gif\" " .
        "title=\"$cText\" alt=\"$cText\" width=\"32\" height=\"20\">";
}

?>
