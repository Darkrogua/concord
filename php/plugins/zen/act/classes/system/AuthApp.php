<?php namespace Zen\Act\Classes\System;

use Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use RainLab\User\Helpers\User as UserHelper;
use RainLab\User\Models\Setting as UserSetting;
use RainLab\User\Models\User;
use RainLab\User\Models\UserLog;
use Validator;

class AuthApp
{
    public static function make(): self
    {
        return new self();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function login(array $input): array
    {
        $login = $this->normalizeLogin((string) ($input['login'] ?? $input['username'] ?? $input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        Validator::make([
            'login' => $login,
            'password' => $password,
        ], [
            'login' => 'required|string',
            'password' => 'required|string',
        ])->validate();

        $remember = $this->parseRemember($input);

        $credentials = [
            'username' => $login,
            'password' => $password,
        ];

        if (! Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'login' => ['Неверный логин или пароль.'],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        return $this->authPayload($user);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function register(array $input): array
    {
        if (! UserSetting::get('allow_registration', true)) {
            throw ValidationException::withMessages([
                'login' => ['Регистрация отключена.'],
            ]);
        }

        $login = $this->normalizeLogin((string) ($input['login'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        if (! array_key_exists('password_confirmation', $input)) {
            $input['password_confirmation'] = $password;
        }

        Validator::make([
            'login' => $login,
            'password' => $password,
            'password_confirmation' => (string) ($input['password_confirmation'] ?? ''),
        ], [
            'login' => ['required', 'string', 'min:2', 'max:32', 'regex:/^[a-zA-Z0-9_]+$/', 'unique:users,username,NULL,id,is_guest,false'],
            'password' => UserHelper::passwordRules(),
        ])->validate();

        $user = new User();
        $user->username = $login;
        $user->first_name = $login;
        $user->name = null;
        $user->email = null;
        $user->password = $password;
        $user->password_confirmation = (string) $input['password_confirmation'];
        $user->rules = [
            'username' => ['required', 'between:2,255', 'unique:users,username,NULL,id,is_guest,false'],
            'password' => UserHelper::passwordRules(),
            'first_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable'],
        ];
        $user->save();

        $user->markEmailAsVerified();

        UserLog::createRecord($user->getKey(), UserLog::TYPE_NEW_USER, [
            'user_full_name' => (string) ($user->name ?: $user->username),
        ]);

        Auth::login($user, $this->parseRemember($input));

        return $this->authPayload($user);
    }

    public function logout(): void
    {
        Auth::logout();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function user(): ?array
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            return null;
        }

        return $this->userPayload($user);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function profileByLogin(string $login): ?array
    {
        $user = $this->findByLogin($login);
        if (! $user) {
            return null;
        }

        /** @var User|null $viewer */
        $viewer = Auth::user();
        $is_self = $viewer !== null && (int) $viewer->id === (int) $user->id;

        return [
            'profile' => $this->publicProfilePayload($user, $is_self),
            'is_self' => $is_self,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function updateProfile(array $input): array
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            throw ValidationException::withMessages([
                'auth' => ['Требуется авторизация.'],
            ]);
        }

        $rules = [
            'name' => ['nullable', 'string', 'max:255'],
        ];

        if (array_key_exists('timezone', $input)) {
            $rules['timezone'] = ['nullable', 'string', 'max:64', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value === null || $value === '') {
                    return;
                }

                if (! is_string($value) || ! $this->isValidTimezone($value)) {
                    $fail('Некорректный часовой пояс.');
                }
            }];
        }

        if (array_key_exists('email', $input)) {
            $rules['email'] = [
                'nullable',
                'string',
                'email',
                'max:255',
                'unique:users,email,'.$user->id.',id,is_guest,false',
            ];
        }

        Validator::make($input, $rules)->validate();

        if (array_key_exists('name', $input)) {
            $name = trim((string) $input['name']);
            $user->name = $name !== '' ? $name : null;
        }

        if (array_key_exists('email', $input)) {
            $email = trim((string) ($input['email'] ?? ''));
            $user->email = $email !== '' ? $email : null;
        }

        if (array_key_exists('timezone', $input)) {
            $timezone = trim((string) ($input['timezone'] ?? ''));
            $user->timezone = $timezone !== '' ? $timezone : null;
        }

        $user->rules = [
            'name' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                'unique:users,email,'.$user->id.',id,is_guest,false',
            ],
        ];
        $user->save();

        return ['user' => $this->userPayload($user->fresh())];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function changePassword(array $input): array
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            throw ValidationException::withMessages([
                'auth' => ['Требуется авторизация.'],
            ]);
        }

        Validator::make($input, [
            'current_password' => ['required', 'string'],
            'password' => UserHelper::passwordRules(),
        ])->validate();

        if (! Auth::validate([
            'username' => (string) $user->username,
            'password' => (string) ($input['current_password'] ?? ''),
        ])) {
            throw ValidationException::withMessages([
                'current_password' => ['Текущий пароль неверный.'],
            ]);
        }

        $user->password = (string) $input['password'];
        $user->password_confirmation = (string) ($input['password_confirmation'] ?? $input['password']);
        $user->rules = [
            'password' => UserHelper::passwordRules(),
        ];
        $user->save();

        return ['user' => $this->userPayload($user->fresh())];
    }

    public function findByLogin(string $login): ?User
    {
        $login = $this->normalizeLogin($login);
        if ($login === '') {
            return null;
        }

        return User::query()
            ->where('username', $login)
            ->where('is_guest', false)
            ->first();
    }

    /**
     * @return list<array{id: int, login: string, display_name: string}>
     */
    public function searchUsers(string $query, int $limit = 10): array
    {
        $query = mb_strtolower(trim($query));
        $limit = max(1, min($limit, 20));

        if ($query === '') {
            return [];
        }

        $escaped = addcslashes($query, '\\%_');
        $contains = '%'.$escaped.'%';
        $prefix = $escaped.'%';

        return User::query()
            ->where('is_guest', false)
            ->where(function ($builder) use ($contains): void {
                $builder
                    ->whereRaw('LOWER(username) LIKE ?', [$contains])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$contains]);
            })
            ->orderByRaw('CASE WHEN LOWER(username) LIKE ? THEN 0 ELSE 1 END', [$prefix])
            ->orderBy('username')
            ->limit($limit)
            ->get()
            ->map(fn (User $user): array => [
                'id' => (int) $user->id,
                'login' => (string) ($user->username ?? ''),
                'display_name' => $this->displayName($user),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function authPayload(User $user): array
    {
        $payload = [
            'user' => $this->userPayload($user),
        ];

        try {
            $token = Auth::getBearerToken($user);
            if ($token) {
                $payload['token'] = $token;
            }
        } catch (\Throwable $e) {
            // JWT опционален — session достаточно для /app
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => (int) $user->id,
            'login' => (string) ($user->username ?? ''),
            'name' => $user->name !== null && $user->name !== '' ? (string) $user->name : null,
            'email' => $user->email !== null && $user->email !== '' ? (string) $user->email : null,
            'timezone' => $user->timezone !== null && $user->timezone !== '' ? (string) $user->timezone : null,
            'display_name' => $this->displayName($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function publicProfilePayload(User $user, bool $is_self = false): array
    {
        $payload = [
            'login' => (string) ($user->username ?? ''),
            'name' => $user->name !== null && $user->name !== '' ? (string) $user->name : null,
            'display_name' => $this->displayName($user),
        ];

        if ($is_self) {
            $payload['email'] = $user->email !== null && $user->email !== '' ? (string) $user->email : null;
            $payload['timezone'] = $user->timezone !== null && $user->timezone !== '' ? (string) $user->timezone : null;
        }

        return $payload;
    }

    private function displayName(User $user): string
    {
        if ($user->name !== null && trim((string) $user->name) !== '') {
            return trim((string) $user->name);
        }

        return (string) ($user->username ?? 'User');
    }

    private function normalizeLogin(string $login): string
    {
        return trim($login);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function parseRemember(array $input): bool
    {
        if (! array_key_exists('remember', $input)) {
            return false;
        }

        return filter_var($input['remember'], FILTER_VALIDATE_BOOLEAN);
    }

    private function isValidTimezone(string $timezone): bool
    {
        try {
            new \DateTimeZone($timezone);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function configureLoginAttribute(): void
    {
        Config::set('rainlab.user::login_attribute', UserSetting::LOGIN_USERNAME);

        $settings = UserSetting::instance();
        $settings->login_attribute = UserSetting::LOGIN_USERNAME;
    }
}
