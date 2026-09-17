<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

/*
 * Sobre os limites abaixo: o público do site se inscreve em grupo, do wi-fi da
 * igreja — várias pessoas saem do MESMO IP ao mesmo tempo. Por isso os tetos
 * são folgados o suficiente para não barrar a galera no dia do evento, e ainda
 * assim pequenos demais para um script, que faz milhares de tentativas.
 */
Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    // Sem limite nenhum aqui, dava para criar contas em massa: o cadastro não
    // exige verificação de e-mail, então cada requisição vira uma conta.
    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:20,1');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    // O LoginRequest já trava 5 tentativas por e-mail+IP. Este teto é contra
    // quem troca de e-mail a cada tentativa e escapa daquela contagem.
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:20,1');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    // Cada pedido dispara um e-mail. Sem limite, dá para queimar a cota diária
    // do SMTP (e o site vira ferramenta para incomodar terceiros).
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    // Confirmar senha é checagem de senha: sem teto, vira força bruta contra
    // uma sessão já aberta.
    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store'])
        ->middleware('throttle:6,1');

    Route::put('password', [PasswordController::class, 'update'])
        ->middleware('throttle:10,1')
        ->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
