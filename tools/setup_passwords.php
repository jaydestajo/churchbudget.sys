<?php
/**
 * One-time setup script.
 * Sets working bcrypt password hashes for the two seed accounts created by
 * sql/schema.sql (the hashes shipped in the seed data are placeholders and
 * will not pass password_verify()).
 *
 * Usage:
 *   php tools/setup_passwords.php
 * or visit it once in the browser, then DELETE this file.
 *
 * Sets:
 *   admin     / admin123     -> Super Admin
 *   treasurer / treasurer123 -> Treasurer
 */

require __DIR__ . '/../config/config.php';

$accounts = [
    'admin'     => 'admin123',
    'treasurer' => 'treasurer123',
];

$isCli = (php_sapi_name() === 'cli');
$out = function (string $line) use ($isCli) {
    echo $isCli ? ($line . PHP_EOL) : ($line . '<br>');
};

$updated = 0;
foreach ($accounts as $username => $plainPassword) {
    $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
    $stmt->execute([$hash, $username]);

    if ($stmt->rowCount() > 0) {
        $out("OK: password for '{$username}' set to '{$plainPassword}'.");
        $updated++;
    } else {
        $out("SKIPPED: no user found with username '{$username}' (already renamed/removed?).");
    }
}

$out('');
$out($updated > 0
    ? "Done. {$updated} account(s) updated. Please delete tools/setup_passwords.php now."
    : 'Nothing updated. Check that sql/schema.sql was imported and DB credentials in config/config.php are correct.');
