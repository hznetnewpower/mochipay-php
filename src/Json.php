<?php
namespace MochiPay;

final class Json
{
    /** Preserve every JSON numeric token as a string, including exact money. */
    public static function decode($json)
    {
        // Validate first so malformed input cannot be repaired by token quoting.
        json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $output = '';
        $length = strlen($json);
        for ($i = 0; $i < $length;) {
            if ($json[$i] === '"') {
                $start = $i++;
                while ($i < $length) {
                    if ($json[$i] === '\\') { $i += 2; continue; }
                    if ($json[$i++] === '"') { break; }
                }
                $output .= substr($json, $start, $i - $start);
            } elseif ($json[$i] === '-' || ctype_digit($json[$i])) {
                $start = $i++;
                while ($i < $length && strpos('0123456789.eE+-', $json[$i]) !== false) { ++$i; }
                $output .= '"' . substr($json, $start, $i - $start) . '"';
            } else {
                $output .= $json[$i++];
            }
        }
        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
}
