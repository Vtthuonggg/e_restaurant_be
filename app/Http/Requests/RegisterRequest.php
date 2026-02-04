<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:15|unique:users,phone',  // ← Phone unique và bắt buộc
            'email' => 'required|email|max:255|unique:users,email',  // ← Email để xác thực
            'password' => 'required|string|min:6',
            'store_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên là bắt buộc',
            'name.string' => 'Tên phải là chuỗi ký tự',
            'name.max' => 'Tên không được vượt quá 255 ký tự',

            'phone.required' => 'Số điện thoại là bắt buộc',
            'phone.string' => 'Số điện thoại phải là chuỗi ký tự',
            'phone.max' => 'Số điện thoại không được vượt quá 15 ký tự',
            'phone.unique' => 'Số điện thoại đã được sử dụng',

            'email.required' => 'Email là bắt buộc',
            'email.email' => 'Email không hợp lệ',
            'email.max' => 'Email không được vượt quá 255 ký tự',
            'email.unique' => 'Email đã được sử dụng',

            'password.required' => 'Mật khẩu là bắt buộc',
            'password.string' => 'Mật khẩu phải là chuỗi ký tự',
            'password.min' => 'Mật khẩu phải có ít nhất 6 ký tự',

            'store_name.string' => 'Tên cửa hàng phải là chuỗi ký tự',
            'store_name.max' => 'Tên cửa hàng không được vượt quá 255 ký tự',

            'address.string' => 'Địa chỉ phải là chuỗi ký tự',
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
