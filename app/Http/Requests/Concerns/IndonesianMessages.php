<?php

namespace App\Http\Requests\Concerns;

/**
 * Pesan validasi Bahasa Indonesia yang dipakai bersama oleh seluruh Form Request.
 */
trait IndonesianMessages
{
    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'integer' => ':attribute harus berupa bilangan bulat.',
            'numeric' => ':attribute harus berupa angka.',
            'string' => ':attribute harus berupa teks.',
            'boolean' => ':attribute tidak valid.',
            'array' => ':attribute tidak valid.',
            'date' => ':attribute bukan tanggal yang valid.',
            'in' => ':attribute yang dipilih tidak valid.',
            'enum' => ':attribute yang dipilih tidak valid.',
            'exists' => ':attribute tidak ditemukan.',
            'distinct' => ':attribute duplikat.',
            'file' => ':attribute harus berupa file.',
            'extensions' => ':attribute harus berformat :values.',
            'after_or_equal' => ':attribute harus setelah atau sama dengan :date.',
            'before_or_equal' => ':attribute tidak boleh melewati :date.',
            'date_format' => ':attribute harus berformat :format.',
            'min' => [
                'numeric' => ':attribute minimal :min.',
                'string' => ':attribute minimal :min karakter.',
                'array' => ':attribute minimal :min item.',
                'file' => ':attribute minimal :min KB.',
            ],
            'max' => [
                'numeric' => ':attribute maksimal :max.',
                'string' => ':attribute maksimal :max karakter.',
                'array' => ':attribute maksimal :max item.',
                'file' => 'Ukuran :attribute maksimal :max KB.',
            ],
        ];
    }
}
