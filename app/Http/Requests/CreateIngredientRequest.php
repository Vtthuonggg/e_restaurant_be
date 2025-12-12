<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateIngredientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'base_cost' => 'nullable|integer',
            'retail_cost' => 'nullable|integer',
            'in_stock' => 'nullable|numeric',
            'image' => 'nullable|string|max:255',
            'unit' => 'nullable|string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên nguyên liệu là bắt buộc',
            'name.string' => 'Tên nguyên liệu phải là chuỗi ký tự',
            'name.max' => 'Tên nguyên liệu không được vượt quá 255 ký tự',
            'base_cost.integer' => 'Giá vốn phải là số nguyên',
            'retail_cost.integer' => 'Giá bán phải là số nguyên',
            'in_stock.numeric' => 'Số lượng tồn kho phải là số',
            'image.string' => 'Hình ảnh phải là chuỗi ký tự',
            'image.max' => 'Đường dẫn hình ảnh không được vượt quá 255 ký tự',
            'unit.string' => 'Đơn vị phải là chuỗi ký tự',
            'unit.max' => 'Đơn vị không được vượt quá 50 ký tự',
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
