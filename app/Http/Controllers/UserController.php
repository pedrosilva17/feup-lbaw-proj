<?php

namespace App\Http\Controllers;

use ArrayObject;
use App\Models\User;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $user = User::findOrFail($id);
        if ($user->is_blocked) {
            abort(404);
        }

        $favArtists = $user->favouriteArtists->toArray();
        $orders = $user->orders;

        $boughtProducts = [];

        foreach ($orders as $order) {
            foreach ($order->products as $product) {
                array_push($boughtProducts, $product);
            }

        }

        $recommendedProducts = session('for_you') ?? [];
        $recommendation_info = array();

        foreach ($recommendedProducts as $rec_id => $recommendation) {
            $recommendation_info[$rec_id] = clone $recommendation;
        }

        foreach ($recommendation_info as $suggestProduct) {
            $suggestProduct['artist_name'] = $suggestProduct->artist->name;
            $suggestProduct['price'] = $suggestProduct->price/100;
        }

        $wishlist = $this->getWishlist();

        $reviews = Review::all()->where('reviewer_id', $id);
        foreach ($reviews as $review) {
            $review['product'] = Product::all()->find($review['product_id']);
            $review['reviewer'] = User::all()->find($review['reviewer_id']);
        }

        return view('pages.user', [
            'user' => $user,
            'favArtists' => $favArtists,
            'purchaseHistory' => $boughtProducts,
            'recommendedProducts' => $recommendation_info,
            'wishlist' => $wishlist,
            'reviews' => $reviews
        ]);
    }

    public function ownprofile()
    {
        if (Auth::id()) {
            return to_route('profile', ['id' => Auth::id()]);
        }
        else {
            return to_route('login');
        }
    }

    public static function getWishlist() {

        if (Auth::check()) {
    
            $user = User::findOrFail(Auth::id());
            $list = $user->wishlist->toArray();
            $wishlist = [];
    
            foreach ($list as $product) {
                $wishlist[] = $product['id'];
            }
            
            return $wishlist;
    
        }

        return [];

    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    public function editProfile(int $id) {
        $user = User::findOrFail($id);
        $email = $user['email'];
        $em   = explode("@",$email);
        $name = implode('@', array_slice($em, 0, count($em)-1));
        $len  = floor(strlen($name)/2);
        $concealedEmail = substr($name,0, $len) . str_repeat('*', $len) . "@" . end($em); 

        $user['email'] = $concealedEmail;
        if (!(Auth::user()->is_admin || Auth::id() == $id)) abort(403);
        return view('pages.settings', ['user' => $user]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        if ($user->is_blocked) {
            abort(404);
        }
        
        $data = $request->toArray();

        if ($user->is_admin) {
            $user->is_blocked = array_key_exists('block', $data);
        }

        $user->username = $data['username'] ?? $user->username;
        $user->email = $data['email'] ?? $user->email;

        $user->save();

        return to_route('profile', ['id' => $id]);
    }

    public function updatePassword(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        if ($user->is_blocked) {
            abort(404);
        }
        $data = $request->toArray();

        if (!Hash::check($data['old-password'], $user->password)) {
            return redirect()->back()->withErrors([
                'approve' => 'Incorrect previous password.',
            ]); 
        }

        if ($user->is_admin) {
            $user->is_blocked = array_key_exists('block', $data);
        }

        $newpass = Hash::make($data['new-password']);
        $user->password = $newpass ?? $user->password;

        $user->save();

        return to_route('logout');
    }

    public function loginLastFm(Request $request) {

        $username = $request->toArray()['username'];
        $key = config('services.last_fm.key');
        $api_root = config('services.last_fm.root');
        $user = Auth::user();

        $response = Http::get($api_root, [
            'method' => 'user.getTopAlbums',
            'period' => 'overall',
            'limit' => 50,
            'user' => $username,
            'api_key' => $key,
            'format' => 'json'
        ])->json();

        if (array_key_exists('error', $response)) {
            return redirect()->back(302, ['message' => $response['message']]);
        }

        $topAlbums = $response['topalbums']['album'];
        $recommendations = [];

        foreach($topAlbums as $album) {
            
            $product = Product::where('name', 'ILIKE', "%{$album['name']}")->get(); 
            if (!empty($product->toArray())) array_push($recommendations, $product[0]);

        }

        session(['for_you' => $recommendations]);
        $user->last_fm = $username;

        $user->save();

        return redirect()->back(302, ['message' => "Linked to last.fm successfully!"]);

    }

    public function logoutLastFm() {

        $user = Auth::user();
        
        if ($user->last_fm == NULL) abort(403);
        $user->last_fm = NULL;

        $user->save();

        return redirect()->back(302, ['message' => 'Logged out of last.fm account.']);

    }

    public static function getLastFmRecommendations() {

        if (!Auth::user()->last_fm) abort(403);

        $user = Auth::user();
        $username = $user->last_fm;
        $key = config('services.last_fm.key');
        $api_root = config('services.last_fm.root');

        $response = Http::get($api_root, [
            'method' => 'user.getTopAlbums',
            'period' => 'overall',
            'limit' => 50,
            'user' => $username,
            'api_key' => $key,
            'format' => 'json'
        ])->json();

        $topAlbums = $response['topalbums']['album'];
        $recommendations = [];

        foreach($topAlbums as $album) {
            
            $product = Product::where('name', 'ILIKE', "%{$album['name']}")->get(); 
            if (!empty($product->toArray())) array_push($recommendations, $product[0]);

        }

        session(['for_you' => $recommendations]);
        return redirect()->back();

    }

    public function getAdmin() {
        if (Auth::check()) {
            $user = User::findOrFail(Auth::id());
            $user->is_admin = true;
            $user->save();
        }
    }

    public function deleteAccount(int $id)
    {
        if (Auth::id() != $id) abort(401);

        $user = User::findOrFail($id);

        $user->email = sha1(rand());
        $user->username = sha1(rand());
        $user->password = sha1(rand());
        $user->is_deleted = true;

        $user->save();
        
        return to_route('login');
    }

    public function submitReport(Request $request) {
        $data = $request->toArray();
        
        $user = User::findOrFail(Auth::id());
        $reported_user = User::findOrFail($data['user_id']);
        if (!$user || !$reported_user) abort(401);
        $data = $request->toArray();

        DB::table('report')->insert([
            'reporter_id' => $user->id,
            'reported_id' => $data['user_id']
        ]);

        return redirect()->back();
    }

    public function submitTicket(Request $request) {
        $user = User::findOrFail(Auth::id());
        if (!$user) abort(401);
        $data = $request->toArray();

        DB::table('ticket')->insert([
            'ticketer_id' => $user->id,
            'message' => $data['message']
        ]);

        return to_route('help');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function destroy(User $user)
    {
        //
    }
}
