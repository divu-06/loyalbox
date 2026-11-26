<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../includes/db.php';

if ($argc < 3) exit("Usage: php database/create_admin.php \"Full Name\" email@example.com\n");
[$_, $name, $email] = $argv;
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) exit("Invalid email.\n");

echo 'Password (min 10 chars, hidden): ';
system('stty -echo'); $pw = trim((string)fgets(STDIN)); system('stty echo'); echo "\n";
if (strlen($pw) < 10) exit("Password too short.\n");

$st = db()->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
$st->execute([$name, strtolower($email), password_hash($pw, PASSWORD_DEFAULT)]);
echo "Admin created.\n";