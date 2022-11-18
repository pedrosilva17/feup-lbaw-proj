<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
  // Don't add create and update timestamps in database.
  public $timestamps  = false;

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $fillable = [
    'genre', 'price', 'stock', 'format', 'year', 'description'
  ];

  /**
   * The artist that authored this product.
   */
  public function artist() {
    return $this->belongsTo(Artist::class, 'artist_id');
  }

  /**
   * The genres that classify this product.
   */
  public function genres() {
    return $this->belongsToMany(Genre::class, 'GenreProduct', 'product_id', 'genre_id');
  }
  /**
   * The orders this product is associated with.
   */
  public function orders() {
    return $this->belongsToMany(Order::class, 'OrderProduct', 'product_id', 'order_id');
  }

  /**
   * The products (and their quantities) that are in a client's wishlist.
   */
  public function inWishlist() {
    return $this->belongsToMany(User::class, 'Wishlist', 'product_id', 'user_id')->withPivot('quantity');
  }

  /**
   * The reviews associated with this product.
   */
  public function reviews() {
    return $this->hasMany(Review::class, 'product_id');
  }

  /**
   * The table associated with the model.
   *
   * @var string
   */
  protected $table = 'Product';
}
