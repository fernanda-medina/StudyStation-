const API_URL = 'http://127.0.0.1:8000/api';

function getToken() {
  return localStorage.getItem ('token');
}

async function api(caminho, opcoes = {}) {
  const token = getToken();

  const resposta = await fetch(API_URL + caminho, {
    ...opcoes,
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...(token ? { Authorization: 'Bearer ' + token } : {}),
      ...opcoes.headers,
    },
  });

  const dados = await resposta.json().catch(() => null);

  if (resposta.status === 401) {
    localStorage.removeItem('token');
  }

  if (!resposta.ok) {
    throw { status: resposta.status, dados };
  }

  return dados;
}

async function fazerLogin(email, password) {
  const dados = await api('/login', {
    method: 'POST',
    body: JSON.stringify({ email, password }),
  });
  localStorage.setItem('token', dados.token);
  return dados.user;
}

async function fazerLogout() {
  try { await api('/logout', { method: 'POST' }); } catch (e) {}
  localStorage.removeItem('token');
}

const MetasAPI = {
  listar: () => api('/metas'),
  criar: (meta) => api('/metas', { method: 'POST', body: JSON.stringify(meta) }),
  atualizar: (id, campos) => api('/metas/' + id, { method: 'PATCH', body: JSON.stringify(campos) }),
  apagar: (id) => api('/metas/' + id, { method: 'DELETE' }),
};

const EventosAPI = {
  listar: () => api('/eventos'),
  criar: (evento) => api('/eventos', { method: 'POST', body: JSON.stringify(evento) }),
  atualizar: (id, campos) => api('/eventos/' + id, { method: 'PATCH', body: JSON.stringify(campos) }),
  apagar: (id) => api('/eventos/' + id, { method: 'DELETE' }),
};

const LivrosAPI = {
  listar: () => api('/livros'),
  criar: (livro) => api('/livros', { method: 'POST', body: JSON.stringify(livro) }),
  atualizar: (id, campos) => api('/livros/' + id, { method: 'PATCH', body: JSON.stringify(campos) }),
  apagar: (id) => api('/livros/' + id, { method: 'DELETE' }),
  lista: () => api('/lista-leitura'),
  adicionarNaLista: (id) => api('/livros/' + id + '/lista', { method: 'POST' }),
  removerDaLista: (id) => api('/livros/' + id + '/lista', { method: 'DELETE' }),
};

const DocumentosAPI = {
  listar: () => api('/documentos'),
  ver: (id) => api('/documentos/' + id),
  criar: (doc) => api('/documentos', { method: 'POST', body: JSON.stringify(doc) }),
  atualizar: (id, campos) => api('/documentos/' + id, { method: 'PATCH', body: JSON.stringify(campos) }),
  apagar: (id) => api('/documentos/' + id, { method: 'DELETE' }),
};

const ArquivosAPI = {
  listar: () => api('/arquivos'),
  apagar: (id) => api('/arquivos/' + id, { method: 'DELETE' }),
};

const MapasAPI = {
  listar: () => api('/mapas-mentais'),
  ver: (id) => api('/mapas-mentais/' + id),
  criar: (mapa) => api('/mapas-mentais', { method: 'POST', body: JSON.stringify(mapa) }),
  atualizar: (id, campos) => api('/mapas-mentais/' + id, { method: 'PATCH', body: JSON.stringify(campos) }),
  apagar: (id) => api('/mapas-mentais/' + id, { method: 'DELETE' }),
};

const PlanilhasAPI = {
  ver: (id) => api('/planilhas/' + id),
  criar: (planilha) => api('/planilhas', { method: 'POST', body: JSON.stringify(planilha) }),
  atualizar: (id, campos) => api('/planilhas/' + id, { method: 'PATCH', body: JSON.stringify(campos) }),
  apagar: (id) => api('/planilhas/' + id, { method: 'DELETE' }),
};

const ApresentacoesAPI = {
  ver: (id) => api('/apresentacoes/' + id),
  criar: (apresentacao) => api('/apresentacoes', { method: 'POST', body: JSON.stringify(apresentacao) }),
  atualizar: (id, campos) => api('/apresentacoes/' + id, { method: 'PATCH', body: JSON.stringify(campos) }),
  apagar: (id) => api('/apresentacoes/' + id, { method: 'DELETE' }),
};