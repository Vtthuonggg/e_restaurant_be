<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:10',
            'image' => 'nullable|string',
            'store_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'name.string' => 'Tên không hợp lệ',
            'name.max' => 'Tên không được vượt quá 255 ký tự',
            'phone.string' => 'Số điện thoại không hợp lệ',
            'phone.max' => 'Số điện thoại không được vượt quá 20 ký tự',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $response = new JsonResponse([
            'status' => 'error',
            'message' => 'Dữ liệu không hợp lệ',
            'data' => $validator->errors(),
        ], Response::HTTP_UNPROCESSABLE_ENTITY);

        throw new ValidationException($validator, $response);
    }
}
