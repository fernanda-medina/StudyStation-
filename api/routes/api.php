<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MetaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\LivroController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\ArquivoController;
use App\Http\Controllers\MapaMentalController;
use App\Http\Controllers\PlanilhaController;
use App\Http\Controllers\ApresentacaoController;

// Rotas públicas (sem token)
 Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
 Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

// Rotas protegidas (precisam da "pulseirinha")
    Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::apiResource('metas', MetaController::class);
    Route::apiResource('eventos', EventoController::class);

    // Qualquer usuário logado pode VER os livros...
    Route::apiResource('livros', LivroController::class)->only(['index', 'show']);
    // ...mas só administrador pode criar, editar e apagar (a biblioteca é compartilhada)
    Route::apiResource('livros', LivroController::class)
        ->only(['store', 'update', 'destroy'])
        ->middleware('admin');
    Route::get('/lista-leitura', [LivroController::class, 'lista']);
    Route::post('/livros/{livro}/lista', [LivroController::class, 'adicionarNaLista']);
    Route::delete('/livros/{livro}/lista', [LivroController::class, 'removerDaLista']);

    Route::apiResource('documentos', DocumentoController::class);
    Route::get('/arquivos', [ArquivoController::class, 'index']);
    Route::delete('/arquivos/{arquivo}', [ArquivoController::class, 'destroy']);
    
    Route::apiResource('mapas-mentais', MapaMentalController::class)
        ->parameters(['mapas-mentais' => 'mapa']);
    
    Route::apiResource('planilhas', PlanilhaController::class)
        ->except('index')
        ->parameters(['planilhas' => 'planilha']);  
        
    Route::apiResource('apresentacoes', ApresentacaoController::class)
        ->except('index')
        ->parameters(['apresentacoes' => 'apresentacao']);    
 });