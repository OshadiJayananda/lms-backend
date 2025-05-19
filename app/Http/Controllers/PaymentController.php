<?php

namespace App\Http\Controllers;

use App\Models\Borrow;
use App\Models\Payment;
use Illuminate\Http\Request;
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

        $fineAmount = $borrow->calculateFine();
        $fineAmountInCents = $fineAmount * 100;

        Stripe::setApiKey(config('services.stripe.secret'));

        try {
            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => 'Overdue Fine: ' . $borrow->book->name,
                        ],
                        'unit_amount' => $fineAmountInCents,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => route('payment.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('payment.cancel'),
                'metadata' => [
                    'borrow_id' => $borrow->id,
                    'user_id' => $borrow->user_id,
                ],
            ]);

            return response()->json(['id' => $session->id]);
        } catch (ApiErrorException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sigHeader,
                $endpointSecret
            );
        } catch (\UnexpectedValueException $e) {
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        switch ($event->type) {
            case 'checkout.session.completed':
                $session = $event->data->object;

                Payment::create([
                    'user_id' => $session->metadata->user_id,
                    'borrow_id' => $session->metadata->borrow_id,
                    'amount' => $session->amount_total / 100,
                    'stripe_payment_id' => $session->payment_intent,
                    'status' => 'completed',
                    'description' => 'Overdue fine payment',
                ]);

                // Mark the borrow as fine paid
                $borrow = Borrow::find($session->metadata->borrow_id);
                if ($borrow) {
                    $borrow->update(['fine_paid' => true]);
                }
                break;

            case 'payment_intent.succeeded':
                // Handle other payment success cases if needed
                break;
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
            ->get();

        return response()->json($payments);
    }
}
