import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE = __ENV.BASE_URL || 'http://127.0.0.1:8000/api';
const HEADERS = { 'Content-Type': 'application/json', Accept: 'application/json' };

export const options = {
  stages: [
    { duration: '30s', target: 20 },   // aquecimento
    { duration: '1m',  target: 100 },  // carga
    { duration: '30s', target: 200 },  // estresse
    { duration: '20s', target: 0 },    // desaceleração
  ],
  thresholds: {
    http_req_duration: ['p(95)<500'],  // 95% das requisições abaixo de 500 ms
    http_req_failed:   ['rate<0.01'],  // menos de 1% de erro
  },
};

// Executa 1 vez: cria um usuário e 20 metas para a listagem ter volume
export function setup() {
  const email = `carga.${Date.now()}@teste.com`;
  const res = http.post(`${BASE}/register`, JSON.stringify({
    name: 'Carga', email, password: 'senha12345', password_confirmation: 'senha12345',
  }), { headers: HEADERS });
  const token = res.json('token');
  const auth = { headers: { ...HEADERS, Authorization: `Bearer ${token}` } };
  for (let i = 0; i < 20; i++) {
    http.post(`${BASE}/metas`, JSON.stringify({
      nome: `Meta ${i}`, disciplina: 'QA', prazo: '2030-01-01',
    }), auth);
  }
  return { auth };
}

export default function ({ auth }) {
  const res = http.get(`${BASE}/metas`, auth);
  check(res, {
    'status 200': r => r.status === 200,
    'retornou 20 metas': r => Array.isArray(r.json()) && r.json().length === 20,
  });
  sleep(1);
}
