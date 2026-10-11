<?php
/**
 * Shared config for all User Manual pages.
 * Defines $manualSections array + helper functions.
 */

if (!defined('MANUAL_CONFIG_LOADED')) {
    define('MANUAL_CONFIG_LOADED', true);

    $manualSections = [
        [
            'group'    => 'Getting Started',
            'group_si' => 'ආරම්භ කිරීම',
            'icon'     => 'fa-rocket',
            'items'    => [
                ['slug' => 'introduction', 'icon' => 'fa-circle-info', 'title' => 'Introduction',       'title_si' => 'හැඳින්වීම',           'desc' => 'IDCMS පද්ධතියේ සමස්ත දසුන',                          'time' => '4 min', 'diff' => 'basic'],
                ['slug' => 'login',        'icon' => 'fa-right-to-bracket', 'title' => 'Login & Session', 'title_si' => 'පිවිසීම සහ සැසිය',       'desc' => 'පද්ධතියට පිවිසෙන ආකාරය සහ session security',     'time' => '3 min', 'diff' => 'basic'],
                ['slug' => 'dashboard',    'icon' => 'fa-table-cells-large', 'title' => 'Dashboard Overview', 'title_si' => 'මුල් පිටුව',           'desc' => 'Dashboard widgets, metrics සහ search preview',        'time' => '6 min', 'diff' => 'basic'],
            ]
        ],
        [
            'group'    => 'Dispatching',
            'group_si' => 'ලිපි බෙදාහැරීම',
            'icon'     => 'fa-paper-plane',
            'items'    => [
                ['slug' => 'add-internal-letter', 'icon' => 'fa-envelope-open-text', 'title' => 'Add Internal Letter',  'title_si' => 'අභ්‍යන්තර ලිපියක් එක් කිරීම',  'desc' => 'නව අභ්‍යන්තර ලිපියක් ලියාපදිංචි කිරීම',                'time' => '8 min', 'diff' => 'basic'],
                ['slug' => 'view-internal',       'icon' => 'fa-list',                'title' => 'View Internal Registry', 'title_si' => 'අභ්‍යන්තර ලේඛනය',            'desc' => 'ලියාපදිංචි ලිපි ලැයිස්තුව, search සහ filter',           'time' => '5 min', 'diff' => 'basic'],
                ['slug' => 'forward-letter',      'icon' => 'fa-share',               'title' => 'Forward a Letter',     'title_si' => 'ලිපියක් යොමු කිරීම',           'desc' => 'Minute එකක් සමඟ ලිපිය තවත් නිලධාරියෙකුට යොමු කිරීම',  'time' => '4 min', 'diff' => 'inter'],
                ['slug' => 'add-outward',         'icon' => 'fa-share-from-square',   'title' => 'Add Outward Letter',   'title_si' => 'බාහිර ලිපියක් එක් කිරීම',      'desc' => 'බාහිර ලිපියක් සකස් කිරීම සහ යැවීම',                    'time' => '9 min', 'diff' => 'inter'],
                ['slug' => 'task-letters',        'icon' => 'fa-list-check',          'title' => 'Task Letters',         'title_si' => 'කාර්ය ලිපි',                   'desc' => 'රෙජිමේන්තු වලට කාර්ය පැවරීම සහ පිළිතුරු නිරීක්ෂණය',  'time' => '10 min', 'diff' => 'inter'],
            ]
        ],
        [
            'group'    => 'Records & Disposal',
            'group_si' => 'වාර්තා සහ බැහැර කිරීම',
            'icon'     => 'fa-box-archive',
            'items'    => [
                ['slug' => 'final-disposal', 'icon' => 'fa-box-archive',      'title' => 'Final Disposal',      'title_si' => 'අවසන් බැහැර කිරීම',  'desc' => 'අවසන් බැහැර කිරීමේ ක්‍රියාවලිය සහ ගොනු කිරීම',        'time' => '8 min', 'diff' => 'adv'],
                ['slug' => 'record-room',    'icon' => 'fa-warehouse',        'title' => 'Record Room / E-Files', 'title_si' => 'වාර්තා කාමරය',         'desc' => 'ගොනු සෑදීම, නිකුත් කිරීම සහ ආපසු ලබා ගැනීම',        'time' => '12 min', 'diff' => 'inter'],
                ['slug' => 'linkage-report', 'icon' => 'fa-diagram-project',  'title' => 'Linkage Report',      'title_si' => 'සම්බන්ධතා වාර්තා',   'desc' => 'ලිපි අතර සම්බන්ධතා සහ PDF dossier සෑදීම',           'time' => '6 min', 'diff' => 'adv'],
            ]
        ],
        [
            'group'    => 'Administration',
            'group_si' => 'පරිපාලනය',
            'icon'     => 'fa-user-shield',
            'items'    => [
                ['slug' => 'informers',       'icon' => 'fa-user-secret',  'title' => 'Informers Management', 'title_si' => 'තොරතුරු දෙන්නන්',        'desc' => 'තොරතුරු දෙන්නන් ලියාපදිංචි කිරීම සහ කළමනාකරණය',     'time' => '6 min', 'diff' => 'inter'],
                ['slug' => 'user-management', 'icon' => 'fa-users-gear',   'title' => 'User Management',      'title_si' => 'පරිශීලක කළමනාකරණය',   'desc' => 'නව පරිශීලකයන් සෑදීම සහ අවසර පැවරීම',                'time' => '10 min', 'diff' => 'adv'],
                ['slug' => 'permissions',     'icon' => 'fa-key',          'title' => 'Permissions & Roles',  'title_si' => 'අවසර සහ භූමිකා',       'desc' => 'Permission codes සහ භූමිකා පැවරීම',                'time' => '7 min', 'diff' => 'adv'],
                ['slug' => 'desks',           'icon' => 'fa-sitemap',      'title' => 'Desk Control',         'title_si' => 'ඩෙස්ක් පාලනය',         'desc' => 'ඩෙස්ක් (අංශ) සෑදීම සහ කළමනාකරණය',                   'time' => '4 min', 'diff' => 'adv'],
            ]
        ],
        [
            'group'    => 'Help',
            'group_si' => 'උපකාර',
            'icon'     => 'fa-life-ring',
            'items'    => [
                ['slug' => 'troubleshooting', 'icon' => 'fa-life-ring', 'title' => 'Troubleshooting', 'title_si' => 'ගැටළු විසඳීම',  'desc' => 'සාමාන්‍ය ගැටළු සහ ඒවා විසඳන ආකාරය',   'time' => '5 min', 'diff' => 'basic'],
                ['slug' => 'glossary',        'icon' => 'fa-book',      'title' => 'Glossary',        'title_si' => 'ශබ්දකෝෂය',     'desc' => 'Technical terms සහ ඒවායේ අර්ථ',         'time' => '3 min', 'diff' => 'basic'],
            ]
        ],
    ];
}

/**
 * Helper — get flattened list of all sections for prev/next navigation.
 */
function manual_all_items($sections) {
    $flat = [];
    foreach ($sections as $g) {
        foreach ($g['items'] as $it) {
            $flat[] = $it;
        }
    }
    return $flat;
}

/**
 * Helper — find previous / next section by slug.
 */
function manual_prev_next($sections, $currentSlug) {
    $flat = manual_all_items($sections);
    $prev = null; $next = null;
    foreach ($flat as $i => $it) {
        if ($it['slug'] === $currentSlug) {
            $prev = $i > 0 ? $flat[$i - 1] : null;
            $next = $i < count($flat) - 1 ? $flat[$i + 1] : null;
            break;
        }
    }
    return [$prev, $next];
}