<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index()
    {
        return view("home");
    }

    public function checkout(Request $request)
    {
        $request->request->add(["total_price" => $request->qty * 100000, "status" => "Unpaid"]);

        $order = Order::create($request->all());

        // Set your Merchant Server Key
        \Midtrans\Config::$serverKey = config("midtrans.server_key");
        // Set to Development/Sandbox Environment (default). Set to true for Production Environment (accept real transaction).
        \Midtrans\Config::$isProduction = config("midtrans.is_production");
        // Set sanitization on (default)
        \Midtrans\Config::$isSanitized = true;
        // Set 3DS transaction for credit card to true
        \Midtrans\Config::$is3ds = true;

        $params = array(
            'transaction_details' => array(
                'order_id' => $order->id,
                'gross_amount' => $order->total_price,
            ),
            'customer_details' => array(
                'first_name' => $order->name,
                'last_name' => '',
                'phone' => $order->phone,
            ),
        );

        $snapToken = \Midtrans\Snap::getSnapToken($params);

        return view("checkout", compact("snapToken", "order"));
    }

    public function callback(Request $request)
    {
        $transaction_status = $request->transaction_status;
        $fraud = $request->fraud_status;
        $hashed = hash('sha512', $request->order_id . $request->status_code . $request->gross_amount . config("midtrans.server_key"));

        if ($request->order_id && $hashed === $request->signature_key) {
            if ($fraud === "accept" && $transaction_status === "capture" || $transaction_status === "settlement") {
                $order = Order::find($request->order_id);
                $order->update(["status" => "Paid"]);
            } else {
                $order = Order::find($request->order_id);
                $order->update(["status" => "Unpaid"]);
            }
        }
    }

    public function invoice($id)
    {
        $order = Order::find($id);

        return view("invoice", compact("order"));
    }
}
