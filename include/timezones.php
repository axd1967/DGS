<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Ragnar Ouchterlony, Rod Ival

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

/* Function added by Ragnar Ouchterlony */

function get_timezone_array()
{
   $TZs['GMT']='GMT';
   $TZs['Africa/Abidjan']='Africa/Abidjan';
   $TZs['Africa/Accra']='Africa/Accra';
   $TZs['Africa/Addis_Ababa']='Africa/Addis_Ababa';
   $TZs['Africa/Algiers']='Africa/Algiers';
   $TZs['Africa/Asmera']='Africa/Asmera';
   $TZs['Africa/Bamako']='Africa/Bamako';
   $TZs['Africa/Bangui']='Africa/Bangui';
   $TZs['Africa/Banjul']='Africa/Banjul';
   $TZs['Africa/Bissau']='Africa/Bissau';
   $TZs['Africa/Blantyre']='Africa/Blantyre';
   $TZs['Africa/Brazzaville']='Africa/Brazzaville';
   $TZs['Africa/Bujumbura']='Africa/Bujumbura';
   $TZs['Africa/Cairo']='Africa/Cairo';
   $TZs['Africa/Casablanca']='Africa/Casablanca';
   $TZs['Africa/Ceuta']='Africa/Ceuta';
   $TZs['Africa/Conakry']='Africa/Conakry';
   $TZs['Africa/Dakar']='Africa/Dakar';
   $TZs['Africa/Dar_es_Salaam']='Africa/Dar_es_Salaam';
   $TZs['Africa/Djibouti']='Africa/Djibouti';
   $TZs['Africa/Douala']='Africa/Douala';
   $TZs['Africa/El_Aaiun']='Africa/El_Aaiun';
   $TZs['Africa/Freetown']='Africa/Freetown';
   $TZs['Africa/Gaborone']='Africa/Gaborone';
   $TZs['Africa/Harare']='Africa/Harare';
   $TZs['Africa/Johannesburg']='Africa/Johannesburg';
   $TZs['Africa/Kampala']='Africa/Kampala';
   $TZs['Africa/Khartoum']='Africa/Khartoum';
   $TZs['Africa/Kigali']='Africa/Kigali';
   $TZs['Africa/Kinshasa']='Africa/Kinshasa';
   $TZs['Africa/Lagos']='Africa/Lagos';
   $TZs['Africa/Libreville']='Africa/Libreville';
   $TZs['Africa/Lome']='Africa/Lome';
   $TZs['Africa/Luanda']='Africa/Luanda';
   $TZs['Africa/Lubumbashi']='Africa/Lubumbashi';
   $TZs['Africa/Lusaka']='Africa/Lusaka';
   $TZs['Africa/Malabo']='Africa/Malabo';
   $TZs['Africa/Maputo']='Africa/Maputo';
   $TZs['Africa/Maseru']='Africa/Maseru';
   $TZs['Africa/Mbabane']='Africa/Mbabane';
   $TZs['Africa/Mogadishu']='Africa/Mogadishu';
   $TZs['Africa/Monrovia']='Africa/Monrovia';
   $TZs['Africa/Nairobi']='Africa/Nairobi';
   $TZs['Africa/Ndjamena']='Africa/Ndjamena';
   $TZs['Africa/Niamey']='Africa/Niamey';
   $TZs['Africa/Nouakchott']='Africa/Nouakchott';
   $TZs['Africa/Ouagadougou']='Africa/Ouagadougou';
   $TZs['Africa/Porto-Novo']='Africa/Porto-Novo';
   $TZs['Africa/Sao_Tome']='Africa/Sao_Tome';
   $TZs['Africa/Timbuktu']='Africa/Timbuktu';
   $TZs['Africa/Tripoli']='Africa/Tripoli';
   $TZs['Africa/Tunis']='Africa/Tunis';
   $TZs['Africa/Windhoek']='Africa/Windhoek';
   $TZs['America/Adak']='America/Adak';
   $TZs['America/Anchorage']='America/Anchorage';
   $TZs['America/Anguilla']='America/Anguilla';
   $TZs['America/Antigua']='America/Antigua';
   $TZs['America/Araguaina']='America/Araguaina';
   $TZs['America/Aruba']='America/Aruba';
   $TZs['America/Asuncion']='America/Asuncion';
   $TZs['America/Atka']='America/Atka';
   $TZs['America/Barbados']='America/Barbados';
   $TZs['America/Belem']='America/Belem';
   $TZs['America/Belize']='America/Belize';
   $TZs['America/Boa_Vista']='America/Boa_Vista';
   $TZs['America/Bogota']='America/Bogota';
   $TZs['America/Boise']='America/Boise';
   $TZs['America/Buenos_Aires']='America/Buenos_Aires';
   $TZs['America/Cambridge_Bay']='America/Cambridge_Bay';
   $TZs['America/Cancun']='America/Cancun';
   $TZs['America/Caracas']='America/Caracas';
   $TZs['America/Catamarca']='America/Catamarca';
   $TZs['America/Cayenne']='America/Cayenne';
   $TZs['America/Cayman']='America/Cayman';
   $TZs['America/Chicago']='America/Chicago';
   $TZs['America/Chihuahua']='America/Chihuahua';
   $TZs['America/Cordoba']='America/Cordoba';
   $TZs['America/Costa_Rica']='America/Costa_Rica';
   $TZs['America/Cuiaba']='America/Cuiaba';
   $TZs['America/Curacao']='America/Curacao';
   $TZs['America/Dawson']='America/Dawson';
   $TZs['America/Dawson_Creek']='America/Dawson_Creek';
   $TZs['America/Denver']='America/Denver';
   $TZs['America/Detroit']='America/Detroit';
   $TZs['America/Dominica']='America/Dominica';
   $TZs['America/Edmonton']='America/Edmonton';
   $TZs['America/El_Salvador']='America/El_Salvador';
   $TZs['America/Ensenada']='America/Ensenada';
   $TZs['America/Fortaleza']='America/Fortaleza';
   $TZs['America/Fort_Wayne']='America/Fort_Wayne';
   $TZs['America/Glace_Bay']='America/Glace_Bay';
   $TZs['America/Godthab']='America/Godthab';
   $TZs['America/Goose_Bay']='America/Goose_Bay';
   $TZs['America/Grand_Turk']='America/Grand_Turk';
   $TZs['America/Grenada']='America/Grenada';
   $TZs['America/Guadeloupe']='America/Guadeloupe';
   $TZs['America/Guatemala']='America/Guatemala';
   $TZs['America/Guayaquil']='America/Guayaquil';
   $TZs['America/Guyana']='America/Guyana';
   $TZs['America/Halifax']='America/Halifax';
   $TZs['America/Havana']='America/Havana';
   $TZs['America/Hermosillo']='America/Hermosillo';
   $TZs['America/Indiana/Indianapolis']='America/Indiana/Indianapolis';
   $TZs['America/Indiana/Knox']='America/Indiana/Knox';
   $TZs['America/Indiana/Marengo']='America/Indiana/Marengo';
   $TZs['America/Indiana/Vevay']='America/Indiana/Vevay';
   $TZs['America/Indianapolis']='America/Indianapolis';
   $TZs['America/Inuvik']='America/Inuvik';
   $TZs['America/Iqaluit']='America/Iqaluit';
   $TZs['America/Jamaica']='America/Jamaica';
   $TZs['America/Jujuy']='America/Jujuy';
   $TZs['America/Juneau']='America/Juneau';
   $TZs['America/Knox_IN']='America/Knox_IN';
   $TZs['America/La_Paz']='America/La_Paz';
   $TZs['America/Lima']='America/Lima';
   $TZs['America/Los_Angeles']='America/Los_Angeles';
   $TZs['America/Louisville']='America/Louisville';
   $TZs['America/Maceio']='America/Maceio';
   $TZs['America/Managua']='America/Managua';
   $TZs['America/Manaus']='America/Manaus';
   $TZs['America/Martinique']='America/Martinique';
   $TZs['America/Mazatlan']='America/Mazatlan';
   $TZs['America/Mendoza']='America/Mendoza';
   $TZs['America/Menominee']='America/Menominee';
   $TZs['America/Mexico_City']='America/Mexico_City';
   $TZs['America/Miquelon']='America/Miquelon';
   $TZs['America/Montevideo']='America/Montevideo';
   $TZs['America/Montreal']='America/Montreal';
   $TZs['America/Montserrat']='America/Montserrat';
   $TZs['America/Nassau']='America/Nassau';
   $TZs['America/New_York']='America/New_York';
   $TZs['America/Nipigon']='America/Nipigon';
   $TZs['America/Nome']='America/Nome';
   $TZs['America/Noronha']='America/Noronha';
   $TZs['America/Panama']='America/Panama';
   $TZs['America/Pangnirtung']='America/Pangnirtung';
   $TZs['America/Paramaribo']='America/Paramaribo';
   $TZs['America/Phoenix']='America/Phoenix';
   $TZs['America/Port-au-Prince']='America/Port-au-Prince';
   $TZs['America/Porto_Acre']='America/Porto_Acre';
   $TZs['America/Port_of_Spain']='America/Port_of_Spain';
   $TZs['America/Porto_Velho']='America/Porto_Velho';
   $TZs['America/Puerto_Rico']='America/Puerto_Rico';
   $TZs['America/Rainy_River']='America/Rainy_River';
   $TZs['America/Rankin_Inlet']='America/Rankin_Inlet';
   $TZs['America/Regina']='America/Regina';
   $TZs['America/Rosario']='America/Rosario';
   $TZs['America/Santiago']='America/Santiago';
   $TZs['America/Santo_Domingo']='America/Santo_Domingo';
   $TZs['America/Sao_Paulo']='America/Sao_Paulo';
   $TZs['America/Scoresbysund']='America/Scoresbysund';
   $TZs['America/Shiprock']='America/Shiprock';
   $TZs['America/St_Johns']='America/St_Johns';
   $TZs['America/St_Kitts']='America/St_Kitts';
   $TZs['America/St_Lucia']='America/St_Lucia';
   $TZs['America/St_Thomas']='America/St_Thomas';
   $TZs['America/St_Vincent']='America/St_Vincent';
   $TZs['America/Swift_Current']='America/Swift_Current';
   $TZs['America/Tegucigalpa']='America/Tegucigalpa';
   $TZs['America/Thule']='America/Thule';
   $TZs['America/Thunder_Bay']='America/Thunder_Bay';
   $TZs['America/Tijuana']='America/Tijuana';
   $TZs['America/Tortola']='America/Tortola';
   $TZs['America/Vancouver']='America/Vancouver';
   $TZs['America/Virgin']='America/Virgin';
   $TZs['America/Whitehorse']='America/Whitehorse';
   $TZs['America/Winnipeg']='America/Winnipeg';
   $TZs['America/Yakutat']='America/Yakutat';
   $TZs['America/Yellowknife']='America/Yellowknife';
   $TZs['Antarctica/Casey']='Antarctica/Casey';
   $TZs['Antarctica/Davis']='Antarctica/Davis';
   $TZs['Antarctica/DumontDUrville']='Antarctica/DumontDUrville';
   $TZs['Antarctica/Mawson']='Antarctica/Mawson';
   $TZs['Antarctica/McMurdo']='Antarctica/McMurdo';
   $TZs['Antarctica/Palmer']='Antarctica/Palmer';
   $TZs['Antarctica/South_Pole']='Antarctica/South_Pole';
   $TZs['Antarctica/Syowa']='Antarctica/Syowa';
   $TZs['Arctic/Longyearbyen']='Arctic/Longyearbyen';
   $TZs['Asia/Aden']='Asia/Aden';
   $TZs['Asia/Almaty']='Asia/Almaty';
   $TZs['Asia/Amman']='Asia/Amman';
   $TZs['Asia/Anadyr']='Asia/Anadyr';
   $TZs['Asia/Aqtau']='Asia/Aqtau';
   $TZs['Asia/Aqtobe']='Asia/Aqtobe';
   $TZs['Asia/Ashkhabad']='Asia/Ashkhabad';
   $TZs['Asia/Baghdad']='Asia/Baghdad';
   $TZs['Asia/Bahrain']='Asia/Bahrain';
   $TZs['Asia/Baku']='Asia/Baku';
   $TZs['Asia/Bangkok']='Asia/Bangkok';
   $TZs['Asia/Beirut']='Asia/Beirut';
   $TZs['Asia/Bishkek']='Asia/Bishkek';
   $TZs['Asia/Brunei']='Asia/Brunei';
   $TZs['Asia/Calcutta']='Asia/Calcutta';
   $TZs['Asia/Chungking']='Asia/Chungking';
   $TZs['Asia/Colombo']='Asia/Colombo';
   $TZs['Asia/Dacca']='Asia/Dacca';
   $TZs['Asia/Damascus']='Asia/Damascus';
   $TZs['Asia/Dili']='Asia/Dili';
   $TZs['Asia/Dubai']='Asia/Dubai';
   $TZs['Asia/Dushanbe']='Asia/Dushanbe';
   $TZs['Asia/Gaza']='Asia/Gaza';
   $TZs['Asia/Harbin']='Asia/Harbin';
   $TZs['Asia/Hong_Kong']='Asia/Hong_Kong';
   $TZs['Asia/Hovd']='Asia/Hovd';
   $TZs['Asia/Irkutsk']='Asia/Irkutsk';
   $TZs['Asia/Istanbul']='Asia/Istanbul';
   $TZs['Asia/Jakarta']='Asia/Jakarta';
   $TZs['Asia/Jayapura']='Asia/Jayapura';
   $TZs['Asia/Jerusalem']='Asia/Jerusalem';
   $TZs['Asia/Kabul']='Asia/Kabul';
   $TZs['Asia/Kamchatka']='Asia/Kamchatka';
   $TZs['Asia/Karachi']='Asia/Karachi';
   $TZs['Asia/Kashgar']='Asia/Kashgar';
   $TZs['Asia/Katmandu']='Asia/Katmandu';
   $TZs['Asia/Krasnoyarsk']='Asia/Krasnoyarsk';
   $TZs['Asia/Kuala_Lumpur']='Asia/Kuala_Lumpur';
   $TZs['Asia/Kuching']='Asia/Kuching';
   $TZs['Asia/Kuwait']='Asia/Kuwait';
   $TZs['Asia/Macao']='Asia/Macao';
   $TZs['Asia/Magadan']='Asia/Magadan';
   $TZs['Asia/Manila']='Asia/Manila';
   $TZs['Asia/Muscat']='Asia/Muscat';
   $TZs['Asia/Nicosia']='Asia/Nicosia';
   $TZs['Asia/Novosibirsk']='Asia/Novosibirsk';
   $TZs['Asia/Omsk']='Asia/Omsk';
   $TZs['Asia/Phnom_Penh']='Asia/Phnom_Penh';
   $TZs['Asia/Pyongyang']='Asia/Pyongyang';
   $TZs['Asia/Qatar']='Asia/Qatar';
   $TZs['Asia/Rangoon']='Asia/Rangoon';
   $TZs['Asia/Riyadh']='Asia/Riyadh';
   $TZs['Asia/Riyadh87']='Asia/Riyadh87';
   $TZs['Asia/Riyadh88']='Asia/Riyadh88';
   $TZs['Asia/Riyadh89']='Asia/Riyadh89';
   $TZs['Asia/Saigon']='Asia/Saigon';
   $TZs['Asia/Samarkand']='Asia/Samarkand';
   $TZs['Asia/Seoul']='Asia/Seoul';
   $TZs['Asia/Shanghai']='Asia/Shanghai';
   $TZs['Asia/Singapore']='Asia/Singapore';
   $TZs['Asia/Taipei']='Asia/Taipei';
   $TZs['Asia/Tashkent']='Asia/Tashkent';
   $TZs['Asia/Tbilisi']='Asia/Tbilisi';
   $TZs['Asia/Tehran']='Asia/Tehran';
   $TZs['Asia/Tel_Aviv']='Asia/Tel_Aviv';
   $TZs['Asia/Thimbu']='Asia/Thimbu';
   $TZs['Asia/Tokyo']='Asia/Tokyo';
   $TZs['Asia/Ujung_Pandang']='Asia/Ujung_Pandang';
   $TZs['Asia/Ulaanbaatar']='Asia/Ulaanbaatar';
   $TZs['Asia/Ulan_Bator']='Asia/Ulan_Bator';
   $TZs['Asia/Urumqi']='Asia/Urumqi';
   $TZs['Asia/Vientiane']='Asia/Vientiane';
   $TZs['Asia/Vladivostok']='Asia/Vladivostok';
   $TZs['Asia/Yakutsk']='Asia/Yakutsk';
   $TZs['Asia/Yekaterinburg']='Asia/Yekaterinburg';
   $TZs['Asia/Yerevan']='Asia/Yerevan';
   $TZs['Atlantic/Azores']='Atlantic/Azores';
   $TZs['Atlantic/Bermuda']='Atlantic/Bermuda';
   $TZs['Atlantic/Canary']='Atlantic/Canary';
   $TZs['Atlantic/Cape_Verde']='Atlantic/Cape_Verde';
   $TZs['Atlantic/Faeroe']='Atlantic/Faeroe';
   $TZs['Atlantic/Jan_Mayen']='Atlantic/Jan_Mayen';
   $TZs['Atlantic/Madeira']='Atlantic/Madeira';
   $TZs['Atlantic/Reykjavik']='Atlantic/Reykjavik';
   $TZs['Atlantic/South_Georgia']='Atlantic/South_Georgia';
   $TZs['Atlantic/Stanley']='Atlantic/Stanley';
   $TZs['Atlantic/St_Helena']='Atlantic/St_Helena';
   $TZs['Australia/ACT']='Australia/ACT';
   $TZs['Australia/Adelaide']='Australia/Adelaide';
   $TZs['Australia/Brisbane']='Australia/Brisbane';
   $TZs['Australia/Broken_Hill']='Australia/Broken_Hill';
   $TZs['Australia/Canberra']='Australia/Canberra';
   $TZs['Australia/Darwin']='Australia/Darwin';
   $TZs['Australia/Hobart']='Australia/Hobart';
   $TZs['Australia/LHI']='Australia/LHI';
   $TZs['Australia/Lindeman']='Australia/Lindeman';
   $TZs['Australia/Lord_Howe']='Australia/Lord_Howe';
   $TZs['Australia/Melbourne']='Australia/Melbourne';
   $TZs['Australia/North']='Australia/North';
   $TZs['Australia/NSW']='Australia/NSW';
   $TZs['Australia/Perth']='Australia/Perth';
   $TZs['Australia/Queensland']='Australia/Queensland';
   $TZs['Australia/South']='Australia/South';
   $TZs['Australia/Sydney']='Australia/Sydney';
   $TZs['Australia/Tasmania']='Australia/Tasmania';
   $TZs['Australia/Victoria']='Australia/Victoria';
   $TZs['Australia/West']='Australia/West';
   $TZs['Australia/Yancowinna']='Australia/Yancowinna';
   $TZs['Brazil/Acre']='Brazil/Acre';
   $TZs['Brazil/DeNoronha']='Brazil/DeNoronha';
   $TZs['Brazil/East']='Brazil/East';
   $TZs['Brazil/West']='Brazil/West';
   $TZs['Canada/Atlantic']='Canada/Atlantic';
   $TZs['Canada/Central']='Canada/Central';
   $TZs['Canada/Eastern']='Canada/Eastern';
   $TZs['Canada/East-Saskatchewan']='Canada/East-Saskatchewan';
   $TZs['Canada/Mountain']='Canada/Mountain';
   $TZs['Canada/Newfoundland']='Canada/Newfoundland';
   $TZs['Canada/Pacific']='Canada/Pacific';
   $TZs['Canada/Saskatchewan']='Canada/Saskatchewan';
   $TZs['Canada/Yukon']='Canada/Yukon';
   $TZs['CET']='CET';
   $TZs['Chile/Continental']='Chile/Continental';
   $TZs['Chile/EasterIsland']='Chile/EasterIsland';
   $TZs['China/Beijing']='China/Beijing';
   $TZs['China/Shanghai']='China/Shanghai';
   $TZs['CST6CDT']='CST6CDT';
   $TZs['Cuba']='Cuba';
   $TZs['EET']='EET';
   $TZs['Egypt']='Egypt';
   $TZs['Eire']='Eire';
   $TZs['EST']='EST';
   $TZs['EST5EDT']='EST5EDT';
   $TZs['Europe/Amsterdam']='Europe/Amsterdam';
   $TZs['Europe/Andorra']='Europe/Andorra';
   $TZs['Europe/Athens']='Europe/Athens';
   $TZs['Europe/Belfast']='Europe/Belfast';
   $TZs['Europe/Belgrade']='Europe/Belgrade';
   $TZs['Europe/Berlin']='Europe/Berlin';
   $TZs['Europe/Bratislava']='Europe/Bratislava';
   $TZs['Europe/Brussels']='Europe/Brussels';
   $TZs['Europe/Bucharest']='Europe/Bucharest';
   $TZs['Europe/Budapest']='Europe/Budapest';
   $TZs['Europe/Chisinau']='Europe/Chisinau';
   $TZs['Europe/Copenhagen']='Europe/Copenhagen';
   $TZs['Europe/Dublin']='Europe/Dublin';
   $TZs['Europe/Gibraltar']='Europe/Gibraltar';
   $TZs['Europe/Helsinki']='Europe/Helsinki';
   $TZs['Europe/Istanbul']='Europe/Istanbul';
   $TZs['Europe/Kaliningrad']='Europe/Kaliningrad';
   $TZs['Europe/Kiev']='Europe/Kiev';
   $TZs['Europe/Lisbon']='Europe/Lisbon';
   $TZs['Europe/Ljubljana']='Europe/Ljubljana';
   $TZs['Europe/London']='Europe/London';
   $TZs['Europe/Luxembourg']='Europe/Luxembourg';
   $TZs['Europe/Madrid']='Europe/Madrid';
   $TZs['Europe/Malta']='Europe/Malta';
   $TZs['Europe/Minsk']='Europe/Minsk';
   $TZs['Europe/Monaco']='Europe/Monaco';
   $TZs['Europe/Moscow']='Europe/Moscow';
   $TZs['Europe/Oslo']='Europe/Oslo';
   $TZs['Europe/Paris']='Europe/Paris';
   $TZs['Europe/Prague']='Europe/Prague';
   $TZs['Europe/Riga']='Europe/Riga';
   $TZs['Europe/Rome']='Europe/Rome';
   $TZs['Europe/Samara']='Europe/Samara';
   $TZs['Europe/San_Marino']='Europe/San_Marino';
   $TZs['Europe/Sarajevo']='Europe/Sarajevo';
   $TZs['Europe/Simferopol']='Europe/Simferopol';
   $TZs['Europe/Skopje']='Europe/Skopje';
   $TZs['Europe/Sofia']='Europe/Sofia';
   $TZs['Europe/Stockholm']='Europe/Stockholm';
   $TZs['Europe/Tallinn']='Europe/Tallinn';
   $TZs['Europe/Tirane']='Europe/Tirane';
   $TZs['Europe/Tiraspol']='Europe/Tiraspol';
   $TZs['Europe/Uzhgorod']='Europe/Uzhgorod';
   $TZs['Europe/Vaduz']='Europe/Vaduz';
   $TZs['Europe/Vatican']='Europe/Vatican';
   $TZs['Europe/Vienna']='Europe/Vienna';
   $TZs['Europe/Vilnius']='Europe/Vilnius';
   $TZs['Europe/Warsaw']='Europe/Warsaw';
   $TZs['Europe/Zagreb']='Europe/Zagreb';
   $TZs['Europe/Zaporozhye']='Europe/Zaporozhye';
   $TZs['Europe/Zurich']='Europe/Zurich';
   $TZs['Factory']='Factory';
   $TZs['GB']='GB';
   $TZs['GB-Eire']='GB-Eire';
   $TZs['GMT0']='GMT0';
   $TZs['GMT-0']='GMT-0';
   $TZs['GMT+0']='GMT+0';
   $TZs['Greenwich']='Greenwich';
   $TZs['Hongkong']='Hongkong';
   $TZs['HST']='HST';
   $TZs['Iceland']='Iceland';
   $TZs['Indian/Antananarivo']='Indian/Antananarivo';
   $TZs['Indian/Chagos']='Indian/Chagos';
   $TZs['Indian/Christmas']='Indian/Christmas';
   $TZs['Indian/Cocos']='Indian/Cocos';
   $TZs['Indian/Comoro']='Indian/Comoro';
   $TZs['Indian/Kerguelen']='Indian/Kerguelen';
   $TZs['Indian/Mahe']='Indian/Mahe';
   $TZs['Indian/Maldives']='Indian/Maldives';
   $TZs['Indian/Mauritius']='Indian/Mauritius';
   $TZs['Indian/Mayotte']='Indian/Mayotte';
   $TZs['Indian/Reunion']='Indian/Reunion';
   $TZs['Iran']='Iran';
   $TZs['Israel']='Israel';
   $TZs['Jamaica']='Jamaica';
   $TZs['Japan']='Japan';
   $TZs['Kwajalein']='Kwajalein';
   $TZs['Libya']='Libya';
   $TZs['MET']='MET';
   $TZs['Mexico/BajaNorte']='Mexico/BajaNorte';
   $TZs['Mexico/BajaSur']='Mexico/BajaSur';
   $TZs['Mexico/General']='Mexico/General';
   $TZs['Mideast/Riyadh87']='Mideast/Riyadh87';
   $TZs['Mideast/Riyadh88']='Mideast/Riyadh88';
   $TZs['Mideast/Riyadh89']='Mideast/Riyadh89';
   $TZs['MST']='MST';
   $TZs['MST7MDT']='MST7MDT';
   $TZs['Navajo']='Navajo';
   $TZs['NZ']='NZ';
   $TZs['NZ-CHAT']='NZ-CHAT';
   $TZs['Pacific/Apia']='Pacific/Apia';
   $TZs['Pacific/Auckland']='Pacific/Auckland';
   $TZs['Pacific/Chatham']='Pacific/Chatham';
   $TZs['Pacific/Easter']='Pacific/Easter';
   $TZs['Pacific/Efate']='Pacific/Efate';
   $TZs['Pacific/Enderbury']='Pacific/Enderbury';
   $TZs['Pacific/Fakaofo']='Pacific/Fakaofo';
   $TZs['Pacific/Fiji']='Pacific/Fiji';
   $TZs['Pacific/Funafuti']='Pacific/Funafuti';
   $TZs['Pacific/Galapagos']='Pacific/Galapagos';
   $TZs['Pacific/Gambier']='Pacific/Gambier';
   $TZs['Pacific/Guadalcanal']='Pacific/Guadalcanal';
   $TZs['Pacific/Guam']='Pacific/Guam';
   $TZs['Pacific/Honolulu']='Pacific/Honolulu';
   $TZs['Pacific/Johnston']='Pacific/Johnston';
   $TZs['Pacific/Kiritimati']='Pacific/Kiritimati';
   $TZs['Pacific/Kosrae']='Pacific/Kosrae';
   $TZs['Pacific/Kwajalein']='Pacific/Kwajalein';
   $TZs['Pacific/Majuro']='Pacific/Majuro';
   $TZs['Pacific/Marquesas']='Pacific/Marquesas';
   $TZs['Pacific/Midway']='Pacific/Midway';
   $TZs['Pacific/Nauru']='Pacific/Nauru';
   $TZs['Pacific/Niue']='Pacific/Niue';
   $TZs['Pacific/Norfolk']='Pacific/Norfolk';
   $TZs['Pacific/Noumea']='Pacific/Noumea';
   $TZs['Pacific/Pago_Pago']='Pacific/Pago_Pago';
   $TZs['Pacific/Palau']='Pacific/Palau';
   $TZs['Pacific/Pitcairn']='Pacific/Pitcairn';
   $TZs['Pacific/Ponape']='Pacific/Ponape';
   $TZs['Pacific/Port_Moresby']='Pacific/Port_Moresby';
   $TZs['Pacific/Rarotonga']='Pacific/Rarotonga';
   $TZs['Pacific/Saipan']='Pacific/Saipan';
   $TZs['Pacific/Samoa']='Pacific/Samoa';
   $TZs['Pacific/Tahiti']='Pacific/Tahiti';
   $TZs['Pacific/Tarawa']='Pacific/Tarawa';
   $TZs['Pacific/Tongatapu']='Pacific/Tongatapu';
   $TZs['Pacific/Truk']='Pacific/Truk';
   $TZs['Pacific/Wake']='Pacific/Wake';
   $TZs['Pacific/Wallis']='Pacific/Wallis';
   $TZs['Pacific/Yap']='Pacific/Yap';
   $TZs['Poland']='Poland';
   $TZs['Portugal']='Portugal';
   $TZs['PRC']='PRC';
   $TZs['PST8PDT']='PST8PDT';
   $TZs['ROC']='ROC';
   $TZs['ROK']='ROK';
   $TZs['Singapore']='Singapore';
   $TZs['Turkey']='Turkey';
   $TZs['UCT']='UCT';
   $TZs['Universal']='Universal';
   $TZs['US/Alaska']='US/Alaska';
   $TZs['US/Aleutian']='US/Aleutian';
   $TZs['US/Arizona']='US/Arizona';
   $TZs['US/Central']='US/Central';
   $TZs['US/Eastern']='US/Eastern';
   $TZs['US/East-Indiana']='US/East-Indiana';
   $TZs['US/Hawaii']='US/Hawaii';
   $TZs['US/Indiana-Starke']='US/Indiana-Starke';
   $TZs['US/Michigan']='US/Michigan';
   $TZs['US/Mountain']='US/Mountain';
   $TZs['US/Pacific']='US/Pacific';
   $TZs['US/Samoa']='US/Samoa';
   $TZs['UTC']='UTC';
   $TZs['WET']='WET';
   $TZs['W-SU']='W-SU';
   $TZs['Zulu']='Zulu';

   return $TZs;
}

?>
