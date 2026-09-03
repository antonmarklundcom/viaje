<?php
declare(strict_types=1);

/**
 * Print a bcrypt hash for config.local.php:
 *   php engine/bin/hash-password.php 'my secret'
 * With no argument the password is read from stdin so it stays out of shell history.
 */

$pw = $argv[1] ?? null;
if ($pw === null) {
    fwrite(STDERR, "Password: ");
    $pw = trim((string)fgets(STDIN));
}
if ($pw === '' || strlen($pw) < 10) {
    fwrite(STDERR, "Use at least 10 characters.\n");
    exit(1);
}
echo "'admin_password_hash' => '" . password_hash($pw, PASSWORD_BCRYPT) . "',\n";
