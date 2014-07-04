<?php


function cayl_config_form($form, &$form_state) {
  $form['cayl_enabled'] = array(
    '#type' => 'checkbox',
    '#title' => t('Enable Amber'),
    '#default_value' => variable_get('cayl_enabled', 1),
    '#description' => t('Enable all functionality (front-end widgets, caching, and link checking). Disabling this will not remove existing cached data.'),
  );
  $form['cayl_cache'] = array(
    '#type' => 'fieldset',
    '#title' => t('Cache Settings'),
    '#description' => t("Settings that control how the cache is managed"),
  );
  $form['cayl_cache']['cayl_max_file'] = array(
    '#type' => 'textfield',
    '#title' => t('Maximum file size (kB)'),
    '#size' => 10,
    '#default_value' => variable_get('cayl_max_file', '1000'),
    '#description' => t('The maximum file size of an item to be cached. Items (or assets associated with items) that exceed this size will not be cached'),
  );
  $form['cayl_cache']['cayl_max_disk'] = array(
    '#type' => 'textfield',
    '#title' => t('Maximum disk usage (MB)'),
    '#size' => 10,
    '#default_value' => variable_get('cayl_max_disk', '1000'),
    '#description' => t('The maximum amount of disk space to be used for all cached items. If this disk space usage is exceeded, old items will be removed from the cache'),
  );
  $form['cayl_cache']['cayl_storage_location'] = array(
    '#type' => 'textfield',
    '#title' => t('Storage location'),
    '#default_value' => variable_get('cayl_storage_location', 'sites/default/files/cayl'),
    '#description' => t('Path to the location where sites are stored on disk, relative to the Drupal root'),
  );

  $form['cayl_cache']['advanced'] = array(
    '#type' => 'fieldset',
    '#title' => t('Advanced'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );

  $form['cayl_cache']['advanced']['cayl_update_strategy'] = array(
    '#type' => 'select',
    '#title' => t('Cache update strategy'),
    '#options' => array (
      0 => t('Update the cache periodically'),
      1 => t('Do not update a cached item after it is cached'),
    ),
    '#default_value' => variable_get('cayl_update_strategy', 0),
    '#description' => t('Whether an item in the cache should be updated periodically, or never change after it\'s cached for the first time'),
  );

  $form['cayl_cache']['advanced']['cayl_excluded_sites'] = array(
    '#type' => 'textarea',
    '#title' => t('Excluded sites'),
    '#default_value' => variable_get('cayl_excluded_sites', ''),
    '#description' => t('A list of hostnames or IP addresses. Links to servers that match any of the excluded sites will not be cached. A link matches an excluded site if the excluded site is contained within the link\'s hostname using substring matching'),
  );

  $form['cayl_cache']['advanced']['cayl_excluded_formats'] = array(
    '#type' => 'textarea',
    '#title' => t('Excluded file formats'),
    '#default_value' => variable_get('cayl_excluded_formats', ''),
    '#description' => t('A list of of excluded mime-types for files that will not be cached'),
  );

  $form['cayl_delivery'] = array(
    '#type' => 'fieldset',
    '#title' => t('Cache Delivery'),
    '#description' => t("Settings that control the user experience"),
  );

  $form['cayl_delivery']['cayl_available_action'] = array(
    '#type' => 'select',
    '#title' => t('Available site links'),
    '#options' => array (
      CAYL_ACTION_NONE => t('None'),
      CAYL_ACTION_HOVER => t('Hover'),
      CAYL_ACTION_POPUP => t('Link to popup'),
    ),
    '#default_value' => variable_get('cayl_available_action', 0),
    '#description' => t('What action should be taken for links to available sites.'),
  );

  $form['cayl_delivery']['cayl_available_action_hover'] = array(
    '#type' => 'textfield',
    '#title' => t('Available sites hover delay (seconds)'),
    '#size' => 2,
    '#default_value' => variable_get('cayl_available_action_hover', 2),
    '#description' => t('How long before the hover box appears for links to available sites'),
    '#states' => array(
      'visible' => array(
        ':input[name="cayl_available_action"]' => array('value' => '1'),
      ),
     ),
  );

  $form['cayl_delivery']['cayl_unavailable_action'] = array(
    '#type' => 'select',
    '#title' => t('Unavailable site links'),
    '#options' => array (
      CAYL_ACTION_NONE => t('None'),
      CAYL_ACTION_HOVER => t('Hover'),
      CAYL_ACTION_POPUP => t('Link to popup'),
      CAYL_ACTION_CACHE => t('Link to cache'),
    ),
    '#default_value' => variable_get('cayl_unavailable_action', 1),
    '#description' => t('What action should be taken for links to unavailable sites.'),
  );

  $form['cayl_delivery']['cayl_unavailable_action_hover'] = array(
    '#type' => 'textfield',
    '#title' => t('Unavailable sites hover delay (seconds)'),
    '#size' => 2,
    '#default_value' => variable_get('cayl_unavailable_action_hover', 2),
    '#description' => t('How long before the hover box appears for links to available sites'),
    '#states' => array(
      'visible' => array(
        ':input[name="cayl_unavailable_action"]' => array('value' => '1'),
      ),
     ),
  );

  $form['cayl_country'] = array(
    '#type' => 'fieldset',
    '#title' => t('Cache Delivery for a specific country'),
    '#description' => t("Customize the user experience for visitors from a specific country"),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );

  $form['cayl_country']['cayl_country_id'] = array(
    '#type' => 'select',
    '#title' => t('Country'),
    '#options' => array("" => "") + _cayl_get_countries(),
    '#default_value' => variable_get('cayl_country_id', ""),
    '#description' => t('Specify cache delivery setting for browsers from this country'),
  );

  $form['cayl_country']['cayl_country_proxy'] = array(
    '#type' => 'textfield',
    '#title' => t('Proxy'),
    '#default_value' => variable_get('cayl_country_proxy', ''),
    '#description' => t('IP address or hostname for a proxy to be used when checking site availability for users in this country'),
    '#states' => array(
      'visible' => array(
        ':input[name="cayl_country_id"]' => array('!value' => ''),
      ),
     ),
  );

  $form['cayl_country']['cayl_country_available_action'] = array(
    '#type' => 'select',
    '#title' => t('Available site links'),
    '#options' => array (
      CAYL_ACTION_NONE => t('None'),
      CAYL_ACTION_HOVER => t('Hover'),
      CAYL_ACTION_POPUP => t('Link to popup'),
    ),
    '#default_value' => variable_get('cayl_country_available_action', 0),
    '#description' => t('What action should be taken for links to available sites.'),
    '#states' => array(
      'visible' => array(
        ':input[name="cayl_country_id"]' => array('!value' => ''),
      ),
     ),
  );

  $form['cayl_country']['cayl_country_available_action_hover'] = array(
    '#type' => 'textfield',
    '#title' => t('Available sites hover delay (seconds)'),
    '#size' => 2,
    '#default_value' => variable_get('cayl_country_available_action_hover', 2),
    '#description' => t('How long before the hover box appears for links to available sites'),
    '#states' => array(
      'visible' => array(
        ':input[name="cayl_country_id"]' => array('!value' => ''),
        ':input[name="cayl_country_available_action"]' => array('value' => '1'),
      ),
     ),
  );

  $form['cayl_country']['cayl_country_unavailable_action'] = array(
    '#type' => 'select',
    '#title' => t('Unavailable site links'),
    '#options' => array (
      CAYL_ACTION_NONE => t('None'),
      CAYL_ACTION_HOVER => t('Hover'),
      CAYL_ACTION_POPUP => t('Link to popup'),
      CAYL_ACTION_CACHE => t('Link to cache'),
    ),
    '#default_value' => variable_get('cayl_country_unavailable_action', 1),
    '#description' => t('What action should be taken for links to unavailable sites.'),
    '#states' => array(
      'visible' => array(
        ':input[name="cayl_country_id"]' => array('!value' => ''),
      ),
     ),
  );

  $form['cayl_country']['cayl_country_unavailable_action_hover'] = array(
    '#type' => 'textfield',
    '#title' => t('Unavailable sites hover delay (seconds)'),
    '#size' => 2,
    '#default_value' => variable_get('cayl_country_unavailable_action_hover', 2),
    '#description' => t('How long before the hover box appears for links to available sites'),
    '#states' => array(
      'visible' => array(
        ':input[name="cayl_country_id"]' => array('!value' => ''),
        ':input[name="cayl_country_unavailable_action"]' => array('value' => '1'),
      ),
     ),
  );

  $form = system_settings_form($form);
  $form['#submit'][] = 'cayl_settings_submit';
  return $form;
}

/* Custom submit handler to flush filter cache after the settings are updated */
function cayl_settings_submit($form, &$form_state) {
  cache_clear_all('*', 'cache_filter',TRUE);
  cache_clear_all('*', 'cache_field',TRUE);
}

function _cayl_get_countries() {
  if (function_exists("country_get_list")) {
    $countries = country_get_list(); // From Locale module
  } else {
    $countries = array(
      'AD' => t('Andorra'),
      'AE' => t('United Arab Emirates'),
      'AF' => t('Afghanistan'),
      'AG' => t('Antigua and Barbuda'),
      'AI' => t('Anguilla'),
      'AL' => t('Albania'),
      'AM' => t('Armenia'),
      'AN' => t('Netherlands Antilles'),
      'AO' => t('Angola'),
      'AQ' => t('Antarctica'),
      'AR' => t('Argentina'),
      'AS' => t('American Samoa'),
      'AT' => t('Austria'),
      'AU' => t('Australia'),
      'AW' => t('Aruba'),
      'AX' => t('Aland Islands'),
      'AZ' => t('Azerbaijan'),
      'BA' => t('Bosnia and Herzegovina'),
      'BB' => t('Barbados'),
      'BD' => t('Bangladesh'),
      'BE' => t('Belgium'),
      'BF' => t('Burkina Faso'),
      'BG' => t('Bulgaria'),
      'BH' => t('Bahrain'),
      'BI' => t('Burundi'),
      'BJ' => t('Benin'),
      'BL' => t('Saint Barthélemy'),
      'BM' => t('Bermuda'),
      'BN' => t('Brunei'),
      'BO' => t('Bolivia'),
      'BR' => t('Brazil'),
      'BS' => t('Bahamas'),
      'BT' => t('Bhutan'),
      'BV' => t('Bouvet Island'),
      'BW' => t('Botswana'),
      'BY' => t('Belarus'),
      'BZ' => t('Belize'),
      'CA' => t('Canada'),
      'CC' => t('Cocos (Keeling) Islands'),
      'CD' => t('Congo (Kinshasa)'),
      'CF' => t('Central African Republic'),
      'CG' => t('Congo (Brazzaville)'),
      'CH' => t('Switzerland'),
      'CI' => t('Ivory Coast'),
      'CK' => t('Cook Islands'),
      'CL' => t('Chile'),
      'CM' => t('Cameroon'),
      'CN' => t('China'),
      'CO' => t('Colombia'),
      'CR' => t('Costa Rica'),
      'CU' => t('Cuba'),
      'CW' => t('Curaçao'),
      'CV' => t('Cape Verde'),
      'CX' => t('Christmas Island'),
      'CY' => t('Cyprus'),
      'CZ' => t('Czech Republic'),
      'DE' => t('Germany'),
      'DJ' => t('Djibouti'),
      'DK' => t('Denmark'),
      'DM' => t('Dominica'),
      'DO' => t('Dominican Republic'),
      'DZ' => t('Algeria'),
      'EC' => t('Ecuador'),
      'EE' => t('Estonia'),
      'EG' => t('Egypt'),
      'EH' => t('Western Sahara'),
      'ER' => t('Eritrea'),
      'ES' => t('Spain'),
      'ET' => t('Ethiopia'),
      'FI' => t('Finland'),
      'FJ' => t('Fiji'),
      'FK' => t('Falkland Islands'),
      'FM' => t('Micronesia'),
      'FO' => t('Faroe Islands'),
      'FR' => t('France'),
      'GA' => t('Gabon'),
      'GB' => t('United Kingdom'),
      'GD' => t('Grenada'),
      'GE' => t('Georgia'),
      'GF' => t('French Guiana'),
      'GG' => t('Guernsey'),
      'GH' => t('Ghana'),
      'GI' => t('Gibraltar'),
      'GL' => t('Greenland'),
      'GM' => t('Gambia'),
      'GN' => t('Guinea'),
      'GP' => t('Guadeloupe'),
      'GQ' => t('Equatorial Guinea'),
      'GR' => t('Greece'),
      'GS' => t('South Georgia and the South Sandwich Islands'),
      'GT' => t('Guatemala'),
      'GU' => t('Guam'),
      'GW' => t('Guinea-Bissau'),
      'GY' => t('Guyana'),
      'HK' => t('Hong Kong S.A.R., China'),
      'HM' => t('Heard Island and McDonald Islands'),
      'HN' => t('Honduras'),
      'HR' => t('Croatia'),
      'HT' => t('Haiti'),
      'HU' => t('Hungary'),
      'ID' => t('Indonesia'),
      'IE' => t('Ireland'),
      'IL' => t('Israel'),
      'IM' => t('Isle of Man'),
      'IN' => t('India'),
      'IO' => t('British Indian Ocean Territory'),
      'IQ' => t('Iraq'),
      'IR' => t('Iran'),
      'IS' => t('Iceland'),
      'IT' => t('Italy'),
      'JE' => t('Jersey'),
      'JM' => t('Jamaica'),
      'JO' => t('Jordan'),
      'JP' => t('Japan'),
      'KE' => t('Kenya'),
      'KG' => t('Kyrgyzstan'),
      'KH' => t('Cambodia'),
      'KI' => t('Kiribati'),
      'KM' => t('Comoros'),
      'KN' => t('Saint Kitts and Nevis'),
      'KP' => t('North Korea'),
      'KR' => t('South Korea'),
      'KW' => t('Kuwait'),
      'KY' => t('Cayman Islands'),
      'KZ' => t('Kazakhstan'),
      'LA' => t('Laos'),
      'LB' => t('Lebanon'),
      'LC' => t('Saint Lucia'),
      'LI' => t('Liechtenstein'),
      'LK' => t('Sri Lanka'),
      'LR' => t('Liberia'),
      'LS' => t('Lesotho'),
      'LT' => t('Lithuania'),
      'LU' => t('Luxembourg'),
      'LV' => t('Latvia'),
      'LY' => t('Libya'),
      'MA' => t('Morocco'),
      'MC' => t('Monaco'),
      'MD' => t('Moldova'),
      'ME' => t('Montenegro'),
      'MF' => t('Saint Martin (French part)'),
      'MG' => t('Madagascar'),
      'MH' => t('Marshall Islands'),
      'MK' => t('Macedonia'),
      'ML' => t('Mali'),
      'MM' => t('Myanmar'),
      'MN' => t('Mongolia'),
      'MO' => t('Macao S.A.R., China'),
      'MP' => t('Northern Mariana Islands'),
      'MQ' => t('Martinique'),
      'MR' => t('Mauritania'),
      'MS' => t('Montserrat'),
      'MT' => t('Malta'),
      'MU' => t('Mauritius'),
      'MV' => t('Maldives'),
      'MW' => t('Malawi'),
      'MX' => t('Mexico'),
      'MY' => t('Malaysia'),
      'MZ' => t('Mozambique'),
      'NA' => t('Namibia'),
      'NC' => t('New Caledonia'),
      'NE' => t('Niger'),
      'NF' => t('Norfolk Island'),
      'NG' => t('Nigeria'),
      'NI' => t('Nicaragua'),
      'NL' => t('Netherlands'),
      'NO' => t('Norway'),
      'NP' => t('Nepal'),
      'NR' => t('Nauru'),
      'NU' => t('Niue'),
      'NZ' => t('New Zealand'),
      'OM' => t('Oman'),
      'PA' => t('Panama'),
      'PE' => t('Peru'),
      'PF' => t('French Polynesia'),
      'PG' => t('Papua New Guinea'),
      'PH' => t('Philippines'),
      'PK' => t('Pakistan'),
      'PL' => t('Poland'),
      'PM' => t('Saint Pierre and Miquelon'),
      'PN' => t('Pitcairn'),
      'PR' => t('Puerto Rico'),
      'PS' => t('Palestinian Territory'),
      'PT' => t('Portugal'),
      'PW' => t('Palau'),
      'PY' => t('Paraguay'),
      'QA' => t('Qatar'),
      'RE' => t('Reunion'),
      'RO' => t('Romania'),
      'RS' => t('Serbia'),
      'RU' => t('Russia'),
      'RW' => t('Rwanda'),
      'SA' => t('Saudi Arabia'),
      'SB' => t('Solomon Islands'),
      'SC' => t('Seychelles'),
      'SD' => t('Sudan'),
      'SE' => t('Sweden'),
      'SG' => t('Singapore'),
      'SH' => t('Saint Helena'),
      'SI' => t('Slovenia'),
      'SJ' => t('Svalbard and Jan Mayen'),
      'SK' => t('Slovakia'),
      'SL' => t('Sierra Leone'),
      'SM' => t('San Marino'),
      'SN' => t('Senegal'),
      'SO' => t('Somalia'),
      'SR' => t('Suriname'),
      'ST' => t('Sao Tome and Principe'),
      'SV' => t('El Salvador'),
      'SY' => t('Syria'),
      'SZ' => t('Swaziland'),
      'TC' => t('Turks and Caicos Islands'),
      'TD' => t('Chad'),
      'TF' => t('French Southern Territories'),
      'TG' => t('Togo'),
      'TH' => t('Thailand'),
      'TJ' => t('Tajikistan'),
      'TK' => t('Tokelau'),
      'TL' => t('Timor-Leste'),
      'TM' => t('Turkmenistan'),
      'TN' => t('Tunisia'),
      'TO' => t('Tonga'),
      'TR' => t('Turkey'),
      'TT' => t('Trinidad and Tobago'),
      'TV' => t('Tuvalu'),
      'TW' => t('Taiwan'),
      'TZ' => t('Tanzania'),
      'UA' => t('Ukraine'),
      'UG' => t('Uganda'),
      'UM' => t('United States Minor Outlying Islands'),
      'US' => t('United States'),
      'UY' => t('Uruguay'),
      'UZ' => t('Uzbekistan'),
      'VA' => t('Vatican'),
      'VC' => t('Saint Vincent and the Grenadines'),
      'VE' => t('Venezuela'),
      'VG' => t('British Virgin Islands'),
      'VI' => t('U.S. Virgin Islands'),
      'VN' => t('Vietnam'),
      'VU' => t('Vanuatu'),
      'WF' => t('Wallis and Futuna'),
      'WS' => t('Samoa'),
      'YE' => t('Yemen'),
      'YT' => t('Mayotte'),
      'ZA' => t('South Africa'),
      'ZM' => t('Zambia'),
      'ZW' => t('Zimbabwe'),
    );
    natcasesort($countries);

  }
  return $countries;
}


