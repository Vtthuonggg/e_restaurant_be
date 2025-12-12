<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => 'required|string',
            'password' => 'required|string',
            'is_employee' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'Số điện thoại là bắt buộc',
            'phone.string' => 'Số điện thoại không hợp lệ',
            'password.required' => 'Mật khẩu là bắt buộc',
            'password.string' => 'Mật khẩu không hợp lệ',
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
