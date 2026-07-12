<?php
declare(strict_types=1);

namespace Controllers;

use App\Csrf;
use App\Response;
use App\Session;
use App\Validator;
use App\View;
use Models\RateLimit;
use Models\User;

final class AuthController
{
    public function __construct(private string $root) {}

    public function home(): void
    {
        if (Session::userId()) {
            Response::redirect('/dashboard');
        }
        Response::redirect('/login');
    }

    public function showLogin(): void
    {
        if (Session::userId()) Response::redirect('/dashboard');
        View::render('auth/login', ['loginError' => Session::flash('error'), 'email' => $_COOKIE['last_email'] ?? ''], null);
    }

    public function login(): void
    {
        Csrf::requireValid();
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            Session::flash('error', 'Please enter a valid email and password.');
            Response::redirect('/login');
        }
        if (RateLimit::isBlocked($email)) {
            Session::flash('error', 'Too many attempts. Please try again in a few minutes.');
            Response::redirect('/login');
        }

        $user = User::findByEmail($email);
        if (!$user || !User::verifyPassword($user, $password)) {
            RateLimit::recordFailure($email, $ip);
            Session::flash('error', 'That email and password combination is not right.');
            setcookie('last_email', $email, time() + 60, '/', '', false, true);
            Response::redirect('/login');
        }

        RateLimit::clearFailures($email);
        RateLimit::recordSuccess($email, $ip);
        Session::regenerate();
        Session::set('user_id', (int) $user['id']);
        Response::redirect('/dashboard');
    }

    public function showRegister(): void
    {
        if (Session::userId()) Response::redirect('/dashboard');
        View::render('auth/register', [
            'registerError' => Session::flash('error'),
            'old' => $_SESSION['_old'] ?? [],
        ], null);
        unset($_SESSION['_old']);
    }

    public function register(): void
    {
        Csrf::requireValid();

        // Per-IP rate limit so a stranger can't enumerate registered emails via
        // /register as a free oracle. Login already rate-limits per (email,ip);
        // register rate-limits per IP because the email hasn't been verified yet.
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (RateLimit::isRegisterBlocked($ip)) {
            Session::flash('error', 'Too many attempts. Please try again in a few minutes.');
            Response::redirect('/register');
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $displayName = trim((string) ($_POST['display_name'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');
        $timezone = (string) ($_POST['timezone'] ?? 'UTC');

        $errors = [];
        if (!Validator::email($email)) $errors[] = 'Please use a valid email address.';
        if (Validator::nonEmpty($displayName, 80) === null) $errors[] = 'Display name is required.';
        if (!Validator::password($password)) $errors[] = 'Password must be at least 8 characters.';
        if ($password !== $confirm) $errors[] = 'Passwords do not match.';
        if (Validator::timezone($timezone) === null) $errors[] = 'Pick a valid timezone.';

        // Every failure path increments the per-IP register-attempt counter so
        // an attacker can't rotate through validation errors forever.
        if ($errors) {
            RateLimit::recordRegisterFailure($ip);
            $_SESSION['_old'] = [
                'email' => $email,
                'display_name' => $displayName,
                'timezone' => $timezone,
            ];
            Session::flash('error', $errors[0]);
            Response::redirect('/register');
        }

        // Don't leak whether an email is already registered — collapse the
        // duplicate-email case into a generic "couldn't create" reply so an
        // attacker can't enumerate accounts via repeated /register probes.
        $existing = User::findByEmail($email);
        if ($existing) {
            RateLimit::recordRegisterFailure($ip);
            $_SESSION['_old'] = [
                'display_name' => $displayName,
            ];
            Session::flash('error', 'We couldn’t create an account with that email. Please try a different one.');
            Response::redirect('/register');
        }

        $id = User::create($email, $displayName, $password, $timezone);
        RateLimit::recordRegisterSuccess($ip);   // audit row: succeeded=1
        RateLimit::clearRegisterFailures($ip);   // prune failed probes for this IP (succeeded=0)
        Session::regenerate();
        Session::set('user_id', $id);
        Response::redirect('/dashboard');
    }

    public function logout(): void
    {
        Csrf::requireValid();
        Session::flush();
        Response::redirect('/login');
    }
}
