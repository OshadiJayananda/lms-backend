<?php

namespace App\Http\Controllers;

use App\Models\Borrow;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;

class PaymentController extends Controller
{
    public function createCheckoutSession(Request $request, $borrowId)
    {
        $borrow = Borrow::with('book', 'user')->findOrFail($borrowId);

        if (!$borrow->isOverdue()) {
            return response()->json(['message' => 'This book is not overdue'], 400);
        }

        if (!$borrow->returned_date) {
            return response()->json(['message' => 'Please return the book first'], 400);
        }

        $fineAmount = $borrow->calculateFine();
        $minimumLKR = 150; // about $0.50
        if ($fineAmount < $minimumLKR) {
            return response()->json(['message' => 'Minimum payment amount must be at least 150 LKR due to Stripe limits.'], 400);
        }
        $fineAmountInCents = $fineAmount * 100;
        $currency = config('services.stripe.currency', 'lkr');

        Stripe::setApiKey(config('services.stripe.secret'));

        try {
            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => $currency,
                        'product_data' => [
                            'name' => 'Overdue Fine: ' . $borrow->book->name,
                        ],
                        'unit_amount' => $fineAmountInCents,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'payment_intent_data' => [
                    'metadata' => [
                        'borrow_id' => $borrow->id,
                        'user_id' => $borrow->user_id,
                    ],
                ],
                'success_url' => config('app.frontend_url') . '/payment-success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => config('app.frontend_url') . '/payment-cancel',
            ]);

            return response()->json(['id' => $session->id]);
        } catch (ApiErrorException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function handleWebhook(Request $request)
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        Log::info('Stripe Webhook Received', ['payload' => $payload]);

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sigHeader,
                $endpointSecret
            );
        } catch (\UnexpectedValueException $e) {
            Log::error('Stripe Webhook Error: Invalid Payload', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::error('Stripe Webhook Error: Invalid Signature', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        try {
            switch ($event->type) {
                case 'checkout.session.completed':
                    $session = $event->data->object;

                    Log::info('Checkout Session Completed', ['session' => $session]);

                    $paymentIntentId = $session->payment_intent;

                    $alreadyProcessed = Payment::where('stripe_payment_id', $paymentIntentId)->exists();
                    if ($alreadyProcessed) {
                        Log::info('Payment already processed: ' . $paymentIntentId);
                        return response('OK', 200);
                    }

                    $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);
                    $userId = $paymentIntent->metadata->user_id ?? null;
                    $borrowId = $paymentIntent->metadata->borrow_id ?? null;

                    Log::info('Payment Intent Retrieved', [
                        'paymentIntent' => $paymentIntent,
                        'userId' => $userId,
                        'borrowId' => $borrowId,
                    ]);

                    Payment::create([
                        'user_id' => $userId,
                        'borrow_id' => $borrowId,
                        'amount' => $session->amount_total / 100,
                        'stripe_payment_id' => $paymentIntentId,
                        'status' => 'completed',
                        'description' => 'Overdue fine payment',
                    ]);

                    if ($borrowId) {
                        $borrow = Borrow::find($borrowId);
                        if ($borrow) {
                            $borrow->update(['fine_paid' => true]);
                        }
                    }

                    break;

                case 'payment_intent.succeeded':
                    Log::info('Payment Intent Succeeded', ['event' => $event]);
                    break;
            }
        } catch (\Exception $e) {
            Log::error('Stripe Webhook Processing Failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }

        return response()->json(['success' => true]);
    }

    public function paymentSuccess(Request $request)
    {
        $sessionId = $request->get('session_id');
        return response()->json(['message' => 'Payment successful', 'session_id' => $sessionId]);
    }

    public function paymentCancel()
    {
        return response()->json(['message' => 'Payment canceled'], 400);
    }

    public function getPaymentHistory()
    {
        $user = auth()->user();
        $payments = Payment::with('borrow', 'borrow.book')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($payments);
    }

    public function getPaymentList(Request $request)
    {
        $perPage = $request->query('per_page', 10); // Default to 10 items per page
        $searchQuery = $request->query('q');

        $payments = Payment::with('borrow', 'borrow.book', 'borrow.user')
            ->when($searchQuery, function ($query) use ($searchQuery) {
                $query->whereHas('borrow.book', function ($q) use ($searchQuery) {
                    $q->where('name', 'like', "%{$searchQuery}%")
                        ->orWhere('isbn', 'like', "%{$searchQuery}%")
                        ->orWhere('id', 'like', "%{$searchQuery}%");
                })
                    ->orWhereHas('borrow.user', function ($q) use ($searchQuery) {
                        $q->where('name', 'like', "%{$searchQuery}%")
                            ->orWhere('email', 'like', "%{$searchQuery}%")
                            ->orWhere('id', 'like', "%{$searchQuery}%");
                    })
                    ->orWhere('stripe_payment_id', 'like', "%{$searchQuery}%")
                    ->orWhere('amount', 'like', "%{$searchQuery}%")
                    ->orWhere('description', 'like', "%{$searchQuery}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json($payments);
    }
}
