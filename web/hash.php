<?php
// hash_admin.php
$plain = '74108520963';
$hash = password_hash($plain, PASSWORD_DEFAULT);
echo $hash;
