<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted'             => 'El :attribute debe ser aceptado.',
    'active_url'           => 'El :attribute no es un URL válido.',
    'after'                => 'El :attribute debe ser una fecha después :date.',
    'alpha'                => 'El :attribute solo debe contener letras.',
    'alpha_dash'           => 'El :attribute solo puede contener letras, números y guiones.',
    'alpha_num'            => 'El :attribute solo puede contener letras y números.',
    'array'                => 'El :attribute debe ser un arreglo.',
    'before'               => 'El :attribute debe ser una fecha anterior :date.',
    'between'              => [
        'numeric' => 'El :attribute debe estar entre :min y :max.',
        'file'    => 'El :attribute debe tener entre :min y :max kilobytes.',
        'string'  => 'El :attribute debe tener entre :min y :max caracteres.',
        'array'   => 'El :attribute debe tener entre :min y :max de objetos.',
    ],
    'boolean'              => 'El :attribute valor del campo debe ser verdadero o falso.',
    'confirmed'            => 'El :attribute de confirmación no coincide.',
    'date'                 => 'El :attribute no es una fecha válida.',
    'date_format'          => 'El :attribute no coincide con el formato :format.',
    'different'            => 'El :attribute y :other deben ser diferentes.',
    'digits'               => 'El :attribute deben ser :digits digitos.',
    'digits_between'       => 'El :attribute debe tener entre :min y :max de digitos.',
    'distinct'             => 'El :attribute tiene un valor duplicado.',
    'email'                => 'El :attribute debe ser una dirección de email válida.',
    'exists'               => 'El :attribute seleccionado is invalid.',
    'filled'               => 'El :attribute campo es requerido.',
    'image'                => 'El :attribute debe ser una imagen.',
    'in'                   => 'El :attribute seleccionado es inválido.',
    'in_array'             => 'El :attribute campo no existe en :other.',
    'integer'              => 'El :attributedebe ser un número entero.',
    'ip'                   => 'El :attribute debe ser una dirección IP válida.',
    'json'                 => 'El :attribute debe ser una cadena JSON válida.',
    'max'                  => [
        'numeric' => 'El :attribute no puede ser mayor que :max.',
        'file'    => 'El :attribute no puede ser mayor de :max kilobytes.',
        'string'  => 'El :attribute no puede ser mayor a :max caracteres.',
        'array'   => 'TEl :attribute no puede tener mas de :max objetos.',
    ],
    'mimes'                => 'El :attribute debe ser un archivo del tipo: :values.',
    'min'                  => [
        'numeric' => 'El :attribute debe ser al menos :min.',
        'file'    => 'El :attribute debe tener al menos :min kilobytes.',
        'string'  => 'El :attribute debe tener al menos :min caracteres.',
        'array'   => 'El :attribute debe tener al menos :min objetos.',
    ],
    'not_in'               => 'El :attribute seleccionado es inválido.',
    'numeric'              => 'El :attribute debe ser un número.',
    'present'              => 'El :attribute campo debe estar presente.',
    'regex'                => 'El :attribute formato es inválido.',
    'required'             => 'El :attribute campo es requerido.',
    'required_if'          => 'El :attribute campo es requerido cuendo :other es :value.',
    'required_unless'      => 'El :attribute campo es requerido a menos que :other sea :values.',
    'required_with'        => 'El :attribute campo es requerido cuando :values esta presente.',
    'required_with_all'    => 'El :attribute campo es requerido cuando :values esta presente.',
    'required_without'     => 'El :attribute campo es requerido cuando :values no está presente.',
    'required_without_all' => 'El :attribute campo es requerido cuando ninguno de :values estan presentes.',
    'same'                 => 'El :attribute y :other deben coincidir.',
    'size'                 => [
        'numeric' => 'El :attribute debe ser de :size.',
        'file'    => 'El :attribute debe ser de :size kilobytes.',
        'string'  => 'El :attribute puede ser de :size caracteres.',
        'array'   => 'El :attribute debe contener :size objetos.',
    ],
    'string'               => 'El :attribute debe ser una cadena.',
    'timezone'             => 'El :attribute debe ser una zona válida.',
    'unique'               => 'El :attribute ya había sido tomado.',
    'url'                  => 'El :attribute formato es inválido.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap attribute place-holders
    | with something more reader friendly such as E-Mail Address instead
    | of "email". This simply helps us make messages a little cleaner.
    |
    */

    'attributes' => [],

];
