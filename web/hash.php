<?php
// hash_admin.php
$plain = '';
$hash = password_hash($plain, PASSWORD_DEFAULT);
echo $hash;
