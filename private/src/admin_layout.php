<?php
declare(strict_types=1);

function admin_layout_start(string $title, string $current, string $shellAttributes = ''): void {
    layout_header($title);
    $activeRoot = in_array($current, ['settings', 'reports', 'history', 'payout-settings', 'payout-reports', 'payout-history', 'royalty'], true) ? 'payout' : (in_array($current, ['list', 'edit', 'create'], true) ? 'users' : $current);
    $link = static function (string $label, string $href, string $key) use ($current): void {
        echo '<a class="'.($current === $key ? 'active' : '').'" href="'.e(url($href)).'"><span>•</span>'.e($label).'</a>';
    };
    $flyout = static function (string $label, string $href, string $root, array $items, string $glyph) use ($current, $activeRoot): void {
        echo '<div class="admin-nav-flyout"><a class="admin-nav-settings '.($activeRoot === $root ? 'active' : '').'" href="'.e(url($href)).'"><span>'.e($glyph).'</span>'.e($label).'<b aria-hidden="true">›</b></a><div class="admin-nav-submenu">';
        foreach ($items as $item) echo '<a class="'.($current === $item[2] ? 'active' : '').'" href="'.e(url($item[1])).'">'.e($item[0]).'</a>';
        echo '</div></div>';
    };
    $attrs = trim($shellAttributes) === '' ? '' : ' '.trim($shellAttributes);
    echo '<div class="admin-shell"'.$attrs.'><aside class="panel admin-menu"><div class="admin-menu-title"><strong>Admin</strong><small>'.e(site_name()).'</small></div><nav class="admin-menu-nav">';
    $link('Overview', 'admin.php?section=overview', 'overview'); echo '<div class="admin-menu-separator"><hr></div>';
    $link('Tracks', 'admin.php?section=tracks', 'tracks'); $link('Track Reviews', 'admin.php?section=reviews', 'reviews'); $link('Artists', 'admin.php?section=artists', 'artists'); $link('Genres', 'admin.php?section=genres', 'genres'); $link('EPs (Releases)', 'admin.php?section=releases', 'releases'); echo '<div class="admin-menu-separator"><hr></div>';
    $link('Orders', 'admin.php?section=orders', 'orders'); $link('Customer Support', 'admin-support.php', 'support');
    $flyout('Artist Payouts', 'admin-payout-reports.php', 'payout', [['Royalty Ledger', 'admin-royalty.php', 'royalty'], ['Payout Settings', 'admin-payout-settings.php', 'payout-settings'], ['Payout Reports', 'admin-payout-reports.php', 'payout-reports'], ['Payout History', 'admin-payout-history.php', 'payout-history']], '£');
    echo '<a class="'.($current === 'discounts' ? 'active' : '').'" href="'.e(url('admin-discounts.php')).'"><span>％</span>Discounts</a>';
    $link('Reporting', 'admin.php?section=reporting', 'reporting'); $link('Preview Analytics', 'admin-preview-analytics.php', 'preview-analytics'); $link('Marketing', 'admin.php?section=marketing', 'marketing'); $link('Email Templates', 'admin.php?section=mail_templates', 'mail_templates'); echo '<div class="admin-menu-separator"><hr></div>';
    $flyout('Users', 'admin/users', 'users', [['All Users', 'admin/users', 'list'], ['Edit Users', 'admin/users/edit', 'edit'], ['Create User', 'admin/users/create', 'create']], '♙');
    $link('Audit Log', 'admin.php?section=audit', 'audit'); echo '<div class="admin-menu-separator"><hr></div>';
    $flyout('Settings', 'admin-2fa.php', 'settings', [['Admin 2FA', 'admin-2fa.php', 'admin-2fa'], ['Mail Settings', 'admin.php?section=mail', 'mail'], ['Scheduled Tasks Help', 'admin.php?section=help', 'help'], ['Site Customizations', 'admin.php?section=theme', 'theme'], ['Payment Settings', 'admin.php?section=payments', 'payments'], ['Business Details', 'admin.php?section=business', 'business']], '⚙');
    echo '</nav></aside><section class="admin-workspace">';
}

function admin_layout_end(): void { echo '</section></div>'; layout_footer(); }
