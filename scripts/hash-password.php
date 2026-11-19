<?php

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$email = $argv[1] ?? 'admin@rekanweb.biz.id';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Email tidak valid.\n");
    exit(1);
}

fwrite(STDOUT, "Password baru untuk {$email} (8-72 karakter): ");
@shell_exec('stty -echo');
$password = rtrim((string) fgets(STDIN), "\r\n");
@shell_exec('stty echo');
fwrite(STDOUT, PHP_EOL);

if (strlen($password) < 8 || strlen($password) > 72) {
    fwrite(STDERR, "Password harus 8-72 karakter.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

echo "\nHash:\n{$hash}\n\nSQL:\nUPDATE users SET password = '{$hash}' WHERE email = '{$email}';\n";