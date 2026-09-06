<?php

namespace App\Support;

class Phone
{
    public const PATTERN = '/^05[0-9]{8}$/';

    /**
     * @return list<string>
     */
    public static function rules(bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'regex:'.self::PATTERN,
        ];
    }

    public static function message(): string
    {
        return 'الهاتف يجب أن يكون 10 خانات ويبدأ بـ 05.';
    }
}
