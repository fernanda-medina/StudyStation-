# Como aplicar no repositório StudyStation-

Copie TUDO desta pasta para a raiz do repositório (ao lado de login.php, cadastro.php etc.):

- api/        -> API Laravel completa (versão estudaflow-api + controle de admin da pasta "API StudyStation")
                 já com os testes novos e o BuscaLivro corrigido
- frontend/   -> front-end (cópia da pasta "projeto StudyStation")
- features/   -> especificação BDD (Gherkin)
- e2e/        -> teste E2E Playwright
- k6/         -> teste de carga
- .github/    -> pipeline de CI (Quality Gate)

Os comandos do PHP (composer, php artisan) rodam DENTRO da pasta api/.
Os comandos do Playwright e do k6 rodam na RAIZ do repositório.
