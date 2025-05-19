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
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        // Log::info('Stripe Webhook Received', ['payload' => $payload]);

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

                    // Log::info('Checkout Session Completed', ['session' => $session]);
                    $paymentIntentId = $event->data->object->id;

                    $alreadyProcessed = Payment::where('stripe_payment_id', $paymentIntentId)->exists();
                    if ($alreadyProcessed) {
                        Log::info('Payment already processed: ' . $paymentIntentId);
                        return response('OK', 200);
                    }

                    Payment::create([
                        'user_id' => $session->metadata->user_id,
                        'borrow_id' => $session->metadata->borrow_id,
                        'amount' => $session->amount_total / 100,
                        'stripe_payment_id' => $session->payment_intent,
                        'status' => 'completed',
                        'description' => 'Overdue fine payment',
                    ]);

                    $borrow = Borrow::find($session->metadata->borrow_id);
                    if ($borrow) {
                        $borrow->update(['fine_paid' => true]);
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
            ->get();

        return response()->json($payments);
    }
}
