<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Laravel\Firebase\Facades\Firebase;

/**
 * @OA\Info(
 * version="1.0.0",
 * title="Notification Service API (Public)",
 * description="FCM & APNs Token Management - AUTH REMOVED",
 * @OA\Contact(email="admin@example.com")
 * )
 */
class NotificationController extends Controller
{
    /**
     * @OA\Post(
     * path="/api/notifications/tokens",
     * summary="Register token (Publicly)",
     * tags={"Notifications"},
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"user_id", "token", "provider"},
     * @OA\Property(property="user_id", type="integer", example=1),
     * @OA\Property(property="token", type="string", example="fcm_token_123"),
     * @OA\Property(property="provider", type="string", enum={"fcm", "apns"}),
     * @OA\Property(property="device_type", type="string", example="Web/Android")
     * )
     * ),
     * @OA\Response(response=200, description="Success")
     * )
     */
    public function registerToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id'  => 'required|integer', // Now explicitly required since auth is gone
            'token'    => 'required|string',
            'provider' => 'required|in:fcm,apns',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // We use the ID provided in the request body since auth() is removed
        $deviceToken = DeviceToken::updateOrCreate(
            ['token' => $request->token],
            [
                'user_id'     => $request->user_id,
                'provider'    => $request->provider,
                'device_type' => $request->device_type,
            ]
        );

        return response()->json(['success' => true, 'data' => $deviceToken]);
    }

    /**
     * @OA\Post(
     * path="/api/notifications/send",
     * summary="Send Push (Publicly)",
     * tags={"Notifications"},
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"user_id","title","body"},
     * @OA\Property(property="user_id", type="integer"),
     * @OA\Property(property="title", type="string"),
     * @OA\Property(property="body", type="string"),
     * @OA\Property(property="data", type="object")
     * )
     * ),
     * @OA\Response(response=200, description="Sent")
     * )
     */
    public function sendPush(Request $request)
    {


        $tokens = DeviceToken::where('user_id', $request->user_id)->pluck('token')->toArray();

        if (empty($tokens)) {
            return response()->json(['message' => 'No tokens found for this user'], 404);
        }

        try {
            $messaging = \Kreait\Laravel\Firebase\Facades\Firebase::messaging();

            $message = \Kreait\Firebase\Messaging\CloudMessage::new()
                ->withNotification(\Kreait\Firebase\Messaging\Notification::create($request->title, $request->body));

            $report = $messaging->sendMulticast($message, $tokens);

            return response()->json([
                'success' => true,
                'delivered' => $report->successes()->count()
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
