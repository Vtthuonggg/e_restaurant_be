<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'retail_cost' => 'nullable|integer',
            'base_cost' => 'nullable|integer',
            'image' => 'nullable|string|max:255',
            'unit' => 'nullable|string|max:50',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:categories,id',
            'ingredients' => 'nullable|array',
            'ingredients.*.id' => 'required|integer|exists:ingredients,id',
            'ingredients.*.quantity' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên sản phẩm là bắt buộc',
            'name.string' => 'Tên sản phẩm phải là chuỗi ký tự',
            'name.max' => 'Tên sản phẩm không được vượt quá 255 ký tự',
            'retail_cost.integer' => 'Giá bán phải là số nguyên',
            'base_cost.integer' => 'Giá vốn phải là số nguyên',
            'image.string' => 'Hình ảnh phải là chuỗi ký tự',
            'image.max' => 'Đường dẫn hình ảnh không được vượt quá 255 ký tự',
            'unit.string' => 'Đơn vị phải là chuỗi ký tự',
            'unit.max' => 'Đơn vị không được vượt quá 50 ký tự',
            'category_ids.array' => 'Danh mục phải là mảng',
            'category_ids.*.integer' => 'ID danh mục phải là số nguyên',
            'category_ids.*.exists' => 'Danh mục không tồn tại',
            'ingredients.array' => 'Nguyên liệu phải là mảng',
            'ingredients.*.id.required' => 'ID nguyên liệu là bắt buộc',
            'ingredients.*.id.integer' => 'ID nguyên liệu phải là số nguyên',
            'ingredients.*.id.exists' => 'Nguyên liệu không tồn tại',
            'ingredients.*.quantity.required' => 'Số lượng nguyên liệu là bắt buộc',
            'ingredients.*.quantity.numeric' => 'Số lượng nguyên liệu phải là số',
            'ingredients.*.quantity.min' => 'Số lượng nguyên liệu phải lớn hơn hoặc bằng 0',
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
