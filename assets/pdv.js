(() => {
  'use strict';
  const config = window.GC_PDV || {};
  const brl = cents => (cents / 100).toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'});
  const items = new Map();
  const list = document.querySelector('#listaCarrinho');
  const button = document.querySelector('#finalizarVenda');
  const status = document.querySelector('#feedbackManual');
  function draw() {
    if (!list) return;
    list.replaceChildren();
    let total = 0;
    for (const item of items.values()) {
      total += item.price * item.qtd;
      const line = document.createElement('div'); line.className = 'cart-line';
      const description = document.createElement('div');
      const strong = document.createElement('strong'); strong.textContent = item.name;
      const small = document.createElement('small'); small.textContent = (item.details ? item.details + ' · ' : '') + brl(item.price) + ' × ' + item.qtd;
      description.append(strong, small);
      const counter = document.createElement('div'); counter.className = 'counter';
      const down = document.createElement('button'); down.textContent = '−'; down.type = 'button'; down.setAttribute('aria-label', 'Reduzir ' + item.name);
      down.addEventListener('click', () => change(item.id, -1));
      const n = document.createElement('span'); n.textContent = item.qtd;
      const up = document.createElement('button'); up.textContent = '+'; up.type = 'button'; up.setAttribute('aria-label', 'Aumentar ' + item.name);
      up.addEventListener('click', () => change(item.id, 1));
      counter.append(down, n, up); line.append(description, counter); list.append(line);
    }
    if (!items.size) { const p = document.createElement('p'); p.className='muted'; p.textContent='Nenhum produto adicionado.'; list.append(p); }
    document.querySelector('#valorTotal').textContent = brl(total);
  }
  function change(id, d) {
    const item = items.get(id); if (!item) return;
    if (item.qtd + d > item.stock) return;
    item.qtd += d; if (item.qtd < 1) items.delete(id);
    draw();
  }
  document.querySelectorAll('.js-product').forEach(card => card.addEventListener('click', () => {
    const id = Number(card.dataset.id), stock = Number(card.dataset.stock);
    const item = items.get(id);
    if (item && item.qtd >= stock) { if (status) status.textContent='Estoque máximo deste produto no carrinho.'; return; }
    if (item) item.qtd++; else items.set(id, {id, name:card.dataset.name, details:card.dataset.details || '', price:Number(card.dataset.price), stock, qtd:1});
    if (status) status.textContent=''; draw();
  }));
  button?.addEventListener('click', async () => {
    if (!items.size) { status.textContent='Adicione algum produto.'; return; }
    if (!window.confirm('O pagamento já foi recebido? Registrar a venda e baixar o estoque?')) return;
    button.disabled=true; status.textContent='Registrando…';
    try {
      const r = await fetch('loja.php?aba=manual', {method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',
        body:JSON.stringify({csrf:config.csrf, aluno_id:Number(document.querySelector('#alunoComprador').value),
          forma_pagamento:document.querySelector('#pagamentoLoja').value,
          itens:Array.from(items.values(), p=>({id:p.id,qtd:p.qtd}))})});
      const data = await r.json(); if (!r.ok || !data.ok) throw new Error(data.erro || 'Falha no registro.');
      alert('Venda #' + data.venda_id + ' registrada. Total: R$ ' + data.total + '.');
      location.reload();
    } catch (err) { status.textContent=err.message || 'Erro ao registrar venda.'; button.disabled=false; }
  });
  document.querySelector('#copiarLinkTotem')?.addEventListener('click', async () => {
    const field = document.querySelector('#linkTotem');
    if (!field) return;
    const url = new URL(field.value, window.location.href).href;
    try { await navigator.clipboard.writeText(url); alert('Link completo copiado.'); }
    catch { field.value=url;field.select();alert('Copie o endereço selecionado.'); }
  });
  document.querySelectorAll('.js-photo').forEach(input => input.addEventListener('change', () => {
    const img = document.getElementById(input.dataset.preview); if (!img) return;
    const file = input.files?.[0]; if (!file) {img.hidden=true;img.removeAttribute('src');return;}
    const url = URL.createObjectURL(file); img.onload=()=>URL.revokeObjectURL(url); img.src=url; img.hidden=false;
  }));
})();
