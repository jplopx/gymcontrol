/* Inclusão/remoção dinâmica de exercícios: os dados e as permissões são validados no PHP. */
(() => {
  'use strict';
  const linhas = document.getElementById('linhas-modelo');
  const adicionar = document.getElementById('adicionar-exercicio');
  const contador = document.getElementById('contagem-modelo');
  if (!linhas || !adicionar || !contador) return;
  const atualizar = () => {
    const items = [...linhas.querySelectorAll('[data-modelo-linha]')];
    items.forEach((row, i) => { const span = row.querySelector('[data-numero]'); if (span) span.textContent = i + 1; });
    contador.textContent = `(${items.length}/30)`;
    adicionar.disabled = items.length >= 30;
    linhas.querySelectorAll('[data-remover-exercicio]').forEach(button => {
      button.disabled = items.length <= 1;
    });
  };
  adicionar.addEventListener('click', () => {
    const atual = linhas.querySelector('[data-modelo-linha]');
    if (!atual || linhas.querySelectorAll('[data-modelo-linha]').length >= 30) return;
    const nova = atual.cloneNode(true);
    nova.querySelectorAll('input').forEach(input => {
      input.value = input.name === 'ex_series[]' ? '3' : (input.name === 'ex_repeticoes[]' ? '10 a 12' : '');
    });
    linhas.appendChild(nova);
    atualizar();
    nova.querySelector('input[name="ex_nome[]"]')?.focus();
  });
  linhas.addEventListener('click', event => {
    const botao = event.target.closest('[data-remover-exercicio]');
    if (!botao || linhas.querySelectorAll('[data-modelo-linha]').length <= 1) return;
    botao.closest('[data-modelo-linha]')?.remove();
    atualizar();
  });
  atualizar();
})();
