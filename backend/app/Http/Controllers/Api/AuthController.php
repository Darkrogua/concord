<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserOnboardingService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request, UserOnboardingService $onboarding): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $onboarding->setup($user);
        $request->session()->regenerate();
        auth()->login($user);

        Log::info('auth.registered', [
            'event' => 'auth.registered',
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return response()->json(['user' => $this->payload($user->fresh('signatures'))]);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! auth()->attempt($credentials, true)) {
            Log::warning('auth.login_failed', [
                'event' => 'auth.login_failed',
                'email' => $credentials['email'],
            ]);
            throw ValidationException::withMessages([
                'email' => 'Неверный email или пароль.',
            ]);
        }

        $request->session()->regenerate();

        Log::info('auth.login', [
            'event' => 'auth.login',
            'user_id' => auth()->id(),
            'email' => $credentials['email'],
        ]);

        return response()->json(['user' => $this->payload(auth()->user()->load('signatures'))]);
    }

    public function logout(Request $request): JsonResponse
    {
        Log::info('auth.logout', [
            'event' => 'auth.logout',
            'user_id' => $request->user()?->id,
        ]);
        auth()->guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->payload($request->user()->load('signatures'))]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));
        Log::info('auth.password_reset_requested', [
            'event' => 'auth.password_reset_requested',
            'email' => $request->string('email')->toString(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            Log::warning('auth.password_reset_failed', [
                'event' => 'auth.password_reset_failed',
                'email' => $request->string('email')->toString(),
            ]);
            throw ValidationException::withMessages(['email' => 'Не удалось сбросить пароль.']);
        }

        Log::info('auth.password_reset', [
            'event' => 'auth.password_reset',
            'email' => $request->string('email')->toString(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $user = $request->user();
        Log::warning('auth.account_deleted', [
            'event' => 'auth.account_deleted',
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
        auth()->guard('web')->logout();
        $request->session()->invalidate();
        $user->delete();

        return response()->json(['ok' => true]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'theme' => ['sometimes', 'in:light,dark'],
        ]);

        $request->user()->update($data);

        return response()->json(['user' => $this->payload($request->user()->fresh('signatures'))]);
    }

    private function payload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'theme' => $user->theme,
            'is_admin' => $user->is_admin,
            'signatures' => $user->signatures,
            'active_signature' => $user->activeSignature(),
        ];
    }
}
