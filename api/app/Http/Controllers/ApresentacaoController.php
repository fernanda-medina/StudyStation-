<?php

namespace App\Http\Controllers;

use App\Models\Apresentacao;
use App\Models\Arquivo;
use App\Models\Slide;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApresentacaoController extends Controller
{
    private const REGRAS = [
        'slides'          => 'nullable|array|max:100',
        'slides.*.titulo' => 'required|string|max:255',
        'slides.*.corpo'  => 'nullable|string|max:5000',
    ];

    public function show(Request $request, Apresentacao $apresentacao)
    {
        return $this->formatar($this->doDono($request, $apresentacao));
    }

    public function store(Request $request)
    {
        $dados = $request->validate(array_merge(self::REGRAS, [
            'nome' => 'required|string|max:255',
        ]));
        $slides = $dados['slides'] ?? [];

        $apresentacao = DB::transaction(function () use ($request, $dados, $slides) {
            $arquivo = Arquivo::create([
                'user_id'    => $request->user()->id,
                'nome'       => $dados['nome'],
                'tipo'       => 'apresentacao',
                'tamanho_kb' => $this->tamanhoKb($slides),
            ]);

            $apresentacao = $arquivo->apresentacao()->create();
            $this->salvarSlides($apresentacao, $slides);

            return $apresentacao;
        });

        return response()->json($this->formatar($apresentacao->load(['arquivo', 'slides'])), 201);
    }

    public function update(Request $request, Apresentacao $apresentacao)
    {
        $apresentacao = $this->doDono($request, $apresentacao);

        $dados = $request->validate(array_merge(self::REGRAS, [
            'nome' => 'sometimes|required|string|max:255',
        ]));
        $slides = $dados['slides'] ?? [];

        DB::transaction(function () use ($apresentacao, $dados, $slides) {
            $this->salvarSlides($apresentacao, $slides);
            $apresentacao->touch();

            $apresentacao->arquivo->tamanho_kb = $this->tamanhoKb($slides);
            if (isset($dados['nome'])) {
                $apresentacao->arquivo->nome = $dados['nome'];
            }
            $apresentacao->arquivo->save();
        });

        return $this->formatar($apresentacao->fresh(['arquivo', 'slides']));
    }

    public function destroy(Request $request, Apresentacao $apresentacao)
    {
        $apresentacao = $this->doDono($request, $apresentacao);
        $apresentacao->arquivo->delete(); // o cascade apaga a apresentação e os slides

        return response()->noContent();
    }

    // ---------- Auxiliares ----------

    // Apaga os slides antigos e grava de novo na ordem em que chegaram (1, 2, 3...)
    private function salvarSlides(Apresentacao $apresentacao, array $slides): void
    {
        $apresentacao->slides()->delete();

        $agora = now();
        $linhas = [];
        foreach (array_values($slides) as $i => $s) {
            $linhas[] = [
                'apresentacao_id' => $apresentacao->id,
                'ordem'           => $i + 1,
                'titulo'          => $s['titulo'],
                'corpo'           => $s['corpo'] ?? null,
                'created_at'      => $agora,
                'updated_at'      => $agora,
            ];
        }

        if ($linhas) {
            Slide::insert($linhas);
        }
    }

    private function doDono(Request $request, Apresentacao $apresentacao): Apresentacao
    {
        $apresentacao->load('arquivo');
        abort_unless($apresentacao->arquivo->user_id === $request->user()->id, 404);

        return $apresentacao;
    }

    private function tamanhoKb(array $slides): int
    {
        return max(1, (int) ceil(strlen(json_encode($slides)) / 1024));
    }

    private function formatar(Apresentacao $a): array
    {
        return [
            'id'         => $a->id,
            'arquivo_id' => $a->arquivo_id,
            'nome'       => $a->arquivo->nome,
            'tamanho_kb' => $a->arquivo->tamanho_kb,
            'slides'     => $a->slides->sortBy('ordem')->map(fn ($s) => [
                'ordem'  => $s->ordem,
                'titulo' => $s->titulo,
                'corpo'  => $s->corpo,
            ])->values(),
            'created_at' => $a->created_at,
            'updated_at' => $a->updated_at,
        ];
    }
}