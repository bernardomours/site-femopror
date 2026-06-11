<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Models\Registration;
use App\Models\Delegate;

Route::livewire('/', 'home-page')->name('home');
Route::livewire('/eventos/{id}', 'event-show')->name('events.show');


Route::get('/dashboard', function () {
    $user = auth()->user();

    $inscricoesAvulsas = Registration::where('user_id', $user->id)
                            ->with(['event', 'church'])
                            ->orderBy('created_at', 'desc') 
                            ->get();

    $inscricoesDelegado = Delegate::where('email', $user->email)
                            ->with('congressSubscription.church')
                            ->orderBy('created_at', 'desc')
                            ->get();

    return view('dashboard', [
        'inscricoesAvulsas' => $inscricoesAvulsas,
        'inscricoesDelegado' => $inscricoesDelegado,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';