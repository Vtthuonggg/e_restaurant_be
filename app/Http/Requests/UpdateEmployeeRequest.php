<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:20|unique:users,phone,' . $this->route('employee'),
            'password' => 'nullable|string|min:6',
            'role' => 'sometimes|string|in:employee,supervisor,cashier,chef',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên nhân viên là bắt buộc',
            'name.string' => 'Tên nhân viên phải là chuỗi ký tự',
            'name.max' => 'Tên nhân viên không được vượt quá 255 ký tự',
            'phone.required' => 'Số điện thoại là bắt buộc',
            'phone.string' => 'Số điện thoại phải là chuỗi ký tự',
            'phone.max' => 'Số điện thoại không được vượt quá 20 ký tự',
            'phone.unique' => 'Số điện thoại đã tồn tại',
            'password.string' => 'Mật khẩu phải là chuỗi ký tự',
            'password.min' => 'Mật khẩu phải có ít nhất 6 ký tự',
            'role.string' => 'Vai trò phải là chuỗi ký tự',
            'role.in' => 'Vai trò không hợp lệ. Chọn: employee, supervisor, cashier, chef',
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
