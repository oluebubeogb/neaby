<?php

/*
 * MAIL_SCHEME arrives from Coolify / .env in many spellings. Symfony only
 * understands "smtp" (plain/STARTTLS, e.g. port 587) and "smtps" (implicit
 * TLS, port 465). A literal "null" string — which Coolify can pass through
 * quoted — makes Symfony throw "The "null" scheme is not supported", which is
 * what turned forgot-password into a 500. Normalise everything here.
 */
/*
 * Mirrors what Paddy (Node/nodemailer, mail works) does right:
 *   1. Accepts Paddy-style env names too (SMTP_HOST, SMTP_PORT, SMTP_USER,
 *      SMTP_PASSWORD / SMTP_PASS, SMTP_SECURE, EMAIL_FROM) so the same Coolify
 *      variables work in both apps. MAIL_* still wins when set.
 *   2. TLS mode follows the PORT (nodemailer: secure = port === 465) instead
 *      of trusting a free-text scheme — "tls"/"ssl" on the wrong port was a
 *      silent connect/handshake failure. 465 → implicit TLS, everything else →
 *      plain + opportunistic STARTTLS. SMTP_SECURE=true|false is an explicit
 *      override, exactly like Paddy.
 *   3. When a SMTP host is configured but MAIL_MAILER is unset, use SMTP
 *      (Paddy auto-picks the provider from SMTP_HOST).
 *   4. Sender falls back to the SMTP login when MAIL_FROM_ADDRESS is empty or a
 *      placeholder — most SMTP servers reject a From the login can't send as.
 *   5. IPv4-first connect (Paddy: NODE_OPTIONS=--dns-result-order=ipv4first),
 *      see App\Mail\NeabySmtpTransportFactory.
 */
$neabyEnv = static function (string ...$keys) {
    foreach ($keys as $k) {
        $v = env($k);
        if ($v === null) {
            continue;
        }
        $t = trim((string) $v, " \t\n\r\0\x0B\"'");
        if ($t !== '' && strtolower($t) !== 'null') {
            return $t;
        }
    }

    return null;
};

$neabyMailHost = $neabyEnv('MAIL_HOST', 'SMTP_HOST');
// The .env.example placeholder counts as "not set" so SMTP_HOST can take over.
if ($neabyMailHost !== null && preg_match('/(^|\.)example\.(com|org|net)$/i', $neabyMailHost)) {
    $neabyMailHost = $neabyEnv('SMTP_HOST');
}
$neabyMailPort = (int) ($neabyEnv('MAIL_PORT', 'SMTP_PORT') ?? 587);
$neabyMailUser = $neabyEnv('MAIL_USERNAME', 'SMTP_USER', 'SMTP_USERNAME');
$neabyMailPass = $neabyEnv('MAIL_PASSWORD', 'SMTP_PASSWORD', 'SMTP_PASS');

/*
 * MAIL_SCHEME arrives from Coolify / .env in many spellings. Symfony only
 * understands "smtp" (plain/STARTTLS) and "smtps" (implicit TLS). A literal
 * "null" string made Symfony throw "The "null" scheme is not supported".
 */
$neabyMailScheme = (static function ($raw, $secure, int $port) {
    $s = strtolower((string) $secure);
    if (in_array($s, ['true', '1', 'yes', 'on'], true)) {
        return 'smtps';
    }
    if (in_array($s, ['false', '0', 'no', 'off'], true)) {
        return 'smtp';
    }
    // Port decides, like nodemailer. 587/25/2525 are never implicit TLS.
    if ($port === 465) {
        return 'smtps';
    }
    if (in_array($port, [25, 587, 2525], true)) {
        return 'smtp';
    }

    return match (strtolower((string) $raw)) {
        'ssl', 'smtps' => 'smtps',
        default => 'smtp',
    };
})($neabyEnv('MAIL_SCHEME', 'MAIL_ENCRYPTION'), $neabyEnv('SMTP_SECURE'), $neabyMailPort);

// EMAIL_FROM may be "Name <addr>" (Paddy format).
$neabyFromAddr = $neabyEnv('MAIL_FROM_ADDRESS');
$neabyFromName = $neabyEnv('MAIL_FROM_NAME');
if ($neabyFromAddr === null && ($ef = $neabyEnv('EMAIL_FROM'))) {
    if (preg_match('/^\s*(.*?)\s*<([^>]+)>\s*$/', $ef, $m)) {
        $neabyFromAddr = trim($m[2]);
        $neabyFromName ??= trim($m[1], " \"'") ?: null;
    } else {
        $neabyFromAddr = $ef;
    }
}
$neabyPlaceholderFrom = $neabyFromAddr === null
    || preg_match('/@(.+\.)?(example\.(com|org|net)|localhost|your-domain\.com)$/i', $neabyFromAddr);
if ($neabyPlaceholderFrom && $neabyMailUser && filter_var($neabyMailUser, FILTER_VALIDATE_EMAIL)) {
    $neabyFromAddr = $neabyMailUser;
}

$neabyMailer = $neabyEnv('MAIL_MAILER') ?? ($neabyMailHost ? 'smtp' : 'log');

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => $neabyMailer,

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => $neabyMailScheme,
            'url' => env('MAIL_URL'),
            'host' => $neabyMailHost ?? '127.0.0.1',
            'port' => $neabyMailPort,
            'username' => $neabyMailUser,
            'password' => $neabyMailPass,
            // nodemailer waits up to 30s for the greeting; 15s cut off slow
            // shared-hosting SMTP servers (greet-pause / reverse-DNS checks).
            'timeout' => (int) env('MAIL_TIMEOUT', 30),
            // Paddy runs with --dns-result-order=ipv4first. Containers often
            // resolve AAAA but have no IPv6 route. true = pin to the A record.
            'prefer_ipv4' => filter_var(env('MAIL_PREFER_IPV4', true), FILTER_VALIDATE_BOOL),
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => $neabyFromAddr ?? 'hello@example.com',
        'name' => $neabyFromName ?? env('APP_NAME', 'neaby'),
    ],

];
