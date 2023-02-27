<?php

namespace Instrum\Main\Filter;

class FilterUrlMaker
{
    /**
     * @param string $cyr_str
     * @return string
     */
    protected function translit($cyr_str)
    {
        $tr = array(
            'Ґ' => 'G',
            'Ё' => 'YO',
            'Є' => 'E',
            'Ї' => 'YI',
            'І' => 'I',
            'і' => 'i',
            'ґ' => 'g',
            'ё' => 'yo',
            '№' => '#',
            'є' => 'e',
            'ї' => 'yi',
            'А' => 'A',
            'Б' => 'B',
            'В' => 'V',
            'Г' => 'G',
            'Д' => 'D',
            'Е' => 'E',
            'Ж' => 'ZH',
            'З' => 'Z',
            'И' => 'I',
            'Й' => 'Y',
            'К' => 'K',
            'Л' => 'L',
            'М' => 'M',
            'Н' => 'N',
            'О' => 'O',
            'П' => 'P',
            'Р' => 'R',
            'С' => 'S',
            'Т' => 'T',
            'У' => 'U',
            'Ф' => 'F',
            'Х' => 'H',
            'Ц' => 'TS',
            'Ч' => 'CH',
            'Ш' => 'SH',
            'Щ' => 'SCH',
            'Ъ' => "'",
            'Ы' => 'YI',
            'Ь' => '',
            'Э' => 'E',
            'Ю' => 'YU',
            'Я' => 'YA',
            'а' => 'a',
            'б' => 'b',
            'в' => 'v',
            'г' => 'g',
            'д' => 'd',
            'е' => 'e',
            'ж' => 'zh',
            'з' => 'z',
            'и' => 'i',
            'й' => 'y',
            'к' => 'k',
            'л' => 'l',
            'м' => 'm',
            'н' => 'n',
            'о' => 'o',
            'п' => 'p',
            'р' => 'r',
            'с' => 's',
            'т' => 't',
            'у' => 'u',
            'ф' => 'f',
            'х' => 'h',
            'ц' => 'ts',
            'ч' => 'ch',
            'ш' => 'sh',
            'щ' => 'sch',
            'ъ' => "'",
            'ы' => 'y',
            'ь' => '',
            'э' => 'e',
            'ю' => 'yu',
            'я' => 'ya',
        );

        return strtr($cyr_str, $tr);
    }

    /**
     * @param $value
     * @return string
     */
    public function makeUrlPart($value)
    {
        $value = $this->translit($value);
        $value = str_replace(
            [
                '€',
                '/',
                '"',
                '+',
            ],
            [
                '-euro-',
                '-of-',
                '-inch-',
                '-plyus-'
            ],
            $value
        );

        $value = htmlentities($value, null, 'utf-8');
        $value = str_replace('&nbsp;', '-', $value);
        $value = html_entity_decode($value);

        $value = preg_replace('#\s+#si', '-', $value);
        $value = preg_replace("/(?![.=$'%-])\p{P}/u", '', $value);
        $value = trim($value, '-');
        $value = mb_strtolower($value);
        return $value;
    }
}