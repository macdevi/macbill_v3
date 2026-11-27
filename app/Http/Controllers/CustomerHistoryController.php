<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerChange;

class CustomerHistoryController extends Controller
{
    public function index(Customer $customer)
    {
        return response()->view('customers.history', [
            'customer' => $customer,
            'entries' => CustomerChange::where('customer_id', $customer->id)
                ->orderByDesc('id')->paginate(20),
        ])->header('Cache-Control', 'private, no-store');
    }
}
