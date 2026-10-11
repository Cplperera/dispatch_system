<?php
require_once '../../../includes/auth.php';
require_once '../../../config/db.php';
checkSessionTimeout();
if (!isset($_SESSION['user_id'])) die('Session Invalid');

require_once __DIR__ . '/../_config.php';

$page = [
    'slug'     => 'dashboard',
    'icon'     => 'fa-table-cells-large',
    'title'    => 'Dashboard Overview',
    'title_si' => 'මුල් පිටුව',
    'desc'     => 'Dashboard widgets, metrics, සහ search preview center',
    'diff'     => 'basic',
    'time'     => '6 min',
    'group'    => 'Getting Started',
];

include __DIR__ . '/../_header.php';
?>

<div class="m-card">
    <div class="m-card-title">
        <i class="fa-solid fa-circle-info"></i>
        <span>Overview — සමාලෝචනය</span>
    </div>
    <p>
        Dashboard එක තමයි ඔබ පද්ධතියට පිවිසුණු පසු පළමුව දකින page එක. මෙහි ඔබට <strong>ලිපි සංඛ්‍යාලේඛන, ඔබට යොමු කර ඇති ලිපි, සහ සජීවී සෙවුම්</strong> එකම තැනකින් දැකිය හැක.
    </p>
</div>

<div class="m-card">
    <div class="m-card-title">
        <i class="fa-solid fa-list-ol"></i>
        <span>Dashboard එකේ කොටස්</span>
    </div>
    <ol class="m-steps">
        <li><strong>Sidebar</strong> — පද්ධතියේ සියලුම modules වලට පිවිසුම්</li>
        <li><strong>Top Bar</strong> — ඔරලෝසුව, notification, theme toggle, ඔබේ පරිශීලක තොරතුරු</li>
        <li><strong>Search Bar</strong> — සජීවී සෙවුම (CDR, TSK, title)</li>
        <li><strong>Filter Controls</strong> — Type, Status, Limit, Deep Search</li>
        <li><strong>Metrics Grid</strong> — 6 metric කොටු</li>
        <li><strong>Search Preview Center</strong> — සෙවුම් ප්‍රතිඵල කාඩ්පත් ලෙස</li>
    </ol>
</div>

<div class="m-card">
    <div class="m-card-title">
        <i class="fa-solid fa-chart-line"></i>
        <span>Metrics — සංඛ්‍යාලේඛන</span>
    </div>
    <div class="m-table-wrap">
        <table class="m-table">
            <thead>
                <tr><th>Metric</th><th>අර්ථය</th></tr>
            </thead>
            <tbody>
                <tr><td><strong>Today (Inward Only)</strong></td><td>අද දිනයේ ලැබුණු අභ්‍යන්තර ලිපි ගණන</td></tr>
                <tr><td><strong>Pending</strong></td><td>අවසන් බැහැර කිරීමට රැඳී ඇති ලිපි</td></tr>
                <tr><td><strong>All Letters</strong></td><td>සියලුම ලිපි (Internal + External + Tasked)</td></tr>
                <tr><td><strong>Internal</strong></td><td>මුළු අභ්‍යන්තර ලිපි</td></tr>
                <tr><td><strong>External</strong></td><td>මුළු බාහිර ලිපි</td></tr>
                <tr><td><strong>Tasked</strong></td><td>මුළු කාර්ය ලිපි</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="m-card">
    <div class="m-card-title">
        <i class="fa-solid fa-magnifying-glass"></i>
        <span>සෙවුම් තීරුව — Search Bar</span>
    </div>
    <p>සෙවුම් තීරුවේ ඔබ ටයිප් කරන විටම ප්‍රතිඵල පහළින් පෙන්වයි. සෙවිය හැකි දේ:</p>
    <ul class="m-steps">
        <li><strong>CDR Number</strong> — උදා: <code>REG/2026/13</code></li>
        <li><strong>TSK Number</strong> — කාර්ය ලිපි සඳහා</li>
        <li><strong>Letter Reference</strong></li>
        <li><strong>Title</strong> — ලිපියේ මාතෘකාව</li>
    </ul>

    <div class="m-callout tip">
        <i class="fa-solid fa-lightbulb"></i>
        <div>
            <strong>Tip</strong>
            <p><strong>Deep Search</strong> කොටුව tick කළහොත්, ලිපියේ <em>විස්තරය</em>, <em>ටිප්පණි</em>, සහ <em>About Letter</em> කොටස්වල පවා ඔබේ වචනය සොයයි.</p>
        </div>
    </div>
</div>

<div class="m-card">
    <div class="m-card-title">
        <i class="fa-solid fa-filter"></i>
        <span>Filters — පෙරහන්</span>
    </div>

    <div class="m-table-wrap">
        <table class="m-table">
            <thead>
                <tr><th>Filter</th><th>විකල්ප</th><th>විස්තරය</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Type</strong></td>
                    <td>Internal, External, Task</td>
                    <td>සෙවිය යුතු ලිපි වර්ග තෝරන්න</td>
                </tr>
                <tr>
                    <td><strong>Status</strong></td>
                    <td>Pending, Forwarded, Completed, Sent, Draft, Archived</td>
                    <td>ලිපියේ තත්ත්වය අනුව පෙරහන් කරන්න</td>
                </tr>
                <tr>
                    <td><strong>Limit</strong></td>
                    <td>5, 10, 20, 50</td>
                    <td>එක පිටුවක පෙන්වන ප්‍රතිඵල ගණන</td>
                </tr>
                <tr>
                    <td><strong>Deep Search</strong></td>
                    <td>On / Off</td>
                    <td>ලිපියේ විස්තර තුළ පවා සෙවීම</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="m-card">
    <div class="m-card-title">
        <i class="fa-solid fa-bell"></i>
        <span>Notifications — දැනුම්දීම්</span>
    </div>
    <p>ඉහළ දකුණේ <strong>සීනු (Bell) අයිකනය</strong> මත ක්ලික් කරන්න. ඔබට යොමු කර ඇති නමුත් තවම නොබැලූ ලිපි සංඛ්‍යාව රතු රවුමක පෙන්වයි.</p>

    <div class="m-callout warn">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <div>
            <strong>සටහන</strong>
            <p>සීනුව පෙන්වන්නේ <strong>Inbox Internal</strong> ලිපි පමණි. Disposal Inbox දැනුම්දීම් සඳහා වෙනම "Final Disposal" bell එකක් ඇත (ඔබ එය ස්ථාපනය කර ඇත්නම්).</p>
        </div>
    </div>
</div>

<div class="m-card">
    <div class="m-card-title">
        <i class="fa-solid fa-keyboard"></i>
        <span>Keyboard Shortcuts</span>
    </div>
    <div class="m-table-wrap">
        <table class="m-table">
            <thead>
                <tr><th>Key</th><th>ක්‍රියාව</th></tr>
            </thead>
            <tbody>
                <tr><td><kbd>/</kbd></td><td>සෙවුම් තීරුවට focus කරන්න</td></tr>
                <tr><td><kbd>T</kbd></td><td>Light / Dark theme මාරු කිරීම</td></tr>
                <tr><td><kbd>P</kbd></td><td>Print / PDF export</td></tr>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../_footer.php'; ?>