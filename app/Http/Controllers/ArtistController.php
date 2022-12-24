<?php

namespace App\Http\Controllers;

use App\Models\Artist;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\User;

use Illuminate\Support\Facades\Auth;


class ArtistController extends Controller
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
    public function show(int $id)
    {

        $artist = Artist::find($id);
        $products = Product::all()->where('artist_id', $id);

        foreach ($products as $product) {
            $product['artist_name'] = $product->artist->name;
            $product['price'] = $product->price/100;
        }
        
        $wishlist = getWishlist();

        return view('pages.artist', [
            'artist' => $artist,
            'products' => $products,
            'wishlist' => $wishlist
        ]);
    }
}

function getWishlist() {
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