<x-Head page="checkout"/>
<main>
    <form method="POST" action="{{route('buy')}}">
        {{ csrf_field() }}
        <div id="payment-wrapper">
            <section id="items-wrapper">
                @if (session()->get('cart'))
                    @foreach (session()->get('cart') as $id => $details)
                        @include('partials.checkoutitem', ['id' => $id, 'details' => $details])
                    @endforeach
                @else
                    <h2> Nothing in cart. </h2>
                @endif
            </section>
            <aside id="payment-info">
                <x-Subtitle title="Payment Information" />
                <div id="payment-description">
                    @if (session('cart'))
                        @foreach (session('cart') as $id => $details)
                            @include('partials.itemprice', ['id' => $id, 'details' => $details])
                        @endforeach
                    @else
                        <h2> Nothing in cart. </h2>
                    @endif
                </div>
                <div id="billing-info">
                    <x-Subtitle title="Billing Information" />
                    <input placeholder="Address" class="text-input" type="text" id="address" name="address" onkeyup="checkDone(event)" required>
                    <label class="input-label" for="address">Address</label>
                </div>
                <div id="payment-method">
                    <x-Subtitle title="Payment Method" />
                    <label for="mbway" class="radio-label">
                    <input type="radio" class="radio" name="payment-method" id="mbway" value="mbway" checked required>MBWay</label>
                    <label for="billing" class="radio-label">
                    <input type="radio" class="radio" name="payment-method" id="billing" value="billing" required>Billing</label>
                </div>
                <div id="checkout-total">
                    <a class="subtitle1" id="checkout-value">0€</a>
                    @if (Auth::user())
                        <button type="submit" class="confirm-button" id="confirm-checkout" disabled>Confirm</button>
                    @else 
                        <button type="submit" class="confirm-button" id="confirm-checkout">Register to Buy</button>
                    @endif
                </div>
            </aside>
        </div>
    </form>
</main>
<x-Foot/>