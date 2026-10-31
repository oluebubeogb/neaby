<?php

namespace App\Mail;

use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

/**
 * SMTP transport that connects the way Paddy's nodemailer does in production:
 *
 *  - IPv4 first. Paddy runs with NODE_OPTIONS=--dns-result-order=ipv4first
 *    because Coolify containers often resolve AAAA records but have no IPv6
 *    route, so the connect hangs until the timeout. We resolve the A record
 *    and dial that IP, while keeping the real hostname for TLS SNI and
 *    certificate verification (peer_name), so security is unchanged.
 *  - TLS mode comes from config/mail.php (port-driven, like nodemailer).
 *  - EHLO name from APP_URL host so strict servers don't reject "[127.0.0.1]".
 */
final class NeabySmtpTransportFactory
{
    public static function make(array $config): EsmtpTransport
    {
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (int) ($config['port'] ?? 587);
        $scheme = $config['scheme'] ?? null ?: ($port === 465 ? 'smtps' : 'smtp');

        $transport = (new EsmtpTransportFactory)->create(new Dsn(
            $scheme,
            $host,
            $config['username'] ?? null,
            $config['password'] ?? null,
            $port,
            array_filter([
                'local_domain' => $config['local_domain'] ?? null,
            ]),
        ));

        $stream = $transport->getStream();
        if ($stream instanceof SocketStream) {
            if (isset($config['timeout'])) {
                $stream->setTimeout((float) $config['timeout']);
            }
            if (isset($config['source_ip'])) {
                $stream->setSourceIp($config['source_ip']);
            }

            $ip = ($config['prefer_ipv4'] ?? true) ? self::ipv4For($host) : null;
            if ($ip !== null && $ip !== $host) {
                $stream->setHost($ip);
                $stream->setStreamOptions(array_replace_recursive($stream->getStreamOptions(), [
                    'ssl' => ['peer_name' => $host, 'SNI_enabled' => true],
                ]));
            }
        }

        return $transport;
    }

    /** A-record for $host, or null (already an IP, no A record, or DNS failure → let PHP resolve). */
    public static function ipv4For(string $host): ?string
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return null;
        }
        // System resolver (honours /etc/hosts + Docker DNS), IPv4 results only.
        $ips = @gethostbynamel($host) ?: [];
        $ip = $ips[0] ?? null;

        return ($ip && $ip !== $host && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) ? $ip : null;
    }
}
