<?php
/**
 * Arabic Glyphs Reshaper
 *
 * This class is a trimmed-down version of the I18N_Arabic class from the ar-php library,
 * specifically tailored for reshaping Arabic/Persian text for GD image rendering.
 *
 * Original library by Khaled Al-Shamaa <khaled.alshamaa@gmail.com>
 *
 * @package     Ar-PHP
 * @author      Khaled Al-Shamaa
 * @version     3.3
 * @license     LGPL
 */
class Arabic
{
    // CHARACTERS
    protected $_ar = array(
        'ARABIC_COMMA'               => 0x060C,
        'ARABIC_SEMICOLON'           => 0x061B,
        'ARABIC_QUESTION_MARK'       => 0x061F,
        'ARABIC_HAMZA'               => 0x0621,
        'ARABIC_LETTER_ALEF_WITH_MADDA_ABOVE' => 0x0622,
        'ARABIC_LETTER_ALEF_WITH_HAMZA_ABOVE' => 0x0623,
        'ARABIC_LETTER_WAW_WITH_HAMZA_ABOVE' => 0x0624,
        'ARABIC_LETTER_ALEF_WITH_HAMZA_BELOW' => 0x0625,
        'ARABIC_LETTER_YEH_WITH_HAMZA_ABOVE' => 0x0626,
        'ARABIC_LETTER_ALEF'         => 0x0627,
        'ARABIC_LETTER_BEH'          => 0x0628,
        'ARABIC_LETTER_TEH_MARBUTA'  => 0x0629,
        'ARABIC_LETTER_TEH'          => 0x062A,
        'ARABIC_LETTER_THEH'         => 0x062B,
        'ARABIC_LETTER_JEEM'         => 0x062C,
        'ARABIC_LETTER_HAH'          => 0x062D,
        'ARABIC_LETTER_KHAH'         => 0x062E,
        'ARABIC_LETTER_DAL'          => 0x062F,
        'ARABIC_LETTER_THAL'         => 0x0630,
        'ARABIC_LETTER_REH'          => 0x0631,
        'ARABIC_LETTER_ZAIN'         => 0x0632,
        'ARABIC_LETTER_SEEN'         => 0x0633,
        'ARABIC_LETTER_SHEEN'        => 0x0634,
        'ARABIC_LETTER_SAD'          => 0x0635,
        'ARABIC_LETTER_DAD'          => 0x0636,
        'ARABIC_LETTER_TAH'          => 0x0637,
        'ARABIC_LETTER_ZAH'          => 0x0638,
        'ARABIC_LETTER_AIN'          => 0x0639,
        'ARABIC_LETTER_GHAIN'        => 0x063A,
        'ARABIC_TATWEEL'             => 0x0640,
        'ARABIC_LETTER_FEH'          => 0x0641,
        'ARABIC_LETTER_QAF'          => 0x0642,
        'ARABIC_LETTER_KAF'          => 0x0643,
        'ARABIC_LETTER_LAM'          => 0x0644,
        'ARABIC_LETTER_MEEM'         => 0x0645,
        'ARABIC_LETTER_NOON'         => 0x0646,
        'ARABIC_LETTER_HEH'          => 0x0647,
        'ARABIC_LETTER_WAW'          => 0x0648,
        'ARABIC_LETTER_ALEF_MAKSURA' => 0x0649,
        'ARABIC_LETTER_YEH'          => 0x064A,
        'ARABIC_FATHATAN'            => 0x064B,
        'ARABIC_DAMMATAN'            => 0x064C,
        'ARABIC_KASRATAN'            => 0x064D,
        'ARABIC_FATHA'               => 0x064E,
        'ARABIC_DAMMA'               => 0x064F,
        'ARABIC_KASRA'               => 0x0650,
        'ARABIC_SHADDA'              => 0x0651,
        'ARABIC_SUKUN'               => 0x0652,
        'ARABIC_LETTER_PEH'          => 0x067E,
        'ARABIC_LETTER_TCHEH'        => 0x0686,
        'ARABIC_LETTER_JEH'          => 0x0698,
        'ARABIC_LETTER_GAF'          => 0x06AF,
        'FARSI_YEH'                  => 0x06CC,
    );

    protected $_glyph = array(
        0x0621 => array(0xFE80, 0xFE80, 0xFE80, 0xFE80), // 'HAMZA'
        0x0622 => array(0xFE81, 0xFE82, 0xFE81, 0xFE82), // 'ALEF WITH MADDA ABOVE'
        0x0623 => array(0xFE83, 0xFE84, 0xFE83, 0xFE84), // 'ALEF WITH HAMZA ABOVE'
        0x0624 => array(0xFE85, 0xFE86, 0xFE85, 0xFE86), // 'WAW WITH HAMZA ABOVE'
        0x0625 => array(0xFE87, 0xFE88, 0xFE87, 0xFE88), // 'ALEF WITH HAMZA BELOW'
        0x0626 => array(0xFE89, 0xFE8A, 0xFE8B, 0xFE8C), // 'YEH WITH HAMZA ABOVE'
        0x0627 => array(0xFE8D, 0xFE8E, 0xFE8D, 0xFE8E), // 'ALEF'
        0x0628 => array(0xFE8F, 0xFE90, 0xFE91, 0xFE92), // 'BEH'
        0x0629 => array(0xFE93, 0xFE94, 0xFE93, 0xFE94), // 'TEH MARBUTA'
        0x062A => array(0xFE95, 0xFE96, 0xFE97, 0xFE98), // 'TEH'
        0x062B => array(0xFE99, 0xFE9A, 0xFE9B, 0xFE9C), // 'THEH'
        0x062C => array(0xFE9D, 0xFE9E, 0xFE9F, 0xFEA0), // 'JEEM'
        0x062D => array(0xFEA1, 0xFEA2, 0xFEA3, 0xFEA4), // 'HAH'
        0x062E => array(0xFEA5, 0xFEA6, 0xFEA7, 0xFEA8), // 'KHAH'
        0x062F => array(0xFEA9, 0xFEAA, 0xFEA9, 0xFEAA), // 'DAL'
        0x0630 => array(0xFEAB, 0xFEAC, 0xFEAB, 0xFEAC), // 'THAL'
        0x0631 => array(0xFEAD, 0xFEAE, 0xFEAD, 0xFEAE), // 'REH'
        0x0632 => array(0xFEAF, 0xFEB0, 0xFEAF, 0xFEB0), // 'ZAIN'
        0x0633 => array(0xFEB1, 0xFEB2, 0xFEB3, 0xFEB4), // 'SEEN'
        0x0634 => array(0xFEB5, 0xFEB6, 0xFEB7, 0xFEB8), // 'SHEEN'
        0x0635 => array(0xFEB9, 0xFEBA, 0xFEBB, 0xFEBC), // 'SAD'
        0x0636 => array(0xFEBD, 0xFEBE, 0xFEBF, 0xFEC0), // 'DAD'
        0x0637 => array(0xFEC1, 0xFEC2, 0xFEC3, 0xFEC4), // 'TAH'
        0x0638 => array(0xFEC5, 0xFEC6, 0xFEC7, 0xFEC8), // 'ZAH'
        0x0639 => array(0xFEC9, 0xFECA, 0xFECB, 0xFECC), // 'AIN'
        0x063A => array(0xFECD, 0xFECE, 0xFECF, 0xFED0), // 'GHAIN'
        0x0641 => array(0xFED1, 0xFED2, 0xFED3, 0xFED4), // 'FEH'
        0x0642 => array(0xFED5, 0xFED6, 0xFED7, 0xFED8), // 'QAF'
        0x0643 => array(0xFED9, 0xFEDA, 0xFEDB, 0xFEDC), // 'KAF'
        0x0644 => array(0xFEDD, 0xFEDE, 0xFEDF, 0xFEE0), // 'LAM'
        0x0645 => array(0xFEE1, 0xFEE2, 0xFEE3, 0xFEE4), // 'MEEM'
        0x0646 => array(0xFEE5, 0xFEE6, 0xFEE7, 0xFEE8), // 'NOON'
        0x0647 => array(0xFEE9, 0xFEEA, 0xFEEB, 0xFEEC), // 'HEH'
        0x0648 => array(0xFEED, 0xFEEE, 0xFEED, 0xFEEE), // 'WAW'
        0x0649 => array(0xFEEF, 0xFEF0, 0xFEF0, 0xFEEF), // 'ALEF MAKSURA'
        0x064A => array(0xFEF1, 0xFEF2, 0xFEF3, 0xFEF4), // 'YEH'
        0x067E => array(0xFB56, 0xFB57, 0xFB58, 0xFB59), // 'PEH'
        0x0686 => array(0xFB7A, 0xFB7B, 0xFB7C, 0xFB7D), // 'TCHEH'
        0x0698 => array(0xFB8A, 0xFB8B, 0xFB8A, 0xFB8B), // 'JEH'
        0x06AF => array(0xFB92, 0xFB93, 0xFB94, 0xFB95), // 'GAF'
        0x06CC => array(0xFBFC, 0xFBFD, 0xFBFE, 0xFBFF), // 'FARSI YEH'
    );
    
    public function int2utf8($int)
    {
        return html_entity_decode('&#x' . dechex($int) . ';', ENT_COMPAT, 'UTF-8');
    }

    public function reshape($text)
    {
        $text = $this->utf8Strrev(preg_replace("/\n/", "<br>", $text));
        $text = preg_replace_callback('/(.)/u', array($this, '_utf8StrrevCallback'), $text);

        $utf8_str = $this->utf8str($text);
        $result = '';

        for ($i = 0; $i < count($utf8_str); $i++) {
            $prev = isset($utf8_str[$i - 1]) ? $utf8_str[$i-1] : false;
            $next = isset($utf8_str[$i + 1]) ? $utf8_str[$i+1] : false;
            $result .= $this->getCharGlyph($utf8_str[$i], $prev, $next);
        }
        
        return $result;
    }
    
    protected function getCharGlyph($char, $prev, $next)
    {
        $char_map = $this->_glyph;
        $arabic_map = $this->_ar;
        
        if (isset($char_map[$char])) {
            $prev_link = $this->isForwardLink($prev);
            $next_link = $this->isBackwardLink($next);
            $glyph = $char_map[$char][0];
            
            if ($prev_link && $next_link) {
                $glyph = $char_map[$char][3];
            } else if ($next_link) {
                $glyph = $char_map[$char][2];
            } else if ($prev_link) {
                $glyph = $char_map[$char][1];
            }
            
            return $this->int2utf8($glyph);
        } else {
            return $this->int2utf8($char);
        }
    }
    
    protected function isForwardLink($char)
    {
        if (in_array($char, array($this->_ar['ARABIC_LETTER_ALEF_WITH_MADDA_ABOVE'],
            $this->_ar['ARABIC_LETTER_ALEF_WITH_HAMZA_ABOVE'],
            $this->_ar['ARABIC_LETTER_ALEF_WITH_HAMZA_BELOW'],
            $this->_ar['ARABIC_LETTER_ALEF'], $this->_ar['ARABIC_LETTER_DAL'],
            $this->_ar['ARABIC_LETTER_THAL'], $this->_ar['ARABIC_LETTER_REH'],
            $this->_ar['ARABIC_LETTER_ZAIN'], $this->_ar['ARABIC_LETTER_WAW'],
            $this->_ar['ARABIC_LETTER_WAW_WITH_HAMZA_ABOVE'],
            $this->_ar['ARABIC_LETTER_JEH'], false))) {
            return false;
        }
        return true;
    }
    
    protected function isBackwardLink($char)
    {
        if (in_array($char, array($this->_ar['ARABIC_HAMZA'], false))) {
            return false;
        }
        return true;
    }
    
    protected function utf8str($str)
    {
        preg_match_all('/./u', $str, $matches);
        $result = array();
        foreach ($matches[0] as $char) {
            $result[] = $this->uniord($char);
        }
        return $result;
    }

    protected function utf8Strrev($str)
    {
        preg_match_all('/./u', $str, $matches);
        return join('', array_reverse($matches[0]));
    }
    
    protected function _utf8StrrevCallback($matches)
    {
        if (ord($matches[0]) >= 128) {
            return $this->utf8Strrev($matches[0]);
        } else {
            return $matches[0];
        }
    }

    protected function uniord($c)
    {
        $h = ord($c{0});
        if ($h <= 0x7F) {
            return $h;
        } else if ($h < 0xE0) {
            return (($h & 0x1F) << 6) | (ord($c{1}) & 0x3F);
        } else if ($h < 0xF0) {
            return (($h & 0x0F) << 12) | ((ord($c{1}) & 0x3F) << 6)
                                        | (ord($c{2}) & 0x3F);
        } else {
            return (($h & 0x07) << 18) | ((ord($c{1}) & 0x3F) << 12)
                                        | ((ord($c{2}) & 0x3F) << 6)
                                        | (ord($c{3}) & 0x3F);
        }
    }
}
?>