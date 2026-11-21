<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use Concerns\LogsAdminAudit;

    public function customers(Request $request)
    {
        $query = User::where('is_admin', false)->withCount(['vpsInstances', 'orders'])->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $search = trim($request->search);
            $escapedSearch = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
            $query->where(function ($q) use ($escapedSearch) {
                $q->where('full_name', 'like', "%{$escapedSearch}%")
                  ->orWhere('username', 'like', "%{$escapedSearch}%")
                  ->orWhere('email', 'like', "%{$escapedSearch}%")
                  ->orWhere('shopee_order_id', 'like', "%{$escapedSearch}%");
            });
        }

        if ($request->filled('channel')) {
            $query->where('channel', $request->channel);
        }

        $customers = $query->paginate(15)->withQueryString();

        return view('admin.customers', compact('customers'));
    }
}
