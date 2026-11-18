(() => {
  'use strict';
  const csrf = document.querySelector('meta[name="gc-csrf"]')?.content || '';
  const catalog = new Map();
  const cart = new Map();
  const brl = cents => (cents/100).toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
  document.querySelectorAll('.kiosk-product').forEach(el => {
    const p = {id:Number(el.dataset.id), name:el.dataset.name, details:el.dataset.details || '', price:Number(el.dataset.price), stock:Number(el.dataset.stock), element:el};
    catalog.set(p.id, p);
    el.querySelector('.add-product').addEventListener('click', () => {
      const qtd = cart.get(p.id) || 0;
      if (qtd >= Math.min(20,p.stock)) { announce('Quantidade máxima disponível neste produto.'); return; }
      cart.set(p.id,qtd+1); draw();
      el.querySelector('.add-product').animate?.([{transform:'scale(.86)'},{transform:'scale(1)'}],{duration:160});
    });
  });
  const screens = ['welcome','shopping','review','complete'];
  const begin = document.querySelector('#startOrder');
  let inactivityTimer;
  function show(section) {
    screens.forEach(s => {document.getElementById(s).hidden = s!==section;});
    document.getElementById('mobileCart').hidden = section!=='shopping' || !cart.size;
    window.scrollTo({top:0,behavior:'instant'});
    resetIdle();
  }
  function resetIdle() {
    clearTimeout(inactivityTimer);
    if (!document.getElementById('welcome').hidden) return;
    inactivityTimer = setTimeout(() => {cart.clear();draw();show('welcome');}, 3*60*1000);
  }
  ['click','touchstart','keydown'].forEach(name => document.addEventListener(name,resetIdle,{passive:true}));
  function announce(message) { const node=document.querySelector('#orderError'); if (node) node.textContent=message; }
  function draw() {
    const lines=document.querySelector('#kioskCartItems'); lines.replaceChildren();
    let total=0,quantity=0;
    for (const [id,qtd] of cart) {
      const p = catalog.get(id); if (!p) continue;
      total+=p.price*qtd; quantity+=qtd;
      const line=document.createElement('div');line.className='kiosk-cart-line';
      const label=document.createElement('div'); const strong=document.createElement('strong');strong.textContent=p.name;
      const small=document.createElement('small');small.textContent=(p.details ? p.details + ' · ' : '')+brl(p.price)+' × '+qtd;label.append(strong,small);
      const controls=document.createElement('div');controls.className='quantity-control';
      const minus=document.createElement('button');minus.type='button';minus.textContent='−';minus.setAttribute('aria-label','Reduzir '+p.name);minus.addEventListener('click',()=>change(id,-1));
      const count=document.createElement('span');count.textContent=qtd;
      const plus=document.createElement('button');plus.type='button';plus.textContent='+';plus.setAttribute('aria-label','Aumentar '+p.name);plus.addEventListener('click',()=>change(id,1));
      controls.append(minus,count,plus);line.append(label,controls);lines.append(line);
    }
    if (!cart.size) {const empty=document.createElement('p');empty.className='cart-empty';empty.textContent='Seu carrinho está vazio. Adicione produtos para continuar.'; lines.append(empty);}
    document.querySelector('#cartTotal').textContent=brl(total);
    document.querySelector('#mobileTotal').textContent=brl(total);
    document.querySelector('#cartQuantity').textContent=quantity+' '+(quantity===1?'item':'itens');
    document.querySelector('#reviewOrder').disabled=!quantity;
    document.querySelector('#mobileCart').hidden = !quantity || document.querySelector('#shopping').hidden;
  }
  function change(id,amount) {
    const qtd=(cart.get(id)||0)+amount;
    if (qtd>Math.min(20,catalog.get(id).stock)) return;
    if (qtd<=0) cart.delete(id); else cart.set(id,qtd);
    draw();
  }
  function start() {cart.clear();draw();document.querySelector('#searchProducts').value=''; filter();show('shopping');}
  begin.addEventListener('click', start);
  document.querySelector('#backWelcome').addEventListener('click',()=>{if(cart.size&&!confirm('Descartar o carrinho atual?'))return;cart.clear();draw();show('welcome');});
  function filter() {
    const text=document.querySelector('#searchProducts').value.trim().toLocaleLowerCase('pt-BR');
    catalog.forEach(p=>{p.element.hidden=!(p.name+' '+p.details).toLocaleLowerCase('pt-BR').includes(text);});
  }
  document.querySelector('#searchProducts').addEventListener('input',filter);
  function review() {
    if (!cart.size) return;
    const list=document.querySelector('#reviewItems');list.replaceChildren();
    let total=0;
    for(const [id,qtd] of cart){const p=catalog.get(id);if(!p)continue;total+=p.price*qtd;
      const line=document.createElement('div');line.className='review-row';
      const description=document.createElement('div');
      const n=document.createElement('strong');n.textContent=qtd+' × '+p.name;
      description.append(n);
      if (p.details) { const meta=document.createElement('small');meta.textContent=p.details;description.append(meta); }
      const price=document.createElement('span');price.textContent=brl(p.price*qtd);
      line.append(description,price);list.append(line);
    }
    document.querySelector('#reviewTotal').textContent=brl(total);
    announce('');show('review');
  }
  document.querySelector('#reviewOrder').addEventListener('click',review);
  document.querySelector('#mobileReview').addEventListener('click',review);
  document.querySelector('#editOrder').addEventListener('click',()=>show('shopping'));
  document.querySelector('#restartOrder').addEventListener('click',()=>{cart.clear();draw();show('welcome');});
  const send=document.querySelector('#sendOrder');
  send.addEventListener('click', async () => {
    if (!cart.size) return;
    send.disabled=true; send.textContent='Enviando…'; announce('');
    try {
      const r=await fetch('api.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',
        body:JSON.stringify({csrf,itens:Array.from(cart,([id,qtd])=>({id,qtd}))})});
      const data=await r.json(); if(!r.ok||!data.ok) throw new Error(data.erro||'Não foi possível enviar o pedido.');
      document.querySelector('#orderNumber').textContent='#'+String(data.pedido_id).padStart(5,'0');
      cart.clear();draw();show('complete');
    } catch(err) {announce(err.message||'Erro no pedido. Peça ajuda na recepção.');}
    finally {send.disabled=false;send.textContent='Enviar pedido →';}
  });
  draw();
})();
