<?php

namespace App\Http\Controllers;

use App\Actions\Generations\ProcessPixelforgeWebhook;
use App\Integrations\Pixelforge\PixelforgeWebhookData;
use App\Integrations\Pixelforge\PixelforgeWebhookSignatureVerifier;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;
use UnexpectedValueException;

class PixelforgeWebhookController extends Controller
{
    public function __invoke(Request $request, PixelforgeWebhookSignatureVerifier $verifier, ProcessPixelforgeWebhook $process): Response
    {
        $raw = $request->getContent();
        $signatures = $request->headers->all('Pixelforge-Signature');

        if (count($signatures) !== 1 || ! $verifier->verify($raw, $signatures[0])) {
            return response('Invalid webhook signature.', 401);
        }

        try {
            $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
            $ids = $request->headers->all('Pixelforge-Event-Id');
            $types = $request->headers->all('Pixelforge-Event-Type');

            if (! is_array($data) || count($ids) > 1 || count($types) > 1) {
                return response('Invalid webhook event.', 400);
            }

            $event = PixelforgeWebhookData::fromArray($data, $ids[0] ?? null, $types[0] ?? null);
        } catch (Throwable) {
            return response('Invalid webhook event.', 400);
        }

        try {
            $process->handle($event);
        } catch (ModelNotFoundException) {
            return response('Webhook processing is temporarily unavailable.', 503);
        } catch (UnexpectedValueException|InvalidArgumentException) {
            return response('Inconsistent webhook event.', 400);
        } catch (Throwable) {
            Log::warning('Pixelforge webhook transaction did not complete.', ['event_id' => $event->id, 'code' => 'processing_failed']);

            return response('Webhook processing is temporarily unavailable.', 503);
        }

        return response()->noContent();
    }
}
