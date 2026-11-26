# language: pt
Funcionalidade: Gerenciamento de Metas de Estudo
  Como estudante cadastrado no StudyStation
  Quero criar, concluir e consultar minhas metas de estudo
  Para acompanhar meu progresso acadêmico com segurança

  Contexto:
    Dado que existe um estudante cadastrado com o e-mail "ana@teste.com" e a senha "senha12345"
    E que ele está autenticado na API com um token Sanctum válido

  # ---------- Fluxo feliz ----------
  Cenário: Criar uma meta válida
    Quando ele envia POST "/api/metas" com:
      | nome            | disciplina             | prazo      | prioridade |
      | Estudar para QA | Garantia de Qualidade  | daqui a 7d | urgente    |
    Então a resposta deve ter status 201
    E o JSON deve conter "nome" igual a "Estudar para QA"
    E a meta deve estar salva no banco vinculada ao estudante

  Cenário: Concluir uma meta atualiza o progresso
    Dado que o estudante possui 4 metas, sendo 3 concluídas
    Quando ele marca a última meta como concluída via PATCH "/api/metas/{id}" com done = true
    Então a resposta deve ter status 200
    E o progresso calculado deve ser 100%

  # ---------- Fluxos de exceção ----------
  Esquema do Cenário: Rejeitar meta com dados inválidos
    Quando ele envia POST "/api/metas" com o campo "<campo>" igual a "<valor>"
    Então a resposta deve ter status 422
    E o JSON deve conter erro de validação no campo "<campo>"
    E nenhuma meta deve ser gravada no banco

    Exemplos:
      | campo      | valor          |
      | nome       |                |
      | prazo      | 2020-01-01     |
      | prioridade | qualquer-coisa |

  Cenário: Acessar metas sem estar autenticado
    Dado que o estudante não envia o token de acesso
    Quando ele envia GET "/api/metas"
    Então a resposta deve ter status 401

  Cenário: Tentar ver a meta de outro estudante
    Dado que existe uma meta pertencente ao estudante "bruno@teste.com"
    Quando "ana@teste.com" envia GET "/api/metas/{id da meta do Bruno}"
    Então a resposta deve ter status 404
    E os dados da meta do Bruno não devem aparecer na resposta
