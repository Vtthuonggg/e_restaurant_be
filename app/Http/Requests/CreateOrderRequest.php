<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'type' => 'required|integer|in:1,2',
            'note' => 'nullable|string',
            'discount' => 'sometimes|numeric|min:0',
            'discount_type' => 'sometimes|integer|in:1,2',
            'status_order' => 'sometimes|integer|in:1,2',
            'payment' => 'required',
            'payment.type' => 'required|integer|in:1,2,3',
            'payment.price' => 'required|numeric|min:0',
            'order_detail' => 'required|array|min:1',
        ];

        // Validation cho đơn BÁN (type = 1)
        if ($this->input('type') == 1) {
            $rules = array_merge($rules, [
                'room_id' => 'required|integer|exists:rooms,id',
                'room_type' => 'required|string|in:free,using,pre_book',
                'customer_id' => 'nullable|integer|exists:customers,id',
                'supplier_id' => 'nullable', // Không cần supplier

                // Order detail cho đơn bán (product)
                'order_detail.*.product_id' => 'required|integer|exists:products,id',
                'order_detail.*.ingredient_id' => 'nullable', // Không dùng ingredient_id
                'order_detail.*.quantity' => 'required|numeric|min:0.1',
                'order_detail.*.price' => 'required|numeric|min:0',
                'order_detail.*.discount' => 'sometimes|numeric|min:0',
                'order_detail.*.discount_type' => 'sometimes|integer|in:1,2',
                'order_detail.*.note' => 'nullable|string',
                'order_detail.*.topping' => 'sometimes|array',
                'order_detail.*.topping.*.product_id' => 'required|integer|exists:products,id',
                'order_detail.*.topping.*.quantity' => 'required|numeric|min:0.1',
            ]);
        }

        // Validation cho đơn NHẬP (type = 2)
        if ($this->input('type') == 2) {
            $rules = array_merge($rules, [
                'room_id' => 'nullable', // Không cần room
                'room_type' => 'nullable',
                'customer_id' => 'nullable', // Không cần customer
                'supplier_id' => 'nullable',

                // Order detail cho đơn nhập (ingredient)
                'order_detail.*.ingredient_id' => 'required|integer|exists:ingredients,id',
                'order_detail.*.product_id' => 'nullable', // Không dùng product_id
                'order_detail.*.quantity' => 'required|numeric|min:0.1',
                'order_detail.*.price' => 'required|numeric|min:0',
                'order_detail.*.discount' => 'sometimes|numeric|min:0',
                'order_detail.*.discount_type' => 'sometimes|integer|in:1,2',
                'order_detail.*.note' => 'nullable|string',
            ]);
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Loại đơn hàng là bắt buộc',
            'type.integer' => 'Loại đơn hàng phải là số nguyên',
            'type.in' => 'Loại đơn hàng không hợp lệ (1: Bán, 2: Nhập)',

            // Đơn bán
            'room_id.required' => 'Bàn là bắt buộc với đơn bán',
            'room_id.integer' => 'ID bàn phải là số nguyên',
            'room_id.exists' => 'Bàn không tồn tại',
            'room_type.required' => 'Loại bàn là bắt buộc với đơn bán',
            'room_type.string' => 'Loại bàn phải là chuỗi ký tự',
            'room_type.in' => 'Loại bàn không hợp lệ (free, using, pre_book)',

            // Đơn nhập
            'supplier_id.required' => 'Nhà cung cấp là bắt buộc với đơn nhập',
            'supplier_id.integer' => 'ID nhà cung cấp phải là số nguyên',
            'supplier_id.exists' => 'Nhà cung cấp không tồn tại',

            'customer_id.integer' => 'ID khách hàng phải là số nguyên',
            'customer_id.exists' => 'Khách hàng không tồn tại',
            'note.string' => 'Ghi chú phải là chuỗi ký tự',
            'discount.numeric' => 'Giảm giá phải là số',
            'discount.min' => 'Giảm giá phải lớn hơn hoặc bằng 0',
            'discount_type.integer' => 'Loại giảm giá phải là số nguyên',
            'discount_type.in' => 'Loại giảm giá không hợp lệ (1: %, 2: VNĐ)',
            'status_order.integer' => 'Trạng thái đơn hàng phải là số nguyên',
            'status_order.in' => 'Trạng thái đơn hàng không hợp lệ (1: Hoàn thành, 2: Chưa hoàn thành)',

            'payment.required' => 'Thông tin thanh toán là bắt buộc',
            'payment.array' => 'Thông tin thanh toán phải là mảng',
            'payment.type.required' => 'Loại thanh toán là bắt buộc',
            'payment.type.integer' => 'Loại thanh toán phải là số nguyên',
            'payment.type.in' => 'Loại thanh toán không hợp lệ (1: Tiền mặt, 2: Chuyển khoản, 3: Thẻ)',
            'payment.price.required' => 'Số tiền thanh toán là bắt buộc',
            'payment.price.numeric' => 'Số tiền thanh toán phải là số',
            'payment.price.min' => 'Số tiền thanh toán phải lớn hơn hoặc bằng 0',

            'order_detail.required' => 'Chi tiết đơn hàng là bắt buộc',
            'order_detail.array' => 'Chi tiết đơn hàng phải là mảng',
            'order_detail.min' => 'Đơn hàng phải có ít nhất 1 mục',

            // Product (đơn bán)
            'order_detail.*.product_id.required' => 'ID sản phẩm là bắt buộc',
            'order_detail.*.product_id.integer' => 'ID sản phẩm phải là số nguyên',
            'order_detail.*.product_id.exists' => 'Sản phẩm không tồn tại',

            // Ingredient (đơn nhập)
            'order_detail.*.ingredient_id.required' => 'ID nguyên liệu là bắt buộc',
            'order_detail.*.ingredient_id.integer' => 'ID nguyên liệu phải là số nguyên',
            'order_detail.*.ingredient_id.exists' => 'Nguyên liệu không tồn tại',

            'order_detail.*.quantity.required' => 'Số lượng là bắt buộc',
            'order_detail.*.quantity.numeric' => 'Số lượng phải là số',
            'order_detail.*.quantity.min' => 'Số lượng phải lớn hơn 0',
            'order_detail.*.price.required' => 'Giá là bắt buộc',
            'order_detail.*.price.numeric' => 'Giá phải là số',
            'order_detail.*.price.min' => 'Giá phải lớn hơn hoặc bằng 0',
            'order_detail.*.discount.numeric' => 'Giảm giá phải là số',
            'order_detail.*.discount.min' => 'Giảm giá phải lớn hơn hoặc bằng 0',
            'order_detail.*.discount_type.integer' => 'Loại giảm giá phải là số nguyên',
            'order_detail.*.discount_type.in' => 'Loại giảm giá không hợp lệ',
            'order_detail.*.note.string' => 'Ghi chú phải là chuỗi ký tự',

            // Topping (chỉ đơn bán)
            'order_detail.*.topping.array' => 'Topping phải là mảng',
            'order_detail.*.topping.*.product_id.required' => 'ID topping là bắt buộc',
            'order_detail.*.topping.*.product_id.integer' => 'ID topping phải là số nguyên',
            'order_detail.*.topping.*.product_id.exists' => 'Topping không tồn tại',
            'order_detail.*.topping.*.quantity.required' => 'Số lượng topping là bắt buộc',
            'order_detail.*.topping.*.quantity.numeric' => 'Số lượng topping phải là số',
            'order_detail.*.topping.*.quantity.min' => 'Số lượng topping phải lớn hơn 0',
            'order_detail.*.topping.*.price.required' => 'Giá topping là bắt buộc',
            'order_detail.*.topping.*.price.numeric' => 'Giá topping phải là số',
            'order_detail.*.topping.*.price.min' => 'Giá topping phải lớn hơn hoặc bằng 0',
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
