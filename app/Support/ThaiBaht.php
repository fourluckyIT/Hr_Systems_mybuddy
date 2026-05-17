<?php

namespace App\Support;

/**
 * Convert numeric amounts to formal Thai-baht prose, e.g.
 *   33123.51  →  "สามหมื่นสามพันหนึ่งร้อยยี่สิบสามบาทห้าสิบเอ็ดสตางค์"
 *   1000.00   →  "หนึ่งพันบาทถ้วน"
 *
 * Algorithm follows the standard Thai-numeric reading rules:
 *   - position digits: หน่วย / สิบ / ร้อย / พัน / หมื่น / แสน / ล้าน
 *   - 1 in tens-place is "สิบ" (not "หนึ่งสิบ")
 *   - 2 in tens-place is "ยี่สิบ" (not "สองสิบ")
 *   - 1 in ones-place after non-zero tens is "เอ็ด" (not "หนึ่ง")
 *   - "ล้าน" repeats every 6 digits for very large numbers
 */
class ThaiBaht
{
    private const DIGITS    = ['', 'หนึ่ง', 'สอง', 'สาม', 'สี่', 'ห้า', 'หก', 'เจ็ด', 'แปด', 'เก้า'];
    private const POSITIONS = ['', 'สิบ', 'ร้อย', 'พัน', 'หมื่น', 'แสน'];

    public static function inWords(float|int|string $amount): string
    {
        $amount = (float) $amount;
        $negative = $amount < 0;
        $amount = abs($amount);

        // Split baht / satang
        $baht = (int) floor($amount);
        $satang = (int) round(($amount - $baht) * 100);

        $bahtWords = self::numberToThai($baht);
        $out = ($baht > 0 ? $bahtWords : 'ศูนย์') . 'บาท';

        if ($satang > 0) {
            $out .= self::numberToThai($satang) . 'สตางค์';
        } else {
            $out .= 'ถ้วน';
        }

        return ($negative ? 'ลบ' : '') . $out;
    }

    protected static function numberToThai(int $n): string
    {
        if ($n === 0) return '';

        // For numbers > 999_999 (millions+), recurse with "ล้าน" suffix.
        if ($n >= 1_000_000) {
            $millions = intdiv($n, 1_000_000);
            $remainder = $n % 1_000_000;
            $out = self::numberToThai($millions) . 'ล้าน';
            if ($remainder > 0) $out .= self::numberToThai($remainder);
            return $out;
        }

        $digits = array_map('intval', str_split((string) $n));
        $len = count($digits);
        $out = '';

        foreach ($digits as $i => $digit) {
            $pos = $len - 1 - $i;
            if ($digit === 0) continue;

            // Special: tens-place
            if ($pos === 1) {
                if ($digit === 1) {
                    $out .= 'สิบ';
                    continue;
                }
                if ($digit === 2) {
                    $out .= 'ยี่สิบ';
                    continue;
                }
            }

            // Special: ones-place after non-zero tens uses "เอ็ด"
            if ($pos === 0 && $digit === 1 && $len > 1 && $digits[$len - 2] !== 0) {
                $out .= 'เอ็ด';
                continue;
            }

            $out .= self::DIGITS[$digit] . self::POSITIONS[$pos];
        }

        return $out;
    }
}
