<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use App\Notifications\RegistrationForExistingAddress;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Self-registration, neutral: a new address and an address that is already taken get the
 * same answer (201, empty body), so the form does not tell which addresses have an account.
 *
 * - new address: the account is created and the person is logged in (as before); the
 *   confirmation mail goes out through the queue;
 * - taken address: nothing is created or changed and nobody is logged in; the owner gets a
 *   mail instead ("you already have an account", or "this registration is not confirmed yet"),
 *   at most one an hour per address so nobody can flood a mailbox with registrations;
 * - invalid input (name, format of the address, password): 422 with the fields, never "taken".
 *
 * It replaces the route of Fortify (feature switched off in config/fortify.php), which
 * logs in whoever it creates and refuses a taken address with 422.
 */
class RegisterController extends Controller
{
    public function __invoke(Request $request, CreateNewUser $creator, StatefulGuard $guard): JsonResponse
    {
        if ($request->has('email')) {
            $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        }

        $raw = $request->all();
        $input = $creator->validate($raw);
        $existing = $this->existingAccount($input['email']);

        if ($existing === null) {
            try {
                $user = $creator->create($raw);
            } catch (UniqueConstraintViolationException $exception) {
                // Two registrations at once: the database index is the last word, and it is the same case as "taken".
                $existing = $this->existingAccount($input['email']) ?? throw $exception;
            }
        }

        if ($existing !== null) {
            $this->tellTheOwner($existing, $input['password']);

            return new JsonResponse('', 201);
        }

        event(new Registered($user));
        $guard->login($user, $request->boolean('remember'));

        if ($request->hasSession()) {
            $request->session()->regenerate();
            // The person has just typed the password: setting up the first factor right after does not ask again
            // (the same as after /login, see ConfirmPasswordOnLogin; a passkey login or a taken address do not count).
            $request->session()->put('auth.password_confirmed_at', time());
        }

        return new JsonResponse('', 201);
    }

    /** The account with this address, compared like the unique index of the database does (without regard to case). */
    private function existingAccount(string $email): ?User
    {
        return User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first();
    }

    private function tellTheOwner(User $existing, string $password): void
    {
        // Hashing costs the same in both cases (a new account hashes once too), so the time does not tell them apart.
        Hash::make($password);

        // One mail an hour per address.
        if (! Cache::add('registration-notice:'.sha1(mb_strtolower($existing->email)), true, now()->addHour())) {
            return;
        }

        $existing->notify((new RegistrationForExistingAddress($existing->hasVerifiedEmail()))->locale(app()->getLocale()));
    }
}
