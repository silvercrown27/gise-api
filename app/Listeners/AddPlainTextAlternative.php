<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;

/**
 * Adds a plain-text version to every HTML email that doesn't have one.
 *
 * An email with only an HTML part is a classic spam signal, and some clients
 * (and people on slow connections) show the text version instead. Doing it here,
 * as the message goes out, covers every email the app sends.
 */
class AddPlainTextAlternative
{
    public function handle(MessageSending $event): void
    {
        $message = $event->message;

        if ($message->getTextBody() || !($html = $message->getHtmlBody())) {
            return;
        }

        $message->text(self::toText((string) $html));
    }

    /** Readable text from our (table-based) email HTML: links keep their address, hidden preview text is dropped. */
    public static function toText(string $html): string
    {
        $html = preg_replace('#<(head|style|script)\b.*?</\1>#is', '', $html);
        // The hidden "preheader" line exists only for the inbox preview.
        $html = preg_replace('#<div[^>]*display:\s*none[^>]*>.*?</div>#is', '', $html);

        // Images (the logo) contribute their alt text.
        $html = preg_replace('#<img\b[^>]*\balt=(["\'])(.*?)\1[^>]*>#is', '$2', $html);

        // <a href="url">label</a> -> "label (url)"; mailto links just keep the label.
        $html = preg_replace_callback('#<a\b[^>]*\bhref=(["\'])(.*?)\1[^>]*>(.*?)</a>#is', function (array $match) {
            $url = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $label = trim(preg_replace('/\s+/', ' ', strip_tags($match[3])));

            if ($label === '') {
                return $url;
            }

            return ($label === $url || str_starts_with($url, 'mailto:') || str_starts_with($url, '#')) ? $label : "{$label} ({$url})";
        }, $html);

        $html = preg_replace('#<br\s*/?>#i', "\n", $html);
        $html = preg_replace('#</(p|div|tr|h[1-6]|li|table)>#i', "\n", $html);
        // Two cells in a row are a label and its value (the details boxes): "Cohort: November 2026".
        $html = preg_replace('#</t[dh]>\s*<t[dh]#i', ': <td', $html);
        $html = preg_replace('#</t[dh]>#i', ' ', $html);

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
        $text = preg_replace('/ *\n */', "\n", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }
}
