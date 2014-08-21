<?php


function amber_config_form($form, &$form_state) {
  $form['amber_enabled'] = array(
    '#type' => 'checkbox',
    '#title' => t('Enable Amber'),
    '#default_value' => variable_get('amber_enabled', 1),
    '#description' => t('Enable all functionality, including the preservation of new captures and notifications to site visitors. Disabling this will not remove existing captures.'),
  );
  $form['amber_cache'] = array(
    '#type' => 'fieldset',
    '#title' => t('Storage Settings'),
    '#description' => t("Control how Amber stores captures"),
  );
  $form['amber_cache']['amber_max_file'] = array(
    '#type' => 'textfield',
    '#title' => t('Maximum file size (kB)'),
    '#size' => 10,
    '#default_value' => variable_get('amber_max_file', '1000'),
    '#description' => t('Amber will store captures up to a specified size. Links to pages that exceed this size will not be preserved.'),
  );
  $form['amber_cache']['amber_max_disk'] = array(
    '#type' => 'textfield',
    '#title' => t('Maximum disk usage (MB)'),
    '#size' => 10,
    '#default_value' => variable_get('amber_max_disk', '1000'),
    '#description' => t('The maximum amount of disk space to be used for all cached items. If this disk space usage is exceeded, old items will be removed from the cache'),
  );
  $form['amber_cache']['amber_storage_location'] = array(
    '#type' => 'textfield',
    '#title' => t('Storage location'),
    '#default_value' => variable_get('amber_storage_location', 'sites/default/files/amber'),
    '#description' => t('Path to the location where captures are stored on disk, relative to the Drupal root'),
  );

  $form['amber_cache']['advanced'] = array(
    '#type' => 'fieldset',
    '#title' => t('Advanced'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );

  $form['amber_cache']['advanced']['amber_update_strategy'] = array(
    '#type' => 'select',
    '#title' => t('Update strategy for captures'),
    '#options' => array (
      0 => t('Update captures periodically'),
      1 => t('Do not update captures'),
    ),
    '#default_value' => variable_get('amber_update_strategy', 0),
    '#description' => t('Select "Do not update" if you want to preserve links at the time the content is published. Otherwise, link storage will be periodically updated.'),
  );

  global $base_url;
  $form['amber_cache']['advanced']['amber_excluded_sites'] = array(
    '#type' => 'textarea',
    '#title' => t('Excluded sites'),
    '#default_value' => variable_get('amber_excluded_sites', parse_url($base_url,PHP_URL_HOST)),
    '#description' => t('A list of hostnames or IP addresses, separated by line. Amber will not preserve any link containing an excluded site.'),
  );

  $form['amber_cache']['advanced']['amber_excluded_formats'] = array(
    '#type' => 'textarea',
    '#title' => t('Excluded file formats'),
    '#default_value' => variable_get('amber_excluded_formats', ''),
    '#description' => t('A list of of MIME types, separated by line. Amber will not preserve any link containing an excluded MIME type.'),
  );

  $form['amber_delivery'] = array(
    '#type' => 'fieldset',
    '#title' => t('Amber Delivery'),
    '#description' => t("Settings that control the user experience"),
  );

  $form['amber_delivery']['amber_available_action'] = array(
    '#type' => 'select',
    '#title' => t('Available links'),
    '#options' => array (
      AMBER_ACTION_NONE => t('None'),
      AMBER_ACTION_HOVER => t('Hover'),
      AMBER_ACTION_POPUP => t('Link to popup'),
    ),
    '#default_value' => variable_get('amber_available_action', 0),
    '#description' => t('How a visitor to your site will experience links to pages that are currently available.'),
  );

  $form['amber_delivery']['amber_available_action_hover'] = array(
    '#type' => 'textfield',
    '#title' => t('Hover delay (seconds)'),
    '#size' => 2,
    '#default_value' => variable_get('amber_available_action_hover', 2),
    '#description' => t('Delay before "Site Available" notification appears to a visitor on your site.'),
    '#states' => array(
      'visible' => array(
        ':input[name="amber_available_action"]' => array('value' => '1'),
      ),
     ),
  );

  $form['amber_delivery']['amber_unavailable_action'] = array(
    '#type' => 'select',
    '#title' => t('Unavailable links'),
    '#options' => array (
      AMBER_ACTION_NONE => t('None'),
      AMBER_ACTION_HOVER => t('Hover'),
      AMBER_ACTION_POPUP => t('Link to popup'),
      AMBER_ACTION_CACHE => t('Link to cache'),
    ),
    '#default_value' => variable_get('amber_unavailable_action', 1),
    '#description' => t('How a visitor to your site will experience links to pages that are currently unavailable.'),
  );

  $form['amber_delivery']['amber_unavailable_action_hover'] = array(
    '#type' => 'textfield',
    '#title' => t('Hover delay (seconds)'),
    '#size' => 2,
    '#default_value' => variable_get('amber_unavailable_action_hover', 2),
    '#description' => t('Delay before "Site Unvailable" notification appears to a visitor on your site.'),
    '#states' => array(
      'visible' => array(
        ':input[name="amber_unavailable_action"]' => array('value' => '1'),
      ),
     ),
  );

  $form['amber_country'] = array(
    '#type' => 'fieldset',
    '#title' => t('Activity for a specific country'),
    '#description' => t("Customize the user experience for visitors from a specific country"),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );

  $form['amber_country']['amber_country_id'] = array(
    '#type' => 'select',
    '#title' => t('Country'),
    '#options' => array("" => "") + _amber_get_countries(),
    '#default_value' => variable_get('amber_country_id', ""),
    '#description' => t('Visitors to your website with browser IP addresses originating in this country will experience specified behavior.'),
  );

  // $form['amber_country']['amber_country_proxy'] = array(
  //   '#type' => 'textfield',
  //   '#title' => t('Proxy'),
  //   '#default_value' => variable_get('amber_country_proxy', ''),
  //   '#description' => t('IP address or hostname of a proxy. Amber will use this proxy to test country-specific site availability.'),
  //   '#states' => array(
  //     'visible' => array(
  //       ':input[name="amber_country_id"]' => array('!value' => ''),
  //     ),
  //    ),
  // );

  $form['amber_country']['amber_country_available_action'] = array(
    '#type' => 'select',
    '#title' => t('Available sites'),
    '#options' => array (
      AMBER_ACTION_NONE => t('None'),
      AMBER_ACTION_HOVER => t('Hover'),
      AMBER_ACTION_POPUP => t('Link to popup'),
    ),
    '#default_value' => variable_get('amber_country_available_action', 0),
    '#description' => t('How links that are available will appear to visitors to your site.'),
    '#states' => array(
      'visible' => array(
        ':input[name="amber_country_id"]' => array('!value' => ''),
      ),
     ),
  );

  $form['amber_country']['amber_country_available_action_hover'] = array(
    '#type' => 'textfield',
    '#title' => t('Hover delay (seconds)'),
    '#size' => 2,
    '#default_value' => variable_get('amber_country_available_action_hover', 2),
    '#description' => t('Delay a visitor to your site will experience before notification appears.'),
    '#states' => array(
      'visible' => array(
        ':input[name="amber_country_id"]' => array('!value' => ''),
        ':input[name="amber_country_available_action"]' => array('value' => '1'),
      ),
     ),
  );

  $form['amber_country']['amber_country_unavailable_action'] = array(
    '#type' => 'select',
    '#title' => t('Unavailable sites'),
    '#options' => array (
      AMBER_ACTION_NONE => t('None'),
      AMBER_ACTION_HOVER => t('Hover'),
      AMBER_ACTION_POPUP => t('Link to popup'),
      AMBER_ACTION_CACHE => t('Link to cache'),
    ),
    '#default_value' => variable_get('amber_country_unavailable_action', 1),
    '#description' => t('How links that are available will appear to visitors to your site.'),
    '#states' => array(
      'visible' => array(
        ':input[name="amber_country_id"]' => array('!value' => ''),
      ),
     ),
  );

  $form['amber_country']['amber_country_unavailable_action_hover'] = array(
    '#type' => 'textfield',
    '#title' => t('Hover delay (seconds)'),
    '#size' => 2,
    '#default_value' => variable_get('amber_country_unavailable_action_hover', 2),
    '#description' => t('Delay a visitor to your site will experience before notification appears.'),
    '#states' => array(
      'visible' => array(
        ':input[name="amber_country_id"]' => array('!value' => ''),
        ':input[name="amber_country_unavailable_action"]' => array('value' => '1'),
      ),
     ),
  );

  $form = system_settings_form($form);
  $form['#submit'][] = 'amber_settings_submit';
  return $form;
}

/* Custom submit handler to flush filter cache after the settings are updated */
function amber_settings_submit($form, &$form_state) {
  cache_clear_all('*', 'cache_filter',TRUE);
  cache_clear_all('*', 'cache_field',TRUE);
}

function _amber_get_countries() {
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


