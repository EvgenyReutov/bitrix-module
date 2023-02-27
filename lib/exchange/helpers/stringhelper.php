<?php


namespace Instrum\Main\Exchange\Helpers;


class StringHelper
{
    /**
     * @param string $cyr_str
     * @return string
     */
    public static function translit($cyr_str)
    {
        $tr = array(
            'Ґ' => 'G', 'Ё' => 'YO', 'Є' => 'E', 'Ї' => 'YI', 'І' => 'I',
            'і' => 'i', 'ґ' => 'g', 'ё' => 'yo', '№' => '#', 'є' => 'e',
            'ї' => 'yi', 'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G',
            'Д' => 'D', 'Е' => 'E', 'Ж' => 'ZH', 'З' => 'Z', 'И' => 'I',
            'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N',
            'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T',
            'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'TS', 'Ч' => 'CH',
            'Ш' => 'SH', 'Щ' => 'SCH', 'Ъ' => "'", 'Ы' => 'YI', 'Ь' => '',
            'Э' => 'E', 'Ю' => 'YU', 'Я' => 'YA', 'а' => 'a', 'б' => 'b',
            'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ж' => 'zh',
            'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l',
            'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r',
            'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h',
            'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => "'",
            'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        );

        return strtr($cyr_str, $tr);
    }

    /**
     * @param $value
     * @return string
     */
    public static function prepareUrl($value, $separator = '-')
    {
        $value = static::translit($value);
        $value = preg_replace('#\s+#si', $separator, $value);
        $value = preg_replace('/[^A-Za-z0-9' . $separator . ']+/', '', $value);
        $value = preg_replace('/' . $separator . '+/', $separator, $value);
        $value = toLower($value);
        return $value;
    }

    /**
     * @param string $value
     * @param string $separator
     * @return string
     */
    public static function increment($value, $separator = '-')
    {
        $parts = explode($separator, $value);
        $last = array_pop($parts);
        if(is_numeric($last)) {
            ++$last;
        } else {
            $parts[] = $last;
            $last = 2;
        }
        $parts[] = $last;
        return join($separator, $parts);
    }

    /**
     * @param string $value
     * @return bool
     */
    public static function isUuid($value)
    {
        $pm = preg_match('/^[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}$/i', $value);
        return $pm === 1;
    }


    /**
     * @param $str
     * @param $start
     * @return bool
     */
    public static function startsWith($str, $start)
    {
        $len = strlen($start);
        return (substr($str, 0, $len) === $start);
    }
}