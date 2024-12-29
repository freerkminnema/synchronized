# `synchronized()` for Laravel

This package installs a global `synchronized` function into your Laravel application that ensures that the given callback is only executed one at a time.

So if your application receives ten requests in parallel, and part of that code is wrapped in the `synchronized` function, that part of the code will be executed sequentially.

> [!IMPORTANT]
> This function uses the Cache Atomic Locks feature of Laravel. As per the Laravel documentation — to utilize this feature, your application must be using the `memcached`, `redis`, `dynamodb`, `database`, `file`, or `array` cache driver as your application's default cache driver. In addition, all servers must be communicating with the same central cache server.

## Example usage

In its most elegant form, you can pass a simple closure to the `synchronized` function:

```php
$ticketNumber = synchronized(
  static fn () => Cache::increment('ticket-number')
);
```

Since `Cache::increment` is not an atomic operation, you would normally run the risk of returning identical numbers on parallel server requests. But when we wrap it in `synchronized`, we ensure the `Cache::increment` never runs in parallel.

## How does it work?

Internally, `synchronized` generates an *Atomic Lock Key* (which is simply a hashed string) based on the variables that are used in the callable. This is just like how the ✨magic✨  `once` function works.

## Providing your own *Atomic Lock Key*

In some cases, you may want to provide your own *Atomic Lock Key*. A contrived example is provided below:

```php
$ticketColor = Request::get('ticket-color');
$ticket = synchronized(function () use ($ticketColor) {
    // This is bad, because everytime $ticketColor has a
    // different value, the Atomic Lock Key will be different.
    return [
        'color' => $ticketColor,
        'number' => Cache::increment('ticket-number'),
    ];
});
```

Everytime `$ticketColor` has a different value, a different *Atomic Lock Key* will be created, and it will probably not work as you intended.

To resolve this, you may provide your own `$key` as the second variable.

```php
$ticketColor = Request::get('ticket-color');
$ticket = synchronized(function () use ($ticketColor) {
    // Now it doesn't matter what the value of
    // $ticketColor is, since the Atomic Lock Key is fixed.
    return [
        'color' => $ticketColor,
        'number' => Cache::increment('ticket-number'),
    ];
}, 'atomic-ticket-number-increment');
```

## Providing an Eloquent model as *Atomic Lock Key*

Alternatively, you may provide an instance of a saved Eloquent model to use as the *Atomic Lock Key*. This approach means the callback will be executed one at a time for every unique record in your database.

Also, in this case, the instance of the Eloquent model will be passed as the first parameter to the callback.

```php
use App\Models\TicketDispenser;

$ticket = synchronized(
    static fn (TicketDispenser $dispenser) => $dispenser->nextTicket(),
    TicketDispenser::find(Request::get('ticket-dispenser-id'))
);
```