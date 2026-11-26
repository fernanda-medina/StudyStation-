// @ts-check
const { test, expect } = require('@playwright/test');

test('estudante se cadastra, cria uma meta e a conclui', async ({ page }) => {
  const email = `e2e.${Date.now()}@teste.com`;   // dado único: teste não depende de execução anterior
  const nomeMeta = `Meta E2E ${Date.now()}`;

  // 1. Cadastro
  await page.goto('/estudaflow.html');
  await page.getByText('Cadastrar', { exact: true }).click();
  await page.locator('#reg-name').fill('Estudante E2E');
  await page.locator('#reg-email').fill(email);
  await page.locator('#reg-curso').fill('ADS');
  await page.locator('#reg-pass').fill('senha12345');
  await page.locator('#reg-pass2').fill('senha12345');

  const cadastro = page.waitForResponse(r => r.url().endsWith('/api/register') && r.status() === 201);
  await page.getByRole('button', { name: 'Criar conta gratuita' }).click();
  await cadastro;                                  // espera a API, não um tempo fixo
  await expect(page.locator('#app')).toBeVisible();

  // 2. Criar meta
  await page.locator('.nav-item', { hasText: 'Metas' }).click();
  await page.getByRole('button', { name: '+ Nova Meta' }).click();
  await expect(page.locator('#modal-meta')).toHaveClass(/open/);
  await page.locator('#meta-nome').fill(nomeMeta);
  await page.locator('#meta-disciplina').fill('Garantia de Qualidade');
  await page.locator('#meta-prioridade').selectOption('urgente');

  const criacao = page.waitForResponse(r =>
    r.url().endsWith('/api/metas') && r.request().method() === 'POST');
  await page.getByRole('button', { name: 'Criar Meta' }).click();
  expect((await criacao).status()).toBe(201);

  const item = page.locator('#metas-list .meta-item', { hasText: nomeMeta });
  await expect(item).toBeVisible();
  await expect(item.locator('.meta-badge')).toHaveText('Urgente');

  // 3. Concluir meta
  const conclusao = page.waitForResponse(r =>
    /\/api\/metas\/\d+$/.test(r.url()) && r.request().method() === 'PATCH');
  await item.locator('.meta-check').click();
  expect((await conclusao).status()).toBe(200);

  await expect(item.locator('.meta-name')).toHaveClass(/done/);   // auto-wait: re-tenta até o DOM atualizar
  await expect(page.locator('#dash-metas')).toHaveText('1/1');
});
