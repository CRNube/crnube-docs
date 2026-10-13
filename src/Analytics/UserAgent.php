<?php
declare(strict_types=1);

namespace CRNube\Docs\Analytics;

/**
 * Reads device type, browser and operating system out of a User-Agent string. Only
 * these three families are kept; the string itself is never stored.
 */
final class UserAgent
{
    /** Crawlers, link previews and command-line tools: their hits are not counted. */
    private const BOT_PATTERN = '/bot|crawl|spider|slurp|facebookexternalhit|whatsapp|telegram|preview|monitor|curl|wget|python|java\/|go-http|headless|lighthouse|pingdom|uptime|scrapy|axios|node-fetch/i';

    public function __construct(private readonly string $ua)
    {
    }

    public static function fromRequest(): self
    {
        return new self(isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '');
    }

    public function isBot(): bool
    {
        return $this->ua === '' || (bool) preg_match(self::BOT_PATTERN, $this->ua);
    }

    public function device(): string
    {
        if (preg_match('/iPad|Tablet|PlayBook|Silk|Kindle/i', $this->ua) || (preg_match('/Android/i', $this->ua) && !preg_match('/Mobile/i', $this->ua))) {
            return 'tablet';
        }
        if (preg_match('/Mobi|iPhone|iPod|Android|Windows Phone/i', $this->ua)) {
            return 'mobile';
        }
        return 'desktop';
    }

    public function browser(): string
    {
        // Order matters: Edge, Opera and Samsung also say "Chrome"; Chrome also says "Safari".
        return match (true) {
            (bool) preg_match('/Edg(e|A|iOS)?\//', $this->ua)        => 'edge',
            (bool) preg_match('/OPR\/|Opera/', $this->ua)              => 'opera',
            (bool) preg_match('/SamsungBrowser/', $this->ua)           => 'samsung',
            (bool) preg_match('/Firefox\/|FxiOS/', $this->ua)          => 'firefox',
            (bool) preg_match('/Chrome\/|CriOS/', $this->ua)           => 'chrome',
            (bool) preg_match('/Safari\//', $this->ua)                 => 'safari',
            default                                                    => 'other',
        };
    }

    public function os(): string
    {
        return match (true) {
            (bool) preg_match('/iPhone|iPad|iPod/', $this->ua)  => 'ios',
            (bool) preg_match('/Android/', $this->ua)           => 'android',
            (bool) preg_match('/Windows/', $this->ua)           => 'windows',
            (bool) preg_match('/CrOS/', $this->ua)              => 'chromeos',
            (bool) preg_match('/Mac OS X|Macintosh/', $this->ua) => 'macos',
            (bool) preg_match('/Linux/', $this->ua)             => 'linux',
            default                                             => 'other',
        };
    }
}
