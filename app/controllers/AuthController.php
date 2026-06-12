<?php

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            redirect(Auth::homePathFor(Auth::user()));
        }

        $this->view('auth.login', [
            'title' => 'Connexion',
            'email' => $_SESSION['old']['email'] ?? '',
            'error' => flash('error'),
        ]);

        unset($_SESSION['old']);
    }

    public function login()
    {
        Auth::start();

        if (!verify_csrf($_POST['_token'] ?? '')) {
            flash('error', 'Session expiree. Veuillez reessayer.');
            redirect('login');
        }

        $email = trim($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $_SESSION['old']['email'] = $email;

        if ($email === '' || $password === '') {
            flash('error', 'Veuillez renseigner votre email et votre mot de passe.');
            redirect('login');
        }

        $userModel = $this->model('User');
        $user = $userModel->findByEmail($email);

        if (!$user || !password_verify($password, $user['password'])) {
            flash('error', 'Identifiants invalides.');
            redirect('login');
        }

        unset($_SESSION['old']);
        Auth::login($user);
        $this->model('ActivityLog')->record(
            'login',
            'auth',
            'users',
            $user['id'],
            'Connexion utilisateur reussie.',
            null,
            ['email' => $user['email'], 'role' => $user['role_slug']],
            $user
        );

        $intendedUrl = Auth::intendedUrl();

        if ($intendedUrl) {
            header('Location: ' . $intendedUrl);
            exit;
        }

        redirect(Auth::homePathFor(Auth::user()));
    }

    public function logout()
    {
        if (!verify_csrf($_POST['_token'] ?? '')) {
            flash('error', 'Session expiree. Veuillez reessayer.');
            redirect(Auth::homePathFor(Auth::user()));
        }

        Auth::logout();
        redirect('login');
    }
}
