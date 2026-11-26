<?php

namespace App\Http\Controllers;

use App\Models\Arquivo;
use App\Models\Planilha;
use App\Models\PlanilhaCelula;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlanilhaController extends Controller
{
    private const REGRAS = [
        'linhas'           => 'required|integer|min:1|max:100',
        'colunas'          => 'required|integer|min:1|max:8',
        'celulas'          => 'nullable|array',
        'celulas.*.linha'  => 'required|integer|min:0|max:99',
        'celulas.*.coluna' => 'required|integer|min:0|max:7',
        'celulas.*.valor'  => 'nullable|string|max:255',
    ];

    public function show(Request $request, Planilha $planilha)
    {
        return $this->formatar($this->doDono($request, $planilha));
    }

    public function store(Request $request)
    {
        $dados = $request->validate(array_merge(self::REGRAS, [
            'nome' => 'required|string|max:255',
        ]));
        $celulas = $dados['celulas'] ?? [];

        $planilha = DB::transaction(function () use ($request, $dados, $celulas) {
            $arquivo = Arquivo::create([
                'user_id'    => $request->user()->id,
                'nome'       => $dados['nome'],
                'tipo'       => 'planilha',
                'tamanho_kb' => $this->tamanhoKb($celulas),
            ]);

            $planilha = $arquivo->planilha()->create([
                'linhas'  => $dados['linhas'],
                'colunas' => $dados['colunas'],
            ]);
            $this->salvarCelulas($planilha, $celulas);

            return $planilha;
        });

        return response()->json($this->formatar($planilha->load(['arquivo', 'celulas'])), 201);
    }

    public function update(Request $request, Planilha $planilha)
    {
        $planilha = $this->doDono($request, $planilha);

        $dados = $request->validate(array_merge(self::REGRAS, [
            'nome' => 'sometimes|required|string|max:255',
        ]));
        $celulas = $dados['celulas'] ?? [];

        DB::transaction(function () use ($planilha, $dados, $celulas) {
            $planilha->update([
                'linhas'  => $dados['linhas'],
                'colunas' => $dados['colunas'],
            ]);
            $this->salvarCelulas($planilha, $celulas);

            $planilha->arquivo->tamanho_kb = $this->tamanhoKb($celulas);
            if (isset($dados['nome'])) {
                $planilha->arquivo->nome = $dados['nome'];
            }
            $planilha->arquivo->save();
        });

        return $this->formatar($planilha->fresh(['arquivo', 'celulas']));
    }

    public function destroy(Request $request, Planilha $planilha)
    {
        $planilha = $this->doDono($request, $planilha);
        $planilha->arquivo->delete(); // o cascade apaga a planilha e as células

        return response()->noContent();
    }


    // Apaga as células antigas e grava só as preenchidas (como trocar a página do caderno)
    private function salvarCelulas(Planilha $planilha, array $celulas): void
    {
        $planilha->celulas()->delete();

        $agora = now();
        $linhas = collect($celulas)
            ->filter(fn ($c) => ($c['valor'] ?? '') !== '')
            ->filter(fn ($c) => $c['linha'] < $planilha->linhas && $c['coluna'] < $planilha->colunas)
            ->unique(fn ($c) => $c['linha'] . '-' . $c['coluna'])
            ->map(fn ($c) => [
                'planilha_id' => $planilha->id,
                'linha'       => $c['linha'],
                'coluna'      => $c['coluna'],
                'valor'       => $c['valor'],
                'created_at'  => $agora,
                'updated_at'  => $agora,
            ])
            ->values()
            ->all();

        if ($linhas) {
            PlanilhaCelula::insert($linhas);
        }
    }

    private function doDono(Request $request, Planilha $planilha): Planilha
    {
        $planilha->load('arquivo');
        abort_unless($planilha->arquivo->user_id === $request->user()->id, 404);

        return $planilha;
    }

    private function tamanhoKb(array $celulas): int
    {
        return max(1, (int) ceil(strlen(json_encode($celulas)) / 1024));
    }

    private function formatar(Planilha $p): array
    {
        return [
            'id'         => $p->id,
            'arquivo_id' => $p->arquivo_id,
            'nome'       => $p->arquivo->nome,
            'tamanho_kb' => $p->arquivo->tamanho_kb,
            'linhas'     => $p->linhas,
            'colunas'    => $p->colunas,
            'celulas'    => $p->celulas->map(fn ($c) => [
                'linha'  => $c->linha,
                'coluna' => $c->coluna,
                'valor'  => $c->valor,
            ])->values(),
            'created_at' => $p->created_at,
            'updated_at' => $p->updated_at,
        ];
    }
}