<?php

return [

    // Uncomment the languages that your site supports.
    // Configured for all 24 official languages of the European Union (EU).
    'supportedLocales' => [
        'en' => ['name' => 'English',    'script' => 'Latn', 'native' => 'English',    'regional' => 'en_GB'],
        'ro' => ['name' => 'Romanian',   'script' => 'Latn', 'native' => 'Română',     'regional' => 'ro_RO'],
        'de' => ['name' => 'German',     'script' => 'Latn', 'native' => 'Deutsch',    'regional' => 'de_DE'],
        'fr' => ['name' => 'French',     'script' => 'Latn', 'native' => 'Français',   'regional' => 'fr_FR'],
        'es' => ['name' => 'Spanish',    'script' => 'Latn', 'native' => 'Español',    'regional' => 'es_ES'],
        'it' => ['name' => 'Italian',    'script' => 'Latn', 'native' => 'Italiano',   'regional' => 'it_IT'],
        'nl' => ['name' => 'Dutch',      'script' => 'Latn', 'native' => 'Nederlands', 'regional' => 'nl_NL'],
        'pl' => ['name' => 'Polish',     'script' => 'Latn', 'native' => 'Polski',     'regional' => 'pl_PL'],
        'pt' => ['name' => 'Portuguese', 'script' => 'Latn', 'native' => 'Português',  'regional' => 'pt_PT'],
        'sv' => ['name' => 'Swedish',    'script' => 'Latn', 'native' => 'Svenska',    'regional' => 'sv_SE'],
        'da' => ['name' => 'Danish',     'script' => 'Latn', 'native' => 'Dansk',      'regional' => 'da_DK'],
        'fi' => ['name' => 'Finnish',    'script' => 'Latn', 'native' => 'Suomi',      'regional' => 'fi_FI'],
        'el' => ['name' => 'Greek',      'script' => 'Grek', 'native' => 'Ελληνικά',   'regional' => 'el_GR'],
        'cs' => ['name' => 'Czech',      'script' => 'Latn', 'native' => 'Čeština',    'regional' => 'cs_CZ'],
        'hu' => ['name' => 'Hungarian',  'script' => 'Latn', 'native' => 'Magyar',     'regional' => 'hu_HU'],
        'bg' => ['name' => 'Bulgarian',  'script' => 'Cyrl', 'native' => 'Български',  'regional' => 'bg_BG'],
        'sk' => ['name' => 'Slovak',     'script' => 'Latn', 'native' => 'Slovenčina', 'regional' => 'sk_SK'],
        'hr' => ['name' => 'Croatian',   'script' => 'Latn', 'native' => 'Hrvatski',   'regional' => 'hr_HR'],
        'lt' => ['name' => 'Lithuanian', 'script' => 'Latn', 'native' => 'Lietuvių',   'regional' => 'lt_LT'],
        'sl' => ['name' => 'Slovenian',  'script' => 'Latn', 'native' => 'Slovenščina','regional' => 'sl_SI'],
        'et' => ['name' => 'Estonian',   'script' => 'Latn', 'native' => 'Eesti',      'regional' => 'et_EE'],
        'lv' => ['name' => 'Latvian',    'script' => 'Latn', 'native' => 'Latviešu',   'regional' => 'lv_LV'],
        'ga' => ['name' => 'Irish',      'script' => 'Latn', 'native' => 'Gaeilge',    'regional' => 'ga_IE'],
        'mt' => ['name' => 'Maltese',    'script' => 'Latn', 'native' => 'Malti',      'regional' => 'mt_MT'],
    ],

    // Default locale for application
    'useAcceptLanguageHeader' => true,
    'hideDefaultLocaleInURL' => true,
    'localesOrder' => [],
    'localesMapping' => [],
    'utf8suffix' => env('LARAVELLOCALIZATION_UTF8SUFFIX', '.UTF-8'),
    'urlsIgnored' => ['/skipped'],
    'httpMethodsIgnored' => ['POST', 'PUT', 'PATCH', 'DELETE'],
];

