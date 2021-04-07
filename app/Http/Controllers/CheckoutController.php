<?php

namespace App\Http\Controllers;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\BouquetOrder;
use App\Models\Bouquet;
use Auth;

class CheckoutController extends Controller
{
    public function index()
    {
        if (Cart::instance('default')->count() == 0) {
            return redirect()->route('shop.index');
        }

        if (auth()->user() && request()->is('guestCheckout')) {
            return redirect()->route('checkouts.index');
        }
       
        return view('checkouts.index')->with([
            'total' => Cart::instance('default')->total(),
        ]);
    }

    public function store(Request $request)
    {
        //Check current item stock when there are less items available to purchase
        if ($this->productsAreNoLongerAvailable()) {
            return back()->withErrors('Sorry! One of the items in your cart is no longer avialble.');
        }

        $order = $this->addToOrdersTables($request, null); 
        
        // decrease the quantities of all the bouquets in the cart
        $this->decreaseQuantities();

        Cart::instance('default')->destroy();     
        
        //Delete the cart item in the database
        $userId = Auth::user()->id;
        Cart::instance('default')->store($userId);
    
        if (Cart::instance('default')->count() == 0) {
            return redirect()->route('confirmations.index')->with('success_message', 'Thank you! Your payment has been successfully accepted!');
        }
       
    }

    protected function addToOrdersTables($request, $error)
    {
        // Insert into orders table
        $order = Order::create([
            'user_id' => Auth::user() ? Auth::user()->id : null,
            'billing_email' => $request->email,
            'billing_name' => $request->name,
            'billing_address' => $request->address,
            'billing_city' => $request->city,
            'billing_province' => $request->province,
            'billing_postalcode' => $request->postalcode,
            'billing_phone' => $request->phone,
            'billing_total' => Cart::total(),
        ]);

        // Insert into order_bouquet table
        foreach (Cart::content() as $item) {
            BouquetOrder::create([
                'order_id' => $order->id,
                'bouquet_id' => $item->model->id,
                'quantity' => $item->qty,
            ]);
        }

        return $order;
    }

    protected function decreaseQuantities()
    {
        foreach (Cart::content() as $item) {
            $bouquet = Bouquet::find($item->model->id);

            $bouquet->update(['quantity' => $bouquet->quantity - $item->qty]);
        }
    }

    protected function productsAreNoLongerAvailable()
    {
        foreach (Cart::content() as $item) {
            $bouquet = Bouquet::find($item->model->id);
            if ($bouquet->quantity < $item->qty) {
                return true;
            }
        }

        return false;
    }
}
