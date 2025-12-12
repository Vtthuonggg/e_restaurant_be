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
        return [
            'type' => 'sometimes|integer|in:1,2',
            'room_id' => 'nullable|integer|exists:rooms,id',
            'room_type' => 'sometimes|string|in:free,using,pre_book',
            'note' => 'nullable|string',
            'discount' => 'sometimes|numeric|min:0',
            'discount_type' => 'sometimes|integer|in:1,2',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'status_order' => 'sometimes|integer|in:1,2',
            'payment' => 'required|array',
            'payment.type' => 'required|integer|in:1,2,3',
            'payment.price' => 'required|numeric|min:0',
            'order_detail' => 'required|array|min:1',
            'order_detail.*.product_id' => 'required|integer|exists:products,id',
            'order_detail.*.quantity' => 'required|numeric|min:0.1',
            'order_detail.*.user_price' => 'required|numeric|min:0',
            'order_detail.*.discount' => 'sometimes|numeric|min:0',
            'order_detail.*.discount_type' => 'sometimes|integer|in:1,2',
            'order_detail.*.note' => 'nullable|string',
            'order_detail.*.topping' => 'sometimes|array',
            'order_detail.*.topping.*.product_id' => 'required|integer|exists:products,id',
            'order_detail.*.topping.*.quantity' => 'required|numeric|min:0.1',
            'order_detail.*.topping.*.user_price' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'type.integer' => 'Loại đơn hàng phải là số nguyên',
            'type.in' => 'Loại đơn hàng không hợp lệ (1: Bán, 2: Nhập)',
            'room_id.integer' => 'ID bàn phải là số nguyên',
            'room_id.exists' => 'Bàn không tồn tại',
            'room_type.string' => 'Loại bàn phải là chuỗi ký tự',
            'room_type.in' => 'Loại bàn không hợp lệ (free, using, pre_book)',
            'note.string' => 'Ghi chú phải là chuỗi ký tự',
            'discount.numeric' => 'Giảm giá phải là số',
            'discount.min' => 'Giảm giá phải lớn hơn hoặc bằng 0',
            'discount_type.integer' => 'Loại giảm giá phải là số nguyên',
            'discount_type.in' => 'Loại giảm giá không hợp lệ (1: %, 2: VNĐ)',
            'customer_id.integer' => 'ID khách hàng phải là số nguyên',
            'customer_id.exists' => 'Khách hàng không tồn tại',
            'supplier_id.integer' => 'ID nhà cung cấp phải là số nguyên',
            'supplier_id.exists' => 'Nhà cung cấp không tồn tại',
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
            'order_detail.min' => 'Đơn hàng phải có ít nhất 1 sản phẩm',
            'order_detail.*.product_id.required' => 'ID sản phẩm là bắt buộc',
            'order_detail.*.product_id.integer' => 'ID sản phẩm phải là số nguyên',
            'order_detail.*.product_id.exists' => 'Sản phẩm không tồn tại',
            'order_detail.*.quantity.required' => 'Số lượng là bắt buộc',
            'order_detail.*.quantity.numeric' => 'Số lượng phải là số',
            'order_detail.*.quantity.min' => 'Số lượng phải lớn hơn 0',
            'order_detail.*.user_price.required' => 'Giá bán là bắt buộc',
            'order_detail.*.user_price.numeric' => 'Giá bán phải là số',
            'order_detail.*.user_price.min' => 'Giá bán phải lớn hơn hoặc bằng 0',
            'order_detail.*.discount.numeric' => 'Giảm giá phải là số',
            'order_detail.*.discount.min' => 'Giảm giá phải lớn hơn hoặc bằng 0',
            'order_detail.*.discount_type.integer' => 'Loại giảm giá phải là số nguyên',
            'order_detail.*.discount_type.in' => 'Loại giảm giá không hợp lệ',
            'order_detail.*.note.string' => 'Ghi chú phải là chuỗi ký tự',
            'order_detail.*.topping.array' => 'Topping phải là mảng',
            'order_detail.*.topping.*.product_id.required' => 'ID topping là bắt buộc',
            'order_detail.*.topping.*.product_id.integer' => 'ID topping phải là số nguyên',
            'order_detail.*.topping.*.product_id.exists' => 'Topping không tồn tại',
            'order_detail.*.topping.*.quantity.required' => 'Số lượng topping là bắt buộc',
            'order_detail.*.topping.*.quantity.numeric' => 'Số lượng topping phải là số',
            'order_detail.*.topping.*.quantity.min' => 'Số lượng topping phải lớn hơn 0',
            'order_detail.*.topping.*.user_price.required' => 'Giá topping là bắt buộc',
            'order_detail.*.topping.*.user_price.numeric' => 'Giá topping phải là số',
            'order_detail.*.topping.*.user_price.min' => 'Giá topping phải lớn hơn hoặc bằng 0',
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
