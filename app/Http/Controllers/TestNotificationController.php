<?php

namespace App\Http\Controllers;

use App\Http\Models\User;
use Illuminate\Http\Request;
use App\Http\Models\Notification;
use App\Events\PublicNotificationEvent;
use App\Events\RealTimeNotificationEvent;
use App\Http\Resources\NotificationResource;
use Illuminate\Pagination\LengthAwarePaginator;

class TestNotificationController extends Controller
{
    public function index()
    {
        return view('notification');
    }

    public function form()
    {
        return view('send-notification');
    }

    public function send(Request $request)
    {
        $message = $request->input('message');
        broadcast(new PublicNotificationEvent($message))->toOthers();
        return response()->json(['status' => 'Message sent successfully!'], 200);
    }

    public function formPrivate()
    {
        $users = User::all();
        return view('send-notification-private', ['users' => $users]);
    }

    public function sendPrivate(Request $request)
    {
        $locale = 'id';

        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        $user = User::find($request->user_id);

        $input = [
            'user_id' => $user->id,
            'title' => 'Transaksi Berhasil',
            'text' => 'Anda telah berhasil melakukan transaksi!',
            'type' => 0,
            'status_read' => 0,
        ];

        $allNotifications = store_notification($input);

        $page = 1;
        $perPage = 10;

        if(is_multidimensional_array($allNotifications->toArray())) {
            $results = format_json($allNotifications, $page, $perPage, ['path' => config('app.url') . '/api/v1/' . $locale . '/carts']);
        } else {
            $results = $allNotifications;
        }

        $results['total_unread'] = Notification::where('user_id', $user->id)->where('status_read', 0)->count();

        broadcast(new RealTimeNotificationEvent($results, $user->id));

        return response()->json(['status' => 'Notification sent successfully!'], 200);
    }
}
