<?php
// web/auth_redirect.php
if (session_status() === PHP_SESSION_NONE) session_start();

$configs = [
    'google' => [
        'client_id' => '227014846953-53cg98n8v1u0v86se7kkgp16a52nd2ob.apps.googleusercontent.com',
        'auth_url'  => 'https://accounts.google.com/o/oauth2/v2/auth',
        'scope'     => 'email profile',
        'redirect'  => 'http://gresto.com/web/callback?provider=google'
    ],

];

$provider = $_GET['provider'] ?? '';

if (!isset($configs[$provider])) {
    header("Location: /login");
    exit;
}

$c = $configs[$provider];

// State prevents CSRF attacks
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

$params = [
    'client_id'     => $c['client_id'],
    'redirect_uri'  => $c['redirect'],
    'response_type' => 'code',
    'scope'         => $c['scope'],
    'state'         => $state
];

header("Location: " . $c['auth_url'] . '?' . http_build_query($params));
exit;