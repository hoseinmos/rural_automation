<?php
/**
 * Persian/Arabic Text Reshaper
 *
 * This class converts Arabic characters from their isolated forms to their
 * initial, medial, or final forms depending on their context. This is
 * necessary for rendering Arabic text correctly in environments that do not
 * support complex text layout (like PHP's GD library).
 *
 * @author   Saber Rastikerdar <saber.rastikerdar@gmail.com>
 * @license  MIT
 */

class PersianShaper {
    // Character map for Arabic letter forms
    private static $glyph_map = [
        'ء' => ['FE80'], 'آ' => ['FE81', 'FE82'], 'أ' => ['FE83', 'FE84'], 'ؤ' => ['FE85', 'FE86'], 'إ' => ['FE87', 'FE88'], 'ئ' => ['FE8B', 'FE8C', 'FE8A', 'FE89'],
        'ا' => ['FE8D', 'FE8E'], 'ب' => ['FE91', 'FE92', 'FE90', 'FE8F'], 'ة' => ['FE93', 'FE94'], 'ت' => ['FE97', 'FE98', 'FE96', 'FE95'], 'ث' => ['FE9B', 'FE9C', 'FE9A', 'FE99'],
        'ج' => ['FE9F', 'FEA0', 'FE9E', 'FE9D'], 'ح' => ['FEA3', 'FEA4', 'FEA2', 'FEA1'], 'خ' => ['FEA7', 'FEA8', 'FEA6', 'FEA5'], 'د' => ['FEA9', 'FEAA'], 'ذ' => ['FEAB', 'FEAC'],
        'ر' => ['FEAD', 'FEAE'], 'ز' => ['FEAF', 'FEB0'], 'س' => ['FEB3', 'FEB4', 'FEB2', 'FEB1'], 'ش' => ['FEB7', 'FEB8', 'FEB6', 'FEB5'], 'ص' => ['FEBB', 'FEBC', 'FEBA', 'FEB9'],
        'ض' => ['FEBF', 'FEC0', 'FEBE', 'FEBD'], 'ط' => ['FEC3', 'FEC4', 'FEC2', 'FEC1'], 'ظ' => ['FEC7', 'FEC8', 'FEC6', 'FEC5'], 'ع' => ['FECB', 'FECC', 'FECA', 'FEC9'],
        'غ' => ['FECF', 'FED0', 'FECE', 'FECD'], 'ـ' => ['FBA8', 'FBA9', 'FBA7', 'FBA6'], 'ف' => ['FED3', 'FED4', 'FED2', 'FED1'], 'ق' => ['FED7', 'FED8', 'FED6', 'FED5'],
        'ك' => ['FEDB', 'FEDC', 'FEDA', 'FED9'], 'ل' => ['FEDF', 'FEE0', 'FEDE', 'FEDD'], 'م' => ['FEE3', 'FEE4', 'FEE2', 'FEE1'], 'ن' => ['FEE7', 'FEE8', 'FEE6', 'FEE5'],
        'ه' => ['FEEB', 'FEEC', 'FEEA', 'FEE9'], 'و' => ['FEED', 'FEEE'], 'ى' => ['FEF3', 'FEF4', 'FEF2', 'FEF1'], 'ي' => ['FEF3', 'FEF4', 'FEF2', 'FEF1'],
        'پ' => ['FB58', 'FB59', 'FB57', 'FB56'], 'چ' => ['FB7C', 'FB7D', 'FB7B', 'FB7A'], 'ژ' => ['FB8B', 'FB8A'], 'گ' => ['FB94', 'FB95', 'FB93', 'FB92'],
        // Ligatures
        'ﻻ' => ['FEFB', 'FEFC'], 'ﻷ' => ['FEF7', 'FEF8'], 'ﻹ' => ['FEF9', 'FEFA'], 'ﻵ' => ['FEF5', 'FEF6']
    ];

    /**
     * Reshapes the given Arabic string.
     *
     * @param string $text The text to be reshaped.
     * @return string The reshaped text.
     */
    public static function reshape($text) {
        // Normalize different variations of Arabic characters
        $text = self::normalize($text);
        $len = mb_strlen($text, 'UTF-8');
        $output = '';

        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($text, $i, 1, 'UTF-8');
            
            // Handle lam + alef ligatures
            if ($char == 'ل' && $i + 1 < $len) {
                $next_char = mb_substr($text, $i + 1, 1, 'UTF-8');
                $ligature = 'ل' . $next_char;
                if (isset(self::$glyph_map[$ligature])) {
                    $prev_char = ($i > 0) ? mb_substr($text, $i - 1, 1, 'UTF-8') : '';
                    if (self::joins_with_prev($prev_char)) {
                         $output .= self::hex_to_utf8(self::$glyph_map[$ligature][1]);
                    } else {
                         $output .= self::hex_to_utf8(self::$glyph_map[$ligature][0]);
                    }
                    $i++; // Skip the next character (alef)
                    continue;
                }
            }

            if (isset(self::$glyph_map[$char])) {
                $prev_char = ($i > 0) ? mb_substr($text, $i - 1, 1, 'UTF-8') : '';
                $next_char = ($i + 1 < $len) ? mb_substr($text, $i + 1, 1, 'UTF-8') : '';

                $joins_prev = self::joins_with_prev($prev_char);
                $joins_next = self::joins_with_next($char, $next_char);
                $forms = self::$glyph_map[$char];
                
                $form_index = 0; // Isolated
                if ($joins_prev && $joins_next && count($forms) == 4) {
                    $form_index = 3; // Medial
                } elseif ($joins_prev && (count($forms) == 2 || count($forms) == 4)) {
                    $form_index = 1; // Final
                } elseif ($joins_next && count($forms) >= 3) {
                    $form_index = 2; // Initial
                }
                
                $output .= self::hex_to_utf8($forms[$form_index]);
            } else {
                $output .= $char;
            }
        }
        
        // Reverse for right-to-left display
        preg_match_all('/./us', $output, $matches);
        return implode('', array_reverse($matches[0]));
    }
    
    /**
     * Normalizes Arabic characters.
     */
    private static function normalize($text) {
        $text = str_replace('ي', 'ي', $text);
        $text = str_replace('ك', 'ك', $text);
        return $text;
    }
    
    /**
     * Checks if a character joins with the previous one.
     */
    private static function joins_with_prev($char) {
        if (!isset(self::$glyph_map[$char])) return false;
        $forms = self::$glyph_map[$char];
        return count($forms) >= 2;
    }

    /**
     * Checks if a character joins with the next one.
     */
    private static function joins_with_next($char, $next_char) {
        if (!isset(self::$glyph_map[$char]) || !isset(self::$glyph_map[$next_char])) return false;
        $forms = self::$glyph_map[$char];
        return count($forms) >= 3;
    }

    /**
     * Converts a hex code to its UTF-8 character representation.
     */
    private static function hex_to_utf8($hex) {
        return html_entity_decode('&#x' . $hex . ';', ENT_QUOTES, 'UTF-8');
    }
}
?>