<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use App\Models\Artist;
use App\Models\Ticket;
use App\Models\Genre;
use App\Models\Report;
use App\Mail\TicketResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * Display the specified resource.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        $products = Product::all()->sortBy('stock', false)->take(5);
        $orders = Order::all()->sortBy('id', true)->take(5);
        $reports = Report::all()->sortBy('id', true)->take(5);
        $tickets = Ticket::all()->sortBy('id', true)->take(5);
        return view('pages.admin.index', ['products' => $products, 'orders' => $orders, 'reports' => $reports, 'tickets' => $tickets]);
    }


    public function showUser(Request $request)
    {

        $search = (array_key_exists('user', $request->toArray())) ? $request->toArray()['user'] : '';
        
        $users = User::search($search)->where('is_admin', 0)->where('is_deleted', 0);
        $users = $users->paginate(20)->withQueryString();
        return view('pages.admin.user', ['users' => $users]);
    }


    public function showProduct(Request $request) {
        
        $search = (array_key_exists('product', $request->toArray())) ? $request->toArray()['product'] : '';
        if ($search) {
            $products = Product::adminSearch($search);
            $products = $products->paginate(20)->withQueryString();
        } else {
            $products = Product::paginate(20)->withQueryString();
        }
        return view('pages.admin.product', ['products' => $products]);
    }

    public function showArtist(Request $request) {
        
        $search = (array_key_exists('artist', $request->toArray())) ? $request->toArray()['artist'] : '';

        $artists = Artist::search($search);
        $artists = $artists->paginate(20)->withQueryString();
        return view('pages.admin.artist', ['artists' => $artists]);
    }

    public function showOrder(Request $request) {
        
        $search = (array_key_exists('order', $request->toArray())) ? $request->toArray()['order'] : '';
        if ($search) {
            $orders = Order::where('id', 'LIKE', $search)
                            ->orWhere('user_id', 'LIKE', $search);
            $orders = $orders->paginate(20)->withQueryString();
        } else {
            $orders = Order::paginate(20)->withQueryString();
        }
        
        foreach ($orders as $order) {
            $order['products'] = $order->products;
            foreach($order['products'] as $product) {
                $product['artist_name'] = $product->artist->name;
            }
        }

        return view('pages.admin.orders', ['orders' => $orders]);
    }

    public function showReports(Request $request) {
        
        $search = (array_key_exists('report', $request->toArray())) ? $request->toArray()['report'] : '';
        if ($search) {
            $reports = Report::where('id', 'LIKE', '%' . $search . '%');
            $reports = $reports->paginate(20)->withQueryString();
        } else {
            $reports = Report::paginate(20)->withQueryString();
        }
        
        return view('pages.admin.report', ['reports' => $reports]);
    }

    public function showTickets(Request $request) {
        
        $search = (array_key_exists('ticket', $request->toArray())) ? $request->toArray()['ticket'] : '';
        if ($search) {
            $tickets = Ticket::where('id', 'LIKE', '%' . $search . '%');
            $tickets = $tickets->paginate(20)->withQueryString();
        } else {
            $tickets = Ticket::paginate(20)->withQueryString();
        }

        
        return view('pages.admin.ticket', ['tickets' => $tickets]);
    }

    public function showUserCreate() 
    {

        return view('auth.admin-create');
    }

    public function showProductCreate() 
    {

        $artists = Artist::all();
        $genres = Genre::all();

        return view('auth.admin-product-create', ['artists' => $artists, 'genres' => $genres]);
    }

   /**
     * Create a new user after a valid registration.
     *
     * @param  array  $data
     * @return \App\Models\User
     */
    public function createUser(Request $request) {
        $data = $request->toArray();

        $user = User::where('email', $data['email'])->first();
        
        if (!is_null($user))
            return back()->withErrors('User already exists.');

        User::create([
            'email' => $data['email'],
            'username' => $data['username'],
            'password' => bcrypt($data['pwd']),
            'is_admin' => array_key_exists('admin', $data)
        ]);

        return to_route('adminUser');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function editUser(User $user)
    {
        return view('pages.settings', ['id' => $user->id]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function updateUser(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        $data = $request->toArray();

        $user->username = $data['username'] ?? $user->username;
        $user->email = $data['email'] ?? $user->email;
        $user->is_blocked = array_key_exists('block', $data); 

        $user->save();

        return to_route('adminUser');
    }

    public function deleteUser(Request $request) {

        $data = $request->toArray();

        $user = User::findOrFail(intval($data['id']));

        $user->email = sha1(rand());
        $user->username = sha1(rand());
        $user->password = sha1(rand());;
        $user->is_deleted = true;

        $user->save();
        
        return to_route('adminUser');
    }

    public function createProduct(Request $request) {

        $data = $request->toArray();

        $artist_name = trim($data['artist']);
        $artist = DB::table('artist')->where('name', 'ILIKE', '%' . $artist_name . '%')->first();

        if ($artist) 
            $artist_id = $artist->id;
        else {
            $new_artist = Artist::create(['name' => $artist_name]);
            $artist_id = $new_artist->id;
        }

        $price = $data['price']*100;
        Product::create([
            'artist_id' => $artist_id,
            'name' => $data['name'],
            'description' => $data['description'],
            'stock' => $data['stock'],
            'price' => $price,
            'format' => $data['format'],
            'year' => $data['year'],
            'description' => $data['description']
        ]);

        return to_route('adminProduct');
        
    }

    public function updateProduct(Request $request, $id) {

        $product = Product::findOrFail($id);
        if (!$product) {
            abort(404);
        }

        $data = $request->toArray();

        $product->stock = $data['stock'] ?? $product->stock;
        if ($data['price']) $product->price = $data['price']*100;
        $product->discount = $data['discount'] ?? $product->discount;

        $product->save();

        if ($data['discount'] != 0) 
            NotificationController::notifySale($product->id, $data['discount']);

        return to_route('adminProduct');
    } 

    public function deleteProduct(Request $request) {

        $data = $request->toArray();
        Product::findOrFail($data['product'])->delete();

        return to_route('adminProduct');
    }

    public function updateArtist(Request $request, int $id) {

        $artist = Artist::findOrFail($id);
        if (!$artist) {
            abort(404);
        }

        $data = $request->toArray();

        $artist->description = $data['message'] ?? $artist->description;

        $artist->save();

        return to_route('adminArtist');
    } 

    public function answerTicket(Request $request, int $id) {
        if (!Auth::user()->is_admin) abort(401);
        $data = $request->toArray();

        $user = User::findOrFail($id);
        if (!$user) abort(404);

        Mail::to($user->email)->send(new TicketResponse($user->username,
                                                    $data['ticket'],
                                                    $data['title'],
                                                    $data['message']));

        return to_route('adminTicket');
    }

    public function deleteTicket(int $id) {
        if (!Auth::user()->is_admin) abort(401);

        DB::table('ticket')->where('id', $id)->delete();

        return to_route('adminTicket');
    }

    public function blockReported(Request $request) {
        $data = $request->toArray();

        $user = User::findOrFail($data['reported_id']);
        if (!$user) abort(404);

        $user->is_blocked = true;

        $user->save();

        return to_route('adminReport');
    }

    public function deleteReport(int $id) {
        if (!Auth::user()->is_admin) abort(401);

        DB::table('report')->where('id', $id)->delete();

        return to_route('adminReport');
    }

    public function findUser() {
        // $users = User::search(request('search'))->paginate(20);

        // return view('pages.admin', ['users' => $users]);
    }

}
