<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'area_id' => 'sometimes|required|integer|exists:areas,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên bàn là bắt buộc',
            'name.string' => 'Tên bàn phải là chuỗi ký tự',
            'name.max' => 'Tên bàn không được vượt quá 255 ký tự',
            'area_id.required' => 'Khu vực là bắt buộc',
            'area_id.integer' => 'ID khu vực phải là số nguyên',
            'area_id.exists' => 'Khu vực không tồn tại',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status' => 'error',
            'message' => 'Dữ liệu không hợp lệ',
            'errors' => $validator->errors()
        ], 422));
    }
}
